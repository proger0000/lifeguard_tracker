<?php
// index.php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
// config.php має ініціалізувати сесію, PDO, APP_URL та підключити functions.php
require_once ROOT_PATH . '/config.php';

// require_login() має перевірити, чи користувач залогінений, і якщо ні - редирект на login.php
// Ця функція вже є в functions.php і викликається звідти
require_login();

// Определяем активную вкладку админа из GET параметра
$admin_active_tab = null;
if (isset($_GET['tab_admin'])) {
    $admin_active_tab = $_GET['tab_admin'];
}

$page_data = [
    'post_grid_data' => [], // Ініціалізація для панелей, що можуть її використовувати
];

$user_role = $_SESSION['user_role'] ?? null;
$panel_file = null;
$redirect_url = null;
$page_title = "Панель Керування"; // Заголовок за замовчуванням

switch ($user_role) {
    case 'admin':
        $panel_file = ROOT_PATH . '/includes/admin_panel.php';
        $page_title = "Панель Адміністратора";
        break;
    case 'director':
        $panel_file = ROOT_PATH . '/includes/director_panel.php';
        $page_title = "Панель Директора";
        break;
    case 'duty_officer':
        $panel_file = ROOT_PATH . '/includes/duty_officer_dashboard.php'; // Нова панель з вкладками
        $page_title = "Панель Чергового";
        break;
    case 'lifeguard':
        $panel_file = ROOT_PATH . '/includes/lifeguard_panel.php';
        $page_title = "Панель Рятувальника";
        break;
    case 'trainer':
        $redirect_url = rtrim(APP_URL, '/') . '/academy/trainer_dashboard.php';
        break;
    default:
        // Якщо роль невідома або не встановлена, це проблема
        set_flash_message('помилка', 'Не вдалося визначити вашу роль. Будь ласка, увійдіть знову.');
        // Логуємо цю ситуацію
        error_log("Unknown or missing user role in index.php for user_id: " . ($_SESSION['user_id'] ?? 'N/A'));
        // Можна редиректнути на logout.php, щоб очистити сесію
        header('Location: ' . rtrim(APP_URL, '/') . '/logout.php');
        exit();
}

if ($redirect_url) {
    header("Location: " . $redirect_url);
    exit();
}

// Підключаємо хедер (він має бути підключений ПІСЛЯ визначення $page_title)
require_once ROOT_PATH . '/includes/header.php';

// Тіло сторінки
echo '<main class="flex-grow text-white container mb-4 sm:mb-6">';

if ($panel_file && file_exists($panel_file)) {
    require $panel_file;
} elseif ($user_role && !$panel_file && !$redirect_url) {
    // Цей блок тепер менш імовірний, бо default у switch має обробити невідомі ролі
    echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">';
    echo '<strong class="font-bold">Увага!</strong>';
    echo '<span class="block sm:inline"> Для вашої ролі ('.htmlspecialchars($user_role).') ще не налаштовано відображення панелі.</span>';
    echo '</div>';
} elseif (!$user_role && !$redirect_url) { // Якщо user_role все ще null після всіх перевірок
    // Цей блок також малоймовірний, якщо require_login() працює коректно
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">';
    echo '<strong class="font-bold">Помилка!</strong>';
    echo '<span class="block sm:inline"> Не вдалося визначити вашу роль. Будь ласка, увійдіть знову.</span>';
    echo '</div>';
}

echo '</main>';

// Підключаємо футер
require_once ROOT_PATH . '/includes/footer.php';
?>
</body>
</html>