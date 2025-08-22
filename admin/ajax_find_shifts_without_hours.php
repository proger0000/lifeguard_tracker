<?php
require_once '../config.php';
require_once '../includes/helpers.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer'])) {
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.start_time, u.full_name as lifeguard_name
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'completed' 
        AND s.end_time IS NOT NULL 
        AND (s.rounded_work_hours IS NULL OR s.rounded_work_hours <= 0)
        ORDER BY s.start_time DESC
    ");
    $stmt->execute();
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'shifts' => $shifts]);

} catch (PDOException $e) {
    error_log('Error finding shifts without hours: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Помилка бази даних.']);
} 