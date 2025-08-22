<?php
// /includes/panels/duty_officer_content.php
// Контент для вкладки "Оперативна Панель" (для адміна) або основний оперативний контент (для чергового)

// Подключаем дополнительные переводы (без конфликтов)
require_once 'translations_add.php';

// Глобальні змінні, які мають бути доступні
global $pdo, $APP_URL, $page_data;

// --- Визначення базових змінних, якщо вони ще не встановлені ---
if (!isset($APP_URL) && defined('APP_URL')) {
    $APP_URL = rtrim(APP_URL, '/');
} elseif (!isset($APP_URL)) {
    $protocol_op_content = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host_op_content = $_SERVER['HTTP_HOST'];
    $script_dir_from_root_op_content = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
    $project_root_from_web_op_content = dirname($script_dir_from_root_op_content);
    $APP_URL = rtrim($protocol_op_content . $host_op_content . $project_root_from_web_op_content, '/');
}
$base_url = $APP_URL;

if (!isset($page_data) || !is_array($page_data) || !isset($page_data['post_grid_data'])) {
    if (!isset($page_data)) $page_data = [];
    $page_data['post_grid_data'] = [];
}

// --- Визначення дат СПЕЦИФІЧНО для цієї оперативної панелі ---
$today_date_op_content = date('Y-m-d');
$operational_selected_date_op_content = $today_date_op_content;

if (isset($_GET['date']) && 
    !isset($_GET['filter_year']) && 
    !isset($_GET['filter_month']) && 
    !isset($_GET['filter_day']) && 
    !isset($_GET['filter_post_id']) && 
    !isset($_GET['filter_user_id'])) {
    
    $date_from_get_op_panel = $_GET['date'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_get_op_panel) && $date_from_get_op_panel <= $today_date_op_content) {
        try {
            $d_op_panel = new DateTime($date_from_get_op_panel);
            if ($d_op_panel && $d_op_panel->format('Y-m-d') === $date_from_get_op_panel) {
                $operational_selected_date_op_content = $date_from_get_op_panel;
            }
        } catch (Exception $e) {
            // error_log("Invalid date in GET['date'] for op_content: " . $date_from_get_op_panel);
        }
    }
}

$operational_date_start_op_content = $operational_selected_date_op_content . ' 00:00:00';
$operational_date_end_op_content = $operational_selected_date_op_content . ' 23:59:59';

// Ініціалізація змінних для статистики та даних цієї панелі
$all_posts_op_panel_display = $page_data['post_grid_data'];
$shifts_on_date_op_panel_display = [];
$stats_op_panel_display = [
    'active_lifeguards' => 0, 'active_posts' => 0, 'completed_today' => 0,
    'total_hours_today' => 0.0, 'total_incidents_today' => 0,
    'total_preventive_actions' => 0, 'total_suspicious_swimmers' => 0,
    'total_visitor_inquiries' => 0, 'total_bridge_jumpers' => 0,
    'total_alcohol_water_prevented' => 0, 'total_alcohol_drinking_prevented' => 0,
    'total_watercraft_stopped' => 0, 'total_educational_activities' => 0,
];
$panel_error_op_panel_display = '';
$shifts_awaiting_approval_op_panel_display = [];

// Завантаження даних, якщо $pdo доступний
if (isset($pdo)) {
    try {
        // 1. Пости для сітки
        $should_reload_posts = true;
        if (!empty($all_posts_op_panel_display)) {
            $first_post_sample = reset($all_posts_op_panel_display);
            if (isset($first_post_sample['name']) && isset($first_post_sample['active_shifts']) && isset($first_post_sample['completed_shifts'])) {
                $should_reload_posts = false;
            }
        }
        if ($should_reload_posts) {
            $stmt_posts_fetch_op = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
            $all_posts_fetched_items_op = $stmt_posts_fetch_op->fetchAll(PDO::FETCH_ASSOC);
            $all_posts_op_panel_display = [];
            foreach ($all_posts_fetched_items_op as $post_item_val_op) {
                 $all_posts_op_panel_display[$post_item_val_op['id']] = ['name' => $post_item_val_op['name'],'active_shifts' => [],'completed_shifts' => []];
            }
        } else {
             foreach ($all_posts_op_panel_display as $post_id_key_op => $post_data_val_op) {
                if (isset($all_posts_op_panel_display[$post_id_key_op]['active_shifts'])) $all_posts_op_panel_display[$post_id_key_op]['active_shifts'] = [];
                if (isset($all_posts_op_panel_display[$post_id_key_op]['completed_shifts'])) $all_posts_op_panel_display[$post_id_key_op]['completed_shifts'] = [];
            }
        }

        // 2. Зміни на обрану дату
        $stmt_shifts_fetch_op = $pdo->prepare("
            SELECT s.id as shift_id, s.user_id, s.post_id, s.start_time, s.end_time, s.status,
                   s.start_photo_path, s.start_photo_approved_at, u.full_name as lifeguard_name,
                   p.name as post_name, sr.id as report_id,
                   (SELECT COUNT(sr_inner.id) FROM shift_reports sr_inner WHERE sr_inner.shift_id = s.id) as report_count
            FROM shifts s
            JOIN users u ON s.user_id = u.id
            JOIN posts p ON s.post_id = p.id
            LEFT JOIN shift_reports sr ON s.id = sr.shift_id
            WHERE (s.status = 'active' AND s.start_time <= :date_end_for_active)
               OR (s.status = 'completed' AND s.end_time BETWEEN :date_start AND :date_end)
            ORDER BY p.name ASC, s.start_time ASC
        ");
        $stmt_shifts_fetch_op->bindParam(':date_end_for_active', $operational_date_end_op_content);
        $stmt_shifts_fetch_op->bindParam(':date_start', $operational_date_start_op_content);
        $stmt_shifts_fetch_op->bindParam(':date_end', $operational_date_end_op_content);
        $stmt_shifts_fetch_op->execute();
        $shifts_on_date_op_panel_display = $stmt_shifts_fetch_op->fetchAll(PDO::FETCH_ASSOC);

        // [Продолжение логики обработки смен и статистики - оставляю как есть]
        $active_post_ids_calc_op = []; $active_lifeguard_ids_calc_op = [];
        $completed_shift_ids_calc_op = []; $total_seconds_today_calc_op = 0;

        foreach ($shifts_on_date_op_panel_display as $shift_item_val_op) {
            $post_id_loop_op = $shift_item_val_op['post_id'];
            if (!isset($all_posts_op_panel_display[$post_id_loop_op])) {
                 $all_posts_op_panel_display[$post_id_loop_op] = ['name' => $shift_item_val_op['post_name'],'active_shifts' => [],'completed_shifts' => []];
            }

            $shift_data_item_op = [
                'shift_id' => $shift_item_val_op['shift_id'],
                'lifeguard_name' => $shift_item_val_op['lifeguard_name'],
                'start_time' => $shift_item_val_op['start_time'],
                'end_time' => $shift_item_val_op['end_time']
            ];

            if ($shift_item_val_op['status'] === 'active') {
                if (!isset($active_lifeguard_ids_calc_op[$shift_item_val_op['user_id']])) {
                    $stats_op_panel_display['active_lifeguards']++;
                    $active_lifeguard_ids_calc_op[$shift_item_val_op['user_id']] = true;
                }
                $active_post_ids_calc_op[$post_id_loop_op] = true;
                $shift_data_item_op['start_photo_path'] = $shift_item_val_op['start_photo_path'];
                $shift_data_item_op['start_photo_approved_at'] = $shift_item_val_op['start_photo_approved_at'];
                if (isset($all_posts_op_panel_display[$post_id_loop_op])) {
                     $all_posts_op_panel_display[$post_id_loop_op]['active_shifts'][] = $shift_data_item_op;
                }
            } elseif ($shift_item_val_op['status'] === 'completed') {
                if (!isset($completed_shift_ids_calc_op[$shift_item_val_op['shift_id']])) {
                    $stats_op_panel_display['completed_today']++;
                    $completed_shift_ids_calc_op[$shift_item_val_op['shift_id']] = true;
                    if ($shift_item_val_op['start_time'] && $shift_item_val_op['end_time']) {
                        try {
                            $start_dt_item_op = new DateTime($shift_item_val_op['start_time']);
                            $end_dt_item_op = new DateTime($shift_item_val_op['end_time']);
                            $diff_item_op = $end_dt_item_op->getTimestamp() - $start_dt_item_op->getTimestamp();
                            if ($diff_item_op > 0) $total_seconds_today_calc_op += $diff_item_op;
                        } catch (Exception $e) { /* error_log for duration calc */ }
                    }
                }
                $shift_data_item_op['report_id'] = $shift_item_val_op['report_count'] > 0 ? $shift_item_val_op['report_id'] : null;
                 if (isset($all_posts_op_panel_display[$post_id_loop_op])) {
                    $all_posts_op_panel_display[$post_id_loop_op]['completed_shifts'][] = $shift_data_item_op;
                }
            }
        }
        $stats_op_panel_display['active_posts'] = count($active_post_ids_calc_op);
        if ($total_seconds_today_calc_op > 0) $stats_op_panel_display['total_hours_today'] = round($total_seconds_today_calc_op / 3600, 1);

        // [Продолжение статистики отчетов]
        if ($stats_op_panel_display['completed_today'] > 0) {
            $stmt_report_stats_fetch_op = $pdo->prepare("
                SELECT
                    SUM(COALESCE(sr.suspicious_swimmers_count, 0)) as total_suspicious_swimmers,
                    SUM(COALESCE(sr.visitor_inquiries_count, 0)) as total_visitor_inquiries,
                    SUM(COALESCE(sr.bridge_jumpers_count, 0)) as total_bridge_jumpers,
                    SUM(COALESCE(sr.alcohol_water_prevented_count, 0)) as total_alcohol_water_prevented,
                    SUM(COALESCE(sr.alcohol_drinking_prevented_count, 0)) as total_alcohol_drinking_prevented,
                    SUM(COALESCE(sr.watercraft_stopped_count, 0)) as total_watercraft_stopped,
                    SUM(COALESCE(sr.preventive_actions_count, 0)) as total_preventive_actions,
                    SUM(COALESCE(sr.educational_activities_count, 0)) as total_educational_activities
                FROM shift_reports sr
                JOIN shifts s ON sr.shift_id = s.id
                WHERE s.status = 'completed' AND s.end_time BETWEEN :date_start AND :date_end
            ");
            $stmt_report_stats_fetch_op->bindParam(':date_start', $operational_date_start_op_content);
            $stmt_report_stats_fetch_op->bindParam(':date_end', $operational_date_end_op_content);
            $stmt_report_stats_fetch_op->execute();
            $report_results_fetch_op = $stmt_report_stats_fetch_op->fetch();

            $stmt_total_incidents_fetch_op = $pdo->prepare("
               SELECT COUNT(ri.id) as total_detailed_incidents
               FROM report_incidents ri
               JOIN shift_reports sr ON ri.shift_report_id = sr.id
               JOIN shifts s ON sr.shift_id = s.id
               WHERE s.status = 'completed' AND s.end_time BETWEEN :date_start AND :date_end
            ");
            $stmt_total_incidents_fetch_op->bindParam(':date_start', $operational_date_start_op_content);
            $stmt_total_incidents_fetch_op->bindParam(':date_end', $operational_date_end_op_content);
            $stmt_total_incidents_fetch_op->execute();
            $incidents_result_fetch_op = $stmt_total_incidents_fetch_op->fetch();

            if ($report_results_fetch_op) {
                $stats_op_panel_display['total_suspicious_swimmers'] = (int)$report_results_fetch_op['total_suspicious_swimmers'];
                $stats_op_panel_display['total_visitor_inquiries'] = (int)$report_results_fetch_op['total_visitor_inquiries'];
                $stats_op_panel_display['total_bridge_jumpers'] = (int)$report_results_fetch_op['total_bridge_jumpers'];
                $stats_op_panel_display['total_alcohol_water_prevented'] = (int)$report_results_fetch_op['total_alcohol_water_prevented'];
                $stats_op_panel_display['total_alcohol_drinking_prevented'] = (int)$report_results_fetch_op['total_alcohol_drinking_prevented'];
                $stats_op_panel_display['total_watercraft_stopped'] = (int)$report_results_fetch_op['total_watercraft_stopped'];
                $stats_op_panel_display['total_preventive_actions'] = (int)$report_results_fetch_op['total_preventive_actions'];
                $stats_op_panel_display['total_educational_activities'] = (int)$report_results_fetch_op['total_educational_activities'];
                $stats_op_panel_display['total_incidents_today'] = $incidents_result_fetch_op ? (int)$incidents_result_fetch_op['total_detailed_incidents'] : 0;
            }
        }

        // 5. Фото на підтвердження
        $stmt_approval_fetch_op = $pdo->query("
           SELECT s.id as shift_id, s.start_time, s.start_photo_path, u.full_name as lifeguard_name, p.name as post_name
           FROM shifts s JOIN users u ON s.user_id = u.id JOIN posts p ON s.post_id = p.id
           WHERE s.status = 'active' AND s.start_photo_path IS NOT NULL AND s.start_photo_approved_at IS NULL
           ORDER BY s.start_time ASC
        ");
       $shifts_awaiting_approval_op_panel_display = $stmt_approval_fetch_op->fetchAll(PDO::FETCH_ASSOC);

       if (isset($page_data) && is_array($page_data)) {
           $page_data['post_grid_data'] = $all_posts_op_panel_display;
       }

    } catch (PDOException $e) {
        $panel_error_op_panel_display = "Помилка завантаження оперативних даних: " . $e->getMessage();
        error_log("Duty Officer Panel Content DB Error: " . $e->getMessage());
    }
} else {
    $panel_error_op_panel_display = "Помилка: Немає підключення до бази даних.";
}

// Використовуємо локальні змінні для HTML
$operational_selected_date_for_html = $operational_selected_date_op_content;
$today_date_for_html = $today_date_op_content;
$stats_for_html = $stats_op_panel_display;
$panel_error_for_html = $panel_error_op_panel_display;
$shifts_awaiting_approval_for_html = $shifts_awaiting_approval_op_panel_display;

?>

<div class="space-y-8">
    <!-- Operational Information Section -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                   <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                       <i class="fas fa-binoculars text-white text-sm"></i>
                   </div>
                   Оперативна Інформація
                </h3>
            </div>
        </div>

        <div class="p-6">
            <?php if ($panel_error_for_html): ?>
                 <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span class="font-semibold">Помилка!</span>
                    </div>
                    <p class="mt-1"><?php echo htmlspecialchars($panel_error_for_html); ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 mb-8">
                 <div class="xl:col-span-3 bg-gradient-to-br from-gray-50 to-gray-100 p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-bold mb-4 flex items-center text-gray-800">
                         <i class="fas fa-chart-bar mr-3 text-blue-500"></i> 
                         Статистика за <?php echo date("d.m.Y", strtotime($operational_selected_date_for_html)); ?>
                     </h3>
                     <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                         <div class="statistics-card card-emerald p-4 text-center">
                             <div class="stat-number"><?php echo ($stats_for_html['active_lifeguards'] ?? 0); ?></div>
                             <div class="stat-label">Активні рятувальники</div>
                         </div>
                         <div class="statistics-card card-amber p-4 text-center">
                             <div class="stat-number"><?php echo ($stats_for_html['active_posts'] ?? 0); ?></div>
                             <div class="stat-label">Активні пости</div>
                         </div>
                         <div class="statistics-card card-blue p-4 text-center">
                             <div class="stat-number"><?php echo ($stats_for_html['completed_today'] ?? 0); ?></div>
                             <div class="stat-label">Завершено змін</div>
                         </div>
                         <div class="statistics-card card-gray p-4 text-center">
                             <div class="stat-number"><?php echo number_format(($stats_for_html['total_hours_today'] ?? 0.0), 1, '.', ''); ?></div>
                             <div class="stat-label">Відпрац. годин</div>
                         </div>
                         <div class="statistics-card card-red p-4 text-center">
                             <div class="stat-number"><?php echo ($stats_for_html['total_incidents_today'] ?? 0); ?></div>
                             <div class="stat-label">Інцидентів</div>
                         </div>
                         <div class="statistics-card card-purple p-4 text-center">
                             <div class="stat-number"><?php echo ($stats_for_html['total_preventive_actions'] ?? 0); ?></div>
                             <div class="stat-label">Превентивних заходів</div>
                         </div>
                     </div>
                 </div>

                 <!-- Видалено кнопку перегляду сітки постів -->
            </div>
        </div>
    </div>
    <?php
// === Підключення Панелі Ручного Керування Змінами ===
// Переконайся, що $pdo та $APP_URL доступні тут, якщо вони потрібні панелі.
// Вони мають бути глобальними або передані.
// У нашому випадку, панель сама завантажує дані, тому просто підключаємо.
if (file_exists(__DIR__ . '/admin_manual_shift_panel.php')) {
    require __DIR__ . '/admin_manual_shift_panel.php';
} else {
    echo "<p class='text-red-500 p-3'>Помилка: Файл панелі ручного керування не знайдено.</p>";
}
?>
    <!-- Incidents Section -->


    <!-- Photos for Approval Section -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-orange-50 to-amber-50">
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-camera-retro text-white text-sm"></i>
                </div>
                <?php echo tr('photos_for_approval'); ?>
                <?php if (!empty($shifts_awaiting_approval_for_html)): ?>
                    <span class="ml-3 bg-orange-100 text-orange-700 text-sm font-bold px-3 py-1 rounded-full">
                        <?php echo count($shifts_awaiting_approval_for_html); ?>
                    </span>
                <?php endif; ?>
            </h2>
        </div>

        <div class="p-6">
            <?php if (!empty($shifts_awaiting_approval_for_html)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($shifts_awaiting_approval_for_html as $shift_item): ?>
                        <div class="group bg-white rounded-2xl shadow-md hover:shadow-xl border border-gray-200 overflow-hidden transition-all duration-300 hover:scale-[1.02]">
                            <?php
                                $photo_url_item_html = '#';
                                $has_photo = !empty($shift_item['start_photo_path']);
                                if ($has_photo) {
                                    $photo_path_item_html = $shift_item['start_photo_path'];
                                    $photo_url_item_html = rtrim($base_url, '/') . '/' . ltrim($photo_path_item_html, '/');
                                }
                            ?>
                            
                            <!-- Photo Section -->
                            <div class="relative">
                                <?php if ($has_photo): ?>
                                    <a href="<?php echo htmlspecialchars($photo_url_item_html); ?>" 
                                       target="_blank" 
                                       title="<?php echo tr('view_photo_title'); ?>"
                                       class="block relative overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($photo_url_item_html); ?>" 
                                             alt="<?php echo tr('photo_alt_text'); ?>" 
                                             class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-500">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-20 transition-opacity duration-300 flex items-center justify-center">
                                            <i class="fas fa-external-link-alt text-white opacity-0 hover:opacity-100 transition-opacity duration-300 text-lg"></i>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="w-full h-48 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                        <div class="text-center text-gray-400">
                                            <i class="fas fa-image text-4xl mb-2"></i>
                                            <div class="text-sm"><?php echo tr('no_photo_short'); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Status badge -->
                                <div class="absolute top-3 right-3">
                                    <span class="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                        Очікує
                                    </span>
                                </div>
                            </div>

                            <!-- Content Section -->
                            <div class="p-5">
                                <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-user-shield text-blue-500 mr-2"></i>
                                    <?php echo htmlspecialchars(safe_get($shift_item, 'lifeguard_name', 'N/A')); ?>
                                </h3>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt w-4 text-blue-500 mr-2"></i>
                                        <span class="font-medium"><?php echo tr('post_label'); ?>:</span>
                                        <span class="ml-1"><?php echo htmlspecialchars(safe_get($shift_item, 'post_name', 'N/A')); ?></span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="far fa-clock w-4 text-green-500 mr-2"></i>
                                        <span class="font-medium"><?php echo tr('start_time_label'); ?>:</span>
                                        <span class="ml-1 font-mono"><?php echo format_datetime_user_tz(safe_get($shift_item, 'start_time')); ?></span>
                                    </div>
                                </div>

                                <?php if ($has_photo && safe_get($shift_item, 'start_photo_approved_at') === null): ?>
                                    <form action="<?php echo rtrim($base_url, '/'); ?>/approve_photo.php" 
                                          method="POST" 
                                          class="space-y-4">
                                        <?php if(function_exists('csrf_input')) echo csrf_input(); ?>
                                        <input type="hidden" name="shift_id" value="<?php echo safe_get($shift_item, 'shift_id'); ?>">
                                        
                                        <div class="space-y-2">
                                            <label for="lifeguard_assignment_type_<?php echo safe_get($shift_item, 'shift_id'); ?>" 
                                                   class="block text-sm font-semibold text-gray-700">
                                                <?php echo tr('assign_lifeguard_type_on_shift_label'); ?>
                                            </label>
                                            <select name="lifeguard_assignment_type" 
                                                    id="lifeguard_assignment_type_<?php echo safe_get($shift_item, 'shift_id'); ?>" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                                    required>
                                                <option value=""><?php echo tr('select_type_short'); ?></option>
                                                <option value="0"><?php echo tr('lifeguard_l0_label'); ?></option>
                                                <option value="1"><?php echo tr('lifeguard_l1_label'); ?></option>
                                                <option value="2"><?php echo tr('lifeguard_l2_label'); ?></option>
                                            </select>
                                        </div>
                                        
                                        <div class="flex space-x-3">
                                            <button type="submit" 
                                                    name="action" 
                                                    value="approve" 
                                                    class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 flex items-center justify-center space-x-2">
                                                <i class="fas fa-check"></i>
                                                <span><?php echo tr('approve_button'); ?></span>
                                            </button>
                                            <button type="submit" 
                                                    name="action" 
                                                    value="reject" 
                                                    class="flex-1 bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 flex items-center justify-center space-x-2" 
                                                    formnovalidate>
                                                <i class="fas fa-times"></i>
                                                <span><?php echo tr('reject_button'); ?></span>
                                            </button>
                                        </div>
                                    </form>
                                <?php elseif (safe_get($shift_item, 'start_photo_approved_at') !== null): ?>
                                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg p-3">
                                        <div class="flex items-center text-green-700">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            <span class="font-medium"><?php echo tr('photo_already_approved'); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                     <div class="bg-gradient-to-r from-yellow-50 to-amber-50 border border-yellow-200 rounded-lg p-3">
                                        <div class="flex items-center text-yellow-700">
                                            <i class="fas fa-hourglass-half mr-2"></i>
                                            <span class="font-medium"><?php echo tr('waiting_for_photo_upload'); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-images text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Немає фото для підтвердження</h3>
                    <p class="text-gray-600"><?php echo tr('no_photos_for_approval_currently'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Post Grid Modal -->
    <div id="post-grid-modal" class="fixed inset-0 z-[70] hidden overflow-y-auto" aria-labelledby="post-grid-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-2 sm:px-4 pt-4 pb-20 text-center sm:block sm:p-0">
             <div id="post-grid-modal-overlay" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
             <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
             <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle w-full max-w-7xl border border-gray-200">
                  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center sticky top-0 z-10">
                      <h3 class="text-xl font-bold text-gray-800 flex items-center" id="post-grid-modal-title">
                          <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                              <i class="fas fa-th-large text-white text-sm"></i>
                          </div>
                          Сітка Постів за <span id="modal-selected-date" class="ml-2 font-mono bg-white px-2 py-1 rounded text-sm"><?php echo date("d.m.Y", strtotime($operational_selected_date_for_html)); ?></span>
                     </h3>
                      <button type="button" 
                              class="text-gray-400 hover:text-red-600 transition-colors p-2 hover:bg-red-50 rounded-lg" 
                              onclick="closePostGridModal()">
                          <span class="sr-only">Закрити</span> 
                          <i class="fas fa-times text-xl"></i>
                      </button>
                  </div>
                 <div id="post-grid-modal-body" class="px-6 py-6 overflow-y-auto" style="max-height: calc(85vh - 80px);">
                       <div id="post-grid-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                       </div>
                      <div id="post-grid-loading" class="text-center py-16 text-gray-500 hidden">
                          <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
                          <div>Завантаження...</div>
                      </div>
                      <div id="post-grid-nodata" class="text-center py-16 text-gray-500 hidden">
                          <i class="fas fa-info-circle text-2xl mb-3"></i>
                          <div>Немає даних для відображення за обрану дату.</div>
                      </div>
                  </div>
             </div>
         </div>
    </div>
</div>