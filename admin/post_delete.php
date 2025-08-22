<?php
require_once '../config.php'; // Go up one directory for config
global $pdo;
require_role('admin'); // Only admins
save_current_page_for_redirect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('помилка', 'Невірний метод запиту.');
    smart_redirect('index.php', [], 'admin-posts-content');
    exit();
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
     set_flash_message('помилка', 'Помилка CSRF токену.');
     smart_redirect('index.php', [], 'admin-posts-content');
     exit();
}

$post_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$post_id) {
    set_flash_message('помилка', 'Невірний ID поста для видалення.');
    smart_redirect('index.php', [], 'admin-posts-content');
    exit();
}

// Optional: Add check here to see if the post is linked to recent/active shifts
// If so, maybe prevent deletion or warn the user more explicitly.
// Example check (adapt as needed):
/*
try {
    $stmt_check_shifts = $pdo->prepare("SELECT COUNT(*) FROM shifts WHERE post_id = :post_id AND (status = 'active' OR start_time > DATE_SUB(NOW(), INTERVAL 7 DAY))");
    $stmt_check_shifts->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $stmt_check_shifts->execute();
    if ($stmt_check_shifts->fetchColumn() > 0) {
        set_flash_message('помилка', 'Неможливо видалити пост, оскільки він пов\'язаний з активними або недавніми змінами.');
        smart_redirect('index.php', [], 'admin-posts-content');
        exit();
    }
} catch (PDOException $e) {
    // error_log("Post Delete Shift Check Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка перевірки пов\'язаних змін.');
    smart_redirect('index.php', [], 'admin-posts-content');
    exit();
}
*/

// --- Perform Deletion ---
try {
    // First, get the name for the success message before deleting
    $stmt_get_name = $pdo->prepare("SELECT name FROM posts WHERE id = :id");
    $stmt_get_name->bindParam(':id', $post_id, PDO::PARAM_INT);
    $stmt_get_name->execute();
    $post_name_result = $stmt_get_name->fetch();
    $post_name = $post_name_result ? $post_name_result['name'] : 'ID ' . $post_id; // Fallback name

    // Now delete
    $stmt_delete = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt_delete->bindParam(':id', $post_id, PDO::PARAM_INT);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->rowCount() > 0) {
             set_flash_message('успіх', 'Пост "' . escape($post_name) . '" успішно видалено.');
        } else {
            set_flash_message('помилка', 'Пост з ID ' . escape($post_id) . ' не знайдено для видалення.');
        }
    } else {
         set_flash_message('помилка', 'Не вдалося видалити пост.');
    }
} catch (PDOException $e) {
    // error_log("Post Delete DB Error: " . $e->getMessage());
    // Check for foreign key constraint violation
    if ($e->getCode() == '23000') { // Integrity constraint violation
         set_flash_message('помилка', 'Не вдалося видалити пост "' . escape($post_name) . '". Ймовірно, він пов\'язаний з існуючими записами змін.');
    } else {
        set_flash_message('помилка', 'Помилка бази даних під час видалення поста.');
    }
}

// Redirect back to the admin posts tab regardless of outcome (message will indicate success/failure)
smart_redirect('index.php', [], 'admin-posts-content');
exit();
?>