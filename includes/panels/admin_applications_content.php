<?php
// Файл: includes/panels/admin_applications_content.php

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { die('Доступ заборонено.'); }
global $pdo, $APP_URL;

// --- Отримання Параметрів ---
$status_filter = $_GET['app_status'] ?? '';
$search = trim($_GET['app_search'] ?? '');
$page = isset($_GET['app_page']) ? max(1, (int)$_GET['app_page']) : 1;
$per_page_options = [15, 30, 50, 100];
$per_page = isset($_GET['app_per_page']) && in_array((int)$_GET['app_per_page'], $per_page_options) ? (int)$_GET['app_per_page'] : 15;
// --- >>> ЗМІНЕНО: Сортування за замовчуванням на registration_datetime <<< ---
$sort = $_GET['app_sort'] ?? 'registration_datetime';
$order = isset($_GET['app_order']) && strtolower($_GET['app_order']) === 'asc' ? 'ASC' : 'DESC';

// --- Валідація Сортування ---
// --- >>> ЗМІНЕНО: Додано registration_datetime, видалено submitted_at <<< ---
$allowedSortFields = ['id', 'fname', 'phone', 'email', 'birth_date', 'registration_datetime', 'status', 'manager_name', 'updated_at'];
if (!in_array($sort, $allowedSortFields)) {
    $sort = 'registration_datetime'; // Безпечне значення
}

// --- Статуси ---
$statuses = ['Новий', 'Передзвонити', 'Запрошений у басейн', 'Склав нормативи', 'Доданий до групи', 'Пройшов академію', 'Не актуально'];

// --- Змінні для даних та помилок ---
$applications = [];
$total_records = 0;
$db_error = '';
$statusCounts = ['all' => 0];

// --- Отримання Кількісті за Статусами ---
try {
    // --- >>> ЗМІНЕНО: Назва таблиці lifeguard_applications <<< ---
    $sql_counts = "SELECT status, COUNT(*) as count FROM lifeguard_applications GROUP BY status";
    $stmt_counts = $pdo->query($sql_counts);
    $results = $stmt_counts->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $status_key = $row['status'] ?? 'Новий';
        if (empty($status_key)) $status_key = 'Новий';
        if (in_array($status_key, $statuses)) {
            if (!isset($statusCounts[$status_key])) $statusCounts[$status_key] = 0;
            $statusCounts[$status_key] += (int)$row['count'];
        } else {
            if (!isset($statusCounts['Інші'])) $statusCounts['Інші'] = 0;
            $statusCounts['Інші'] += (int)$row['count'];
        }
        $statusCounts['all'] += (int)$row['count'];
    }
    foreach ($statuses as $s) {
        if (!isset($statusCounts[$s])) { $statusCounts[$s] = 0; }
    }
} catch (PDOException $e) {
    $db_error .= " Помилка підрахунку статусів.";
    error_log("App Status Count Error: " . $e->getMessage());
}


// --- Основний Запит та Пагінація ---
try {
    // --- >>> ЗМІНЕНО: Назва таблиці lifeguard_applications <<< ---
    $sql_base = "FROM lifeguard_applications WHERE 1=1";
    $params = [];

    if ($status_filter && in_array($status_filter, $statuses)) {
        if ($status_filter === 'Новий') {
            $sql_base .= " AND (status = :status OR status IS NULL OR status = '')";
        } else {
            $sql_base .= " AND status = :status";
        }
        $params[':status'] = $status_filter;
    }

    if ($search) {
        $sql_base .= " AND (CONCAT_WS(' ', fname, name, lname) LIKE :search_name OR email LIKE :search_email OR phone LIKE :search_phone)";
        $params[':search_name'] = '%' . $search . '%';
        $params[':search_email'] = '%' . $search . '%';
        $params[':search_phone'] = '%' . $search . '%';
    }

    $sql_count = "SELECT COUNT(*) " . $sql_base;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
    $total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    // --- >>> ЗМІНЕНО: Назва таблиці lifeguard_applications та список полів (history, registration_datetime) <<< ---
    $sql_data = "SELECT id, fname, name, lname, phone, email, birth_date, status, manager_note, comments_history, history, manager_id, manager_name, registration_datetime, updated_at
                 " . $sql_base . " ORDER BY `" . $sort . "` " . $order . " LIMIT :limit OFFSET :offset"; // Додав ` ` навколо $sort

    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt_data->bindValue($key, $val);
    }
    $stmt_data->execute();
    $applications = $stmt_data->fetchAll();

} catch (PDOException $e) {
    $db_error = "Помилка завантаження заявок: " . $e->getMessage();
    error_log("Admin Applications Fetch Error: " . $e->getMessage());
}

// --- Допоміжні Функції для Посилань (залишаються без змін) ---
$base_link_params = ['app_status' => $status_filter, 'app_search' => $search, 'app_sort' => $sort, 'app_order' => $order, 'app_per_page' => $per_page];
function get_app_sort_link($current_sort, $current_order, $field, $base_params) { 
    $order = ($current_sort === $field && strtolower($current_order) === 'asc') ? 'desc' : 'asc'; 
    $params = array_merge($base_params, ['app_sort' => $field, 'app_order' => $order, 'app_page' => 1, 'tab_admin' => 'applications']); 
    return '?' . http_build_query($params) . '#admin-applications-content'; 
}

function get_app_page_link($page, $base_params) { 
    $params = array_merge($base_params, ['app_page' => $page, 'tab_admin' => 'applications']); 
    return '?' . http_build_query($params) . '#admin-applications-content'; 
}

function get_app_filter_link($status, $base_params) { 
    $params = array_merge($base_params, ['app_status' => $status, 'app_page' => 1, 'tab_admin' => 'applications']); 
    if ($status === '') unset($params['app_status']); 
    return '?' . http_build_query($params) . '#admin-applications-content'; 
}

function get_app_reset_search_link($base_params) { 
    $params = array_diff_key($base_params, ['app_search' => '', 'app_page' => '']); 
    $params['app_page'] = 1; 
    $params['tab_admin'] = 'applications'; // Добавляем параметр вкладки
    return '?' . http_build_query($params) . '#admin-applications-content'; 
}

function calculate_age($birth_date_str) { if (empty($birth_date_str) || $birth_date_str === '0000-00-00') return '-'; try { $birthDate = new DateTime($birth_date_str); $today = new DateTime(); if ($birthDate > $today) return '?'; return $today->diff($birthDate)->y; } catch (Exception $e) { return '?'; } }
?>

<div class="applications-container glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-gray-200/50">
        <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0 flex items-center font-comfortaa">
            <i class="fas fa-inbox mr-3 text-indigo-500"></i>
            Заявки Кандидатів
        </h3>
        <div>
             <button id="editSelectedApplicationBtn" type="button" class="btn-secondary hidden" onclick="openApplicationEditModal()">
                 <i class="fas fa-edit mr-2"></i> Редагувати Виділене
             </button>
        </div>
    </div>

    <?php if ($db_error): ?>
         <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
            <p><strong class="font-bold">Помилка!</strong> <?php echo escape($db_error); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-gray-50/50 p-3 rounded-lg border border-gray-200/50">
        <h4 class="text-sm font-semibold mb-2 text-gray-700">Фільтр за статусом:</h4>
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo get_app_filter_link('', $base_link_params); ?>"
               class="px-3 py-1 rounded-full text-xs font-medium transition-colors duration-150 <?php echo ($status_filter === '') ? 'bg-red-500 text-white shadow-sm ring-1 ring-red-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                Всі <span class="ml-1 opacity-75">[<?php echo $statusCounts['all']; ?>]</span>
            </a>
            <?php foreach ($statuses as $status): $count = $statusCounts[$status] ?? 0; ?>
                <a href="<?php echo get_app_filter_link($status, $base_link_params); ?>"
                   class="px-3 py-1 rounded-full text-xs font-medium transition-colors duration-150 <?php echo ($status_filter === $status) ? 'bg-red-500 text-white shadow-sm ring-1 ring-red-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                    <?php echo escape($status); ?> <span class="ml-1 opacity-75">[<?php echo $count; ?>]</span>
                </a>
            <?php endforeach; ?>
            <?php if (isset($statusCounts['Інші']) && $statusCounts['Інші'] > 0): ?>
                 <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-400 text-white" title="Статуси, яких немає у стандартному списку">
                     Інші <span class="ml-1 opacity-75">[<?php echo $statusCounts['Інші']; ?>]</span>
                 </span>
             <?php endif; ?>
        </div>
    </div>

    <div class="bg-gray-50/50 p-3 rounded-lg border border-gray-200/50">
         <form method="get" action="index.php#admin-applications-content">
         <input type="hidden" name="tab_admin" value="applications">
             <?php foreach (array_diff_key($base_link_params, ['app_search' => '', 'app_page' => '']) as $key => $value) if ($value !== '') echo '<input type="hidden" name="'.$key.'" value="'.escape($value).'">'; ?>
             <input type="hidden" name="app_page" value="1">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-grow">
                    <label for="app_search_input" class="sr-only">Пошук</label>
                    <input type="text" id="app_search_input" name="app_search" value="<?php echo escape($search); ?>" placeholder="Пошук ПІБ, email, телефон..." class="std-input w-full">
                </div>
                <div class="flex gap-2 flex-shrink-0">
                    <button type="submit" class="btn-secondary !py-2 !px-4"><i class="fas fa-search mr-2"></i>Знайти</button>
                    <?php if ($search): ?>
                    <a href="<?php echo get_app_reset_search_link($base_link_params); ?>" class="btn-secondary !py-2 !px-4" title="Скинути пошук"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
                 <div class="flex items-center gap-2 flex-shrink-0 sm:ml-auto">
                     <label for="app_per_page_select" class="text-sm text-gray-700 whitespace-nowrap">На стор:</label>
                     <select name="app_per_page" id="app_per_page_select" onchange="this.form.submit()" class="std-select !py-2">
                         <?php foreach ($per_page_options as $num): ?>
                             <option value="<?php echo $num; ?>" <?php echo ($per_page == $num) ? 'selected' : ''; ?>><?php echo $num; ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto applications-list">
        <?php if (empty($applications) && !$db_error): ?>
            <div class="text-center py-10 px-4"><i class="fas fa-folder-open text-4xl text-gray-400 mb-3"></i><p class="text-gray-500 italic">За обраними критеріями заявок не знайдено.</p></div>
        <?php elseif (!empty($applications)): ?>
        <table class="min-w-full">
            <thead class="hidden md:table-header-group bg-gray-50/30">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'id', $base_link_params); ?>" class="flex items-center hover:text-red-600">ID <?php if($sort === 'id') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'fname', $base_link_params); ?>" class="flex items-center hover:text-red-600">ПІБ <?php if($sort === 'fname') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'phone', $base_link_params); ?>" class="flex items-center hover:text-red-600">Телефон <?php if($sort === 'phone') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'email', $base_link_params); ?>" class="flex items-center hover:text-red-600">Email <?php if($sort === 'email') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'birth_date', $base_link_params); ?>" class="flex items-center justify-center hover:text-red-600">Вік <?php if($sort === 'birth_date') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <?php // --- >>> ЗМІНЕНО: registration_datetime <<< --- ?>
                    <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'registration_datetime', $base_link_params); ?>" class="flex items-center justify-center hover:text-red-600">Дата Подачі <?php if($sort === 'registration_datetime') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'status', $base_link_params); ?>" class="flex items-center justify-center hover:text-red-600">Статус <?php if($sort === 'status') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Ост. коментар</th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 uppercase tracking-wider"><a href="<?php echo get_app_sort_link($sort, $order, 'manager_name', $base_link_params); ?>" class="flex items-center justify-center hover:text-red-600">Менеджер <?php if($sort === 'manager_name') echo $order === 'ASC' ? '<i class="fas fa-arrow-up ml-1 text-xs"></i>' : '<i class="fas fa-arrow-down ml-1 text-xs"></i>'; ?></a></th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Історія</th>
                    <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Дії</th>
                </tr>
            </thead>
            <tbody class="bg-transparent application-tbody">
                <?php foreach ($applications as $app): ?>
                    <?php
                        $full_name = trim(escape($app['fname'] ?? '') . ' ' . escape($app['name'] ?? '') . ' ' . escape($app['lname'] ?? ''));
                        if (empty($full_name)) $full_name = '-';
                        $age = calculate_age($app['birth_date'] ?? null);
                        $current_status = $app['status'] ?? ''; if ($current_status === '' || $current_status === null) $current_status = 'Новий';
                        $status_class = 'bg-gray-100 text-gray-800';
                        switch ($current_status) {
                            case 'Новий': $status_class = 'bg-blue-100 text-blue-800'; break; case 'Не актуально': $status_class = 'bg-red-100 text-red-800'; break; case 'Передзвонити': $status_class = 'bg-yellow-100 text-yellow-800'; break; case 'Запрошений у басейн': $status_class = 'bg-purple-100 text-purple-800'; break; case 'Склав нормативи': $status_class = 'bg-indigo-100 text-indigo-800'; break; case 'Доданий до групи': $status_class = 'bg-green-100 text-green-800'; break; case 'Пройшов академію': $status_class = 'bg-teal-100 text-teal-800'; break;
                        }
                        $short_comment = !empty($app['manager_note']) ? mb_substr(escape($app['manager_note']), 0, 35) . (mb_strlen($app['manager_note']) > 35 ? '...' : '') : '<span class="text-gray-400 italic">Додати</span>';
                        // --- >>> ЗМІНЕНО: Використовуємо registration_datetime <<< ---
                        $submitted_at_formatted = format_datetime($app['registration_datetime'] ?? null);
                        $comments_history = $app['comments_history'] ?? '';
                        // --- >>> ЗМІНЕНО: Використовуємо history для data-status-history <<< ---
                        $status_history = $app['history'] ?? '';
                    ?>
                    <tr class="application-row block md:table-row mb-4 md:mb-0 border md:border-b-0 md:border-none border-gray-200/50 rounded-lg md:rounded-none shadow-md md:shadow-none overflow-hidden md:overflow-visible bg-white/70 md:bg-transparent hover:bg-gray-50/50 md:hover:bg-gray-50/30 transition-colors duration-150 cursor-pointer"
                        data-id="<?php echo $app['id']; ?>"
                        data-status="<?php echo escape($current_status); ?>"
                        data-manager-name="<?php echo escape($app['manager_name'] ?? ''); ?>"
                        data-manager-note="<?php echo escape($app['manager_note'] ?? ''); ?>"
                        data-comments-history="<?php echo escape($comments_history); ?>"
                        data-status-history="<?php echo escape($status_history); ?>"
                        tabindex="0">

                        <td data-label="ID:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-sm text-gray-500 border-b md:border-b-0 border-gray-200/30 font-mono"><?php echo $app['id']; ?></td>
                        <td data-label="ПІБ:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-sm text-gray-800 font-medium border-b md:border-b-0 border-gray-200/30"><?php echo $full_name; ?></td>
                        <td data-label="Телефон:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-sm text-gray-600 border-b md:border-b-0 border-gray-200/30"><a href="tel:<?php echo escape($app['phone'] ?? ''); ?>" onclick="event.stopPropagation()" class="hover:underline"><?php echo escape($app['phone'] ?? '-'); ?></a></td>
                        <td data-label="Email:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-sm text-gray-600 border-b md:border-b-0 border-gray-200/30"><a href="mailto:<?php echo escape($app['email'] ?? ''); ?>" onclick="event.stopPropagation()" class="hover:underline truncate block max-w-[150px] md:max-w-full"><?php echo escape($app['email'] ?? '-'); ?></a></td>
                        <td data-label="Вік:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-center text-sm text-gray-600 border-b md:border-b-0 border-gray-200/30"><?php echo $age; ?></td>
                        <td data-label="Дата Подачі:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-center text-xs text-gray-500 border-b md:border-b-0 border-gray-200/30"><?php echo $submitted_at_formatted; ?></td>
                        <td data-label="Статус:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-center text-xs border-b md:border-b-0 border-gray-200/30"><span class="px-2 py-0.5 inline-flex leading-5 font-semibold rounded-full <?php echo $status_class; ?>"><?php echo escape($current_status); ?></span></td>
                        <td data-label="Ост. коментар:" class="px-3 py-1.5 md:py-2 text-sm text-gray-600 border-b md:border-b-0 border-gray-200/30 max-w-xs md:max-w-[150px] lg:max-w-xs truncate" title="<?php echo escape($app['manager_note'] ?? ''); ?>"><?php echo $short_comment; ?></td>
                        <td data-label="Менеджер:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-center text-xs text-gray-500 border-b md:border-b-0 border-gray-200/30"><?php echo escape($app['manager_name'] ?? '-'); ?></td>
                        <td data-label="Історія:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-center text-sm border-b md:border-b-0 border-gray-200/30">
                            <div class="flex justify-center items-center gap-1 md:gap-2">
                                <button type="button" aria-label="Історія статусів" tabindex="0" onclick="event.stopPropagation(); openApplicationHistoryModal('status', this);" class="text-blue-600 hover:text-blue-800 p-2 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none transition" style="min-width:36px; min-height:36px;">
                                    <i class="fas fa-history fa-fw"></i>
                                </button>
                                <button type="button" aria-label="Історія коментарів" tabindex="0" onclick="event.stopPropagation(); openApplicationHistoryModal('comment', this);" class="text-gray-500 hover:text-gray-700 p-2 rounded-lg focus:ring-2 focus:ring-gray-400 focus:outline-none transition" style="min-width:36px; min-height:36px;">
                                    <i class="fas fa-comment-dots fa-fw"></i>
                                </button>
                            </div>
                        </td>
                         <td data-label="Дії:" class="px-3 py-1.5 md:py-2 whitespace-nowrap text-center text-sm">
                            <div class="flex justify-end md:justify-center items-center gap-1 md:gap-2">
                                <button type="button" aria-label="Редагувати заявку" tabindex="0" onclick="event.stopPropagation(); openApplicationEditModal(this);" class="text-indigo-600 hover:text-indigo-800 p-2 rounded-lg focus:ring-2 focus:ring-indigo-400 focus:outline-none transition" style="min-width:36px; min-height:36px;">
                                    <i class="fas fa-edit fa-fw"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center text-xs sm:text-sm text-gray-600">
        <div class="mb-2 sm:mb-0">Показано <span class="font-medium"><?php echo count($applications); ?></span> з <span class="font-medium"><?php echo $total_records; ?></span> заявок (Стор. <span class="font-medium"><?php echo $page; ?></span> з <span class="font-medium"><?php echo $total_pages; ?></span>)</div>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <a href="<?php echo $page > 1 ? get_app_page_link($page - 1, $base_link_params) : '#'; ?>" class="relative inline-flex items-center px-2 py-1 rounded-l-md border border-gray-300 bg-white text-xs font-medium <?php echo $page <= 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50'; ?>">&laquo;</a>
            <?php $links_limit = 5; $start_pg = max(1, $page - floor($links_limit / 2)); $end_pg = min($total_pages, $start_pg + $links_limit - 1); if ($end_pg - $start_pg + 1 < $links_limit) $start_pg = max(1, $end_pg - $links_limit + 1);
            if ($start_pg > 1) { echo '<a href="'.get_app_page_link(1, $base_link_params).'" class="relative inline-flex items-center px-3 py-1 border border-gray-300 bg-white text-xs font-medium text-gray-500 hover:bg-gray-50">1</a>'; if ($start_pg > 2) echo '<span class="relative inline-flex items-center px-3 py-1 border border-gray-300 bg-white text-xs font-medium text-gray-700">...</span>'; }
            for ($i = $start_pg; $i <= $end_pg; $i++) echo '<a href="'.get_app_page_link($i, $base_link_params).'" class="relative inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium '.($i == $page ? 'z-10 bg-red-50 border-red-500 text-red-600' : 'bg-white text-gray-500 hover:bg-gray-50').'">'.$i.'</a>';
            if ($end_pg < $total_pages) { if ($end_pg < $total_pages - 1) echo '<span class="relative inline-flex items-center px-3 py-1 border border-gray-300 bg-white text-xs font-medium text-gray-700">...</span>'; echo '<a href="'.get_app_page_link($total_pages, $base_link_params).'" class="relative inline-flex items-center px-3 py-1 border border-gray-300 bg-white text-xs font-medium text-gray-500 hover:bg-gray-50">'.$total_pages.'</a>'; } ?>
            <a href="<?php echo $page < $total_pages ? get_app_page_link($page + 1, $base_link_params) : '#'; ?>" class="relative inline-flex items-center px-2 py-1 rounded-r-md border border-gray-300 bg-white text-xs font-medium <?php echo $page >= $total_pages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50'; ?>">&raquo;</a>
        </nav>
    </div>
    <?php endif; ?>

</div> <?php //?>


<?php // --- Модальні Вікна --- ?>
<div id="applicationEditModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4 transition-opacity duration-300" aria-labelledby="editModalTitle" role="dialog" aria-modal="true">
    <div class="bg-white p-5 sm:p-6 rounded-xl shadow-xl w-full max-w-lg transform transition-all scale-95 opacity-0 duration-300" id="applicationEditModalContent">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800 font-comfortaa" id="editModalTitle">Редагувати заявку</h2>
            <button type="button" onclick="closeApplicationEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Закрити"><i class="fas fa-times fa-lg"></i></button>
        </div>
        <form id="editApplicationForm" method="post" class="space-y-4">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="id" id="editAppId">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="editStatus">Статус *</label>
                <select name="status" id="editStatus" required class="std-select w-full">
                     <?php foreach ($statuses as $status_option): ?>
                         <option value="<?php echo escape($status_option); ?>"><?php echo escape($status_option); ?></option>
                     <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="editManagerName">Ваше ім'я (Менеджер) *</label>
                <input type="text" name="manager_name" id="editManagerName" required class="std-input w-full bg-gray-100 cursor-not-allowed" value="<?php echo escape($_SESSION['full_name'] ?? ''); ?>" readonly >
                <p class="text-xs text-red-500 mt-1 hidden" id="managerNameError">Ім'я менеджера обов'язкове!</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="editManagerNote">Новий коментар <span class="text-gray-500 text-xs">(для зміни статусу або окремо)</span></label>
                <textarea name="manager_note" id="editManagerNote" rows="3" class="std-input w-full" placeholder="Напишіть коментар..."></textarea>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-4 border-t border-gray-200">
                 <button type="button" onclick="submitCommentAdd()" name="add_comment" class="btn-secondary w-full sm:w-auto"><i class="fas fa-comment-dots mr-2"></i> Додати Коментар</button>
                 <button type="button" onclick="submitStatusUpdate()" name="update_status" class="btn-green w-full sm:w-auto"><i class="fas fa-save mr-2"></i> Зберегти Статус (+ коментар)</button>
                 <button type="button" onclick="closeApplicationEditModal()" class="btn-secondary !bg-gray-200 w-full sm:w-auto">Скасувати</button>
            </div>
        </form>
    </div>
</div>

<div id="applicationHistoryModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm hidden flex items-center justify-center z-[60] p-4 transition-opacity duration-300" aria-labelledby="historyModalTitle" role="dialog" aria-modal="true">
    <div class="bg-white p-5 sm:p-6 rounded-xl shadow-xl w-full max-w-xl transform transition-all scale-95 opacity-0 duration-300" id="applicationHistoryModalContent">
         <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
             <h2 class="text-lg font-semibold text-gray-800 font-comfortaa" id="historyModalTitle">Історія</h2>
             <button type="button" onclick="closeApplicationHistoryModal()" class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Закрити"><i class="fas fa-times fa-lg"></i></button>
         </div>
         <div class="mb-4 max-h-[60vh] overflow-y-auto pr-2">
             <div id="historyModalBody" class="space-y-2 text-sm">
                 <p class="text-gray-500 italic p-2">Завантаження історії...</p>
             </div>
         </div>
         <div class="flex items-center justify-end pt-4 border-t border-gray-200">
             <button type="button" onclick="closeApplicationHistoryModal()" class="btn-secondary">Закрити</button>
         </div>
    </div>
</div>