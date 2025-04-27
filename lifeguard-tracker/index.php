<?php
require_once 'config.php'; // Connect db, start session, load functions

// --- Access Control: Require Login ---
require_login(); // Redirects to login.php if not logged in

// --- Include Header ---
require_once 'includes/header.php'; // Includes DOCTYPE, head, navigation, opening <main>

// --- Role-Based Content Inclusion ---
// The <main> tag was opened in header.php
// We now include the content specific to the user's role.

$user_role = $_SESSION['user_role'] ?? null;

switch ($user_role) {
    case 'admin':
        require_once 'includes/admin_panel.php';
        break;
    case 'duty_officer':
        require_once 'includes/duty_officer_panel.php';
        break;
    case 'lifeguard':
        require_once 'includes/lifeguard_panel.php';
        break;
    default:
        // Should not happen if login sets role correctly, but handle gracefully
        echo '<p class="text-red-500">Помилка: Невідома роль користувача.</p>';
        // Optionally log this error
        // error_log("Unknown role encountered for user ID: " . $_SESSION['user_id']);
        break;
}

// --- Include Footer ---
// The footer includes the closing </main>, <footer>, scripts, closing </html>
require_once 'includes/footer.php';

?>