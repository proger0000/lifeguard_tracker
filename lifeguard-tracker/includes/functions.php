<?php

// --- Security Functions ---

/**
 * Generate a CSRF token and store it in the session.
 * @return string The generated token.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the one in the session.
 * @param string $token The token submitted via form.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF hidden input field.
 */
function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

/**
 * Simple HTML escaping function.
 * @param string|null $string The string to escape.
 * @return string The escaped string.
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// --- Role Check Functions ---

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_message'] = ['type' => 'помилка', 'text' => 'Будь ласка, увійдіть для доступу до цієї сторінки.'];
        header('Location: login.php');
        exit();
    }
}

function require_role($required_role) {
    require_login(); // Ensure user is logged in first
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
         $_SESSION['flash_message'] = ['type' => 'помилка', 'text' => 'У вас недостатньо прав для доступу до цієї сторінки.'];
         header('Location: index.php'); // Redirect to their default dashboard
         exit();
    }
}

function require_roles(array $allowed_roles) {
    require_login();
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
        $_SESSION['flash_message'] = ['type' => 'помилка', 'text' => 'У вас недостатньо прав для доступу до цієї сторінки.'];
        header('Location: index.php');
        exit();
    }
}

// --- Flash Message Functions ---

function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $message];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $bgColor = $message['type'] === 'успіх' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        $icon = $message['type'] === 'успіх' ? 'fa-check-circle' : 'fa-exclamation-triangle';

        echo '<div class="border px-4 py-3 rounded relative ' . $bgColor . '" role="alert">';
        echo '<strong class="font-bold"><i class="fas ' . $icon . ' mr-2"></i>' . escape(ucfirst($message['type'])) . '!</strong>';
        echo '<span class="block sm:inline ml-2">' . escape($message['text']) . '</span>';
        echo '</div>';
        unset($_SESSION['flash_message']); // Clear the message after displaying
    }
}

// --- Role Name Mapping ---
function get_role_name_ukrainian($role_code) {
    $roles = [
        'admin' => 'Адміністратор',
        'duty_officer' => 'Черговий',
        'lifeguard' => 'Рятувальник',
    ];
    return $roles[$role_code] ?? 'Невідома роль';
}

// --- Date/Time Formatting ---
 function format_datetime($datetime_string) {
    if (empty($datetime_string)) return '-';
    try {
        $date = new DateTime($datetime_string);
        // Adjust format as needed (e.g., 'd.m.Y H:i')
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}

function format_duration($start_time, $end_time = null) {
    if (empty($start_time)) return '-';
    try {
        $start = new DateTime($start_time);
        $end = $end_time ? new DateTime($end_time) : new DateTime(); // Use current time if end is null
        $interval = $start->diff($end);
        return $interval->format('%h год %i хв'); // Format as hours and minutes
    } catch (Exception $e) {
        return 'Error';
    }
}
?>