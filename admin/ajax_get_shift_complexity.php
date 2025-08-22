<?php
// Файл: admin/ajax_get_shift_complexity.php
// Описание: AJAX-обработчик для получения коэффициента сложности поста для указанной смены.

// Подключаем конфигурацию и хелперы
require_once '../config.php';
require_once '../includes/helpers.php'; // На всякий случай, если понадобятся хелперы

// Устанавливаем заголовок, чтобы браузер понимал, что это JSON
header('Content-Type: application/json; charset=utf-8');

// --- Безопасность ---
// Проверяем, что у пользователя есть права (админ или дежурный)
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer'])) {
    // Если нет прав, отправляем ошибку и выходим
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено.']);
    exit;
}

// --- Получение и Валидация Входных Данных ---
// Получаем ID смены из GET-параметра и проверяем, что это целое число
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);

if (!$shift_id) {
    // Если ID некорректный, отправляем ошибку
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Не вказано або некоректний ID зміни.']);
    exit;
}

// --- Основная Логика ---
try {
    // Готовим запрос в БД.
    // Нам нужно связать смену (shifts) с постом (posts), чтобы получить его коэффициент.
    $stmt = $pdo->prepare("
        SELECT p.complexity_coefficient
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.id = :shift_id
    ");

    // Привязываем параметр shift_id к запросу для безопасности
    $stmt->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt->execute();

    // Получаем результат. fetchColumn() вернет значение только одной колонки.
    $complexity_coefficient = $stmt->fetchColumn();

    // Проверяем, был ли найден коэффициент
    if ($complexity_coefficient !== false) {
        // Успех! Отправляем коэффициент в формате JSON
        echo json_encode([
            'success' => true,
            'complexity' => (float)$complexity_coefficient // Приводим к типу float для точности
        ]);
    } else {
        // Если для смены не найден пост или коэффициент
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'error' => 'Коефіцієнт для даної зміни не знайдено.']);
    }

} catch (PDOException $e) {
    // Если произошла ошибка на стороне БД
    http_response_code(500); // Internal Server Error
    // Записываем ошибку в лог сервера для дальнейшего анализа
    error_log("AJAX Get Shift Complexity Error: " . $e->getMessage());
    // Отправляем общее сообщение об ошибке
    echo json_encode(['success' => false, 'error' => 'Помилка бази даних.']);
}
?>