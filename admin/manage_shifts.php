<?php
/**
 * admin/manage_shifts.php
 * Сторінка для розширеного керування змінами (пошук, фільтрація, редагування, видалення).
 *
 * @version 2.1 (Refactored by Gemini)
 * - Оптимізовано SQL-запит: додано JOIN для балів, щоб уникнути другого запиту.
 * - Додано стовпчик "Рівень" (L0/L1/L2) у десктопну версію.
 * - Покращено відображення статусу: додано іконку для змін, закритих вручну.
 * - Повністю перероблено мобільний вигляд: розширена інформація в розгорнутому рядку.
 */

require_once '../config.php';
require_once '../includes/functions.php';
global $pdo;
require_roles(['admin', 'duty_officer']);
save_current_page_for_redirect();

// =================================================================================
// РОЗДІЛ 1: ПАРАМЕТРИ, ФІЛЬТРИ ТА СОРТУВАННЯ
// =================================================================================

$page_title = "Керування Змінами";
$current_year = date('Y');

// --- Отримання параметрів з GET-запиту ---
$filter_shift_id = filter_input(INPUT_GET, 's_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_year = filter_input(INPUT_GET, 's_year', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_month = filter_input(INPUT_GET, 's_month', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 1, 'max_range' => 12]]);
$filter_day = filter_input(INPUT_GET, 's_day', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 1, 'max_range' => 31]]);
$filter_post_id = filter_input(INPUT_GET, 's_post_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_user_id = filter_input(INPUT_GET, 's_user_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_status = trim($_GET['s_status'] ?? '');
$search_query = trim($_GET['s_search'] ?? '');

// --- Параметри сортування ---
$sort_column = $_GET['s_sort'] ?? 'start_time';
$sort_order = (isset($_GET['s_order']) && strtolower($_GET['s_order']) == 'asc') ? 'ASC' : 'DESC';

$allowed_sort_columns_map = [
    'id' => 's.id', 'start_time' => 's.start_time', 'end_time' => 's.end_time',
    'lifeguard_name' => 'u.full_name', 'post_name' => 'p.name', 'status' => 's.status',
    'duration_seconds' => 'duration_seconds', 'lifeguard_assignment_type' => 's.lifeguard_assignment_type'
];
$sql_sort_column = $allowed_sort_columns_map[$sort_column] ?? 's.start_time';

// --- Параметри пагінації ---
$page = isset($_GET['s_page']) ? max(1, (int)$_GET['s_page']) : 1;
$per_page_options = [10, 25, 50, 100];
$per_page = isset($_GET['s_per_page']) && in_array((int)$_GET['s_per_page'], $per_page_options) ? (int)$_GET['s_per_page'] : 25;


// =================================================================================
// РОЗДІЛ 2: ЗАВАНТАЖЕННЯ ДАНИХ ДЛЯ ФІЛЬТРІВ
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
    set_flash_message('помилка', 'Не вдалося завантажити дані для фільтрів.');
    error_log("Manage Shifts Filter Data Error: " . $e->getMessage());
}

// =================================================================================
// РОЗДІЛ 3: ПОБУДОВА SQL-ЗАПИТУ
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

// --- Застосування фільтрів ---
if ($filter_shift_id) {
    $where_conditions[] = "s.id = :s_id";
    $params[':s_id'] = $filter_shift_id;
}

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

if ($filter_post_id) {
    $where_conditions[] = "s.post_id = :s_post_id";
    $params[':s_post_id'] = $filter_post_id;
}
if ($filter_user_id) {
    $where_conditions[] = "s.user_id = :s_user_id";
    $params[':s_user_id'] = $filter_user_id;
}
if (!empty($filter_status) && array_key_exists($filter_status, $shift_statuses)) {
    $where_conditions[] = "s.status = :s_status";
    $params[':s_status'] = $filter_status;
}
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
// РОЗДІЛ 4: ВИБІРКА ДАНИХ ТА ПАГІНАЦІЯ
// =================================================================================

$total_records = 0;
try {
    $stmt_count = $pdo->prepare("SELECT COUNT(s.id) " . $sql_from_joins . " " . $sql_where);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
} catch (PDOException $e) {
    set_flash_message('помилка', 'Не вдалося підрахувати кількість змін.');
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
    set_flash_message('помилка', 'Не вдалося завантажити дані змін.');
    error_log("Manage Shifts Fetch Error: " . $e->getMessage());
}

// =================================================================================
// РОЗДІЛ 5: ДОПОМІЖНІ ФУНКЦІЇ
// =================================================================================

$base_link_params = array_filter([
    's_id' => $filter_shift_id, 's_year' => $filter_year, 's_month' => $filter_month,
    's_day' => $filter_day, 's_post_id' => $filter_post_id, 's_user_id' => $filter_user_id,
    's_status' => $filter_status, 's_search' => $search_query, 's_per_page' => $per_page,
]);
$active_filters_count = count(array_filter($base_link_params, fn($v, $k) => !in_array($k, ['s_per_page']) && $v, ARRAY_FILTER_USE_BOTH));

function get_sort_link($field, $current_col, $current_order, $base_params) {
    $order = ($current_col === $field && strtolower($current_order) === 'asc') ? 'desc' : 'asc';
    $params = array_merge($base_params, ['s_sort' => $field, 's_order' => $order, 's_page' => 1]);
    return 'manage_shifts.php?' . http_build_query($params);
}

function get_page_link($page_num, $base_params) {
    $params = $base_params;
    $params['s_page'] = $page_num;
    return 'manage_shifts.php?' . http_build_query($params);
}

function get_reset_filters_link() {
    $reset_params = [];
    if (isset($_GET['s_sort'])) $reset_params['s_sort'] = $_GET['s_sort'];
    if (isset($_GET['s_order'])) $reset_params['s_order'] = $_GET['s_order'];
    if (isset($_GET['s_per_page'])) $reset_params['s_per_page'] = $_GET['s_per_page'];
    return 'manage_shifts.php?' . http_build_query($reset_params);
}

function render_media_icons($shift) {
    $app_url = rtrim(APP_URL, '/');
    $html = '<div class="flex items-center justify-center gap-3">';
    if (!empty($shift['start_photo_path'])) {
        $color = $shift['start_photo_approved_at'] ? 'green' : 'orange';
        $title = 'Фото початку' . ($shift['start_photo_approved_at'] ? ' (Підтверджено)' : ' (Очікує)');
        $html .= sprintf('<a href="%s/%s" target="_blank" class="text-%s-500 hover:text-%s-700" title="%s"><i class="fas fa-camera text-lg"></i></a>', $app_url, ltrim($shift['start_photo_path'], '/'), $color, $color, escape($title));
    } else {
        $html .= '<i class="fas fa-camera text-gray-300 text-lg" title="Фото початку: Немає"></i>';
    }
    if (!empty($shift['photo_close_path'])) {
        $html .= sprintf('<a href="%s/%s" target="_blank" class="text-green-500 hover:text-green-700" title="Фото завершення"><i class="fas fa-camera-retro text-lg"></i></a>', $app_url, ltrim($shift['photo_close_path'], '/'));
    } else {
        $html .= '<i class="fas fa-camera-retro text-gray-300 text-lg" title="Фото завершення: Немає"></i>';
    }
    if ($shift['reports_count'] > 0) {
        $html .= '<i class="fas fa-file-alt text-green-500 text-lg" title="Звіт подано"></i>';
    } elseif ($shift['status'] === 'completed') {
        $user_role = $_SESSION['user_role'] ?? null;
        if (in_array($user_role, ['admin', 'duty_officer'])) {
            $report_url = $app_url . '/submit_report.php?shift_id=' . $shift['id'];
            $html .= '<a href="' . $report_url . '" class="text-red-500 hover:text-red-700" title="Подати звіт за цю зміну"><i class="fas fa-file-alt text-lg"></i></a>';
        } else {
            $html .= '<i class="fas fa-file-alt text-red-500 text-lg" title="Звіт не подано"></i>';
        }
    } else {
        $html .= '<i class="fas fa-file-alt text-gray-300 text-lg" title="Звіт ще не потрібен"></i>';
    }
    $html .= '</div>';
    return $html;
}

function render_action_buttons($shift_id, $is_mobile = false) {
    $app_url = rtrim(APP_URL, '/');
    $details_url = "{$app_url}/admin/view_shift_details.php?shift_id={$shift_id}";
    $edit_url = "{$app_url}/admin/edit_single_shift.php?shift_id={$shift_id}";
    $delete_url = "{$app_url}/admin/delete_single_shift.php";
    $csrf = function_exists('csrf_input') ? csrf_input() : '';

    if ($is_mobile) {
        return sprintf('<div class="flex items-center gap-2 mt-3">
                <a href="%s" class="flex-1 bg-sky-100 text-sky-600 hover:bg-sky-200 px-3 py-2 rounded-lg flex items-center justify-center text-sm font-medium"><i class="fas fa-eye mr-2"></i>Деталі</a>
                <a href="%s" class="flex-1 bg-indigo-100 text-indigo-600 hover:bg-indigo-200 px-3 py-2 rounded-lg flex items-center justify-center text-sm font-medium"><i class="fas fa-edit mr-2"></i>Редагувати</a>
                <form action="%s" method="POST" class="flex-1 delete-shift-form"><input type="hidden" name="shift_id_to_delete" value="%d">%s<button type="submit" class="w-full bg-red-100 text-red-600 hover:bg-red-200 px-3 py-2 rounded-lg flex items-center justify-center text-sm font-medium"><i class="fas fa-trash mr-2"></i>Видалити</button></form>
            </div>', $details_url, $edit_url, $delete_url, $shift_id, $csrf);
    }
    return sprintf('<div class="flex items-center justify-center gap-1">
            <a href="%s" class="w-8 h-8 bg-sky-100 text-sky-600 hover:bg-sky-200 rounded-lg flex items-center justify-center" title="Деталі"><i class="fas fa-eye"></i></a>
            <a href="%s" class="w-8 h-8 bg-indigo-100 text-indigo-600 hover:bg-indigo-200 rounded-lg flex items-center justify-center" title="Редагувати"><i class="fas fa-edit"></i></a>
            <form action="%s" method="POST" class="inline delete-shift-form"><input type="hidden" name="shift_id_to_delete" value="%d">%s<button type="submit" class="w-8 h-8 bg-red-100 text-red-600 hover:bg-red-200 rounded-lg flex items-center justify-center" title="Видалити"><i class="fas fa-trash"></i></button></form>
        </div>', $details_url, $edit_url, $delete_url, $shift_id, $csrf);
}

require_once '../includes/header.php';
?>

<div class="min-h-screen bg-transparent">
    <div class="container mx-auto px-4 py-6">
        <div class="glass-effect rounded-2xl shadow-xl border border-white/20">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center mb-2">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-3 shadow-lg"><i class="fas fa-clipboard-list text-white text-xl"></i></div>
                        <?php echo escape($page_title); ?>
                    </h1>
                    <p class="text-gray-600 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-indigo-500"></i>Знайдено записів: <span class="font-semibold ml-2 text-indigo-600"><?php echo $total_records; ?></span>
                        <?php if ($active_filters_count > 0): ?>
                            <span class="ml-4 text-sm"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"><?php echo $active_filters_count; ?> активних фільтрів</span></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="openHoursModalBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition-all duration-200 shadow-lg flex items-center justify-center"><i class="fas fa-calculator mr-2"></i> Розрахунок годин</button>
                <a href="<?php echo rtrim(APP_URL, '/'); ?>/index.php#admin-duty-content" class="bg-gradient-to-r from-gray-600 to-gray-700 text-white px-5 py-2.5 rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105"><i class="fas fa-arrow-left mr-2"></i> До панелі</a>
                </div>
            </div>
        </div>

        <div class="glass-effect rounded-2xl shadow-lg border border-white/20">
            <form action="manage_shifts.php" method="GET" id="filtersForm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center"><i class="fas fa-filter mr-2 text-indigo-500"></i>Фільтри пошуку</h3>
                    <button type="button" id="toggleFilters" class="text-sm text-indigo-600 hover:text-indigo-800 transition-colors"><i class="fas fa-chevron-down mr-1" id="filterToggleIcon"></i><span id="filterToggleText">Розгорнути</span></button>
                </div>
                    <div id="filterContent" class="<?php echo ($active_filters_count > 0) ? '' : 'hidden'; ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 mb-4">
                        <div>
                            <label for="s_id_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-hashtag mr-1 text-gray-400"></i> ID Зміни</label>
                            <input type="number" name="s_id" id="s_id_filter" value="<?php echo escape($filter_shift_id ?: ''); ?>" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Будь-який">
                        </div>
                        <div>
                            <label for="s_year_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-calendar-alt mr-1 text-gray-400"></i> Рік</label>
                            <select name="s_year" id="s_year_filter" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="0">Всі роки</option>
                                <?php foreach ($available_years_for_filter as $year_opt): ?><option value="<?php echo $year_opt; ?>" <?php echo ($filter_year == $year_opt) ? 'selected' : ''; ?>><?php echo $year_opt; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="s_month_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-calendar-day mr-1 text-gray-400"></i> Місяць</label>
                            <select name="s_month" id="s_month_filter" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="0">Всі місяці</option>
                                <?php foreach ($months_ukrainian_filter as $num => $name): ?><option value="<?php echo $num; ?>" <?php echo ($filter_month == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="s_day_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-calendar-check mr-1 text-gray-400"></i> День</label>
                            <input type="number" name="s_day" id="s_day_filter" value="<?php echo $filter_day ?: ''; ?>" min="1" max="31" placeholder="Всі дні" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="s_post_id_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-map-marker-alt mr-1 text-gray-400"></i> Пост</label>
                            <select name="s_post_id" id="s_post_id_filter" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="0">Всі пости</option>
                                <?php foreach ($posts_for_filter as $post_f): ?><option value="<?php echo $post_f['id']; ?>" <?php echo ($filter_post_id == $post_f['id']) ? 'selected' : ''; ?>><?php echo escape($post_f['name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="s_user_id_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-user mr-1 text-gray-400"></i> Лайфгард</label>
                            <select name="s_user_id" id="s_user_id_filter" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="0">Всі лайфгарди</option>
                                <?php foreach ($lifeguards_for_filter as $lg_f): ?><option value="<?php echo $lg_f['id']; ?>" <?php echo ($filter_user_id == $lg_f['id']) ? 'selected' : ''; ?>><?php echo escape($lg_f['full_name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="s_status_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-flag mr-1 text-gray-400"></i> Статус</label>
                            <select name="s_status" id="s_status_filter" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="">Всі статуси</option>
                                <?php foreach ($shift_statuses as $key_status => $label_status): ?><option value="<?php echo $key_status; ?>" <?php echo ($filter_status == $key_status) ? 'selected' : ''; ?>><?php echo escape($label_status); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lg:col-span-2 xl:col-span-1">
                            <label for="s_search_filter" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-search mr-1 text-gray-400"></i> Пошук</label>
                            <input type="text" name="s_search" id="s_search_filter" value="<?php echo escape($search_query); ?>" placeholder="ID, ПІБ, Пост..." class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-lg hover:from-indigo-700 hover:to-purple-700 font-medium shadow-lg hover:shadow-xl"><i class="fas fa-search mr-2"></i> Застосувати</button>
                        <a href="<?php echo get_reset_filters_link(); ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 font-medium"><i class="fas fa-times mr-2"></i> Скинути</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="glass-effect rounded-2xl shadow-lg overflow-hidden border border-white/20" style="padding:0;">
            <?php if (empty($shifts_data) && $total_records == 0 && $active_filters_count == 0): ?>
                <div class="text-center py-16 px-4"><div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-4"><i class="fas fa-clipboard text-gray-400 text-3xl"></i></div><h3 class="text-xl font-semibold text-gray-700 mb-2">Ще немає змін</h3><p class="text-gray-500 mb-4">В системі ще не було створено жодної зміни</p></div>
            <?php elseif (empty($shifts_data)): ?>
                <div class="text-center py-16 px-4"><div class="inline-flex items-center justify-center w-20 h-20 bg-yellow-100 rounded-full mb-4"><i class="fas fa-search text-yellow-600 text-3xl"></i></div><h3 class="text-xl font-semibold text-gray-700 mb-2">Нічого не знайдено</h3><p class="text-gray-500 mb-4">За обраними критеріями змін не знайдено</p><a href="<?php echo get_reset_filters_link(); ?>" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-medium"><i class="fas fa-redo mr-2"></i>Скинути фільтри</a></div>
            <?php else: ?>
                <div class="flex flex-col sm:flex-row justify-between items-center px-4 py-2 sm:px-6 sm:py-3 bg-white/20 border-b border-white/20 backdrop-blur-sm gap-2">
                    <div class="text-xs sm:text-sm text-gray-600">Показано <span class="font-semibold"><?php echo count($shifts_data); ?></span> з <span class="font-semibold"><?php echo $total_records; ?></span> записів</div>
                    <div class="flex items-center gap-3 w-full sm:w-auto justify-between">
                        <div class="flex items-center gap-2">
                             <label for="s_per_page_select" class="text-xs sm:text-sm text-gray-600">Записів:</label>
                             <select id="s_per_page_select" class="px-2 py-1 bg-white border border-gray-300 rounded-lg text-xs sm:text-sm focus:ring-2 focus:ring-indigo-500">
                                <?php foreach ($per_page_options as $opt): ?><option value="<?php echo $opt; ?>" <?php if ($per_page == $opt) echo 'selected'; ?>><?php echo $opt; ?></option><?php endforeach; ?>
                             </select>
                        </div>
                        <form method="GET" action="export_shifts_excel.php" target="_blank">
                            <?php foreach ($base_link_params as $key => $val): ?><input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($val); ?>"><?php endforeach; ?>
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium shadow transition-all flex items-center gap-2 <?php if ($total_records == 0) echo 'opacity-50 cursor-not-allowed'; ?>" <?php if ($total_records == 0) echo 'disabled'; ?>><i class="fas fa-file-excel"></i> Експорт</button>
                        </form>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                        <thead class="bg-white/30 backdrop-blur-sm">
                            <tr>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider">ID</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden lg:table-cell">Дата</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden lg:table-cell">Час</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden lg:table-cell">Тривалість</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider">Лайфгард</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-left text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden md:table-cell">Пост</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider">Статус</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider">Тип</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden md:table-cell">Рівень</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden sm:table-cell">Медіа</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider">Бали</th>
                                <th class="px-3 py-2 sm:px-4 sm:py-3 text-center text-[10px] sm:text-xs font-bold text-gray-800 uppercase tracking-wider hidden sm:table-cell">Дії</th>
                                <th class="px-1 py-2 text-center sm:hidden"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white/20 divide-y divide-gray-200" id="shifts-table-body">
                            <?php foreach ($shifts_data as $shift): ?>
                                <?php
                                $status_key = $shift['status'] ?? 'unknown';
                                $status_map = [
                                    'active' => ['text' => 'Активна', 'bg' => 'from-yellow-400 to-amber-500', 'icon' => 'fa-play-circle', 'animate' => true],
                                    'active_manual' => ['text' => 'Відкрито вручну', 'bg' => 'from-orange-400 to-red-500', 'icon' => 'fa-hand-paper', 'animate' => true],
                                    'completed' => ['text' => 'Завершено', 'bg' => 'from-green-400 to-emerald-500', 'icon' => 'fa-check-circle'],
                                    'cancelled' => ['text' => 'Скасовано', 'bg' => 'from-red-400 to-rose-500', 'icon' => 'fa-times-circle'],
                                    'pending_photo_open' => ['text' => 'Очікує фото', 'bg' => 'from-purple-400 to-pink-500', 'icon' => 'fa-camera']
                                ];
                                $status_info = $status_map[$status_key] ?? ['text' => ucfirst($status_key), 'bg' => 'from-gray-400 to-gray-500', 'icon' => 'fa-question-circle'];
                                $manual_close_info = '';
                                if ($shift['status'] === 'completed' && $shift['manual_closed_by']) {
                                    $admin_name = escape($shift['manual_closed_by_admin_name'] ?? 'Адміністратор');
                                    $manual_close_info = sprintf('<span class="ml-1.5 text-gray-400" title="Закрито вручну: %s"><i class="fas fa-user-shield"></i></span>', $admin_name);
                                }
                                $points = (int)($shift['total_points'] ?? 0);
                                ?>
                                <tr class="hover:bg-white/20 transition-colors duration-150">
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap"><span class="font-mono text-gray-800 font-semibold">#<?php echo $shift['id']; ?></span></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap hidden lg:table-cell font-medium text-gray-800"><?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap hidden lg:table-cell text-gray-800"><?php echo format_datetime($shift['start_time'], 'H:i'); ?></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap hidden lg:table-cell text-gray-800"><?php echo $shift['end_time'] ? format_duration($shift['start_time'], $shift['end_time']) : '<span class="text-gray-500">—</span>'; ?></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap font-medium text-gray-800 font-semibold"><?php echo escape($shift['lifeguard_name']); ?></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap hidden md:table-cell text-gray-800"><?php echo escape($shift['post_name']); ?></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap text-center">
                                        <span class="px-3 py-1 inline-flex items-center text-xs font-semibold rounded-full bg-gradient-to-r text-white shadow-md <?php echo $status_info['bg']; ?> <?php if(isset($status_info['animate'])) echo 'animate-pulse'; ?>"><i class="fas <?php echo $status_info['icon']; ?> mr-1.5"></i><span class="hidden md:inline"><?php echo escape($status_info['text']); ?></span><?php echo $manual_close_info; ?></span>
                                    </td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium <?php echo ($shift['activity_type'] ?? 'shift') === 'training' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'; ?>"><?php echo ($shift['activity_type'] ?? 'shift') === 'training' ? 'Тренування' : 'Зміна'; ?></span>
                                    </td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap text-center hidden md:table-cell">
                                        <?php
                                        $assignment_type = escape($shift['lifeguard_assignment_type'] ?? 'N/A');
                                        $assignment_color = 'bg-gray-200 text-gray-800';
                                        if (str_starts_with($assignment_type, 'L1')) $assignment_color = 'bg-green-100 text-green-800';
                                        if (str_starts_with($assignment_type, 'L2')) $assignment_color = 'bg-orange-100 text-orange-800';
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?php echo $assignment_color; ?>"><?php echo $assignment_type; ?></span>
                                    </td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-center hidden sm:table-cell"><?php echo render_media_icons($shift); ?></td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 whitespace-nowrap text-center">
                                        <button type="button" class="award-points-btn text-indigo-600 hover:text-indigo-800 font-semibold" data-shift-id="<?php echo $shift['id']; ?>"><span class="<?php if ($points > 0) echo 'text-green-700'; ?>"><?php echo $points ?: '---'; ?></span><i class="fas fa-medal ml-2 text-yellow-500"></i></button>
                                    </td>
                                    <td class="px-3 py-2 sm:px-4 sm:py-3 text-center hidden sm:table-cell"><?php echo render_action_buttons($shift['id']); ?></td>
                                    <td class="px-1 py-2 text-center sm:hidden"><button type="button" class="expand-row-btn text-indigo-600 hover:text-indigo-800" data-shift-id="<?php echo $shift['id']; ?>"><i class="fas fa-chevron-down"></i></button></td>
                                </tr>
                                <tr class="mobile-details-row hidden" id="mobile-details-<?php echo $shift['id']; ?>">
                                    <td colspan="12" class="bg-white/20 p-2">
                                        <div class="bg-white/20 backdrop-blur-md rounded-xl border p-4 shadow-sm">
                                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs mb-3 border-b pb-3">
                                                <div class="col-span-2"><strong>Пост:</strong> <?php echo escape($shift['post_name']); ?></div>
                                                <div><strong>Дата:</strong> <?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?></div>
                                                <div><strong>Час:</strong> <?php echo format_datetime($shift['start_time'], 'H:i'); ?> - <?php echo $shift['end_time'] ? format_datetime($shift['end_time'], 'H:i') : '...'; ?></div>
                                                <div><strong>Тривалість:</strong> <?php echo $shift['end_time'] ? format_duration($shift['start_time'], $shift['end_time']) : '---'; ?></div>
                                                <div><strong>Рівень:</strong> <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium <?php echo $assignment_color; ?>"><?php echo $assignment_type; ?></span></div>
                                            </div>
                                            <div class="flex justify-around items-center mb-3 text-center text-xs">
                                                <div>
                                                    <div class="text-[10px] text-gray-500 mb-1">Медіа</div>
                                                    <?php echo render_media_icons($shift); ?>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-gray-500 mb-1">Бали</div>
                                                    <button type="button" class="award-points-btn text-indigo-600 hover:text-indigo-800 font-semibold" data-shift-id="<?php echo $shift['id']; ?>">
                                                        <span class="<?php if ($points > 0) echo 'text-green-700'; ?>"><?php echo $points ?: '---'; ?></span>
                                                        <i class="fas fa-medal ml-2 text-yellow-500"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 mt-3"><?php echo render_action_buttons($shift['id'], true); ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1 && !empty($shifts_data)): ?>
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-gray-600">Сторінка <span class="font-semibold"><?php echo $page; ?></span> з <span class="font-semibold"><?php echo $total_pages; ?></span></div>
            <nav class="flex items-center space-x-1" aria-label="Pagination">
                <a href="<?php echo $page > 1 ? get_page_link($page - 1, $base_link_params) : '#'; ?>" class="px-3 py-2 rounded-lg border <?php echo $page <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php
                $links_limit = 5; $start_pg = max(1, $page - floor($links_limit / 2)); $end_pg = min($total_pages, $start_pg + $links_limit - 1);
                if ($end_pg - $start_pg + 1 < $links_limit) $start_pg = max(1, $end_pg - $links_limit + 1);
                if ($start_pg > 1) { echo '<a href="'.get_page_link(1, $base_link_params).'" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">1</a>'; if ($start_pg > 2) echo '<span class="px-2">...</span>'; }
                for ($i = $start_pg; $i <= $end_pg; $i++) { echo '<a href="'.get_page_link($i, $base_link_params).'" class="px-3 py-2 rounded-lg border '.($i == $page ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white border-transparent shadow-md' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50').'">'.$i.'</a>'; }
                if ($end_pg < $total_pages) { if ($end_pg < $total_pages - 1) echo '<span class="px-2">...</span>'; echo '<a href="'.get_page_link($total_pages, $base_link_params).'" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>'; }
                ?>
                <a href="<?php echo $page < $total_pages ? get_page_link($page + 1, $base_link_params) : '#'; ?>" class="px-3 py-2 rounded-lg border <?php echo $page >= $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'; ?>"><i class="fas fa-chevron-right"></i></a>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="awardPointsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-40 flex items-center justify-center p-4">
    <div class="bg-white/80 backdrop-blur-md rounded-lg shadow-xl p-6 w-full max-w-4xl relative max-h-[90vh] flex flex-col">
        <button id="closeAwardPointsModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-700"><i class="fas fa-times text-xl"></i></button>
        <h2 class="text-xl font-bold mb-4">Нарахування балів за зміну #<span id="modalShiftId"></span></h2>
        <form id="awardPointsForm" class="flex-grow overflow-y-auto">
            <input type="hidden" name="shift_id" id="modalShiftIdInput">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-xl p-4 bg-green-50 border border-green-200">
                    <h3 class="font-bold text-green-800 mb-2 text-lg">Щоденні</h3>
                    <div class="space-y-3">
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[1][awarded]" value="1" class="form-checkbox text-green-600 mt-1"><span>Зміна <span class="font-bold text-green-600">(+1)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[2][awarded]" value="1" class="form-checkbox text-green-600 mt-1"><span>Вчасно на зміну/зі зміни <span class="font-bold text-green-600">(+1)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[4][awarded]" value="1" class="form-checkbox text-green-600 mt-1"><span>Правильне селфі <span class="font-bold text-green-600">(+1)</span><br><span class="text-xs text-gray-500">Форма, вишка, обладнання</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[5][awarded]" value="1" class="form-checkbox text-green-600 mt-1"><span>Вчасне заповнення звіту <span class="font-bold text-green-600">(+1)</span><br><span class="text-xs text-gray-500">Звіт заповнено з 20:00 до 21:00</span></span></label>
                    </div>
                </div>
                <div class="rounded-xl p-4 bg-blue-50 border border-blue-200">
                    <h3 class="font-bold text-blue-800 mb-2 text-lg">Бонусні</h3>
                    <div class="space-y-3">
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[6][awarded]" value="1" class="form-checkbox text-blue-600 mt-1"><span>Тренування <span class="font-bold text-blue-600">(+1)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[8][awarded]" value="1" class="form-checkbox text-blue-600 mt-1"><span>Один(на) на зміні <span class="font-bold text-blue-600">(+2)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[9][awarded]" value="1" class="form-checkbox text-blue-600 mt-1"><span>Гарячий вихід <span class="font-bold text-blue-600">(+5)</span><br><span class="text-xs text-gray-500">Вихід на зміну вранці замість когось</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[12][awarded]" value="1" class="form-checkbox text-blue-600 mt-1"><span>Працював у погану погоду <span class="font-bold text-blue-600">(+2)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[14][awarded]" value="1" class="form-checkbox text-blue-600 mt-1"><span>Участь у змаганнях <span class="font-bold text-blue-600">(+5)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[15][awarded]" value="1" class="form-checkbox text-blue-600 mt-1"><span>Пунктуальність <span class="font-bold text-blue-600">(+3)</span><br><span class="text-xs text-gray-500">Раз на місяць, без запізнень</span></span></label>
                    </div>
                </div>
                <div class="rounded-xl p-4 bg-red-50 border border-red-200">
                    <h3 class="font-bold text-red-800 mb-2 text-lg">Штрафи</h3>
                    <div class="space-y-3">
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[16][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Запізнення до 5 хв <span class="font-bold text-red-500">(-1)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[18][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Запізнення до 15 хв <span class="font-bold text-red-500">(-3)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[21][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Запізнення від 30 хв <span class="font-bold text-red-500">(-10)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[22][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Порушення правил <span class="font-bold text-red-500">(-2)</span><br><span class="text-xs text-gray-500">Телефон, спілкування</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[23][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Грубе порушення <span class="font-bold text-red-500">(-5)</span><br><span class="text-xs text-gray-500">Відсутність, алкоголь</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[24][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Не вихід без поваж. причини <span class="font-bold text-red-500">(-10)</span></span></label>
                        <label class="flex items-start gap-2"><input type="checkbox" name="points[25][awarded]" value="1" class="form-checkbox text-red-500 mt-1"><span>Не вчасно заповнений звіт <span class="font-bold text-red-500">(-1)</span></span></label>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end pt-4 border-t">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 font-medium">Зберегти бали</button>
            </div>
        </form>
    </div>
</div>


<div id="recalculateHoursModal" class="fixed inset-0 z-[60] hidden bg-black bg-opacity-60 flex items-center justify-center p-4">
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-2xl w-full max-w-2xl transform transition-all text-gray-800" style="max-height: 90vh;">

        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-bold flex items-center"><i class="fas fa-calculator text-blue-500 mr-3"></i>Перевірка та розрахунок годин</h2>
            <button type="button" class="close-modal-btn text-gray-500 hover:text-gray-700 transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6" style="overflow-y: auto; max-height: calc(90vh - 140px);">
            <div id="hoursModalInitialState">
                <p class="text-gray-700 mb-4">Цей інструмент дозволяє знайти завершені зміни, для яких не були розраховані години, та виправити це.</p>
                <button id="checkShiftsBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-200 shadow-lg flex items-center justify-center"><span id="checkShiftsBtnText"><i class="fas fa-search mr-2"></i>Перевірити зміни</span><span id="checkShiftsSpinner" class="hidden"><i class="fas fa-spinner fa-spin mr-2"></i>Пошук...</span></button>
            </div>
            <div id="hoursModalResultsState" class="hidden"></div>
        </div>
    </div>
</div>

<script src="../assets/js/award_points.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Логіка для Фільтрів ---
    const toggleButton = document.getElementById('toggleFilters');
    const filterContent = document.getElementById('filterContent');
    const perPageSelect = document.getElementById('s_per_page_select');

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const isHidden = filterContent.classList.toggle('hidden');
            const icon = toggleButton.querySelector('i');
            const text = toggleButton.querySelector('span');
            icon.className = isHidden ? 'fas fa-chevron-down mr-1' : 'fas fa-chevron-up mr-1';
            text.textContent = isHidden ? 'Розгорнути' : 'Згорнути';
        });
    }

    const yearSelect = document.getElementById('s_year_filter');
    const monthSelect = document.getElementById('s_month_filter');
    const dayInput = document.getElementById('s_day_filter');
    const updateDateFiltersState = () => {
        const yearVal = yearSelect ? parseInt(yearSelect.value, 10) : 0;
        const monthVal = monthSelect ? parseInt(monthSelect.value, 10) : 0;
        if (monthSelect) monthSelect.disabled = (yearVal === 0);
        if (dayInput) dayInput.disabled = (yearVal === 0 || monthVal === 0);
    };
    if (yearSelect) yearSelect.addEventListener('change', updateDateFiltersState);
    if (monthSelect) monthSelect.addEventListener('change', updateDateFiltersState);
    updateDateFiltersState();

    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            const url = new URL(window.location);
            url.searchParams.set('s_per_page', this.value);
            url.searchParams.set('s_page', '1');
            window.location.href = url.toString();
        });
    }

    // --- Логіка для Таблиці ---
    const tableBody = document.getElementById('shifts-table-body');
    if (tableBody) {
        let currentOpenRowId = null;
        tableBody.addEventListener('click', (e) => {
            const expandBtn = e.target.closest('.expand-row-btn');
            if (expandBtn) {
                e.preventDefault();
                const shiftId = expandBtn.dataset.shiftId;
                const detailsRow = document.getElementById(`mobile-details-${shiftId}`);
                const icon = expandBtn.querySelector('i');
                
                if (currentOpenRowId && currentOpenRowId !== shiftId) {
                    const prevRow = document.getElementById(`mobile-details-${currentOpenRowId}`);
                    if (prevRow) prevRow.classList.add('hidden');
                    const prevIcon = document.querySelector(`.expand-row-btn[data-shift-id="${currentOpenRowId}"] i`);
                    if(prevIcon) prevIcon.className = 'fas fa-chevron-down';
                }
                
                const isHidden = detailsRow.classList.toggle('hidden');
                icon.className = isHidden ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
                currentOpenRowId = isHidden ? null : shiftId;
            }
        });

        document.querySelectorAll('.delete-shift-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const shiftId = this.querySelector('input[name="shift_id_to_delete"]').value;
                if (!confirm(`УВАГА! Видалити зміну ID ${shiftId}?\n\nЦя дія НЕОБОРОТНА!`)) {
                    e.preventDefault();
                }
            });
        });
    }
    
    // --- Логіка для Модальних вікон ---
    const openHoursModalBtn = document.getElementById('openHoursModalBtn');
    const recalculateHoursModal = document.getElementById('recalculateHoursModal');
    
    if(openHoursModalBtn && recalculateHoursModal) {
        const closeHoursModalBtn = recalculateHoursModal.querySelector('.close-modal-btn'); // Assuming a common class for close buttons
        openHoursModalBtn.addEventListener('click', () => recalculateHoursModal.classList.remove('hidden'));
        if(closeHoursModalBtn) closeHoursModalBtn.addEventListener('click', () => recalculateHoursModal.classList.add('hidden'));
        recalculateHoursModal.addEventListener('click', (e) => {
            if (e.target === recalculateHoursModal) {
                recalculateHoursModal.classList.add('hidden');
            }
        });
    }

    const checkShiftsBtn = document.getElementById('checkShiftsBtn');
    if (checkShiftsBtn) {
        checkShiftsBtn.addEventListener('click', () => {
            const spinner = document.getElementById('checkShiftsSpinner');
            const btnText = document.getElementById('checkShiftsBtnText');
            const hoursModalInitialState = document.getElementById('hoursModalInitialState');
            const hoursModalResultsState = document.getElementById('hoursModalResultsState');

            spinner.classList.remove('hidden');
            btnText.classList.add('hidden');
            checkShiftsBtn.disabled = true;

            fetch('../admin/ajax_find_shifts_without_hours.php').then(res => res.json()).then(data => {
                hoursModalInitialState.classList.add('hidden');
                hoursModalResultsState.classList.remove('hidden');
                if (data.success && data.shifts.length > 0) {

                    let html = `<h3 class="text-lg font-semibold mb-3">Знайдено <span class="text-red-500 font-bold">${data.shifts.length}</span> змін:</h3><div class="max-h-64 overflow-y-auto border rounded-lg p-3 bg-white/40 mb-4">`;

                    data.shifts.forEach(s => { html += `<div class="flex justify-between p-2 border-b last:border-b-0"><span><span class="font-mono text-indigo-600">#${s.id}</span> ${s.lifeguard_name}</span><span class="text-gray-500">${new Date(s.start_time).toLocaleDateString()}</span></div>`; });
                    html += `</div><button id="calculateHoursBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-200 shadow-lg flex items-center justify-center"><i class="fas fa-cogs mr-2"></i>Розрахувати години</button>`;
                    hoursModalResultsState.innerHTML = html;
                    document.getElementById('calculateHoursBtn').addEventListener('click', handleCalculateHours);
                } else {
                    hoursModalResultsState.innerHTML = `<div class="text-center py-8"><div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4"><i class="fas fa-check-circle text-green-500 text-3xl"></i></div><h3 class="text-xl font-semibold">Все гаразд!</h3><p class="mt-2">Не знайдено змін, що потребують розрахунку.</p></div>`;
                }
            }).catch(err => {
                hoursModalResultsState.innerHTML = `<p class="text-red-500">Помилка мережі. Деталі в консолі.</p>`;
                console.error(err);
            }).finally(() => {
                spinner.classList.add('hidden');
                btnText.classList.remove('hidden');
                checkShiftsBtn.disabled = false;
            });
        });
    }

    function handleCalculateHours() {
        const btn = document.getElementById('calculateHoursBtn');
        const hoursModalResultsState = document.getElementById('hoursModalResultsState');
        btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>Розрахунок...`;
        btn.disabled = true;
        fetch('../admin/ajax_calculate_hours_for_shifts.php', { method: 'POST' }).then(res => res.json()).then(data => {
            if (data.success) {
                hoursModalResultsState.innerHTML = `<div class="text-center py-8"><div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4"><i class="fas fa-check-circle text-green-500 text-3xl"></i></div><h3 class="text-xl font-semibold">Успішно оновлено!</h3><p class="mt-2">Розраховано години для <b>${data.updated_count}</b> змін. Перезавантаження сторінки...</p></div>`;
                setTimeout(() => location.reload(), 2500);
            } else {
                hoursModalResultsState.innerHTML = `<p class="text-red-500">Помилка: ${data.error || 'Невідома помилка'}</p>`;
            }
        }).catch(err => {
            hoursModalResultsState.innerHTML = `<p class="text-red-500">Помилка мережі. Деталі в консолі.</p>`;
            console.error(err);
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>