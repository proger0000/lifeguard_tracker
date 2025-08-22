<?php
require_role('duty_officer'); // Залишаємо перевірку ролі для прямого доступу Чергового
global $pdo; // Передаємо PDO, якщо потрібно
// Включаємо відокремлений контент
require_once __DIR__ . '/panels/duty_officer_content.php';
?>