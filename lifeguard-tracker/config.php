<?php
// Show all errors for development (comment out for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
     // In a real app, log this error instead of echoing
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Start Session ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Include Helper Functions ---
require_once __DIR__ . '/includes/functions.php';

?>