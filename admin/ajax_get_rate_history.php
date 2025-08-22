<?php
// admin/ajax_get_rate_history.php

// Підключаємо конфігурацію і стартуємо сесію
require_once '../config.php';
header('Content-Type: application/json');

// Перевірка прав доступу
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено.']);
    exit;
}

// Отримуємо ID користувача з GET-параметра
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некоректний ID користувача.']);
    exit;
}

global $pdo;

try {
    // Вибираємо історію для конкретного користувача, а також ім'я адміна, який зробив зміну
    $stmt = $pdo->prepare(
        "SELECT 
            h.old_rate, 
            h.new_rate, 
            h.changed_at, 
            a.full_name as changed_by_name
        FROM rate_history h
        LEFT JOIN users a ON h.changed_by_user_id = a.id
        WHERE h.user_id = :user_id
        ORDER BY h.changed_at DESC"
    );
    $stmt->execute([':user_id' => $user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'history' => $history]);

} catch (Exception $e) {
    error_log("Error fetching rate history for user {$user_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Помилка сервера при завантаженні історії.']);
}
?>