<?php
// admin/application_add_comment.php
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
$new_comment = trim($_POST['manager_note'] ?? '');
$manager_id = $_SESSION['user_id'];
$manager_name = $_SESSION['full_name'];

if (!$application_id) {
    set_flash_message('помилка', 'Невірний ID заявки.');
} elseif (empty($manager_name)) {
     set_flash_message('помилка', 'Помилка: Не вдалося визначити ім\'я менеджера.');
} elseif (empty($new_comment)) {
     set_flash_message('помилка', 'Текст коментаря не може бути порожнім.');
} else {
    try {
        $pdo->beginTransaction();

        // --- >>> ЗМІНЕНО: Назва таблиці lifeguard_aplications <<< ---
        $stmt_get = $pdo->prepare("SELECT comments_history FROM lifeguard_aplications WHERE id = :id FOR UPDATE");
        $stmt_get->bindParam(':id', $application_id, PDO::PARAM_INT);
        $stmt_get->execute();
        $current_data = $stmt_get->fetch();

        if ($current_data === false) { throw new Exception('Заявку не знайдено.'); }
        $current_comments_history = $current_data['comments_history'] ?? '';

        $current_date_time = date('Y-m-d H:i:s');
        $comment_entry = "$current_date_time: ($manager_name / ID: $manager_id) " . $new_comment;
        $new_comments_history = $comment_entry . "\n" . $current_comments_history;

        // --- >>> ЗМІНЕНО: Назва таблиці lifeguard_aplications <<< ---
        $stmt_update = $pdo->prepare("
            UPDATE lifeguard_aplications SET
                manager_note = :new_comment,
                comments_history = :comments_history,
                manager_id = :manager_id,
                manager_name = :manager_name,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt_update->bindParam(':new_comment', $new_comment);
        $stmt_update->bindParam(':comments_history', $new_comments_history);
        $stmt_update->bindParam(':manager_id', $manager_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':manager_name', $manager_name);
        $stmt_update->bindParam(':id', $application_id, PDO::PARAM_INT);

        if ($stmt_update->execute()) {
            $pdo->commit();
            set_flash_message('успіх', 'Коментар до заявки ID ' . $application_id . ' успішно додано.');
        } else {
            throw new Exception('Не вдалося додати коментар.');
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add App Comment Error: ID {$application_id}, " . $e->getMessage());
        set_flash_message('помилка', 'Помилка бази даних: ' . $e->getMessage());
    }
}

unset($_SESSION['csrf_token']);
$redirect_params = array_intersect_key($_GET, array_flip(['app_status', 'app_search', 'app_page', 'app_sort', 'app_order', 'app_per_page']));
smart_redirect('index.php', $redirect_params, 'admin-applications-content');
exit();
?>