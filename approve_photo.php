<?php
// approve_photo.php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'translations_add.php'; // Подключаем универсальные переводы

// Доступ тільки адміну або черговому
require_roles(['admin', 'duty_officer']); 

global $pdo;

$shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$approver_user_id = $_SESSION['user_id'];

// Перевірка методу, ID та дії
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$shift_id || !in_array($action, ['approve', 'reject'])) {
    set_flash_message(tr('error_flash', 'Помилка'), tr('incorrect_request', 'Некоректний запит.'));
    smart_redirect('index.php', [], 'admin-duty-content');
    exit();
}

// Перевірка CSRF
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    set_flash_message(tr('error_flash', 'Помилка'), tr('csrf_token_error', 'Помилка CSRF токену.'));
    smart_redirect('index.php', [], 'admin-duty-content');
    exit();
}

// --- Обробка Дії ---
try {
    if ($action === 'approve') {
        // Отримуємо тип призначення рятувальника
        $lifeguard_assignment_type_input = $_POST['lifeguard_assignment_type'] ?? null;

        if ($lifeguard_assignment_type_input === null || !in_array($lifeguard_assignment_type_input, ['0', '1', '2'], true)) {
            set_flash_message(tr('error_flash', 'Помилка'), tr('error_assignment_type_required', 'Будь ласка, виберіть тип призначення рятувальника для підтвердження.'));
            smart_redirect('index.php', ['shift_id_error' => $shift_id], 'admin-duty-content');
            exit();
        }
        $lifeguard_assignment_type = (int)$lifeguard_assignment_type_input;

        // Підтвердження фото, призначення типу та оновлення статусу зміни
        $stmt_approve = $pdo->prepare("
            UPDATE shifts
            SET start_photo_approved_at = NOW(), 
                start_photo_approved_by = :approver_id,
                lifeguard_assignment_type = :assignment_type,
                status = 'active'
            WHERE id = :shift_id 
              AND start_photo_path IS NOT NULL 
              AND start_photo_approved_at IS NULL
        ");
        $stmt_approve->bindParam(':approver_id', $approver_user_id, PDO::PARAM_INT);
        $stmt_approve->bindParam(':assignment_type', $lifeguard_assignment_type, PDO::PARAM_INT);
        $stmt_approve->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);

        if ($stmt_approve->execute()) {
            if ($stmt_approve->rowCount() > 0) {
                set_flash_message(tr('success_flash', 'Успіх'), sprintf(tr('photo_approved_type_assigned_msg', 'Фото для зміни ID %s підтверджено, тип призначення встановлено.'), $shift_id));
                log_action($pdo, $approver_user_id, sprintf(tr('log_photo_approved_assigned_type', 'Підтвердив фото відкриття та призначив тип Л%s для зміни #%s'), $lifeguard_assignment_type, $shift_id), $shift_id);
            } else {
                // Перевіряємо, чи зміна взагалі існує і чи потребувала підтвердження
                $check_stmt = $pdo->prepare("SELECT start_photo_approved_at FROM shifts WHERE id = :shift_id");
                $check_stmt->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
                $check_stmt->execute();
                $existing_shift = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_shift && !empty($existing_shift['start_photo_approved_at'])) {
                    set_flash_message(tr('info_flash', 'Інфо'), sprintf(tr('photo_already_approved_msg', 'Фото для зміни ID %s вже було підтверджено.'), $shift_id));
                } else {
                     set_flash_message(tr('info_flash', 'Інфо'), sprintf(tr('photo_not_found_or_no_approval_needed_msg', 'Фото для зміни ID %s не знайдено або не потребувало підтвердження.'), $shift_id));
                }
            }
        } else {
            set_flash_message(tr('error_flash', 'Помилка'), tr('error_approving_photo_db', 'Не вдалося підтвердити фото (помилка БД).'));
        }

    } elseif ($action === 'reject') {
        // При відхиленні фото скидаємо шлях до фото та статус
        $stmt_reject = $pdo->prepare("
            UPDATE shifts
            SET start_photo_path = NULL,
                start_photo_approved_at = NULL,
                start_photo_approved_by = NULL,
                lifeguard_assignment_type = NULL,
                status = 'pending_photo_open'
            WHERE id = :shift_id
        ");
        $stmt_reject->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
        
        if ($stmt_reject->execute()) {
            if ($stmt_reject->rowCount() > 0) {
                set_flash_message(tr('success_flash', 'Успіх'), sprintf(tr('photo_rejected_msg', 'Фото для зміни ID %s відхилено. Рятувальник має завантажити нове.'), $shift_id));
                log_action($pdo, $approver_user_id, sprintf(tr('log_photo_rejected', 'Відхилив фото відкриття для зміни #%s'), $shift_id), $shift_id);
            } else {
                 set_flash_message(tr('info_flash', 'Інфо'), sprintf(tr('shift_not_found_or_no_action_needed_msg', 'Зміна ID %s не знайдена або дія не потрібна.'), $shift_id));
            }
        } else {
            set_flash_message(tr('error_flash', 'Помилка'), tr('error_rejecting_photo_db', 'Не вдалося відхилити фото (помилка БД).'));
        }
    }

} catch (PDOException $e) {
    error_log("Approve Photo DB Error: " . $e->getMessage());
    set_flash_message(tr('error_flash', 'Помилка'), tr('db_error_processing_photo', 'Помилка бази даних під час обробки фото.'));
}

// Очищаємо токен після дії
if (isset($_SESSION['csrf_token'])) {
    unset($_SESSION['csrf_token']); 
}

// Редірект назад на головну панель чергового/адміністратора
smart_redirect('index.php', [], 'admin-duty-content');
exit();

?>