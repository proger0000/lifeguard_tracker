<?php
require_once '../config.php'; // Go up one directory for config
global $pdo;
require_role('admin'); // Only admins

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('помилка', 'Невірний метод запиту.');
    header('Location: ../index.php#admin-users-content');
    exit();
}

if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
     set_flash_message('помилка', 'Помилка CSRF токену.');
     header('Location: ../index.php#admin-users-content');
     exit();
}

$user_id_to_delete = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$user_id_to_delete) {
    set_flash_message('помилка', 'Невірний ID користувача для видалення.');
    header('Location: ../index.php#admin-users-content');
    exit();
}

// --- CRITICAL: Prevent self-deletion ---
if ($user_id_to_delete === $_SESSION['user_id']) {
     set_flash_message('помилка', 'Неможливо видалити власний акаунт.');
     header('Location: ../index.php#admin-users-content');
     exit();
}

// --- Perform Deletion ---
try {
    // Get name for message
    $stmt_get_name = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
    $stmt_get_name->bindParam(':id', $user_id_to_delete, PDO::PARAM_INT);
    $stmt_get_name->execute();
    $user_name_result = $stmt_get_name->fetch();
    $user_name = $user_name_result ? $user_name_result['full_name'] : 'ID ' . $user_id_to_delete;

    // Delete user
    $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt_delete->bindParam(':id', $user_id_to_delete, PDO::PARAM_INT);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->rowCount() > 0) {
             set_flash_message('успіх', 'Користувача "' . escape($user_name) . '" успішно видалено.');
             // Note: Associated shifts might be automatically deleted or restricted
             // depending on the FOREIGN KEY constraint (ON DELETE CASCADE/RESTRICT).
             // The schema uses ON DELETE CASCADE for shifts, so they will be removed too.
        } else {
            set_flash_message('помилка', 'Користувача з ID ' . escape($user_id_to_delete) . ' не знайдено для видалення.');
        }
    } else {
         set_flash_message('помилка', 'Не вдалося видалити користувача.');
    }
} catch (PDOException $e) {
    // error_log("User Delete DB Error: " . $e->getMessage());
     set_flash_message('помилка', 'Помилка бази даних під час видалення користувача.');
     // Could add specific checks for foreign key errors if needed, though CASCADE should handle shifts.
}

// Redirect back to the admin users tab
header('Location: ../index.php#admin-users-content');
exit();
?>