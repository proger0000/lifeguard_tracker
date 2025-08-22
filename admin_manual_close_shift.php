<?php
/**
 * admin_manual_close_shift.php
 *
 * Скрипт для ручного закриття активної зміни адміністратором або черговим.
 */

 require_once __DIR__ . '/config.php';
 require_once __DIR__ . '/includes/helpers.php'; // Для функции calculate_rounded_hours
 global $pdo; // Глобальний об'єкт PDO

require_roles(['admin', 'duty_officer']); // Доступ тільки для адмінів та чергових

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Перевірка CSRF токена
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену. Спробуйте знову.');
        smart_redirect('index.php', [], 'admin-duty-content');
        exit();
    }

    // Отримання даних з форми
    $shift_id_to_close = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
    $end_time_input = $_POST['end_time'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    // Валідація даних
    if (!$shift_id_to_close) {
        $error_message = 'Будь ласка, оберіть активну зміну для закриття.';
    } elseif (empty($end_time_input)) {
        $error_message = 'Будь ласка, вкажіть час завершення зміни.';
    } elseif (empty($comment)) {
        $error_message = 'Будь ласка, вкажіть коментар (причину ручного закриття).';
    } else {
        // Конвертація часу з datetime-local у формат MySQL DATETIME
        try {
            $end_time_dt = new DateTime($end_time_input);
            $end_time_sql = $end_time_dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $error_message = 'Некоректний формат часу завершення зміни.';
            $end_time_sql = null;
        }

        if ($end_time_sql) {
            try {
                // Перевірка, чи зміна існує і чи вона активна
                $stmt_check_shift = $pdo->prepare("SELECT id, start_time FROM shifts WHERE id = :shift_id AND status = 'active'");
                $stmt_check_shift->bindParam(':shift_id', $shift_id_to_close, PDO::PARAM_INT);
                $stmt_check_shift->execute();
                $shift_to_close = $stmt_check_shift->fetch();

                if (!$shift_to_close) {
                    $error_message = 'Обрана зміна не знайдена або вже не активна.';
                } elseif (new DateTime($end_time_sql) < new DateTime($shift_to_close['start_time'])) {
                    $error_message = 'Час завершення не може бути раніше часу початку зміни.';
                } else {
                    // Все добре, можна закривати зміну
                    $manual_closed_by_user_id = $_SESSION['user_id'];

                    // === Расчет округленных часов ===
                    $rounded_hours = calculate_rounded_hours($shift_to_close['start_time'], $end_time_sql);

                    $sql_update_shift = "UPDATE shifts 
                                         SET status = 'completed', 
                                             end_time = :end_time, 
                                             manual_closed_by = :manual_closed_by, 
                                             manual_close_comment = :comment,
                                             rounded_work_hours = :rounded_hours
                                         WHERE id = :shift_id AND status = 'active'";
                    
                    $stmt_update = $pdo->prepare($sql_update_shift);
                    $stmt_update->bindParam(':end_time', $end_time_sql);
                    $stmt_update->bindParam(':manual_closed_by', $manual_closed_by_user_id, PDO::PARAM_INT);
                    $stmt_update->bindParam(':comment', $comment, PDO::PARAM_STR);
                    $stmt_update->bindParam(':rounded_hours', $rounded_hours, PDO::PARAM_INT);
                    $stmt_update->bindParam(':shift_id', $shift_id_to_close, PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        if ($stmt_update->rowCount() > 0) {
                            // Логування дії
                            log_action($pdo, $manual_closed_by_user_id, "Примусово завершив зміну #{$shift_id_to_close}", $shift_id_to_close, $comment);
                            set_flash_message('успіх', "Зміну #{$shift_id_to_close} успішно закрито вручну.");
                        } else {
                            // Це може статися, якщо статус змінився між перевіркою та оновленням
                            $error_message = 'Не вдалося закрити зміну. Можливо, її статус вже змінився.';
                        }
                        smart_redirect('index.php', [], 'admin-duty-content');
                        exit();
                    } else {
                        $error_message = 'Не вдалося закрити зміну. Помилка бази даних.';
                    }
                }
            } catch (PDOException $e) {
                $error_message = 'Помилка бази даних при перевірці або закритті зміни.';
                error_log("Manual Close Shift DB Error: " . $e->getMessage());
            }
        }
    }
    // Якщо були помилки, встановлюємо flash і редиректимо
    if ($error_message) {
        set_flash_message('помилка', $error_message);
    }
    unset($_SESSION['csrf_token']);
    smart_redirect('index.php', [], 'admin-duty-content');
    exit();

} else {
    set_flash_message('помилка', 'Некоректний запит для закриття зміни.');
    smart_redirect('index.php', [], 'admin-duty-content');
    exit();
}
?>