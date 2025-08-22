<?php
// /admin/dashboard.php
require_once '../config.php';
require_roles(['admin', 'duty_officer']);
global $pdo;

// --- Визначення Доступних Років ---
$available_years = [];
$current_year_for_select = date('Y');
$years_error = '';

try {
    $stmt_years = $pdo->query("SELECT DISTINCT YEAR(end_time) as report_year FROM shifts WHERE end_time IS NOT NULL AND status = 'completed' ORDER BY report_year DESC");
    $available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($current_year_for_select, $available_years)) {
        array_unshift($available_years, $current_year_for_select);
        sort($available_years);
        $available_years = array_reverse($available_years);
    }
    if (empty($available_years)) {
        $available_years = [$current_year_for_select];
    }
} catch (PDOException $e) {
    $years_error = "Помилка отримання доступних років.";
    error_log("Admin Dashboard Years Error: " . $e->getMessage());
    $available_years = [$current_year_for_select];
}

// --- ОБРОБКА ФІЛЬТРІВ ПЕРІОДУ ---
$current_date_selected = date('Y-m-d');
$selected_period_type = $_GET['period_type'] ?? 'week';
$selected_year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('Y')]]);
$selected_month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('m')]]);
$selected_day = filter_input(INPUT_GET, 'day', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('d')]]);

$date_range_start_sql = '';
$date_range_end_sql = '';
$active_period_label_display = "Сьогодні (".date('d.m.Y').")";

// Формуємо діапазон дат для SQL запиту
switch ($selected_period_type) {
    case 'day':
        if (checkdate($selected_month, $selected_day, $selected_year)) {
            $date_obj = new DateTime("$selected_year-$selected_month-$selected_day");
            $date_range_start_sql = $date_obj->format('Y-m-d 00:00:00');
            $date_range_end_sql = $date_obj->format('Y-m-d 23:59:59');
            $active_period_label_display = "за " . $date_obj->format('d.m.Y');
        } else {
            $selected_period_type = 'today';
            $date_range_start_sql = date('Y-m-d 00:00:00');
            $date_range_end_sql = date('Y-m-d 23:59:59');
            $active_period_label_display = "Сьогодні (".date('d.m.Y').")";
        }
        break;
    case 'week':
        $date_obj_week = new DateTime();
        if($selected_year != date('Y') || $selected_month != date('m') || $selected_day != date('d')){
            if (checkdate($selected_month, $selected_day, $selected_year)) {
                $date_obj_week = new DateTime("$selected_year-$selected_month-$selected_day");
            }
        }
        $date_range_start_sql = $date_obj_week->modify('monday this week')->format('Y-m-d 00:00:00');
        $date_range_end_sql = $date_obj_week->modify('sunday this week')->format('Y-m-d 23:59:59');
        $active_period_label_display = "Тиждень (" . (new DateTime($date_range_start_sql))->format('d.m') . " - " . (new DateTime($date_range_end_sql))->format('d.m.Y') . ")";
        break;
    case 'month':
        if ($selected_year && $selected_month && checkdate($selected_month, 1, $selected_year) ) {
            $date_obj_month = new DateTime("$selected_year-$selected_month-01");
            $date_range_start_sql = $date_obj_month->format('Y-m-01 00:00:00');
            $date_range_end_sql = $date_obj_month->format('Y-m-t 23:59:59');
            $active_period_label_display = "Місяць (" . $date_obj_month->format('m.Y') . ")";
        } else {
            $selected_period_type = 'today';
            $date_range_start_sql = date('Y-m-d 00:00:00');
            $date_range_end_sql = date('Y-m-d 23:59:59');
            $active_period_label_display = "Сьогодні (".date('d.m.Y').")";
        }
        break;
    case 'year':
        if ($selected_year) {
            $date_range_start_sql = "$selected_year-01-01 00:00:00";
            $date_range_end_sql = "$selected_year-12-31 23:59:59";
            $active_period_label_display = "Рік ($selected_year)";
        } else {
            $selected_period_type = 'today';
            $date_range_start_sql = date('Y-m-d 00:00:00');
            $date_range_end_sql = date('Y-m-d 23:59:59');
            $active_period_label_display = "Сьогодні (".date('d.m.Y').")";
        }
        break;
    case 'today':
    default:
        $selected_period_type = 'today';
        $date_range_start_sql = date('Y-m-d 00:00:00');
        $date_range_end_sql = date('Y-m-d 23:59:59');
        $active_period_label_display = "Сьогодні (".date('d.m.Y').")";
        break;
}

// --- Инициализация Статистики ---
$stats = [
    'total_completed_shifts' => 0,
    'total_hours_worked' => 0,
    'sum_beach_visitors_recorded' => 0,
    'sum_swimmers_recorded' => 0,
    'total_suspicious_swimmers' => 0,
    'total_visitor_inquiries' => 0,
    'total_bridge_jumpers' => 0,
    'total_alcohol_water_prevented' => 0,
    'total_alcohol_drinking_prevented' => 0,
    'total_watercraft_stopped' => 0,
    'total_preventive_actions' => 0,
    'total_educational_activities' => 0,
    'total_incidents' => 0,
    'total_rescued' => 0,
];
$stats_error = '';

// --- Получение Статистики за Выбранный Период ---
try {
    // 1. Статистика со Смен (Количество и Часы)
    $stmt_shifts = $pdo->prepare("
        SELECT
            COUNT(id) as total_completed_shifts,
            SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as total_seconds_worked
        FROM shifts
        WHERE status = 'completed'
          AND end_time IS NOT NULL
          AND start_time IS NOT NULL
          AND end_time BETWEEN :date_start AND :date_end
    ");
    $stmt_shifts->bindParam(':date_start', $date_range_start_sql);
    $stmt_shifts->bindParam(':date_end', $date_range_end_sql);
    $stmt_shifts->execute();
    $shift_results = $stmt_shifts->fetch();

    if ($shift_results) {
        $stats['total_completed_shifts'] = (int)$shift_results['total_completed_shifts'];
        $total_seconds = (int)($shift_results['total_seconds_worked'] ?? 0);
        if ($total_seconds > 0) {
            $stats['total_hours_worked'] = round($total_seconds / 3600, 1);
        }
    }

    // 2. Статистика со Отчетов (Суммы показателей)
    if ($stats['total_completed_shifts'] > 0) {
        $stmt_reports = $pdo->prepare("
            SELECT
                SUM(COALESCE(sr.people_on_beach_estimated, 0)) as sum_beach_visitors,
                SUM(COALESCE(sr.people_in_water_estimated, 0)) as sum_swimmers,
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
            WHERE s.end_time BETWEEN :date_start AND :date_end
              AND s.status = 'completed'
        ");
        $stmt_reports->bindParam(':date_start', $date_range_start_sql);
        $stmt_reports->bindParam(':date_end', $date_range_end_sql);
        $stmt_reports->execute();
        $report_results = $stmt_reports->fetch();

        if ($report_results) {
            $stats['sum_beach_visitors_recorded'] = (int)($report_results['sum_beach_visitors'] ?? 0);
            $stats['sum_swimmers_recorded'] = (int)($report_results['sum_swimmers'] ?? 0);
            $stats['total_suspicious_swimmers'] = (int)($report_results['total_suspicious_swimmers'] ?? 0);
            $stats['total_visitor_inquiries'] = (int)($report_results['total_visitor_inquiries'] ?? 0);
            $stats['total_bridge_jumpers'] = (int)($report_results['total_bridge_jumpers'] ?? 0);
            $stats['total_alcohol_water_prevented'] = (int)($report_results['total_alcohol_water_prevented'] ?? 0);
            $stats['total_alcohol_drinking_prevented'] = (int)($report_results['total_alcohol_drinking_prevented'] ?? 0);
            $stats['total_watercraft_stopped'] = (int)($report_results['total_watercraft_stopped'] ?? 0);
            $stats['total_preventive_actions'] = (int)($report_results['total_preventive_actions'] ?? 0);
            $stats['total_educational_activities'] = (int)($report_results['total_educational_activities'] ?? 0);
        }

        // 3. Общее количество инцидентов за период
        $stmt_incidents = $pdo->prepare("
            SELECT COUNT(ri.id) as total_incidents
            FROM report_incidents ri
            JOIN shift_reports sr ON ri.shift_report_id = sr.id
            JOIN shifts s ON sr.shift_id = s.id
            WHERE s.end_time BETWEEN :date_start AND :date_end
              AND s.status = 'completed'
        ");
        $stmt_incidents->bindParam(':date_start', $date_range_start_sql);
        $stmt_incidents->bindParam(':date_end', $date_range_end_sql);
        $stmt_incidents->execute();
        $incident_result = $stmt_incidents->fetch();
        if ($incident_result) {
            $stats['total_incidents'] = (int)$incident_result['total_incidents'];
        }

        // 4. Статистика по спасенным людям
        $stmt_rescued = $pdo->prepare("
            SELECT COUNT(ri.id) as total_rescued
            FROM report_incidents ri
            JOIN shift_reports sr ON ri.shift_report_id = sr.id
            JOIN shifts s ON sr.shift_id = s.id
            WHERE s.end_time BETWEEN :date_start AND :date_end
              AND s.status = 'completed'
              AND ri.incident_type = 'critical_swimmer'
        ");
        $stmt_rescued->bindParam(':date_start', $date_range_start_sql);
        $stmt_rescued->bindParam(':date_end', $date_range_end_sql);
        $stmt_rescued->execute();
        $rescued_result = $stmt_rescued->fetch();
        if ($rescued_result) {
            $stats['total_rescued'] = (int)$rescued_result['total_rescued'];
        }
    }
} catch (PDOException $e) {
    $stats_error = "Помилка отримання статистики.";
    error_log("Admin Dashboard Stats Error (Period: $active_period_label_display): " . $e->getMessage());
}

require_once '../includes/header.php';

// Группировка статистики по категориям
$stat_groups = [
    'work' => [
        'title' => 'Робоча статистика',
        'icon' => 'fa-briefcase',
        'color' => 'blue',
        'gradient' => 'from-blue-500 to-indigo-600',
        'items' => [
            'total_completed_shifts' => ['label' => 'Завершено змін', 'icon' => 'fa-calendar-check', 'color' => 'blue'],
            'total_hours_worked' => ['label' => 'Відпрацьовано годин', 'icon' => 'fa-clock', 'color' => 'indigo']
        ]
    ],
    'visitors' => [
        'title' => 'Відвідувачі',
        'icon' => 'fa-users',
        'color' => 'green',
        'gradient' => 'from-green-500 to-teal-600',
        'items' => [
            'sum_beach_visitors_recorded' => ['label' => 'На пляжі (всього)', 'icon' => 'fa-umbrella-beach', 'color' => 'green'],
            'sum_swimmers_recorded' => ['label' => 'У воді (всього)', 'icon' => 'fa-swimmer', 'color' => 'teal']
        ]
    ],
    'safety' => [
        'title' => 'Безпека та інциденти',
        'icon' => 'fa-shield-alt',
        'color' => 'red',
        'gradient' => 'from-red-500 to-orange-600',
        'items' => [
            'total_incidents' => ['label' => 'Зафіксовано інцидентів', 'icon' => 'fa-exclamation-triangle', 'color' => 'red'],
            'total_rescued' => ['label' => 'Врятованих людей', 'icon' => 'fa-life-ring', 'color' => 'rose'],
            'total_suspicious_swimmers' => ['label' => 'Підозрілих плавців', 'icon' => 'fa-user-secret', 'color' => 'orange'],
            'total_bridge_jumpers' => ['label' => 'Стрибунів з мосту', 'icon' => 'fa-archway', 'color' => 'amber']
        ]
    ],
    'prevention' => [
        'title' => 'Превентивні заходи',
        'icon' => 'fa-hand-paper',
        'color' => 'purple',
        'gradient' => 'from-purple-500 to-pink-600',
        'items' => [
            'total_alcohol_water_prevented' => ['label' => 'Недопущено у воду (алкоголь)', 'icon' => 'fa-wine-bottle', 'color' => 'purple'],
            'total_alcohol_drinking_prevented' => ['label' => 'Недопущено розпиття алкоголю', 'icon' => 'fa-ban', 'color' => 'pink'],
            'total_watercraft_stopped' => ['label' => 'Зупинено плавзасобів', 'icon' => 'fa-anchor', 'color' => 'violet'],
            'total_preventive_actions' => ['label' => 'Превентивних заходів', 'icon' => 'fa-shield-virus', 'color' => 'fuchsia']
        ]
    ],
    'interaction' => [
        'title' => 'Взаємодія та навчання',
        'icon' => 'fa-comments',
        'color' => 'yellow',
        'gradient' => 'from-yellow-500 to-amber-600',
        'items' => [
            'total_visitor_inquiries' => ['label' => 'Звернень відпочиваючих', 'icon' => 'fa-question-circle', 'color' => 'yellow'],
            'total_educational_activities' => ['label' => 'Освітньої діяльності', 'icon' => 'fa-graduation-cap', 'color' => 'amber']
        ]
    ]
];

$stats_json = json_encode($stats);
$months_ukrainian_selector = [1=>'Січень',2=>'Лютий',3=>'Березень',4=>'Квітень',5=>'Травень',6=>'Червень',7=>'Липень',8=>'Серпень',9=>'Вересень',10=>'Жовтень',11=>'Листопад',12=>'Грудень'];
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100 to-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Header Section -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 border border-gray-200/50">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-3 shadow-lg">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        Панель статистики
                    </h1>
                    <p class="text-gray-600 mt-2 flex items-center">
                        <i class="fas fa-calendar-alt mr-2 text-indigo-500"></i>
                        Дані за період: <span class="font-semibold ml-2 text-indigo-600"><?php echo escape($active_period_label_display); ?></span>
                    </p>
                </div>
                <?php if (!empty($years_error)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2">
                        <span class="text-red-600 text-sm flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo escape($years_error); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="periodForm" action="dashboard.php" method="GET" class="bg-white rounded-2xl shadow-lg p-6 mb-8 border border-gray-200/50">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-filter mr-2 text-indigo-500"></i>
                Фільтр періоду
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label for="period_type_select" class="block text-sm font-medium text-gray-700 mb-2">Тип періоду</label>
                    <select id="period_type_select" name="period_type" class="modern-select w-full" onchange="toggleDateFields()">
                        <option value="today" <?php if ($selected_period_type == 'today') echo 'selected'; ?>>Сьогодні</option>
                        <option value="day" <?php if ($selected_period_type == 'day') echo 'selected'; ?>>Конкретний день</option>
                        <option value="week" <?php if ($selected_period_type == 'week') echo 'selected'; ?>>Тиждень</option>
                        <option value="month" <?php if ($selected_period_type == 'month') echo 'selected'; ?>>Місяць</option>
                        <option value="year" <?php if ($selected_period_type == 'year') echo 'selected'; ?>>Рік</option>
                    </select>
                </div>
                
                <div id="day_field_container" class="<?php if (!in_array($selected_period_type, ['day', 'week'])) echo 'hidden'; ?>">
                    <label for="day_select" class="block text-sm font-medium text-gray-700 mb-2">День</label>
                    <input type="number" id="day_select" name="day" value="<?php echo escape($selected_day); ?>" min="1" max="31" class="modern-input w-full">
                </div>

                <div id="month_field_container" class="<?php if (!in_array($selected_period_type, ['day', 'week', 'month'])) echo 'hidden'; ?>">
                    <label for="month_select" class="block text-sm font-medium text-gray-700 mb-2">Місяць</label>
                    <select id="month_select" name="month" class="modern-select w-full">
                        <?php foreach ($months_ukrainian_selector as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php if ($num == $selected_month) echo 'selected'; ?>><?php echo escape($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="year_field_container" class="<?php if (in_array($selected_period_type, ['today'])) echo 'hidden'; ?>">
                    <label for="year_select_filter" class="block text-sm font-medium text-gray-700 mb-2">Рік</label>
                    <select id="year_select_filter" name="year" class="modern-select w-full">
                        <?php foreach ($available_years as $year_option): ?>
                            <option value="<?php echo $year_option; ?>" <?php if ($year_option == $selected_year) echo 'selected'; ?>><?php echo $year_option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2.5 rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
                        <i class="fas fa-search mr-2"></i> Показати
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($stats_error)): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                <p class="text-red-600 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo escape($stats_error); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Statistics Groups -->
        <?php if ($stats['total_completed_shifts'] > 0 || empty($stats_error)): ?>
            <div class="space-y-6">
                <?php foreach ($stat_groups as $group_key => $group): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden group-card" data-group="<?php echo $group_key; ?>">
                        <!-- Group Header -->
                        <div class="bg-gradient-to-r <?php echo $group['gradient']; ?> p-4 text-white">
                            <h3 class="text-xl font-bold flex items-center">
                                <i class="fas <?php echo $group['icon']; ?> mr-3 text-2xl opacity-80"></i>
                                <?php echo $group['title']; ?>
                            </h3>
                        </div>
                        
                        <!-- Group Items -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-6">
                            <?php foreach ($group['items'] as $stat_key => $stat_info): ?>
                                <?php
                                    $value = $stats[$stat_key] ?? 0;
                                    $color_class = $stat_info['color'];
                                    $bg_gradient = '';
                                    switch($color_class) {
                                        case 'blue': $bg_gradient = 'from-blue-50 to-blue-100'; break;
                                        case 'indigo': $bg_gradient = 'from-indigo-50 to-indigo-100'; break;
                                        case 'green': $bg_gradient = 'from-green-50 to-green-100'; break;
                                        case 'teal': $bg_gradient = 'from-teal-50 to-teal-100'; break;
                                        case 'red': $bg_gradient = 'from-red-50 to-red-100'; break;
                                        case 'orange': $bg_gradient = 'from-orange-50 to-orange-100'; break;
                                        case 'amber': $bg_gradient = 'from-amber-50 to-amber-100'; break;
                                        case 'purple': $bg_gradient = 'from-purple-50 to-purple-100'; break;
                                        case 'pink': $bg_gradient = 'from-pink-50 to-pink-100'; break;
                                        case 'violet': $bg_gradient = 'from-violet-50 to-violet-100'; break;
                                        case 'fuchsia': $bg_gradient = 'from-fuchsia-50 to-fuchsia-100'; break;
                                        case 'yellow': $bg_gradient = 'from-yellow-50 to-yellow-100'; break;
                                        default: $bg_gradient = 'from-gray-50 to-gray-100';
                                    }
                                ?>
                                <div class="stat-item bg-gradient-to-br <?php echo $bg_gradient; ?> rounded-xl p-6 border border-<?php echo $color_class; ?>-200 hover:shadow-lg transition-all duration-300 transform hover:scale-105" data-stat="<?php echo $stat_key; ?>">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <p class="text-<?php echo $color_class; ?>-600 text-sm font-medium mb-1">
                                                <?php echo escape($stat_info['label']); ?>
                                            </p>
                                            <p class="text-3xl font-bold text-<?php echo $color_class; ?>-700 stat-value" id="stat-<?php echo $stat_key; ?>">
                                                0<?php if ($stat_key === 'total_hours_worked') echo '<span class="text-lg font-normal ml-1">год</span>'; ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0 ml-4">
                                            <div class="w-14 h-14 bg-<?php echo $color_class; ?>-500 bg-opacity-20 rounded-full flex items-center justify-center">
                                                <i class="fas <?php echo $stat_info['icon']; ?> text-<?php echo $color_class; ?>-600 text-xl"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($stats['total_completed_shifts'] === 0 && empty($stats_error)): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-8 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-yellow-100 rounded-full mb-4">
                    <i class="fas fa-info-circle text-yellow-600 text-3xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-yellow-800 mb-2">Немає даних за обраний період</h3>
                <p class="text-yellow-700">За обраний період немає даних про завершені зміни або звіти.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const statsData = <?php echo $stats_json; ?>;

    // Animate statistics
    for (const key in statsData) {
        const element = document.getElementById(`stat-${key}`);
        if (element) {
            let targetValue = parseFloat(statsData[key]);
            let formatOptions = { minimumFractionDigits: 0, maximumFractionDigits: 0 };
            let suffix = '';

            if (key === 'total_hours_worked') {
                formatOptions = { minimumFractionDigits: 1, maximumFractionDigits: 1 };
                suffix = '<span class="text-lg font-normal ml-1">год</span>';
            }

            if (typeof anime === 'function') {
                anime({
                    targets: element,
                    innerHTML: [0, targetValue],
                    easing: 'easeOutExpo',
                    round: (key === 'total_hours_worked') ? 10 : 1,
                    duration: 2000,
                    delay: anime.random(100, 600),
                    update: function(anim) {
                        try {
                            const currentValue = parseFloat(element.innerHTML.replace(/<.*?>/g, '').replace(',', '.'));
                            let formattedValue = currentValue.toLocaleString('uk-UA', formatOptions);
                            element.innerHTML = formattedValue + suffix;
                        } catch(e) {}
                    },
                    complete: function(anim) {
                        try {
                            let finalFormattedValue = targetValue.toLocaleString('uk-UA', formatOptions);
                            element.innerHTML = finalFormattedValue + suffix;
                        } catch(e) {}
                    }
                });
            } else {
                element.innerHTML = targetValue.toLocaleString('uk-UA', formatOptions) + suffix;
            }
        }
    }

    // Animate group cards
    if (typeof anime === 'function') {
        anime({
            targets: '.group-card',
            opacity: [0, 1],
            translateY: [30, 0],
            delay: anime.stagger(100),
            duration: 800,
            easing: 'easeOutQuad'
        });

        anime({
            targets: '.stat-item',
            scale: [0.9, 1],
            opacity: [0, 1],
            delay: anime.stagger(50, {start: 300}),
            duration: 600,
            easing: 'easeOutBack'
        });
    }

    // Toggle date fields function
    window.toggleDateFields = function() {
        const periodType = document.getElementById('period_type_select').value;
        const dayContainer = document.getElementById('day_field_container');
        const monthContainer = document.getElementById('month_field_container');
        const yearContainer = document.getElementById('year_field_container');

        dayContainer.classList.add('hidden');
        monthContainer.classList.add('hidden');
        yearContainer.classList.add('hidden');

        if (periodType === 'day' || periodType === 'week') {
            dayContainer.classList.remove('hidden');
            monthContainer.classList.remove('hidden');
            yearContainer.classList.remove('hidden');
        } else if (periodType === 'month') {
            monthContainer.classList.remove('hidden');
            yearContainer.classList.remove('hidden');
        } else if (periodType === 'year') {
            yearContainer.classList.remove('hidden');
        }
    }

    // Initialize on load
    toggleDateFields();

    // Add hover effect to stat items
    document.querySelectorAll('.stat-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.fa');
            if (icon && typeof anime === 'function') {
                anime({
                    targets: icon,
                    rotate: 360,
                    duration: 600,
                    easing: 'easeInOutQuad'
                });
            }
        });
    });
});
</script>

<style>
/* Modern form inputs */
.modern-select, .modern-input {
    width: 100%;
    padding: 0.625rem 1rem;
    background-color: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    color: #374151;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.modern-select:focus, .modern-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.modern-select {
    appearance: none;
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236B7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1.25rem;
}

/* Stat item animations */
.stat-item {
    position: relative;
    overflow: hidden;
}

.stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.5s;
}

.stat-item:hover::before {
    left: 100%;
}

/* Group card effects */
.group-card {
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.group-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Color utilities */
.text-violet-600 { color: #7c3aed; }
.text-violet-700 { color: #6d28d9; }
.bg-violet-500 { background-color: #8b5cf6; }
.bg-violet-100 { background-color: #ede9fe; }
.border-violet-200 { border-color: #ddd6fe; }

.text-fuchsia-600 { color: #c026d3; }
.text-fuchsia-700 { color: #a21caf; }
.bg-fuchsia-500 { background-color: #d946ef; }
.bg-fuchsia-100 { background-color: #fae8ff; }
.border-fuchsia-200 { border-color: #f5d0fe; }

/* Responsive adjustments */
@media (max-width: 640px) {
    .stat-item {
        padding: 1rem;
    }
    
    .stat-value {
        font-size: 1.875rem;
    }
}

/* Loading skeleton animation */
@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

.loading-skeleton {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}
</style>

<?php require_once '../includes/footer.php'; ?>