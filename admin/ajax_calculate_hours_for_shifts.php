<?php
require_once '../config.php';
require_once '../includes/helpers.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer'])) {
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неприпустимий метод запиту.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt_find = $pdo->prepare("
        SELECT id, start_time, end_time
        FROM shifts
        WHERE status = 'completed'
        AND end_time IS NOT NULL
        AND (rounded_work_hours IS NULL OR rounded_work_hours <= 0)
    ");
    $stmt_find->execute();
    $shifts_to_update = $stmt_find->fetchAll(PDO::FETCH_ASSOC);

    $updated_count = 0;
    if (!empty($shifts_to_update)) {
        $stmt_update = $pdo->prepare("
            UPDATE shifts SET rounded_work_hours = :rounded_hours WHERE id = :shift_id
        ");

        foreach ($shifts_to_update as $shift) {
            $start = new DateTime($shift['start_time']);
            $end = new DateTime($shift['end_time']);
            $diff_seconds = $end->getTimestamp() - $start->getTimestamp();

            if ($diff_seconds > 0) {
                $hours = $diff_seconds / 3600;
                // Round to nearest half hour (0.5)
                $rounded_hours = round($hours * 2) / 2;
                
                $stmt_update->execute([
                    ':rounded_hours' => $rounded_hours,
                    ':shift_id' => $shift['id']
                ]);
                $updated_count++;
            }
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'updated_count' => $updated_count]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error calculating hours for shifts: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Помилка під час розрахунку: ' . $e->getMessage()]);
} 