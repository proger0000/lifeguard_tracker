<?php
require_once 'config.php';
require_roles(['admin', 'duty_officer']); // Доступ тільки адміну або черговому

global $pdo;
$shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? ''; // 'approve' або 'reject' (поки використовуємо лише 'approve')
$approver_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$shift_id || !in_array($action, ['approve'/*, 'reject' */])) { // Перевірка методу, ID та дії
    set_flash_message('помилка', 'Некоректний запит.');
    header('Location: index.php'); // Редірект на головну панель
    exit();
}

// Додатково: перевірка CSRF
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    set_flash_message('помилка', 'Помилка CSRF токену.');
     header('Location: index.php');
     exit();
}


// --- Обробка Дії ---
try {
    if ($action === 'approve') {
        $stmt_approve = $pdo->prepare("
            UPDATE shifts
            SET start_photo_approved_at = NOW(), start_photo_approved_by = :approver_id
            WHERE id = :shift_id AND start_photo_path IS NOT NULL AND start_photo_approved_at IS NULL
        "); // Переконуємось, що фото є і ще не підтверджене
        $stmt_approve->bindParam(':approver_id', $approver_user_id, PDO::PARAM_INT);
        $stmt_approve->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);

        if ($stmt_approve->execute()) {
            if ($stmt_approve->rowCount() > 0) {
                 set_flash_message('успіх', 'Фото для зміни ID ' . $shift_id . ' підтверджено.');
            } else {
                 set_flash_message('інфо', 'Фото для зміни ID ' . $shift_id . ' вже було підтверджено або не знайдено.');
            }
        } else {
             set_flash_message('помилка', 'Не вдалося підтвердити фото (DB).');
        }
    }
    /* // Логіка для відхилення (можна додати пізніше)
    elseif ($action === 'reject') {
        // Що робити при відхиленні? Повідомити? Видалити шлях?
        // Наприклад, очистити шлях:
        $stmt_reject = $pdo->prepare("UPDATE shifts SET start_photo_path = NULL, start_photo_approved_at = NULL, start_photo_approved_by = NULL WHERE id = :shift_id");
        $stmt_reject->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
        if($stmt_reject->execute()){
            set_flash_message('інфо', 'Фото для зміни ID ' . $shift_id . ' відхилено.');
             // Тут можна додати логіку сповіщення лайфгарду
        } else {
             set_flash_message('помилка', 'Не вдалося відхилити фото (DB).');
        }
    }
    */

} catch (PDOException $e) {
    // error_log("Approve Photo DB Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка бази даних під час обробки фото.');
}

// Редірект назад на головну панель чергового/адміністратора
unset($_SESSION['csrf_token']); // Очищаємо токен після дії
header('Location: index.php');
exit();

?>