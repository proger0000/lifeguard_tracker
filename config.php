<?php
// --- Basic Configuration ---
define('DB_HOST', 'kyivls.mysql.tools');       // Replace with your DB host
define('DB_NAME', 'kyivls_panelcrm');    // Replace with your DB name
define('DB_USER', 'kyivls_panelcrm');           // Replace with your DB username
define('DB_PASS', 'S5du3%sB+6');               // Replace with your DB password
define('DB_CHARSET', 'utf8mb4');

define('APP_URL', 'https://lifeguard.kyiv.ua/lifeguard-tracker');
// --- PDO Database Connection ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
     $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
     // In a real app, log this error instead of echoing
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Start Session ---
// Установим параметры куки сессии до старта сессии
$parsed_app_url = parse_url(APP_URL);
$cookie_path = $parsed_app_url['path'] ?? '/'; // e.g., '/lifeguard-tracker'
if (substr($cookie_path, -1) !== '/') {
    $cookie_path .= '/'; // Ensure it ends with a slash
}
// $cookie_domain = $parsed_app_url['host']; // e.g., 'lifeguard.kyiv.ua'

session_set_cookie_params([
    'lifetime' => 0, // до закрытия браузера
    'path' => $cookie_path, // Например, '/lifeguard-tracker/'
    'domain' => null, // Изменено на null, чтобы браузер автоматически определял домен
    'secure' => true, // Отправлять куки только по HTTPS
    'httponly' => true, // Недоступно через JavaScript
    'samesite' => 'Lax' // Защита от CSRF
]);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Include Helper Functions ---
// ВАЖЛИВО: ROOT_PATH ВИЗНАЧАЄТЬСЯ ПЕРЕД ПІДКЛЮЧЕННЯМ functions.php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__); // __DIR__ тут буде коренем папки lifeguard-tracker
}
// --- Include Helper Functions ---
require_once __DIR__ . '/includes/functions.php';
