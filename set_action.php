<?php
require_once 'config.php'; // Connect db, start session, load functions

require_roles(['lifeguard']); // Only lifeguards can set actions

$action = $_GET['action'] ?? null;

if ($action === 'start' || $action === 'end') {
    $_SESSION['action_pending'] = $action; // 'start' or 'end'
    $message = ($action === 'start')
        ? 'Тепер скануйте NFC-мітку поста, щоб розпочати зміну.'
        : 'Тепер скануйте NFC-мітку поста, щоб завершити зміну.';
    set_flash_message('інфо', $message); // Use a different type for info
} else {
    set_flash_message('помилка', 'Невідома дія.');
    unset($_SESSION['action_pending']); // Clear invalid action
}

header('Location: index.php'); // Redirect back to the dashboard
exit();
?>