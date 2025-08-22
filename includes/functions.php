<?php
// /includes/functions.php

// --- Security Functions ---

/**
 * Generate a CSRF token and store it in the session if it doesn't exist.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Get the CSRF token from the session.
 * @return string The CSRF token.
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        generate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}


/**
 * Validate a submitted CSRF token against the one in the session.
 * @param string $token The token submitted via form.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token($token) {
    // It's important to have a token in the session to compare against.
    if (empty($_SESSION['csrf_token'])) {
        return false; 
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF hidden input field.
 */
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . get_csrf_token() . '">';
}

/**
 * Simple HTML escaping function.
 * @param string|null $string The string to escape.
 * @return string The escaped string.
 */
function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- Role Check Functions ---

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        set_flash_message('помилка', 'Будь ласка, увійдіть для доступу до цієї сторінки.');
        // Використовуємо APP_URL для коректного шляху до login.php
        header('Location: ' . rtrim(APP_URL, '/') . '/login.php');
        exit();
    }
}

function require_role($required_role) {
    require_login(); 
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
         set_flash_message('помилка', 'У вас недостатньо прав для доступу до цієї сторінки.');
         header('Location: ' . rtrim(APP_URL, '/') . '/index.php'); 
         exit();
    }
}

function require_roles(array $allowed_roles) {
    require_login();
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
        set_flash_message('помилка', 'У вас недостатньо прав для доступу до цієї сторінки.');
        header('Location: ' . rtrim(APP_URL, '/') . '/index.php');
        exit();
    }
}

/**
 * Handles access control and group data retrieval for academy pages.
 * It checks user roles, finds the correct group, and verifies access.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $current_page The filename of the current page for redirects (e.g., 'mark_attendance.php').
 * @return array An array containing 'group_id', 'group_name', 'all_groups_for_select'.
 */
function handle_academy_group_access(PDO $pdo, string $current_page): array
{
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['user_role'];

    $group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
    $group_name = null;
    $all_groups_for_select = [];

    $trainer_dashboard_url = 'trainer_dashboard.php';
    $admin_fallback_url = $current_page; // Go back to the same page without group_id

    try {
        // Admins can see all groups in a dropdown.
        if ($current_user_role === 'admin') {
            $stmt_all_groups = $pdo->query("SELECT id, name FROM academy_groups ORDER BY name");
            $all_groups_for_select = $stmt_all_groups->fetchAll(PDO::FETCH_ASSOC);
        }

        // If no group is selected:
        if (!$group_id) {
            // For trainers, find their assigned group and redirect.
            if ($current_user_role === 'trainer') {
                $stmt_find_group = $pdo->prepare("SELECT id FROM academy_groups WHERE trainer_user_id = :trainer_id LIMIT 1");
                $stmt_find_group->bindParam(':trainer_id', $current_user_id, PDO::PARAM_INT);
                $stmt_find_group->execute();
                $found_group_id = $stmt_find_group->fetchColumn();

                if ($found_group_id) {
                    header("Location: {$current_page}?group_id={$found_group_id}");
                    exit();
                } else {
                    set_flash_message('помилка', 'Вас не призначено тренером жодної групи.');
                    header("Location: {$trainer_dashboard_url}");
                    exit();
                }
            }
            // For admins, we just don't set a group_id and let them pick from the dropdown.
            // So we return early here for admins if no group is chosen.
            return [
                'group_id' => null,
                'group_name' => null,
                'all_groups_for_select' => $all_groups_for_select
            ];
        }

        // If a group_id IS provided, validate access.
        if ($group_id) {
            $sql_check = "SELECT name FROM academy_groups WHERE id = :group_id";
            if ($current_user_role === 'trainer') {
                $sql_check .= " AND trainer_user_id = :trainer_id";
            }

            $stmt_check_group = $pdo->prepare($sql_check);
            $stmt_check_group->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            if ($current_user_role === 'trainer') {
                $stmt_check_group->bindParam(':trainer_id', $current_user_id, PDO::PARAM_INT);
            }

            $stmt_check_group->execute();
            $group_name = $stmt_check_group->fetchColumn();

            if (!$group_name) {
                set_flash_message('помилка', 'Група не знайдена або у вас немає до неї доступу.');
                $redirect_url = ($current_user_role === 'admin') ? $admin_fallback_url : $trainer_dashboard_url;
                header("Location: " . $redirect_url);
                exit();
            }
        }
    } catch (PDOException $e) {
        set_flash_message('помилка', 'Помилка отримання даних груп.');
        error_log(ucfirst($current_page) . " - Group Load/Check Error: " . $e->getMessage());
        $redirect_url = ($current_user_role === 'admin') ? $admin_fallback_url : $trainer_dashboard_url;
        header("Location: " . $redirect_url);
        exit();
    }

    return [
        'group_id' => $group_id,
        'group_name' => $group_name,
        'all_groups_for_select' => $all_groups_for_select
    ];
}

// --- Flash Message Functions ---

function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $message];
}

/**
 * Displays the flash message and clears it from the session.
 * Make sure this is called BEFORE any headers are sent if used in a script that also redirects.
 * Typically called in header.php.
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $message['type'] ?? 'інфо'; // Default to 'info'
        $text = $message['text'] ?? 'Повідомлення відсутнє.';
        $baseClasses = 'border-l-4 p-4 mb-4 rounded-md shadow-sm relative overflow-hidden';
        $typeClasses = '';
        $icon = 'fa-info-circle';
        $title = 'Інформація';

        switch ($type) {
            case 'успіх':
                $typeClasses = 'bg-green-50 border-green-500 text-green-700';
                $icon = 'fa-check-circle';
                $title = 'Успіх';
                break;
            case 'помилка':
                $typeClasses = 'bg-red-50 border-red-500 text-red-700';
                $icon = 'fa-exclamation-triangle';
                $title = 'Помилка';
                break;
            case 'інфо':
            default:
                $typeClasses = 'bg-blue-50 border-blue-500 text-blue-700';
                $icon = 'fa-info-circle';
                $title = 'Інформація';
                break;
        }

        echo '<div id="flash-message" class="' . $baseClasses . ' ' . $typeClasses . '" role="alert">';
        echo '<div class="flash-timer absolute bottom-0 left-0 h-1 bg-white/50"></div>';
        echo '<strong class="font-bold flex items-center"><i class="fas ' . $icon . ' mr-2"></i>' . escape($title) . '</strong>';
        echo '<span class="block sm:inline mt-1 text-sm">' . escape($text) . '</span>';
        echo '<button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 p-1 text-inherit opacity-70 hover:opacity-100">&times;</button>';
        echo '</div>';

        unset($_SESSION['flash_message']);
    }
}


// --- Role Name Mapping ---
function get_role_name_ukrainian($role_code) {
    $roles = [
        'admin' => 'Адміністратор',
        'director' => 'Директор',
        'duty_officer' => 'Черговий',
        'lifeguard' => 'Лайфгард',
        'trainer' => 'Тренер',
        'analyst' => 'Аналітик'
    ];
    return $roles[$role_code] ?? 'Невідома роль';
}

// --- Date/Time Formatting ---
function format_datetime($datetime_string, $format = 'd.m.Y H:i') { // Змінено формат за замовчуванням
    if (empty($datetime_string) || $datetime_string === '0000-00-00 00:00:00') return '-';
    try {
        $date = new DateTime($datetime_string);
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Error formatting date: {$datetime_string} - " . $e->getMessage());
        return 'Нев. дата'; // Повертаємо більш інформативне повідомлення
    }
}

/**
 * Format datetime in user's timezone
 * @param string $datetime_string The datetime string to format
 * @param string $format The format to use
 * @return string Formatted datetime string
 */
function format_datetime_user_tz($datetime_string, $format = 'd.m.Y H:i') {
    if (empty($datetime_string) || $datetime_string === '0000-00-00 00:00:00') return '-';
    try {
        $date = new DateTime($datetime_string);
        $date->setTimezone(new DateTimeZone('Europe/Kiev')); // Set to Ukraine timezone
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Error formatting date with timezone: {$datetime_string} - " . $e->getMessage());
        return 'Нев. дата';
    }
}

function format_duration($start_time, $end_time = null) {
    if (empty($start_time)) return '-';
    try {
        $start = new DateTime($start_time);
        $end = $end_time ? new DateTime($end_time) : new DateTime();
        $interval = $start->diff($end);
        
        $parts = [];
        if ($interval->days > 0) {
            $hours = $interval->days * 24 + $interval->h;
            if ($hours > 0) $parts[] = $hours . ' год';
        } elseif ($interval->h > 0) {
            $parts[] = $interval->h . ' год';
        }
        
        if ($interval->i > 0) $parts[] = $interval->i . ' хв';
        
        if (empty($parts) && $interval->s >= 0) return $interval->s . ' сек';
        
        return !empty($parts) ? implode(' ', $parts) : '0 хв';
    } catch (Exception $e) {
        error_log("Error formatting duration: Start: {$start_time}, End: {$end_time} - " . $e->getMessage());
        return 'Помилка';
    }
}

// --- НОВА ФУНКЦІЯ ДЛЯ "РОЗУМНОГО" РЕДИРЕКТУ ---
/**
 * Redirects the user to a specified URL, an admin tab, or a fallback URL.
 * Preserves specified GET parameters.
 *
 * @param string $fallback_path The PATH part of the URL to redirect to if no other specific URL is provided (e.g., 'index.php' or 'admin/manage_shifts.php'). Should NOT start with a slash if it's relative to APP_URL.
 * @param array $params_to_preserve Associative array of GET parameters to preserve or add.
 * @param string|null $admin_tab_anchor If redirecting to an admin tab on index.php, specify the anchor.
 */
function smart_redirect($fallback_path = 'index.php', $params_to_preserve = [], $admin_tab_anchor = null) {
    $base_app_url = rtrim(APP_URL, '/'); // APP_URL має бути визначено в config.php, напр. https://lifeguard.kyiv.ua/lifeguard-tracker
    $target_url_path_segment = '';    // Шлях відносно $base_app_url, напр. /index.php або /admin/manage_shifts.php
    $current_query_params = [];
    $current_fragment = '';

    // 1. Визначаємо цільовий шлях
    if ($admin_tab_anchor) {
        // Якщо є якір адмін-панелі, то завжди йдемо на index.php (в корені додатку)
        $target_url_path_segment = '/index.php';
        $current_fragment = $admin_tab_anchor; // Якір адмінки має пріоритет
    } elseif (isset($_POST['return_to']) && !empty($_POST['return_to'])) {
        $return_to_url = filter_var($_POST['return_to'], FILTER_SANITIZE_URL);
        // Перевіряємо, чи return_to є повним URL, що починається з APP_URL, чи відносним шляхом
        if (strpos($return_to_url, $base_app_url) === 0) {
            $relative_part = substr($return_to_url, strlen($base_app_url));
            $parsed_return_to = parse_url($relative_part);
            $target_url_path_segment = $parsed_return_to['path'] ?? '/index.php';
            if (!empty($parsed_return_to['query'])) parse_str($parsed_return_to['query'], $current_query_params);
            $current_fragment = $parsed_return_to['fragment'] ?? '';
        } elseif (strpos($return_to_url, '/') === 0) { // Якщо це абсолютний шлях від кореня домену
             $target_url_path_segment = $return_to_url; // Використовуємо як є, але це менш безпечно
        } else { // Якщо це відносний шлях (напр. manage_shifts.php)
            $target_url_path_segment = '/' . ltrim($return_to_url, '/');
        }
    } elseif (isset($_SESSION['previous_page']) && !empty($_SESSION['previous_page'])) {
        // $_SESSION['previous_page'] зазвичай зберігає REQUEST_URI (напр. /lifeguard-tracker/admin/edit_single_shift.php?shift_id=...)
        $previous_uri = $_SESSION['previous_page'];
        $parsed_previous = parse_url($previous_uri);

        // Видаляємо можливий префікс шляху APP_URL з REQUEST_URI, якщо він там є
        $app_url_path_component = parse_url(APP_URL, PHP_URL_PATH); // напр. /lifeguard-tracker
        if ($app_url_path_component && strpos($parsed_previous['path'], $app_url_path_component) === 0) {
            $target_url_path_segment = substr($parsed_previous['path'], strlen($app_url_path_component));
        } else {
            $target_url_path_segment = $parsed_previous['path'] ?? '/index.php';
        }
        // Переконуємося, що шлях починається зі слеша
        if (substr($target_url_path_segment, 0, 1) !== '/') {
            $target_url_path_segment = '/' . $target_url_path_segment;
        }
        
        if (!empty($parsed_previous['query'])) parse_str($parsed_previous['query'], $current_query_params);
        $current_fragment = $parsed_previous['fragment'] ?? '';
        unset($_SESSION['previous_page']);
    } else {
        $target_url_path_segment = '/' . ltrim($fallback_path, '/');
    }

    // 2. Об'єднуємо параметри запиту
    // Спочатку беремо параметри, які вже були в цільовому URL (з return_to або previous_page)
    $final_query_params = $current_query_params;
    // Потім додаємо/перезаписуємо параметри з $params_to_preserve
    if (!empty($params_to_preserve)) {
        $final_query_params = array_merge($final_query_params, $params_to_preserve);
    }
    $query_string = http_build_query($final_query_params);

    // 3. Формуємо фінальний URL
    $final_url = $base_app_url . $target_url_path_segment;
    
    if (!empty($query_string)) {
        $final_url .= '?' . $query_string;
    }
    
    // Додаємо якір
    if (!empty($current_fragment) && empty($admin_tab_anchor)) { // Якщо якір був і не перезаписується адмін-якорем
        $final_url .= '#' . $current_fragment;
    } elseif (!empty($admin_tab_anchor)) { // Адмін-якір має пріоритет
        $final_url .= '#' . $admin_tab_anchor;
    }
    
    // Очищення токена тепер відбувається в скрипті-обробнику (напр. delete_single_shift.php)
    // if (isset($_SESSION['csrf_token'])) {
    //     unset($_SESSION['csrf_token']);
    // }
    
    header('Location: ' . $final_url);
    exit();
}

/**
 * Записує дію користувача в журнал.
 *
 * @param PDO $pdo Об'єкт PDO для з'єднання з БД.
 * @param int $user_id ID користувача, який виконав дію.
 * @param string $action_description Опис дії.
 * @param int|null $target_shift_id ID зміни, до якої відноситься дія (необов'язково).
 * @param string|null $details Додаткові деталі (необов'язково).
 * @return bool True у разі успіху, false у разі помилки.
 */
function log_action(PDO $pdo, int $user_id, string $action_description, ?int $target_shift_id = null, ?string $details = null): bool {
    $sql = "INSERT INTO action_log (user_id, action_description, target_shift_id, details, timestamp) 
            VALUES (:user_id, :action_description, :target_shift_id, :details, NOW())";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action_description', $action_description, PDO::PARAM_STR);
        $stmt->bindParam(':target_shift_id', $target_shift_id, PDO::PARAM_INT); // NULL is handled automatically
        $stmt->bindParam(':details', $details, PDO::PARAM_STR); // NULL is handled automatically
        
        return $stmt->execute();
    } catch (PDOException $e) {
        // Обробка помилки запису в лог (наприклад, записати у файл помилок)
        error_log("Failed to log action: " . $e->getMessage());
        return false;
    }
}

/**
 * Saves the current request URI to the session for later use by smart_redirect.
 * Call this function at the beginning of pages where you want to be able to return.
 */
function save_current_page_for_redirect() {
    $_SESSION['previous_page'] = $_SERVER['REQUEST_URI'];
}

/**
 * Translation function that returns the message from messageTranslations object
 * @param string $key The translation key
 * @return string The translated message
 */
function t($key) {
    global $messageTranslations;
    $lang = $_SESSION['lang'] ?? 'uk';
    return $messageTranslations[$key][$lang] ?? $key;
}

?>