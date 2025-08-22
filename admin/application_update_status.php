<?php
// admin/application_update_status.php
require_once '../config.php';
require_role('admin');
global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('помилка', 'Невірний метод запиту.');
    header('Location: ../index.php#admin-applications-content');
    exit();
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    set_flash_message('помилка', 'Помилка CSRF токену.');
    header('Location: ../index.php#admin-applications-content');
    exit();
}

$application_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$new_status = trim($_POST['status'] ?? '');
$manager_note = trim($_POST['manager_note'] ?? '');
$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['full_name'];

// Валідні статуси
$statuses = ['Новий', 'Передзвонити', 'Запрошений у басейн', 'Склав нормативи', 'Доданий до групи', 'Пройшов академію', 'Не актуально'];

if (!$application_id) {
    set_flash_message('помилка', 'Невірний ID заявки.');
} elseif (empty($manager_name)) {
     set_flash_message('помилка', 'Помилка: Не вдалося визначити ім\'я менеджера.');
} elseif (!in_array($new_status, $statuses)) {
     set_flash_message('помилка', 'Обрано недійсний статус.');
} else {
    try {
        $pdo->beginTransaction();

        // --- >>> ЗМІНЕНО: Використовуємо 'history' замість 'status_history' <<< ---
        $stmt_get = $pdo->prepare("SELECT status, history, comments_history FROM lifeguard_applications WHERE id = :id FOR UPDATE");
        $stmt_get->bindParam(':id', $application_id, PDO::PARAM_INT);
        $stmt_get->execute();
        $current_data = $stmt_get->fetch();

        if (!$current_data) { throw new Exception('Заявку не знайдено.'); }

        $old_status = $current_data['status'] ?? '';
        if (empty($old_status)) $old_status = 'Новий';

        $history_entry_status = null;
        if ($old_status !== $new_status) {
            $current_date_time = date('Y-m-d H:i:s');
            $history_entry_status = "$current_date_time: Менеджер \"$manager_name\" (ID: $manager_id) змінив статус з '$old_status' на '$new_status'.";
            if (!empty($manager_note)) { $history_entry_status .= " Коментар: " . $manager_note; }
        }

        $comment_entry = null;
        if (!empty($manager_note)) {
            $current_date_time_comment = date('Y-m-d H:i:s');
            $comment_entry = "$current_date_time_comment: ($manager_name / ID: $manager_id) " . $manager_note;
        }

        $new_comments_history = $current_data['comments_history'] ?? '';
        if ($comment_entry !== null) { $new_comments_history = $comment_entry . "\n" . $new_comments_history; }

        // --- >>> ЗМІНЕНО: Використовуємо 'history' <<< ---
        $new_status_history = $current_data['history'] ?? '';
        if ($history_entry_status !== null) { $new_status_history = $history_entry_status . "\n" . $new_status_history; }

        // --- >>> ЗМІНЕНО: Назва таблиці та стовпця history <<< ---
        $stmt_update = $pdo->prepare("
            UPDATE lifeguard_applications SET
                status = :status,
                history = :status_history, -- Змінено на history
                comments_history = :comments_history,
                manager_id = :manager_id,
                manager_name = :manager_name,
                manager_note = :manager_note,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt_update->bindParam(':status', $new_status);
        $stmt_update->bindParam(':status_history', $new_status_history); // Зв'язуємо з :status_history
        $stmt_update->bindParam(':comments_history', $new_comments_history);
        $stmt_update->bindParam(':manager_id', $manager_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':manager_name', $manager_name);
        $noteValue = !empty($manager_note) ? $manager_note : ($current_data['manager_note'] ?? null); // Зберігаємо старий коментар, якщо новий порожній? Або очищуємо? Поки що очищаємо, якщо не надано.
        $stmt_update->bindParam(':manager_note', $noteValue, $noteValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_update->bindParam(':id', $application_id, PDO::PARAM_INT);

        if ($stmt_update->execute()) {
            $pdo->commit();
            set_flash_message('успіх', 'Статус заявки ID ' . $application_id . ' успішно оновлено.');
        } else {
            throw new Exception('Не вдалося оновити статус заявки.');
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update App Status Error: ID {$application_id}, " . $e->getMessage());
        set_flash_message('помилка', 'Помилка бази даних: ' . $e->getMessage());
    }
}

unset($_SESSION['csrf_token']);
$redirect_params = array_intersect_key($_GET, array_flip(['app_status', 'app_search', 'app_page', 'app_sort', 'app_order', 'app_per_page']));
smart_redirect('index.php', $redirect_params, 'admin-applications-content');
exit();
?>