<?php
// /scan_handler.php
// ... (початок файлу без змін) ...
require_once 'config.php'; // Connect db, start session, load functions
require_once 'includes/helpers.php'; // Подключаем файл с вспомогательными функциями
global $pdo; //

// --- Get Parameters ---
// IMPORTANT: Assume the NFC tag URL is like: APP_URL/scan_handler.php?post_id=X
$post_id = filter_input(INPUT_GET, 'post_id', FILTER_VALIDATE_INT); //
$user_id = $_SESSION['user_id'] ?? null; //
$user_role = $_SESSION['user_role'] ?? null; //
$pending_action = $_SESSION['action_pending'] ?? null; //

// --- Basic Validation ---
if (!$user_id || $user_role !== 'lifeguard') { //
    // Not logged in as lifeguard, maybe redirect to login or show error
    set_flash_message('помилка', 'Необхідно увійти як рятувальник для сканування.'); //
    header('Location: login.php'); //
    exit(); //
}

if (!$post_id) { //
    set_flash_message('помилка', 'Невірний або відсутній ID поста в URL сканування.'); //
    unset($_SESSION['action_pending']); // Clear action state on error
    header('Location: index.php'); //
    exit(); //
}

if (!$pending_action) { //
    set_flash_message('помилка', 'Немає очікуваної дії (Почати/Завершити). Спочатку натисніть кнопку.'); //
    header('Location: index.php'); //
    exit(); //
}

// --- Check if Post Exists ---
try {
    $stmt_post = $pdo->prepare("SELECT id, name FROM posts WHERE id = :post_id"); //
    $stmt_post->bindParam(':post_id', $post_id, PDO::PARAM_INT); //
    $stmt_post->execute(); //
    $post = $stmt_post->fetch(); //
    if (!$post) { //
        set_flash_message('помилка', 'Пост з ID ' . escape($post_id) . ' не знайдено.'); //
         unset($_SESSION['action_pending']); //
        header('Location: index.php'); //
        exit(); //
    }
    $post_name = $post['name']; //

} catch (PDOException $e) {
    // error_log("Scan Handler Post Check Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка перевірки поста. Спробуйте пізніше.'); //
     unset($_SESSION['action_pending']); //
    header('Location: index.php'); //
    exit(); //
}


// --- Process Action ---
$now = date('Y-m-d H:i:s'); //

try {
    if ($pending_action === 'start') { //
        // 1. Перевірка чи є вже активна зміна
        $stmt_check_active = $pdo->prepare("SELECT id FROM shifts WHERE user_id = :user_id AND status = 'active'"); //
        $stmt_check_active->bindParam(':user_id', $user_id, PDO::PARAM_INT); //
        $stmt_check_active->execute(); //
        $existing_shift = $stmt_check_active->fetch(); //

        if ($existing_shift) { // Якщо знайдено активну зміну //
             set_flash_message('помилка', 'Ви вже маєте активну зміну (ID: ' . $existing_shift['id'] . '). Спочатку завершіть її.'); //
             unset($_SESSION['action_pending']); //
             header('Location: index.php'); //
             exit(); //
        } else {
            // 2. Insert new shift record
            $stmt_start = $pdo->prepare("
                INSERT INTO shifts (user_id, post_id, start_time, status)
                VALUES (:user_id, :post_id, :start_time, 'active')
            "); //
             $stmt_start->bindParam(':user_id', $user_id, PDO::PARAM_INT); //
             $stmt_start->bindParam(':post_id', $post_id, PDO::PARAM_INT); //
             $stmt_start->bindParam(':start_time', $now); //

            if ($stmt_start->execute()) { //
                $new_shift_id = $pdo->lastInsertId(); //
                // Логування дії
                log_action($pdo, $user_id, "Розпочав зміну #{$new_shift_id} на посту {$post_name}", $new_shift_id);
                unset($_SESSION['action_pending']); //
                 header('Location: upload_photo.php?shift_id=' . $new_shift_id); //
                 exit(); //
            } else {
                 set_flash_message('помилка', 'Не вдалося розпочати зміну.'); //
                 unset($_SESSION['action_pending']); //
                 header('Location: index.php'); //
                 exit(); //
            }
        }

    } elseif ($pending_action === 'end') { //
        // --- End Shift ---
        // 1. Find the user's *active* shift, MUST be at the *scanned* post
        $stmt_find = $pdo->prepare("
            SELECT id, start_time FROM shifts
            WHERE user_id = :user_id AND post_id = :post_id AND status = 'active'
            ORDER BY start_time DESC LIMIT 1
        "); //
         $stmt_find->bindParam(':user_id', $user_id, PDO::PARAM_INT); //
         $stmt_find->bindParam(':post_id', $post_id, PDO::PARAM_INT); // Ensure ending at the correct post
        $stmt_find->execute(); //
        $active_shift = $stmt_find->fetch(); //

        if (!$active_shift) { //
            set_flash_message('помилка', 'Не знайдено вашої активної зміни саме на посту "' . escape($post_name) . '". Можливо, ви сканували не ту мітку або зміна вже завершена раніше.'); //
            unset($_SESSION['action_pending']); // Очищаємо дію
            header('Location: index.php'); // Повертаємо на головну
            exit(); //
        } else {
            // 2. Calculate rounded hours
            $rounded_hours = calculate_rounded_hours($active_shift['start_time'], $now);
            
            // 3. Update the shift record with rounded hours
            $stmt_end = $pdo->prepare("
                UPDATE shifts
                SET end_time = :end_time, 
                    status = 'completed',
                    rounded_work_hours = :rounded_hours
                WHERE id = :shift_id AND user_id = :user_id AND status = 'active'
            "); //
            $stmt_end->bindParam(':end_time', $now); //
            $stmt_end->bindParam(':rounded_hours', $rounded_hours, PDO::PARAM_INT); //
            $stmt_end->bindParam(':shift_id', $active_shift['id'], PDO::PARAM_INT); //
            $stmt_end->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Extra check
            
            if ($stmt_end->execute()) { // // МОДИФІКАЦІЯ: перевіряємо результат виконання
                if ($stmt_end->rowCount() > 0) { //
                    // === ЗМІНА ЛОГІКИ: РЕДІРЕКТ НА ЗАВАНТАЖЕННЯ ФОТО ===
                    set_flash_message('інфо', 'Зміну на посту "' . escape($post_name) . '" успішно завершено. Тепер завантажте фото завершення.');
                    // Логування дії
                    log_action($pdo, $user_id, "Завершив зміну #{$active_shift['id']} на посту {$post_name} (скан NFC)", $active_shift['id']);
                    unset($_SESSION['action_pending']); // Очистити дію
                    header('Location: upload_end_photo.php?shift_id=' . $active_shift['id']); // <<<<< НОВИЙ РЕДІРЕКТ
                    exit();
                    // ======================================================
                } else {
                    set_flash_message('помилка', 'Не вдалося завершити зміну (можливо, вона вже була завершена або скановано не той пост).'); //
                    unset($_SESSION['action_pending']); //
                    header('Location: index.php'); //
                    exit(); //
                }
            } else {
                 set_flash_message('помилка', 'Помилка виконання запиту на завершення зміни.');
                 unset($_SESSION['action_pending']);
                 header('Location: index.php');
                 exit();
            }
        }
    } else {
         set_flash_message('помилка', 'Невідома очікувана дія: ' . escape($pending_action)); //
         unset($_SESSION['action_pending']); //
         header('Location: index.php'); //
         exit(); //
     }

} catch (PDOException $e) {
    error_log("Scan Handler Action Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка бази даних під час обробки дії.'); //
    unset($_SESSION['action_pending']); // Очистити дію при помилці БД
    header('Location: index.php'); // Повертаємо на головну
    exit(); //
}

// Цей код більше не потрібен тут, оскільки редіректи відбуваються всередині if/elseif/catch
// unset($_SESSION['action_pending']);
// header('Location: index.php');
// exit();
?>