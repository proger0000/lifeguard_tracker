<?php
// Файл: admin/ajax_award_points.php
// Описание: Обрабатывает сохранение начисленных баллов.
// ВЕРСИЯ С УЧЕТОМ КОЭФФИЦИЕНТА СЛОЖНОСТИ ДЛЯ ПРАВИЛА "Зміна"

require_once '../config.php';
require_once '../includes/helpers.php'; // Подключаем, т.к. может понадобиться

header('Content-Type: application/json');

// --- Безопасность ---
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено']);
    exit;
}

// --- Получение и Валидация Входных Данных ---
$shift_id = isset($_POST['shift_id']) ? (int)$_POST['shift_id'] : 0;
if (!$shift_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не вказано ID зміни']);
    exit;
}

// --- Основная Логика ---
try {
    // Получаем ID пользователя для этой смены
    $stmt_shift = $pdo->prepare("SELECT user_id, post_id FROM shifts WHERE id = :shift_id");
    $stmt_shift->execute([':shift_id' => $shift_id]);
    $shift_data = $stmt_shift->fetch();

    if (!$shift_data) {
        throw new Exception('Зміна не знайдена або не має ID користувача/поста.');
    }
    $user_id = $shift_data['user_id'];
    $post_id = $shift_data['post_id'];


    // Начинаем транзакцию для атомарности операций
    $pdo->beginTransaction();

    // Сначала удаляем все старые баллы для этой смены, чтобы избежать дубликатов
    $pdo->prepare("DELETE FROM lifeguard_shift_points WHERE shift_id = :shift_id")->execute([':shift_id' => $shift_id]);

    $total_points_awarded = 0;
    // Готовим SQL-запрос для вставки один раз
    $stmt_insert = $pdo->prepare(
        "INSERT INTO lifeguard_shift_points
            (shift_id, user_id, rule_id, points_awarded, base_points_from_rule, coefficient_applied, awarded_by_user_id, comment)
         VALUES
            (:shift_id, :user_id, :rule_id, :points_awarded, :base_points, :coefficient, :awarded_by, :comment)"
    );

    // Перебираем все правила, которые были отмечены в форме
    foreach ($_POST['points'] ?? [] as $rule_id_str => $data) {
        // Проверяем, что правило отмечено (checkbox "awarded" пришел)
        if (!empty($data['awarded']) && $data['awarded'] == '1') {
            $rule_id = (int)$rule_id_str;
            $comment = isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null;
            $points_to_insert = 0;
            $base_points = 0;
            $coefficient = 1.00;

            // --- КЛЮЧЕВАЯ ЛОГИКА: ОСОБАЯ ОБРАБОТКА ДЛЯ ПРАВИЛА "Зміна" (ID=1) ---
            if ($rule_id === 1) {
                // Для правила "Зміна" балл = коэффициент сложности поста
                $stmt_coeff = $pdo->prepare("SELECT complexity_coefficient FROM posts WHERE id = :post_id");
                $stmt_coeff->execute([':post_id' => $post_id]);
                $complexity = $stmt_coeff->fetchColumn();

                // Если по какой-то причине коэффициента нет, ставим 1.0 по умолчанию
                $points_to_insert = $complexity !== false ? (float)$complexity : 1.0;
                $base_points = $points_to_insert; // Базовый балл равен самому коэффициенту
                $coefficient = 1.00; // Коэффициент уже применен, так что здесь множитель 1
            } else {
                // Для всех остальных правил берем балл из таблицы `points`
                $stmt_rule = $pdo->prepare("SELECT quantity FROM points WHERE id_balls = :rule_id");
                $stmt_rule->execute([':rule_id' => $rule_id]);
                $rule_data = $stmt_rule->fetch(PDO::FETCH_ASSOC);

                if ($rule_data) {
                    $points_to_insert = (float)$rule_data['quantity'];
                    $base_points = $points_to_insert;
                    $coefficient = 1.00; // Коэффициент сложности на другие правила не влияет
                } else {
                    // Пропускаем правило, если оно не найдено в БД
                    continue;
                }
            }
            // --- КОНЕЦ КЛЮЧЕВОЙ ЛОГИКИ ---

            // Вставляем данные в БД
            $stmt_insert->execute([
                ':shift_id' => $shift_id,
                ':user_id' => $user_id,
                ':rule_id' => $rule_id,
                ':points_awarded' => $points_to_insert,
                ':base_points' => $base_points,
                ':coefficient' => $coefficient,
                ':awarded_by' => $_SESSION['user_id'],
                ':comment' => $comment
            ]);

            $total_points_awarded += $points_to_insert;
        }
    }

    // Если все прошло успешно, подтверждаем транзакцию
    $pdo->commit();

    // Отправляем успешный ответ
    echo json_encode(['success' => true, 'points_sum' => $total_points_awarded]);

} catch (Exception $e) {
    // Если что-то пошло не так, откатываем все изменения
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Логируем ошибку и отправляем сообщение клиенту
    error_log("Award points error for shift #{$shift_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка сервера при збереженні балів.']);
}
?>