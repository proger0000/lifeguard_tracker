<?php
require_once 'config.php'; // Connect db, start session, load functions
global $pdo;

// --- Get Parameters ---
// IMPORTANT: Assume the NFC tag URL is like: APP_URL/scan_handler.php?post_id=X
$post_id = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$pending_action = $_SESSION['action_pending'] ?? null;

// --- Basic Validation ---
if (!$user_id || $user_role !== 'lifeguard') {
    // Not logged in as lifeguard, maybe redirect to login or show error
    set_flash_message('помилка', 'Необхідно увійти як рятувальник для сканування.');
    header('Location: login.php');
    exit();
}

if (!$post_id) {
    set_flash_message('помилка', 'Невірний або відсутній ID поста в URL сканування.');
    unset($_SESSION['action_pending']); // Clear action state on error
    header('Location: index.php');
    exit();
}

if (!$pending_action) {
    set_flash_message('помилка', 'Немає очікуваної дії (Почати/Завершити). Спочатку натисніть кнопку.');
    header('Location: index.php');
    exit();
}

// --- Check if Post Exists ---
try {
    $stmt_post = $pdo->prepare("SELECT id, name FROM posts WHERE id = :post_id");
    $stmt_post->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $stmt_post->execute();
    $post = $stmt_post->fetch();
    if (!$post) {
        set_flash_message('помилка', 'Пост з ID ' . escape($post_id) . ' не знайдено.');
         unset($_SESSION['action_pending']);
        header('Location: index.php');
        exit();
    }
    $post_name = $post['name'];

} catch (PDOException $e) {
    // error_log("Scan Handler Post Check Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка перевірки поста. Спробуйте пізніше.');
     unset($_SESSION['action_pending']);
    header('Location: index.php');
    exit();
}


// --- Process Action ---
$now = date('Y-m-d H:i:s');

try {
    if ($pending_action === 'start') {
        // ... (ваш код перевірки чи немає вже активної зміни) ...

        if ($existing_shift) { // Якщо знайдено активну зміну
             set_flash_message('помилка', 'Ви вже маєте активну зміну...');
             unset($_SESSION['action_pending']);
             header('Location: index.php');
             exit();
        } else {
            // 2. Insert new shift record
            $stmt_start = $pdo->prepare("
                INSERT INTO shifts (user_id, post_id, start_time, status)
                VALUES (:user_id, :post_id, :start_time, 'active')
            ");
            // ... (bindParam для $stmt_start) ...
             $stmt_start->bindParam(':user_id', $user_id, PDO::PARAM_INT);
             $stmt_start->bindParam(':post_id', $post_id, PDO::PARAM_INT);
             $stmt_start->bindParam(':start_time', $now);
            $stmt_start->execute();

            if ($stmt_start->rowCount() > 0) {
                $new_shift_id = $pdo->lastInsertId(); // Отримуємо ID щойно створеної зміни
                unset($_SESSION['action_pending']); // Очищуємо очікування
                 // ---- ЗМІНА РЕДІРЕКТУ ----
                 // Замість set_flash_message та редіректу на index.php:
                 header('Location: upload_photo.php?shift_id=' . $new_shift_id);
                 exit();
                 // -------------------------
            } else {
                 set_flash_message('помилка', 'Не вдалося розпочати зміну (DB).');
                 unset($_SESSION['action_pending']);
                 header('Location: index.php');
                 exit();
            }
        }

    } elseif ($pending_action === 'end') {
        // --- End Shift ---
        // 1. Find the user's *active* shift, MUST be at the *scanned* post
        $stmt_find = $pdo->prepare("
            SELECT id FROM shifts
            WHERE user_id = :user_id AND post_id = :post_id AND status = 'active'
            ORDER BY start_time DESC LIMIT 1
        ");
         $stmt_find->bindParam(':user_id', $user_id, PDO::PARAM_INT);
         $stmt_find->bindParam(':post_id', $post_id, PDO::PARAM_INT); // Ensure ending at the correct post
        $stmt_find->execute();
        $active_shift = $stmt_find->fetch();

        if (!$active_shift) {
            set_flash_message('помилка', 'Не знайдено активної зміни на посту "' . escape($post_name) . '" для завершення. Можливо, ви сканували не ту мітку або зміна вже завершена.');
        } else {
            // 2. Update the shift record
            $stmt_end = $pdo->prepare("
                UPDATE shifts
                SET end_time = :end_time, status = 'completed'
                WHERE id = :shift_id AND user_id = :user_id AND status = 'active'
            ");
            $stmt_end->bindParam(':end_time', $now);
            $stmt_end->bindParam(':shift_id', $active_shift['id'], PDO::PARAM_INT);
            $stmt_end->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Extra check
            $stmt_end->execute();

             if ($stmt_end->rowCount() > 0) {
    $shift_id_for_report = $active_shift['id']; // Запам'ятовуємо ID завершеної зміни
    // Повідомлення можна встановити, але воно може загубитися при редіректі на форму
    // set_flash_message('успіх', 'Зміну ... успішно завершено. Тепер заповніть звіт.');
    unset($_SESSION['action_pending']); // Очистити дію
    header('Location: submit_report.php?shift_id=' . $shift_id_for_report); // <<<<< ЗМІНЕНО РЕДІРЕКТ
    exit();
} else {
     set_flash_message('помилка', 'Не вдалося завершити зміну (rowCount=0).');
     unset($_SESSION['action_pending']);
     header('Location: index.php');
     exit();
}
        }
    }

} catch (PDOException $e) {
     // error_log("Scan Handler Action Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка бази даних під час обробки дії.');
}

// --- Clear Pending Action ---
unset($_SESSION['action_pending']);

// --- Redirect Back to Dashboard ---
header('Location: index.php');
exit();
?>