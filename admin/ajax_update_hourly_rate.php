<?php
// admin/ajax_update_hourly_rate.php

// Підключаємо конфігурацію і стартуємо сесію
require_once '../config.php';

// Встановлюємо заголовок, щоб клієнт очікував JSON
header('Content-Type: application/json');

// Перевірка прав доступу: тільки адміністратор може змінювати ставки
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено.']);
    exit;
}

// Перевірка CSRF-токену для безпеки
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Невірний CSRF токен.']);
    exit;
}

// Отримуємо та валідуємо вхідні дані
$user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$new_rate = filter_input(INPUT_POST, 'new_rate', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$changed_by_user_id = $_SESSION['user_id'] ?? null; // ID адміністратора з сесії

// Перевіряємо, чи всі дані коректні
if (!$user_id_to_update || $new_rate === false || $new_rate < 0 || !$changed_by_user_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Некоректні вхідні дані.']);
    exit;
}

global $pdo;

// Починаємо транзакцію. Це гарантує, що обидва запити (в rate_history і в users)
// виконаються успішно, або жоден з них не виконається.
$pdo->beginTransaction();

try {
    // 1. Спочатку отримуємо поточну ставку користувача, щоб записати її як "стару".
    // Блокуємо рядок 'FOR UPDATE', щоб уникнути race conditions, якщо два адміни одночасно редагують.
    $stmt_get_old_rate = $pdo->prepare("SELECT base_hourly_rate FROM users WHERE id = :user_id FOR UPDATE");
    $stmt_get_old_rate->execute([':user_id' => $user_id_to_update]);
    $old_rate = $stmt_get_old_rate->fetchColumn();

    if ($old_rate === false) {
        throw new Exception('Користувача не знайдено.');
    }
    
    // Перевіряємо, чи ставка дійсно змінилася, щоб не створювати зайвих записів в історії
    if ((float)$old_rate !== (float)$new_rate) {

        // 2. Вставляємо запис в історію змін
        $stmt_history = $pdo->prepare(
            "INSERT INTO rate_history (user_id, old_rate, new_rate, changed_by_user_id) VALUES (:user_id, :old_rate, :new_rate, :changed_by_user_id)"
        );
        $stmt_history->execute([
            ':user_id' => $user_id_to_update,
            ':old_rate' => $old_rate,
            ':new_rate' => $new_rate,
            ':changed_by_user_id' => $changed_by_user_id
        ]);
        
        // 3. Оновлюємо ставку в основній таблиці `users`
        $stmt_update_user = $pdo->prepare(
            "UPDATE users SET base_hourly_rate = :new_rate, updated_at = NOW() WHERE id = :user_id"
        );
        $stmt_update_user->execute([
            ':new_rate' => $new_rate,
            ':user_id' => $user_id_to_update
        ]);
        
        if ($stmt_update_user->rowCount() === 0) {
            // Це малоймовірно через FOR UPDATE, але це додаткова перевірка
            throw new Exception('Не вдалося оновити ставку користувача.');
        }
    }

    // 4. Якщо все пройшло без помилок, підтверджуємо транзакцію
    $pdo->commit();

    // Повертаємо успішну відповідь
    echo json_encode(['success' => true, 'message' => 'Ставку успішно оновлено.']);

} catch (Exception $e) {
    // Якщо сталася помилка, відкочуємо всі зміни
    $pdo->rollBack();
    
    // Логуємо помилку на сервері для подальшого аналізу
    error_log("Failed to update rate for user {$user_id_to_update}: " . $e->getMessage());
    
    // Повертаємо помилку клієнту
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Помилка сервера при оновленні ставки.']);
}

?>