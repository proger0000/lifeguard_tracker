<?php
/**
 * admin_manual_open_shift.php
 *
 * Скрипт для ручного відкриття нової зміни адміністратором або черговим.
 */

 require_once __DIR__ . '/config.php';
 global $pdo; // Глобальний об'єкт PDO

require_roles(['admin', 'duty_officer']); // Доступ тільки для адмінів та чергових

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Перевірка CSRF токена
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену. Спробуйте знову.');
        smart_redirect('index.php', [], 'admin-duty-content'); // Редирект на вкладку "Статус Змін"
        exit();
    }

    // Отримання даних з форми
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $user_id_lifeguard = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT); // ID лайфгарда
    $assignment_type = filter_input(INPUT_POST, 'assignment_type', FILTER_VALIDATE_INT);
    $start_time_input = $_POST['start_time'] ?? '';
    $activity_type = $_POST['activity_type'] ?? 'shift';
    if (!in_array($activity_type, ['shift', 'training'])) {
        $activity_type = 'shift';
    }

    // Валідація даних
    if (!$post_id) {
        $error_message = 'Будь ласка, оберіть пост.';
    } elseif (!$user_id_lifeguard) {
        $error_message = 'Будь ласка, оберіть лайфгарда.';
    } elseif ($assignment_type === null || !in_array($assignment_type, [0, 1, 2])) {
        $error_message = 'Будь ласка, оберіть коректний тип зміни (L0, L1, L2).';
    } elseif (empty($start_time_input)) {
        $error_message = 'Будь ласка, вкажіть час початку зміни.';
    } else {
        // Конвертація часу з datetime-local у формат MySQL DATETIME
        try {
            $start_time_dt = new DateTime($start_time_input);
            $start_time_sql = $start_time_dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $error_message = 'Некоректний формат часу початку зміни.';
            $start_time_sql = null; // Встановлюємо в null, щоб не пройшла подальша логіка
        }

        if ($start_time_sql) {
            // Перевірка, чи обраний лайфгард вже не має активної зміни
            try {
                $stmt_check_active = $pdo->prepare("SELECT id FROM shifts WHERE user_id = :user_id AND status = 'active'");
                $stmt_check_active->bindParam(':user_id', $user_id_lifeguard, PDO::PARAM_INT);
                $stmt_check_active->execute();
                $existing_active_shift = $stmt_check_active->fetch();

                if ($existing_active_shift) {
                    $error_message = 'Обраний лайфгард вже має активну зміну (ID: ' . $existing_active_shift['id'] . ').';
                } else {
                    // Всі перевірки пройдені, можна створювати зміну
                    $manual_opened_by_user_id = $_SESSION['user_id']; // ID адміна/чергового, що відкриває зміну

                    $sql_insert_shift = "INSERT INTO shifts 
                                            (activity_type, user_id, post_id, start_time, status, manual_opened_by, lifeguard_assignment_type, 
                                             start_photo_path, start_photo_approved_at, start_photo_approved_by, 
                                             end_time, manual_closed_by, manual_close_comment, photo_close_path, photo_close_uploaded_at, photo_close_approved)
                                         VALUES 
                                            (:activity_type, :user_id, :post_id, :start_time, 'active', :manual_opened_by, :assignment_type,
                                             NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)";
                    
                    $stmt_insert = $pdo->prepare($sql_insert_shift);
                    $stmt_insert->bindParam(':activity_type', $activity_type);
                    $stmt_insert->bindParam(':user_id', $user_id_lifeguard, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':post_id', $post_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':start_time', $start_time_sql);
                    $stmt_insert->bindParam(':manual_opened_by', $manual_opened_by_user_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':assignment_type', $assignment_type, PDO::PARAM_INT);

                    if ($stmt_insert->execute()) {
                        $new_shift_id = $pdo->lastInsertId();
                        // Якщо це тренування — одразу закриваємо (end_time = start_time, status = 'completed')
                        if ($activity_type === 'training') {
                            $stmt_close = $pdo->prepare("UPDATE shifts SET end_time = start_time, status = 'completed' WHERE id = :id");
                            $stmt_close->bindParam(':id', $new_shift_id, PDO::PARAM_INT);
                            $stmt_close->execute();
                        }
                        // Логування дії
                        $log_details = "Лайфгард ID: {$user_id_lifeguard}, Пост ID: {$post_id}, Тип: L{$assignment_type}, Час: {$start_time_sql}";
                        log_action($pdo, $manual_opened_by_user_id, "Вручну відкрив зміну #{$new_shift_id}", $new_shift_id, $log_details);
                        set_flash_message('успіх', "Зміну #{$new_shift_id} успішно відкрито вручну.");
                        smart_redirect('index.php', [], 'admin-duty-content');
                        exit();
                    } else {
                        $error_message = 'Не вдалося відкрити зміну вручну. Помилка бази даних.';
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Помилка бази даних при перевірці або створенні зміни.';
                error_log("Manual Open Shift DB Error: " . $e->getMessage());
            }
        }
    }
    // Якщо були помилки, встановлюємо flash і редиректимо назад, щоб показати панель
    if ($error_message) {
        set_flash_message('помилка', $error_message);
    }
    // Регенеруємо CSRF токен для наступної спроби
    unset($_SESSION['csrf_token']);
    smart_redirect('index.php', [], 'admin-duty-content'); // Завжди редирект на вкладку з панеллю
    exit();

} else {
    // Якщо це не POST-запит, просто редирект на головну
    set_flash_message('помилка', 'Некоректний запит для відкриття зміни.');
    smart_redirect('index.php', [], 'admin-duty-content');
    exit();
}
?>