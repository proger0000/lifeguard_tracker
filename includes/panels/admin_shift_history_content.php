<?php
// /includes/panels/admin_shift_history_content.php
// Цей файл буде підключатися в admin_panel.php

// Переконуємось, що доступ мають лише адмін та черговий
if (!isset($base_anchor_history)) {
    $base_anchor_history = ''; // За замовчуванням порожній
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $base_anchor_history = '#admin-shift-history-content';
    }
}

global $pdo, $APP_URL;

// --- ВИЗНАЧЕННЯ БАЗОВОГО ЯКОРЯ ДЛЯ ПОСИЛАНЬ ---
$base_anchor_history = '';
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $base_anchor_history = '#admin-shift-history-content';
}

$page_title_history = "Історія Змін";

// --- ПАРАМЕТРИ ФІЛЬТРАЦІЇ ТА СОРТУВАННЯ ---
$current_year = date('Y');
$current_month = date('m');

// Фільтри (отримуємо з GET-запиту)
$filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT, ['options' => ['default' => $current_year, 'min_range' => 2020, 'max_range' => $current_year + 5]]);
$filter_month = filter_input(INPUT_GET, 'filter_month', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 12]]);
$filter_day = filter_input(INPUT_GET, 'filter_day', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 31]]);
$filter_post_id = filter_input(INPUT_GET, 'filter_post_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_user_id = filter_input(INPUT_GET, 'filter_user_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$search_query = trim($_GET['search_query'] ?? '');

// Ініціалізація змінної помилки
$db_error = '';

// Сортування
$sort_column = $_GET['sort'] ?? 'start_time';
$sort_order = (isset($_GET['order']) && strtolower($_GET['order']) == 'asc') ? 'ASC' : 'DESC';
$allowed_sort_columns = ['start_time', 'end_time', 'lifeguard_name', 'post_name', 'status', 'duration_seconds'];
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'start_time';
}

// Пагінація
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page_options_history = [15, 30, 50, 100, 200];
$per_page_history = isset($_GET['h_per_page']) && in_array((int)$_GET['h_per_page'], $per_page_options_history) ? (int)$_GET['h_per_page'] : 30;

// --- ОТРИМАННЯ ДАНИХ ДЛЯ ФІЛЬТРІВ ---
$posts_for_filter = [];
$lifeguards_for_filter = [];
$available_years_for_filter = [];

try {
    $stmt_posts = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
    $posts_for_filter = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

    $stmt_lifeguards = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lifeguard' ORDER BY full_name ASC");
    $lifeguards_for_filter = $stmt_lifeguards->fetchAll(PDO::FETCH_ASSOC);

    $stmt_years = $pdo->query("SELECT DISTINCT YEAR(start_time) as year FROM shifts WHERE start_time IS NOT NULL ORDER BY year DESC");
    $available_years_for_filter = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($current_year, $available_years_for_filter) && !empty($available_years_for_filter)) {
        $available_years_for_filter[] = $current_year;
        sort($available_years_for_filter);
        $available_years_for_filter = array_reverse($available_years_for_filter);
    } elseif (empty($available_years_for_filter)) {
         $available_years_for_filter = [$current_year];
    }
} catch (PDOException $e) {
    set_flash_message('помилка', 'Не вдалося завантажити дані для фільтрів.');
    error_log("Shift History Filter Data Error: " . $e->getMessage());
}

$months_ukrainian_filter = [1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень', 5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень', 9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'];

// --- ПОБУДОВА SQL-ЗАПИТУ ---
$sql_base = "FROM shifts s
             JOIN users u ON s.user_id = u.id
             JOIN posts p ON s.post_id = p.id
             WHERE 1=1";
$params = [];

if ($filter_year) {
    $sql_base .= " AND YEAR(s.start_time) = :year";
    $params[':year'] = $filter_year;
}
if ($filter_month) {
    $sql_base .= " AND MONTH(s.start_time) = :month";
    $params[':month'] = $filter_month;
}
if ($filter_day) {
    $sql_base .= " AND DAYOFMONTH(s.start_time) = :day";
    $params[':day'] = $filter_day;
}
if ($filter_post_id) {
    $sql_base .= " AND s.post_id = :post_id";
    $params[':post_id'] = $filter_post_id;
}
if ($filter_user_id) {
    $sql_base .= " AND s.user_id = :user_id";
    $params[':user_id'] = $filter_user_id;
}
if (!empty($search_query)) {
    $sql_base .= " AND (u.full_name LIKE :search_name OR p.name LIKE :search_post)";
    $params[':search_name'] = '%' . $search_query . '%';
    $params[':search_post'] = '%' . $search_query . '%';
}

// --- Пагінація ---
$total_records = 0;
try {
    $stmt_count = $pdo->prepare("SELECT COUNT(s.id) " . $sql_base);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
    
    if (isset($_GET['debug']) || $page > 1) {
        echo "<!-- Debug info: Total records for year $filter_year: $total_records, Page: $page -->";
    }
} catch (PDOException $e) {
    set_flash_message('помилка', 'Не вдалося підрахувати кількість записів для історії.');
    error_log("Shift History Count Error: " . $e->getMessage());
}

$total_pages = $total_records > 0 ? ceil($total_records / $per_page_history) : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $per_page_history;

if ($page > $total_pages && $total_records > 0) {
    set_flash_message('помилка', "Запитана сторінка $page не існує. Всього сторінок: $total_pages");
    $page = $total_pages;
    $offset = ($page - 1) * $per_page_history;
}

// --- ОТРИМАННЯ ДАНИХ ЗМІН ---
$shifts_history = [];
try {
    $sql_data = "SELECT s.id, s.start_time, s.end_time, s.status,
                    u.full_name as lifeguard_name, u.id as lifeguard_id,
                    p.name as post_name, p.id as post_id_val,
                    TIMESTAMPDIFF(SECOND, s.start_time, s.end_time) as duration_seconds,
                    0 as reports_count
             " . $sql_base . " LIMIT :limit OFFSET :offset";

    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->bindValue(':limit', $per_page_history, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_data->bindValue($key, $val);
    }
    $stmt_data->execute();
    $shifts_history = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_flash_message('помилка', 'Не вдалося завантажити історію змін.');
    error_log("Shift History Fetch Error: " . $e->getMessage());
}

if (empty($shifts_history) && $page > 1) {
    $actual_pages = $total_records > 0 ? ceil($total_records / $per_page_history) : 1;
    if ($page > $actual_pages) {
        $redirect_params = $base_history_link_params;
        $redirect_params['page'] = $actual_pages;
        $redirect_url = '?' . http_build_query($redirect_params) . $base_anchor_history;
        header("Location: $redirect_url");
        exit();
    }
}

// --- Допоміжні функції для посилань ---
$base_history_link_params = [
    'filter_year' => $filter_year, 
    'filter_month' => $filter_month, 
    'filter_day' => $filter_day,
    'filter_post_id' => $filter_post_id, 
    'filter_user_id' => $filter_user_id,
    'search_query' => $search_query,
    'sort' => $sort_column, 
    'order' => $sort_order, 
    'h_per_page' => $per_page_history
];

function get_history_sort_link($field, $current_sort, $current_order, $base_params) {
    global $base_anchor_history;
    $order = ($current_sort === $field && strtolower($current_order) === 'asc') ? 'desc' : 'asc';
    $params = array_merge($base_params, ['sort' => $field, 'order' => $order, 'page' => 1]);
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $params['tab_admin'] = 'shift_history';
    }
    
    return '?' . http_build_query(array_filter($params)) . $base_anchor_history;
}

function get_history_page_link($page_num, $base_params) {
    global $base_anchor_history;
    $params = array_merge($base_params, ['page' => $page_num]);
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $params['tab_admin'] = 'shift_history';
    }
    
    return '?' . http_build_query(array_filter($params)) . $base_anchor_history;
}

function get_history_reset_filters_link() {
    global $base_anchor_history;
    $reset_params = array_intersect_key($_GET, array_flip(['sort', 'order', 'h_per_page']));
    
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        $reset_params['tab_admin'] = 'shift_history';
    }
    
    return '?' . http_build_query($reset_params) . $base_anchor_history;
}
?>

<div class="shift-history-container">
    <!-- Header with gradient -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 sm:px-6 py-4 rounded-t-xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-xl sm:text-2xl font-bold flex items-center font-comfortaa">
                    <i class="fas fa-history mr-3 text-white/80"></i>
                    <?php echo $page_title_history; ?>
                </h3>
                <p class="text-indigo-100 text-sm mt-1 hidden sm:block">Повна історія всіх змін у системі</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="export-pdf-btn" class="bg-white/20 backdrop-blur-sm text-white px-3 py-1.5 rounded-lg hover:bg-white/30 transition-all duration-200 flex items-center text-sm font-medium" onclick="handleExport('pdf')">
                    <i class="fas fa-file-pdf mr-1.5 text-sm"></i> PDF
                </button>
                <button id="export-excel-btn" class="bg-white/20 backdrop-blur-sm text-white px-3 py-1.5 rounded-lg hover:bg-white/30 transition-all duration-200 flex items-center text-sm font-medium" onclick="handleExport('excel')">
                    <i class="fas fa-file-excel mr-1.5 text-sm"></i> Excel
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-b-xl shadow-lg border border-gray-200 p-4 sm:p-6">
        <?php display_flash_message(); ?>
        
        <!-- Filters Section -->
        <div class="mb-6">
            <?php
            // Определяем правильный action для формы
            $form_action = basename($_SERVER['PHP_SELF']);
            ?>
            <form id="shiftHistoryFiltersForm" action="<?php echo $form_action; ?>" method="GET" class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <input type="hidden" name="tab_admin" value="shift_history">
                <?php endif; ?>
                <?php if (isset($_GET['tab_duty'])): ?>
                    <input type="hidden" name="tab_duty" value="<?php echo htmlspecialchars($_GET['tab_duty']); ?>">
                <?php endif; ?>
                <input type="hidden" name="sort" value="<?php echo escape($sort_column); ?>">
                <input type="hidden" name="order" value="<?php echo escape($sort_order); ?>">
                <input type="hidden" name="h_per_page" value="<?php echo escape($per_page_history); ?>">
                <input type="hidden" name="page" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                    <!-- Year Filter -->
                    <div class="space-y-1">
                        <label for="filter_year" class="block text-xs font-medium text-gray-700">
                            <i class="fas fa-calendar-alt mr-1 text-indigo-500 text-xs"></i> Рік
                        </label>
                        <select name="filter_year" id="filter_year" class="modern-select w-full">
                            <?php foreach ($available_years_for_filter as $year_option): ?>
                                <option value="<?php echo $year_option; ?>" <?php echo ($filter_year == $year_option) ? 'selected' : ''; ?>><?php echo $year_option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Month Filter -->
                    <div class="space-y-1">
                        <label for="filter_month" class="block text-xs font-medium text-gray-700">
                            <i class="fas fa-calendar-day mr-1 text-indigo-500 text-xs"></i> Місяць
                        </label>
                        <select name="filter_month" id="filter_month" class="modern-select w-full">
                            <option value="0">Всі місяці</option>
                            <?php foreach ($months_ukrainian_filter as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($filter_month == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Day Filter -->
                    <div class="space-y-1">
                        <label for="filter_day" class="block text-xs font-medium text-gray-700">
                            <i class="fas fa-calendar-check mr-1 text-indigo-500 text-xs"></i> День
                        </label>
                        <input type="number" name="filter_day" id="filter_day" value="<?php echo $filter_day ?: ''; ?>" min="1" max="31" placeholder="Всі дні" class="modern-input w-full">
                    </div>
                    
                    <!-- Post Filter -->
                    <div class="space-y-1">
                        <label for="filter_post_id" class="block text-xs font-medium text-gray-700">
                            <i class="fas fa-map-marker-alt mr-1 text-indigo-500 text-xs"></i> Пост
                        </label>
                        <select name="filter_post_id" id="filter_post_id" class="modern-select w-full">
                            <option value="0">Всі пости</option>
                            <?php foreach ($posts_for_filter as $post_item): ?>
                                <option value="<?php echo $post_item['id']; ?>" <?php echo ($filter_post_id == $post_item['id']) ? 'selected' : ''; ?>><?php echo escape($post_item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Lifeguard Filter -->
                    <div class="space-y-1">
                        <label for="filter_user_id" class="block text-xs font-medium text-gray-700">
                            <i class="fas fa-user mr-1 text-indigo-500 text-xs"></i> Лайфгард
                        </label>
                        <select name="filter_user_id" id="filter_user_id" class="modern-select w-full">
                            <option value="0">Всі лайфгарди</option>
                            <?php foreach ($lifeguards_for_filter as $lifeguard_item): ?>
                                <option value="<?php echo $lifeguard_item['id']; ?>" <?php echo ($filter_user_id == $lifeguard_item['id']) ? 'selected' : ''; ?>><?php echo escape($lifeguard_item['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Search -->
                    <div class="space-y-1 lg:col-span-2 xl:col-span-3">
                        <label for="search_query" class="block text-xs font-medium text-gray-700">
                            <i class="fas fa-search mr-1 text-indigo-500 text-xs"></i> Пошук
                        </label>
                        <input type="text" name="search_query" id="search_query" value="<?php echo escape($search_query); ?>" placeholder="ПІБ або назва посту..." class="modern-input w-full">
                    </div>
                    
                    <!-- Actions -->
                    <div class="space-y-1 lg:col-span-1 xl:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 invisible">Дії</label>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors duration-200 font-medium text-sm">
                                <i class="fas fa-filter mr-1.5 text-sm"></i> Фільтр
                            </button>
                            <a href="<?php echo get_history_reset_filters_link(); ?>" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium text-center text-sm">
                                <i class="fas fa-times mr-1.5 text-sm"></i> Скинути
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Active Filters Display -->
                <?php if ($filter_month || $filter_day || $filter_post_id || $filter_user_id || $search_query): ?>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="text-sm font-medium text-gray-600">Активні фільтри:</span>
                        <?php if ($filter_month): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                <?php echo $months_ukrainian_filter[$filter_month]; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($filter_day): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                <?php echo $filter_day; ?> число
                            </span>
                        <?php endif; ?>
                        <?php if ($filter_post_id && isset($posts_for_filter)): ?>
                            <?php foreach ($posts_for_filter as $post): ?>
                                <?php if ($post['id'] == $filter_post_id): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        <?php echo escape($post['name']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($filter_user_id && isset($lifeguards_for_filter)): ?>
                            <?php foreach ($lifeguards_for_filter as $lifeguard): ?>
                                <?php if ($lifeguard['id'] == $filter_user_id): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-teal-100 text-teal-800">
                                        <?php echo escape($lifeguard['full_name']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($search_query): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                "<?php echo escape($search_query); ?>"
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Results Section -->
        <?php if (empty($shifts_history) && !$db_error): ?>
            <div class="text-center py-16 px-4">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full mb-4">
                    <i class="fas fa-folder-open text-3xl text-indigo-600"></i>
                </div>
                <h4 class="text-xl font-semibold text-gray-800 mb-2 font-comfortaa">Записів не знайдено</h4>
                <p class="text-gray-600">За обраними критеріями змін не знайдено. Спробуйте змінити фільтри.</p>
            </div>
        <?php elseif (!empty($shifts_history)): ?>
            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-b">
                                <a href="<?php echo get_history_sort_link('start_time', $sort_column, $sort_order, $base_history_link_params); ?>" class="flex items-center hover:text-indigo-600 transition-colors">
                                    Дата 
                                    <?php if($sort_column === 'start_time'): ?>
                                        <i class="fas fa-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-b">
                                <a href="<?php echo get_history_sort_link('lifeguard_name', $sort_column, $sort_order, $base_history_link_params); ?>" class="flex items-center hover:text-indigo-600 transition-colors">
                                    Лайфгард
                                    <?php if($sort_column === 'lifeguard_name'): ?>
                                        <i class="fas fa-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-b">
                                <a href="<?php echo get_history_sort_link('post_name', $sort_column, $sort_order, $base_history_link_params); ?>" class="flex items-center hover:text-indigo-600 transition-colors">
                                    Пост
                                    <?php if($sort_column === 'post_name'): ?>
                                        <i class="fas fa-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-b">
                                <a href="<?php echo get_history_sort_link('end_time', $sort_column, $sort_order, $base_history_link_params); ?>" class="flex items-center hover:text-indigo-600 transition-colors">
                                    Час
                                    <?php if($sort_column === 'end_time'): ?>
                                        <i class="fas fa-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider border-b">
                                <a href="<?php echo get_history_sort_link('duration_seconds', $sort_column, $sort_order, $base_history_link_params); ?>" class="flex items-center hover:text-indigo-600 transition-colors">
                                    Тривалість
                                    <?php if($sort_column === 'duration_seconds'): ?>
                                        <i class="fas fa-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider border-b">
                                <a href="<?php echo get_history_sort_link('status', $sort_column, $sort_order, $base_history_link_params); ?>" class="flex items-center justify-center hover:text-indigo-600 transition-colors">
                                    Статус
                                    <?php if($sort_column === 'status'): ?>
                                        <i class="fas fa-arrow-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider border-b">Звіт</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider border-b">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php foreach ($shifts_history as $index => $shift): ?>
                            <?php
                                $status_text = ''; $status_class = '';
                                switch ($shift['status']) {
                                    case 'completed': 
                                        $status_text = 'Завершено'; 
                                        $status_class = 'bg-green-100 text-green-800'; 
                                        break;
                                    case 'active':    
                                        $status_text = 'Активна';   
                                        $status_class = 'bg-yellow-100 text-yellow-800'; 
                                        break;
                                    case 'cancelled': 
                                        $status_text = 'Скасовано'; 
                                        $status_class = 'bg-red-100 text-red-800';   
                                        break;
                                    default:          
                                        $status_text = escape(ucfirst($shift['status'])); 
                                        $status_class = 'bg-gray-100 text-gray-800';
                                }
                            ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 border-b border-gray-200">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                    <?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 font-medium">
                                    <?php echo escape($shift['lifeguard_name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo escape($shift['post_name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 font-mono">
                                    <?php echo format_datetime($shift['start_time'], 'H:i'); ?> - <?php echo $shift['end_time'] ? format_datetime($shift['end_time'], 'H:i') : 'триває'; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 font-mono">
                                    <?php echo format_duration($shift['start_time'], $shift['end_time']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php if ($shift['status'] === 'completed' && $shift['reports_count'] > 0): ?>
                                        <i class="fas fa-check-circle text-green-500" title="Звіт подано"></i>
                                    <?php elseif ($shift['status'] === 'completed' && $shift['reports_count'] == 0): ?>
                                        <i class="fas fa-times-circle text-red-500" title="Звіт не подано"></i>
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/view_shift_details.php?shift_id=<?php echo $shift['id']; ?>"
                                       class="text-indigo-600 hover:text-indigo-800 transition-colors"
                                       title="Переглянути деталі">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards View -->
            <div class="lg:hidden space-y-4">
                <?php foreach ($shifts_history as $index => $shift): ?>
                    <?php
                        $status_text = ''; $status_class = '';
                        switch ($shift['status']) {
                            case 'completed': 
                                $status_text = 'Завершено'; 
                                $status_class = 'bg-green-100 text-green-800'; 
                                break;
                            case 'active':    
                                $status_text = 'Активна';   
                                $status_class = 'bg-yellow-100 text-yellow-800'; 
                                break;
                            case 'cancelled': 
                                $status_text = 'Скасовано'; 
                                $status_class = 'bg-red-100 text-red-800';
                                break;
                            default:          
                                $status_text = escape(ucfirst($shift['status'])); 
                                $status_class = 'bg-gray-100 text-gray-800';
                        }
                    ?>
                    <div class="shift-card bg-white rounded-lg shadow-sm border border-gray-200 p-4" data-index="<?php echo $index; ?>">
                        <!-- Card Header -->
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-semibold text-gray-800"><?php echo escape($shift['lifeguard_name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo escape($shift['post_name']); ?></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                        
                        <!-- Card Details -->
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Дата:</span>
                                <span class="font-medium"><?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Час:</span>
                                <span class="font-mono text-xs">
                                    <?php echo format_datetime($shift['start_time'], 'H:i'); ?> - <?php echo $shift['end_time'] ? format_datetime($shift['end_time'], 'H:i') : 'триває'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Тривалість:</span>
                                <span class="font-medium"><?php echo format_duration($shift['start_time'], $shift['end_time']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Звіт:</span>
                                <?php if ($shift['status'] === 'completed' && $shift['reports_count'] > 0): ?>
                                    <span class="text-green-600 font-medium">
                                        <i class="fas fa-check-circle mr-1"></i> Подано
                                    </span>
                                <?php elseif ($shift['status'] === 'completed' && $shift['reports_count'] == 0): ?>
                                    <span class="text-red-600 font-medium">
                                        <i class="fas fa-times-circle mr-1"></i> Не подано
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Card Action -->
                        <div class="mt-4 pt-3 border-t border-gray-200">
                            <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/view_shift_details.php?shift_id=<?php echo $shift['id']; ?>"
                               class="block w-full text-center bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors duration-200 text-sm font-medium">
                                <i class="fas fa-eye mr-2"></i> Переглянути деталі
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1 && !empty($shifts_history)): ?>
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm">
            <div class="text-gray-600 text-center sm:text-left">
                Показано <span class="font-semibold"><?php echo count($shifts_history); ?></span> з 
                <span class="font-semibold"><?php echo $total_records; ?></span> записів
                (Стор. <?php echo $page; ?> з <?php echo $total_pages; ?>)
            </div>
            
            <nav class="flex items-center space-x-1" aria-label="Pagination">
                <!-- Previous -->
                <a href="<?php echo $page > 1 ? get_history_page_link($page - 1, $base_history_link_params) : '#'; ?>" 
                   class="px-3 py-2 rounded border <?php echo $page <= 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'; ?>">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>
                
                <?php
                $links_limit = 5;
                $start_pg = max(1, $page - floor($links_limit / 2));
                $end_pg = min($total_pages, $start_pg + $links_limit - 1);
                if ($end_pg - $start_pg + 1 < $links_limit) $start_pg = max(1, $end_pg - $links_limit + 1);

                if ($start_pg > 1) {
                    echo '<a href="'.get_history_page_link(1, $base_history_link_params).'" class="px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">1</a>';
                    if ($start_pg > 2) echo '<span class="px-2">...</span>';
                }
                
                for ($i = $start_pg; $i <= $end_pg; $i++) {
                    echo '<a href="'.get_history_page_link($i, $base_history_link_params).'" class="px-3 py-2 rounded border '.($i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50').'">'.$i.'</a>';
                }
                
                if ($end_pg < $total_pages) {
                    if ($end_pg < $total_pages - 1) echo '<span class="px-2">...</span>';
                    echo '<a href="'.get_history_page_link($total_pages, $base_history_link_params).'" class="px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
                }
                ?>
                
                <!-- Next -->
                <a href="<?php echo $page < $total_pages ? get_history_page_link($page + 1, $base_history_link_params) : '#'; ?>" 
                   class="px-3 py-2 rounded border <?php echo $page >= $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'; ?>">
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function handleExport(format) {
    const form = document.getElementById('shiftHistoryFiltersForm');
    if (!form) return;

    const formData = new FormData(form);
    const params = new URLSearchParams();
    for (const pair of formData) {
        if (pair[1] !== '' && pair[1] !== '0') {
            params.append(pair[0], pair[1]);
        }
    }
    
    const currentUrlParams = new URLSearchParams(window.location.search);
    if (currentUrlParams.has('sort')) params.append('sort', currentUrlParams.get('sort'));
    if (currentUrlParams.has('order')) params.append('order', currentUrlParams.get('order'));

    const exportUrl = `<?php echo rtrim(APP_URL, '/'); ?>/admin/export_handler.php?format=${format}&export_target=shift_history&${params.toString()}`;
    window.open(exportUrl, '_blank');

    // Show notification
    showNotification(`Розпочато експорт в ${format.toUpperCase()}...`, 'info');
}

function showNotification(message, type = 'info') {
    // Use existing toast notification if available
    const toast = document.getElementById('toast-notification');
    if(toast){
        toast.textContent = message;
        toast.className = 'toast show';
        if (type === 'info') toast.classList.add('bg-blue-500');
        else if (type === 'success') toast.classList.add('bg-green-500');
        else if (type === 'error') toast.classList.add('bg-red-500');
        toast.classList.add('text-white');
        setTimeout(() => { toast.classList.remove('show'); }, 3000);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Form submission handler
    const filterForm = document.getElementById('shiftHistoryFiltersForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            // Form will submit normally, but we'll add the anchor after page load
        });
    }
    
    // Add anchor to URL after page load if we're on admin panel
    if (window.location.search.includes('tab_admin=shift_history') && !window.location.hash) {
        window.location.hash = 'admin-shift-history-content';
    }
    
    // Simple fade-in animation for rows if anime.js is available
    if (typeof anime === 'function') {
        const tableRows = document.querySelectorAll('.shift-row, .shift-card');
        if (tableRows.length > 0) {
            anime({
                targets: tableRows,
                opacity: [0, 1],
                translateY: [20, 0],
                delay: anime.stagger(50),
                duration: 400,
                easing: 'easeOutQuad'
            });
        }
    }
    
    // Filter form enhancement
    const monthSelect = document.getElementById('filter_month');
    const dayInput = document.getElementById('filter_day');
    if (monthSelect && dayInput) {
        monthSelect.addEventListener('change', function() {
            if (this.value === '0') {
                dayInput.value = '';
                dayInput.disabled = true;
                dayInput.placeholder = 'Спочатку оберіть місяць';
                dayInput.classList.add('opacity-50');
            } else {
                dayInput.disabled = false;
                dayInput.placeholder = 'Всі дні';
                dayInput.classList.remove('opacity-50');
            }
        });
        
        // Initialize state
        if (monthSelect.value === '0') {
            dayInput.disabled = true;
            dayInput.placeholder = 'Спочатку оберіть місяць';
            dayInput.classList.add('opacity-50');
        }
    }
});
</script>

<style>
/* Modern form inputs */
.modern-select, .modern-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    background-color: white;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    color: #374151;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.modern-select:focus, .modern-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.modern-select:hover, .modern-input:hover {
    border-color: #9ca3af;
}

.modern-select {
    appearance: none;
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236B7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.7rem center;
    background-size: 1.2em 1.2em;
}

/* Mobile cards */
@media (max-width: 1023px) {
    .shift-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: white;
        overflow: hidden;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
}

/* Custom scrollbar */
.shift-history-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.shift-history-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.shift-history-container::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 10px;
}

.shift-history-container::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}
</style>