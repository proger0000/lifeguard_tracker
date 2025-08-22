<?php
/**
 * admin/delete_single_shift.php
 * Скрипт для видалення окремої зміни.
 */

require_once '../config.php';
require_once '../includes/functions.php';
global $pdo;
require_roles(['admin', 'duty_officer']); // Або тільки 'admin', якщо черговим не можна видаляти

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('помилка', 'Невірний метод запиту для видалення.');
    smart_redirect('manage_shifts.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    set_flash_message('помилка', 'Помилка CSRF токену.');
    smart_redirect('manage_shifts.php');
    exit();
}

$shift_id_to_delete = filter_input(INPUT_POST, 'shift_id_to_delete', FILTER_VALIDATE_INT);

if (!$shift_id_to_delete) {
    set_flash_message('помилка', 'Невірний ID зміни для видалення.');
    smart_redirect('manage_shifts.php');
    exit();
}

try {
    // Опціонально: Отримуємо інформацію про зміну для логування перед видаленням
    $stmt_get_info = $pdo->prepare("SELECT user_id, post_id, start_time FROM shifts WHERE id = :shift_id");
    $stmt_get_info->bindParam(':shift_id', $shift_id_to_delete, PDO::PARAM_INT);
    $stmt_get_info->execute();
    $shift_info_for_log = $stmt_get_info->fetch(PDO::FETCH_ASSOC);
    $log_details_delete = "Деталі видаленої зміни: " . ($shift_info_for_log ? http_build_query($shift_info_for_log) : 'не знайдено');


    // Видалення пов'язаних інцидентів (якщо ON DELETE CASCADE не налаштовано для report_incidents -> shift_reports)
    // Спочатку видаляємо інциденти, потім звіти, потім зміну
    $stmt_delete_incidents = $pdo->prepare("DELETE ri FROM report_incidents ri JOIN shift_reports sr ON ri.shift_report_id = sr.id WHERE sr.shift_id = :shift_id");
    $stmt_delete_incidents->bindParam(':shift_id', $shift_id_to_delete, PDO::PARAM_INT);
    $stmt_delete_incidents->execute();

    // Видалення пов'язаних звітів (якщо ON DELETE CASCADE не налаштовано для shift_reports -> shifts)
    $stmt_delete_reports = $pdo->prepare("DELETE FROM shift_reports WHERE shift_id = :shift_id");
    $stmt_delete_reports->bindParam(':shift_id', $shift_id_to_delete, PDO::PARAM_INT);
    $stmt_delete_reports->execute();

    // Тепер видаляємо саму зміну
    $stmt_delete_shift = $pdo->prepare("DELETE FROM shifts WHERE id = :shift_id");
    $stmt_delete_shift->bindParam(':shift_id', $shift_id_to_delete, PDO::PARAM_INT);
    
    if ($stmt_delete_shift->execute()) {
        if ($stmt_delete_shift->rowCount() > 0) {
            log_action($pdo, $_SESSION['user_id'], "Видалив зміну #{$shift_id_to_delete}", $shift_id_to_delete, $log_details_delete);
            set_flash_message('успіх', "Зміну #{$shift_id_to_delete} та всі пов'язані дані (звіти, інциденти) успішно видалено.");
        } else {
            set_flash_message('помилка', "Зміну з ID {$shift_id_to_delete} не знайдено для видалення.");
        }
    } else {
        set_flash_message('помилка', 'Не вдалося видалити зміну.');
    }

} catch (PDOException $e) {
    set_flash_message('помилка', 'Помилка бази даних під час видалення зміни: ' . $e->getMessage());
    error_log("Delete Single Shift Error: " . $e->getMessage());
}

// Регенеруємо CSRF токен
unset($_SESSION['csrf_token']);
smart_redirect($_SESSION['previous_page'] ?? 'manage_shifts.php');
exit();
?>