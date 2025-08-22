<?php
// admin/ajax_get_post_grid.php
// AJAX endpoint для получения данных сетки постов

require_once '../config.php';

// Проверка доступа
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer', 'director'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ заборонено']);
    exit();
}

// Проверка методы запроса
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено']);
    exit();
}

// Получение даты (если передана)
$selected_date = date('Y-m-d'); // По умолчанию сегодня
if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $input_date = $_GET['date'];
    if ($input_date <= date('Y-m-d')) {
        try {
            $date_obj = new DateTime($input_date);
            if ($date_obj && $date_obj->format('Y-m-d') === $input_date) {
                $selected_date = $input_date;
            }
        } catch (Exception $e) {
            error_log("Invalid date in AJAX request: " . $input_date);
        }
    }
}

$date_start = $selected_date . ' 00:00:00';
$date_end = $selected_date . ' 23:59:59';

$post_grid_data = [];

try {
    // 1. Получаем все посты
    $stmt_posts = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
    $all_posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
    
    // Инициализируем структуру данных
    foreach ($all_posts as $post) {
        $post_grid_data[$post['id']] = [
            'name' => $post['name'],
            'active_shifts' => [],
            'completed_shifts' => []
        ];
    }
    
    // 2. Получаем смены за выбранную дату
    $stmt_shifts = $pdo->prepare("
        SELECT s.id as shift_id, s.user_id, s.post_id, s.start_time, s.end_time, s.status,
               s.start_photo_path, s.start_photo_approved_at, u.full_name as lifeguard_name,
               p.name as post_name
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        JOIN posts p ON s.post_id = p.id
        WHERE (s.status = 'active' AND s.start_time <= :date_end_for_active)
           OR (s.status = 'completed' AND s.end_time BETWEEN :date_start AND :date_end)
        ORDER BY p.name ASC, s.start_time ASC
    ");
    
    $stmt_shifts->bindParam(':date_end_for_active', $date_end);
    $stmt_shifts->bindParam(':date_start', $date_start);
    $stmt_shifts->bindParam(':date_end', $date_end);
    $stmt_shifts->execute();
    
    $shifts = $stmt_shifts->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Группируем смены по постам
    foreach ($shifts as $shift) {
        $post_id = $shift['post_id'];
        
        // Если пост не найден в списке постов, добавляем его
        if (!isset($post_grid_data[$post_id])) {
            $post_grid_data[$post_id] = [
                'name' => $shift['post_name'],
                'active_shifts' => [],
                'completed_shifts' => []
            ];
        }
        
        $shift_data = [
            'shift_id' => $shift['shift_id'],
            'lifeguard_name' => $shift['lifeguard_name'],
            'start_time' => $shift['start_time'],
            'end_time' => $shift['end_time']
        ];
        
        if ($shift['status'] === 'active') {
            $shift_data['start_photo_path'] = $shift['start_photo_path'];
            $shift_data['start_photo_approved_at'] = $shift['start_photo_approved_at'];
            $post_grid_data[$post_id]['active_shifts'][] = $shift_data;
        } elseif ($shift['status'] === 'completed') {
            $post_grid_data[$post_id]['completed_shifts'][] = $shift_data;
        }
    }
    
    // Устанавливаем правильные заголовки для JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // Возвращаем данные
    echo json_encode($post_grid_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log("AJAX Post Grid DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Помилка бази даних'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("AJAX Post Grid General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Загальна помилка сервера'], JSON_UNESCAPED_UNICODE);
}
?>
