<?php
// Файл: admin/ajax_get_shift_points_data.php
// Описание: Возвращает JSON с рассчитанными баллами и уже отмеченными правилами для смены.

require_once '../config.php';
require_once '../includes/helpers.php'; // Подключаем хелперы с функцией расчета

header('Content-Type: application/json; charset=utf-8');

// Безопасность: проверка роли
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено.']);
    exit;
}

// Валидация ID смены
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
if (!$shift_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректний ID зміни.']);
    exit;
}

try {
    // 1. Получаем актуальные (рассчитанные) баллы для КАЖДОГО правила для ДАННОЙ смены
    // Мы используем нашу надёжную функцию из helpers.php
    $calculated_points = get_calculated_points_for_shift($pdo, $shift_id);

    // 2. Получаем уже сохраненные (отмеченные) баллы для этой смены из БД
    $checked_rules = [];
    $stmt_checked = $pdo->prepare("SELECT rule_id, comment FROM lifeguard_shift_points WHERE shift_id = :shift_id");
    $stmt_checked->execute([':shift_id' => $shift_id]);
    foreach ($stmt_checked->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $checked_rules[$row['rule_id']] = [
            'comment' => $row['comment'] ?? ''
        ];
    }

    // 3. Отправляем всё одним красивым JSON-объектом
    echo json_encode([
        'success' => true,
        'calculated_points' => $calculated_points, // Объект { "1": 1.5, "2": 1, ... }
        'checked_rules' => $checked_rules        // Объект { "1": {"comment": ""}, "5": {"comment": "Молодец!"} }
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("AJAX Get Shift Points Data Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Помилка сервера при отриманні даних про бали.']);
}
?>