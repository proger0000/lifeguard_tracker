<?php
/**
 * includes/panels/admin_manage_shifts_content.php
 *
 * This file contains the content for the shift management tab in the admin panel.
 * It is included by admin_panel.php.
 */

// Since this file is included by admin_panel.php, we assume the following are already available:
// - $pdo: The PDO database connection object.
// - All necessary functions from functions.php.
// - User role has been verified.

// Most of the logic from the original manage_shifts.php is here.
// Some parts might be adapted to better fit the tabbed interface.

// =================================================================================
// INITIALIZATION
// =================================================================================

$current_year = date('Y');

// =================================================================================
// PARAMETER HANDLING
// =================================================================================

$filter_shift_id = filter_input(INPUT_GET, 's_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_year = filter_input(INPUT_GET, 's_year', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_month = filter_input(INPUT_GET, 's_month', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 1, 'max_range' => 12]]);
$filter_day = filter_input(INPUT_GET, 's_day', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 1, 'max_range' => 31]]);
$filter_post_id = filter_input(INPUT_GET, 's_post_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_user_id = filter_input(INPUT_GET, 's_user_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_status = trim($_GET['s_status'] ?? '');
$search_query = trim($_GET['s_search'] ?? '');

$sort_column = $_GET['s_sort'] ?? 'start_time';
$sort_order = (isset($_GET['s_order']) && strtolower($_GET['s_order']) == 'asc') ? 'ASC' : 'DESC';

$allowed_sort_columns_map = [
    'id' => 's.id', 'start_time' => 's.start_time', 'end_time' => 's.end_time',
    'lifeguard_name' => 'u.full_name', 'post_name' => 'p.name', 'status' => 's.status',
    'duration_seconds' => 'duration_seconds', 'lifeguard_assignment_type' => 's.lifeguard_assignment_type'
];
$sql_sort_column = $allowed_sort_columns_map[$sort_column] ?? 's.start_time';

$page = isset($_GET['s_page']) ? max(1, (int)$_GET['s_page']) : 1;
$per_page_options = [10, 25, 50, 100];
$per_page = isset($_GET['s_per_page']) && in_array((int)$_GET['s_per_page'], $per_page_options) ? (int)$_GET['s_per_page'] : 25;

// =================================================================================
// DATA LOADING FOR FILTERS
// =================================================================================

$posts_for_filter = [];
$lifeguards_for_filter = [];
$available_years_for_filter = [];
$shift_statuses = [
    'active' => 'Активна', 'completed' => 'Завершено', 'cancelled' => 'Скасовано',
    'pending_photo_open' => 'Очікує фото відкриття', 'active_manual' => 'Відкрито вручну'
];
$months_ukrainian_filter = [1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень', 5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень', 9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'];

try {
    $stmt_posts = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
    $posts_for_filter = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

    $stmt_lifeguards = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lifeguard' ORDER BY full_name ASC");
    $lifeguards_for_filter = $stmt_lifeguards->fetchAll(PDO::FETCH_ASSOC);

    $stmt_years = $pdo->query("SELECT DISTINCT YEAR(start_time) as year FROM shifts WHERE start_time IS NOT NULL ORDER BY year DESC");
    $available_years_for_filter = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($current_year, $available_years_for_filter)) {
        array_unshift($available_years_for_filter, (string)$current_year);
    }
    rsort($available_years_for_filter);

} catch (PDOException $e) {
    // Error handling can be simplified as the main panel will catch it.
    error_log("Manage Shifts Filter Data Error: " . $e->getMessage());
}

// =================================================================================
// SQL QUERY CONSTRUCTION
// =================================================================================

$sql_from_joins = "FROM shifts s
                   JOIN users u ON s.user_id = u.id
                   JOIN posts p ON s.post_id = p.id
                   LEFT JOIN users open_admin ON s.manual_opened_by = open_admin.id
                   LEFT JOIN users close_admin ON s.manual_closed_by = close_admin.id
                   LEFT JOIN (
                       SELECT shift_id, SUM(points_awarded) as total_points
                       FROM lifeguard_shift_points
                       GROUP BY shift_id
                   ) pts ON s.id = pts.shift_id";

$where_conditions = [];
$params = [];

if ($filter_shift_id) { $where_conditions[] = "s.id = :s_id"; $params[':s_id'] = $filter_shift_id; }
if ($filter_year) {
    if ($filter_month) {
        if ($filter_day) {
            $start_date = new DateTime("{$filter_year}-{$filter_month}-{$filter_day}");
            $end_date = (clone $start_date)->modify('+1 day');
        } else {
            $start_date = new DateTime("{$filter_year}-{$filter_month}-01");
            $end_date = (clone $start_date)->modify('+1 month');
        }
    } else {
        $start_date = new DateTime("{$filter_year}-01-01");
        $end_date = (clone $start_date)->modify('+1 year');
    }
    $where_conditions[] = "s.start_time >= :start_date AND s.start_time < :end_date";
    $params[':start_date'] = $start_date->format('Y-m-d H:i:s');
    $params[':end_date'] = $end_date->format('Y-m-d H:i:s');
}
if ($filter_post_id) { $where_conditions[] = "s.post_id = :s_post_id"; $params[':s_post_id'] = $filter_post_id; }
if ($filter_user_id) { $where_conditions[] = "s.user_id = :s_user_id"; $params[':s_user_id'] = $filter_user_id; }
if (!empty($filter_status)) { $where_conditions[] = "s.status = :s_status"; $params[':s_status'] = $filter_status; }
if (!empty($search_query)) {
    $search_parts = ["u.full_name LIKE :search_name", "p.name LIKE :search_post"];
    $params[':search_name'] = '%' . $search_query . '%';
    $params[':search_post'] = '%' . $search_query . '%';
    if (is_numeric($search_query)) {
        $search_parts[] = "s.id = :search_id";
        $params[':search_id'] = (int)$search_query;
    }
    $where_conditions[] = "(" . implode(" OR ", $search_parts) . ")";
}

$sql_where = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// =================================================================================
// DATA FETCHING AND PAGINATION
// =================================================================================

$total_records = 0;
try {
    $stmt_count = $pdo->prepare("SELECT COUNT(s.id) " . $sql_from_joins . " " . $sql_where);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Manage Shifts Count Error: " . $e->getMessage());
}

$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $per_page;

$shifts_data = [];
try {
    $sql_select_fields = "s.id, s.start_time, s.end_time, s.status, s.post_id, s.user_id, s.activity_type,
                          u.full_name as lifeguard_name, p.name as post_name, s.lifeguard_assignment_type,
                          s.start_photo_path, s.start_photo_approved_at, s.photo_close_path,
                          s.manual_opened_by, s.manual_closed_by,
                          open_admin.full_name as manual_opened_by_admin_name,
                          close_admin.full_name as manual_closed_by_admin_name,
                          TIMESTAMPDIFF(SECOND, s.start_time, s.end_time) as duration_seconds,
                          (SELECT COUNT(sr.id) FROM shift_reports sr WHERE sr.shift_id = s.id) as reports_count,
                          pts.total_points";

    $sql_data = "SELECT " . $sql_select_fields . " " . $sql_from_joins . " " . $sql_where
              . " ORDER BY {$sql_sort_column} {$sort_order} LIMIT :limit OFFSET :offset";

    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_data->bindValue($key, $val);
    }
    $stmt_data->execute();
    $shifts_data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Manage Shifts Fetch Error: " . $e->getMessage());
}

// =================================================================================
// HELPER FUNCTIONS
// =================================================================================

$base_link_params = array_filter([
    'page' => 'manage_shifts', // Important for tab navigation
    's_id' => $filter_shift_id, 's_year' => $filter_year, 's_month' => $filter_month,
    's_day' => $filter_day, 's_post_id' => $filter_post_id, 's_user_id' => $filter_user_id,
    's_status' => $filter_status, 's_search' => $search_query, 's_per_page' => $per_page,
]);
$active_filters_count = count(array_filter($base_link_params, fn($v, $k) => !in_array($k, ['s_per_page', 'page']) && $v, ARRAY_FILTER_USE_BOTH));

function get_panel_link($base_params, $extra_params = []) {
    $params = array_merge($base_params, $extra_params);
    return '?' . http_build_query($params);
}

// Re-declaring these functions to be self-contained, just in case.
if (!function_exists('render_media_icons')) {
    function render_media_icons($shift) {
        // ... (copy from manage_shifts.php)
    }
}
if (!function_exists('render_action_buttons')) {
    function render_action_buttons($shift_id, $is_mobile = false) {
        // ... (copy from manage_shifts.php)
    }
}

?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-gray-50 -m-4 p-4">
    <div class="w-full">
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 border border-gray-200/50">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center mb-2">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-3 shadow-lg"><i class="fas fa-clipboard-list text-white text-xl"></i></div>
                        Керування Змінами
                    </h1>
                    <p class="text-gray-600 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-indigo-500"></i>Знайдено записів: <span class="font-semibold ml-2 text-indigo-600"><?php echo $total_records; ?></span>
                        <?php if ($active_filters_count > 0): ?>
                            <span class="ml-4 text-sm"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"><?php echo $active_filters_count; ?> активних фільтрів</span></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="openHoursModalBtn" class="bg-gradient-to-r from-blue-500 to-teal-500 text-white px-5 py-2.5 rounded-lg hover:from-blue-600 hover:to-teal-600 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105"><i class="fas fa-calculator mr-2"></i> Розрахунок годин</button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border border-gray-200/50">
            <form action="" method="GET" id="filtersForm">
                <input type="hidden" name="page" value="manage_shifts">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center"><i class="fas fa-filter mr-2 text-indigo-500"></i>Фільтри пошуку</h3>
                    <button type="button" id="toggleFilters" class="text-sm text-indigo-600 hover:text-indigo-800 transition-colors"><i class="fas fa-chevron-down mr-1" id="filterToggleIcon"></i><span id="filterToggleText">Розгорнути</span></button>
                </div>
                <div id="filterContent" class="<?php echo ($active_filters_count > 0) ? '' : 'hidden'; ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
                        <!-- Filter inputs here, same as in manage_shifts.php -->
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-lg hover:from-indigo-700 hover:to-purple-700 font-medium shadow-lg hover:shadow-xl"><i class="fas fa-search mr-2"></i> Застосувати</button>
                        <a href="?page=manage_shifts" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 font-medium"><i class="fas fa-times mr-2"></i> Скинути</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200/50">
            <!-- Table and pagination here, same as in manage_shifts.php -->
            <!-- IMPORTANT: Links for sorting and pagination must be updated to use get_panel_link() -->
        </div>
    </div>
</div>

<!-- Modals here, same as in manage_shifts.php -->

<script>
// All the JS from manage_shifts.php should be here.
// It might need to be adapted to avoid conflicts if other tabs have similar JS.
// Using more specific IDs for elements within this tab is a good practice.
</script>
