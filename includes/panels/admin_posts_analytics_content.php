<?php
// /includes/panels/admin_posts_analytics_content.php

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'director'])) {
    set_flash_message('помилка', 'У вас недостатньо прав для доступу до цієї сторінки.');
    smart_redirect('index.php');
    exit();
}

global $pdo;

require_once __DIR__ . '/../models/posts_analytics.php';

$page_title_analytics = "Комплексна Аналітика Постів";

// --- БЛОК ФІЛЬТРАЦІЇ ПЕРІОДУ ---
$current_date_analytics = date('Y-m-d');
$selected_period_type = $_GET['period_type'] ?? 'month';
$selected_custom_date_start = $_GET['custom_date_start'] ?? null;
$selected_custom_date_end = $_GET['custom_date_end'] ?? null;

$date_range_start = '';
$date_range_end = '';
$active_period_label = '';

switch ($selected_period_type) {
    case 'today':
        $date_range_start = $current_date_analytics;
        $date_range_end = $current_date_analytics;
        $active_period_label = "Сьогодні (" . format_datetime($current_date_analytics, 'd.m.Y') . ")";
        break;
    case 'week':
        $date_range_start = date('Y-m-d', strtotime('monday this week', strtotime($current_date_analytics)));
        $date_range_end = date('Y-m-d', strtotime('sunday this week', strtotime($current_date_analytics)));
        $active_period_label = "Поточний тиждень (" . format_datetime($date_range_start, 'd.m') . " - " . format_datetime($date_range_end, 'd.m') . ")";
        break;
    case 'month':
        $date_range_start = date('Y-m-01', strtotime($current_date_analytics));
        $date_range_end = date('Y-m-t', strtotime($current_date_analytics));
        $active_period_label = "Поточний місяць (" . format_datetime($date_range_start, 'F Y') . ")";
        break;
    case 'year':
        $current_year_num = date('Y', strtotime($current_date_analytics));
        $date_range_start = $current_year_num . '-01-01';
        $date_range_end = $current_year_num . '-12-31';
        $active_period_label = "Поточний рік (" . $current_year_num . ")";
        break;
    case 'custom':
        if ($selected_custom_date_start && $selected_custom_date_end) {
            $date_range_start = $selected_custom_date_start;
            $date_range_end = $selected_custom_date_end;
            $active_period_label = "Період: " . format_datetime($date_range_start, 'd.m.Y') . " - " . format_datetime($date_range_end, 'd.m.Y');
        } else {
            $selected_period_type = 'month';
            $date_range_start = date('Y-m-01');
            $date_range_end = date('Y-m-t');
            $active_period_label = "Поточний місяць";
        }
        break;
    default:
        $selected_period_type = 'month';
        $date_range_start = date('Y-m-01');
        $date_range_end = date('Y-m-t');
        $active_period_label = "Поточний місяць";
}

$date_range_end_for_sql = $date_range_end . ' 23:59:59';
$date_range_start_for_sql = $date_range_start . ' 00:00:00';

$analytics = get_posts_analytics($pdo, $date_range_start_for_sql, $date_range_end_for_sql);
if (!empty($analytics['error'])) {
    set_flash_message('помилка', 'Помилка завантаження аналітичних даних: ' . $analytics['error']);
    error_log('Analytics Fetch Error: ' . $analytics['error']);
}

extract($analytics);
?>

<div class="space-y-8" id="analytics-container">
    <!-- Header & Filters -->
    <div class="glass-effect p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-chart-pie mr-3 text-indigo-300"></i><?php echo escape($page_title_analytics); ?>
                </h2>
                <p class="text-sm text-gray-300 mt-1">
                    Обраний період: <span class="font-semibold text-gray-100"><?php echo escape($active_period_label); ?></span>
                </p>
            </div>
            <form action="#analytics-container" method="get" class="flex flex-wrap items-center gap-2" id="analyticsPeriodForm">
                <input type="hidden" name="tab_admin" value="posts-analytics">
                <select name="period_type" id="period_type_selector" class="form-select text-sm rounded-lg border-gray-300 shadow-sm">
                    <option value="today" <?php echo ($selected_period_type === 'today') ? 'selected' : ''; ?>>Сьогодні</option>
                    <option value="week" <?php echo ($selected_period_type === 'week') ? 'selected' : ''; ?>>Цей тиждень</option>
                    <option value="month" <?php echo ($selected_period_type === 'month') ? 'selected' : ''; ?>>Цей місяць</option>
                    <option value="year" <?php echo ($selected_period_type === 'year') ? 'selected' : ''; ?>>Цей рік</option>
                    <option value="custom" <?php echo ($selected_period_type === 'custom') ? 'selected' : ''; ?>>Свій період</option>
                </select>
                <div id="custom_date_fields_container" class="<?php echo ($selected_period_type !== 'custom') ? 'hidden' : ''; ?> flex items-center gap-2">
                    <input type="date" name="custom_date_start" value="<?php echo escape($selected_custom_date_start ?? ''); ?>" class="form-input text-sm rounded-lg">
                    <input type="date" name="custom_date_end" value="<?php echo escape($selected_custom_date_end ?? ''); ?>" class="form-input text-sm rounded-lg">
                </div>
                <button type="submit" class="btn btn-sm btn-gradient-warning"><i class="fas fa-filter mr-1"></i> Показати</button>
            </form>
        </div>
    </div>

    <!-- KPI Cards Section -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
        <div class="kpi-card"><i class="fas fa-clipboard-check text-indigo-500"></i><div><span>Всього змін</span><strong><?php echo number_format($total_stats['total_shifts'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-users text-yellow-500"></i><div><span>На пляжі</span><strong><?php echo number_format($total_stats['total_on_beach'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-swimmer text-blue-500"></i><div><span>У воді</span><strong><?php echo number_format($total_stats['total_in_water'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-first-aid text-red-600"></i><div><span>Інцидентів</span><strong><?php echo number_format($total_stats['total_incidents'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-ambulance text-red-500"></i><div><span>Виклики швидкої</span><strong><?php echo number_format($total_stats['ambulance_calls'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-user-shield text-yellow-500"></i><div><span>Виклики поліції</span><strong><?php echo number_format($total_stats['police_calls'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-child text-yellow-600"></i><div><span>Загублені діти</span><strong><?php echo number_format($total_stats['lost_child'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-user-md text-green-500"></i><div><span>Мед. допомога</span><strong><?php echo number_format($total_stats['medical_aid'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-exclamation-triangle text-indigo-400"></i><div><span>Крит. плавець</span><strong><?php echo number_format($total_stats['critical_swimmer'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-hands-helping text-green-500"></i><div><span>Профілактика</span><strong><?php echo number_format($total_stats['preventive_actions_count'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-bullhorn text-yellow-500"></i><div><span>Освітня робота</span><strong><?php echo number_format($total_stats['educational_activities_count'] ?? 0); ?></strong></div></div>
        <div class="kpi-card"><i class="fas fa-water text-indigo-500"></i><div><span>Стрибки з мосту</span><strong><?php echo number_format($total_stats['bridge_jumpers_count'] ?? 0); ?></strong></div></div>
    </div>
    
       <!-- Детальна аналітика нижче -->
    <div class="animated-entry">
        <h3 class="section-title"><i class="fas fa-chart-area"></i>Детальний аналіз</h3>
        
        <?php
            // Функція для форматування рядка деталізації
            function format_details_row($details) {
                return "[{$details['male_count']} ч - {$details['female_count']} ж - {$details['child_count']} д]";
            }
            
            // Розраховуємо загальну кількість критичних ситуацій
            $critical_situations_total = 
                ($critical_details_map['medical_aid']['total_count'] ?? 0) + 
                ($critical_details_map['police_call']['total_count'] ?? 0) + 
                ($critical_details_map['ambulance_call']['total_count'] ?? 0) + 
                ($critical_details_map['critical_swimmer']['total_count'] ?? 0);
        ?>
        
        <!-- Сітка на 2 основні колонки для детального аналізу -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Блок 1: Критичні плавці -->
            <div class="glass-effect p-6">
                <h4 class="chart-title text-gray-700">Глибокий аналіз: Критичні плавці</h4>
                 <div class="text-2xl font-bold text-red-600 my-3">
                    Всього: <?php echo (int)($critical_swimmer_details['total_count'] ?? 0); ?>
                </div>
                <?php if (($critical_swimmer_details['total_count'] ?? 0) > 0): ?>
                <!-- Змінено на вертикальний список -->
                <div class="space-y-4 mt-4">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center text-gray-700"><i class="fas fa-male text-blue-500 text-lg mr-3"></i>Чоловіки</span>
                        <strong class="text-xl font-bold"><?php echo (int)($critical_swimmer_details['male_count'] ?? 0); ?></strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center text-gray-700"><i class="fas fa-female text-pink-500 text-lg mr-3"></i>Жінки</span>
                        <strong class="text-xl font-bold"><?php echo (int)($critical_swimmer_details['female_count'] ?? 0); ?></strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center text-gray-700"><i class="fas fa-child text-yellow-500 text-lg mr-3"></i>Діти (до 14 р.)</span>
                        <strong class="text-xl font-bold"><?php echo (int)($critical_swimmer_details['child_count'] ?? 0); ?></strong>
                    </div>
                </div>

                <div class="mt-6 space-y-3 text-gray-700">
                    <div>Середній вік: <strong><?php echo $critical_swimmer_avg_age !== null ? number_format($critical_swimmer_avg_age, 1) : '—'; ?></strong></div>

                    <?php if (!empty($critical_swimmer_top_posts)): ?>
                    <div>
                        <p class="font-semibold">Топ постів:</p>
                        <ul class="list-disc list-inside">
                            <?php foreach ($critical_swimmer_top_posts as $post): ?>
                                <li><?php echo escape($post['post_name']); ?> (<?php echo (int)$post['total_incidents']; ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($critical_swimmer_top_causes)): ?>
                    <div>
                        <p class="font-semibold">Топ причин:</p>
                        <ul class="list-disc list-inside">
                            <?php foreach ($critical_swimmer_top_causes as $causeKey => $data): ?>
                                <li><?php echo escape(translate_cause_detail_uk($causeKey)); ?> (<?php echo (int)$data['count']; ?>, <?php echo $data['percentage']; ?>%)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="chart-placeholder h-32"><i class="fas fa-check-circle text-green-500"></i><p>Не зафіксовано</p></div>
                <?php endif; ?>
            </div>
            
            <!-- Блок 2: Критичні ситуації з деталізацією -->
            <div class="glass-effect p-6">
                <h4 class="chart-title text-gray-700">Глибокий аналіз: Критичні ситуації</h4>
                <div class="text-2xl font-bold text-orange-600 my-3">
                    Всього: <?php echo (int)($critical_situations_total); ?>
                </div>
                <div class="space-y-3 mt-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center text-gray-700"><i class="fas fa-user-md text-green-500 mr-3"></i>Мед. допомога</span>
                            <strong class="text-xl font-bold"><?php echo (int)($critical_details_map['medical_aid']['total_count'] ?? 0); ?></strong>
                        </div>
                        <p class="text-xs text-gray-500 ml-8"><?php echo format_details_row($critical_details_map['medical_aid'] ?? ['male_count'=>0, 'female_count'=>0, 'child_count'=>0]); ?></p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center text-gray-700"><i class="fas fa-exclamation-triangle text-indigo-400 mr-3"></i>Крит. плавець</span>
                            <strong class="text-xl font-bold"><?php echo (int)($critical_details_map['critical_swimmer']['total_count'] ?? 0); ?></strong>
                        </div>
                        <p class="text-xs text-gray-500 ml-8"><?php echo format_details_row($critical_details_map['critical_swimmer'] ?? ['male_count'=>0, 'female_count'=>0, 'child_count'=>0]); ?></p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center text-gray-700"><i class="fas fa-user-shield text-yellow-500 mr-3"></i>Виклики поліції</span>
                            <strong class="text-xl font-bold"><?php echo (int)($critical_details_map['police_call']['total_count'] ?? 0); ?></strong>
                        </div>
                         <p class="text-xs text-gray-500 ml-8"><?php echo format_details_row($critical_details_map['police_call'] ?? ['male_count'=>0, 'female_count'=>0, 'child_count'=>0]); ?></p>
                    </div>
                     <div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center text-gray-700"><i class="fas fa-ambulance text-red-500 mr-3"></i>Виклики швидкої</span>
                            <strong class="text-xl font-bold"><?php echo (int)($critical_details_map['ambulance_call']['total_count'] ?? 0); ?></strong>
                        </div>
                         <p class="text-xs text-gray-500 ml-8"><?php echo format_details_row($critical_details_map['ambulance_call'] ?? ['male_count'=>0, 'female_count'=>0, 'child_count'=>0]); ?></p>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Блок 3: Інциденти за категоріями (ПЕРЕМІЩЕНО) -->
        <div class="glass-effect p-6 mt-6">
             <h4 class="chart-title">Інциденти за категоріями</h4>
            <?php if (!empty($incidents_by_category_data)): ?>
                <div class="h-80"><canvas id="incidentsByCategoryChart"></canvas></div>
            <?php else: ?>
                <div class="chart-placeholder h-80"><i class="fas fa-chart-pie"></i><p>Немає даних</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="animated-entry">
        <h3 class="section-title"><i class="fas fa-map-marker-alt"></i>Аналіз Постів</h3>
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 bg-white rounded-xl shadow-md p-6 border border-gray-200/50">
                <h4 class="chart-title">Навантаження на пости (відвідувачі)</h4>
                 <?php if (!empty(array_filter(array_merge($visitors_chart['beach_data'], $visitors_chart['water_data'])))): ?>
                    <div class="h-96"><canvas id="visitorsPerPostChart"></canvas></div>
                <?php else: ?>
                    <div class="chart-placeholder h-96"><i class="fas fa-chart-bar"></i><p>Немає даних про відвідувачів</p></div>
                <?php endif; ?>
            </div>
            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6 border border-gray-200/50">
                <h4 class="chart-title">Рейтинг постів за "Індексом Небезпеки"</h4>
                <div class="overflow-y-auto mt-4 max-h-96">
                    <table class="data-table">
                        <thead><tr><th>Пост</th><th>Інциденти</th><th>Індекс</th></tr></thead>
                        <tbody>
                            <?php if (!empty($post_danger_rating)): ?>
                                <?php $has_danger_data = false; ?>
                                <?php foreach ($post_danger_rating as $post): ?>
                                    <?php if ($post['danger_index'] > 0 || $post['total_incidents'] > 0): $has_danger_data = true; ?>
                                    <tr class="<?php echo $post['danger_index'] > 0.5 ? 'bg-red-50' : ($post['danger_index'] > 0.2 ? 'bg-yellow-50' : ''); ?>">
                                        <td><?php echo escape($post['name']); ?></td>
                                        <td><?php echo (int)$post['total_incidents']; ?></td>
                                        <td><?php echo number_format($post['danger_index'], 2); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if(!$has_danger_data): ?>
                                     <tr><td colspan="3">Немає даних для розрахунку</td></tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr><td colspan="3">Немає даних для розрахунку</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <p class="text-xs text-gray-400 mt-2">Індекс = (Інциденти / Години) * Коеф. складності</p>
            </div>
        </div>
    </div>

    <div class="animated-entry">
        <h3 class="section-title"><i class="fas fa-user-friends"></i>Аналіз Рятувальників</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="glass-effect p-6">
                <h4 class="chart-title">Топ-10 за відпрацьованими годинами</h4>
                <div class="overflow-x-auto mt-4 max-h-80">
                    <table class="data-table">
                        <thead><tr><th>Рятувальник</th><th>Годин</th></tr></thead>
                        <tbody>
                            <?php if (!empty($lifeguard_performance_hours)): ?>
                                <?php foreach ($lifeguard_performance_hours as $lifeguard): ?>
                                    <tr>
                                        <td><?php echo escape($lifeguard['full_name']); ?></td>
                                        <td><?php echo (int)$lifeguard['total_hours']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2">Немає даних</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="glass-effect p-6">
                <h4 class="chart-title">Топ-10 за опрацьованими інцидентами</h4>
                <div class="overflow-x-auto mt-4 max-h-80">
                     <table class="data-table">
                        <thead><tr><th>Рятувальник</th><th>Інцидентів</th></tr></thead>
                        <tbody>
                            <?php if (!empty($lifeguard_performance_incidents)): ?>
                                <?php foreach ($lifeguard_performance_incidents as $lifeguard): ?>
                                    <tr>
                                        <td><?php echo escape($lifeguard['full_name']); ?></td>
                                        <td><?php echo (int)$lifeguard['total_incidents']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2">Немає даних</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
@keyframes slideInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.animated-entry { animation: slideInUp 0.5s ease-out forwards; opacity: 0; }
.animated-entry:nth-child(2) { animation-delay: 0.1s; }
.animated-entry:nth-child(3) { animation-delay: 0.2s; }
.animated-entry:nth-child(4) { animation-delay: 0.3s; }
.kpi-card {
    color: #000; padding: 1rem 1.25rem; border-radius: 0.75rem;
    display: flex; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 8px 15px -4px rgba(0,0,0,.2); }
.kpi-card i { font-size: 1.75rem; margin-right: 1rem; opacity: 0.9; }
.kpi-card div span { font-size: 0.875rem; line-height: 1.25rem; display: block; font-weight: 500; }
.kpi-card div strong { font-size: 1.75rem; line-height: 2.25rem; font-weight: 700; }
.section-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-top: 2rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; }
.section-title i { margin-right: 0.75rem; color: #a5b4fc; }
.chart-title { font-size: 1.1rem; font-weight: 600; color: #fff; margin-bottom: 1rem; }
.chart-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 10rem; color: #d1d5db; background: rgba(255,255,255,0.05); border-radius: 0.5rem; }
.chart-placeholder i { font-size: 2.5rem; margin-bottom: 0.75rem; color: #9ca3af; }
.data-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.9); border-radius: 0.5rem; overflow: hidden; }
.data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; white-space: nowrap; color: #000; }
.data-table thead th { background-color: #f3f4f6; color: #374151; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; }
.data-table tbody tr:hover { background-color: #f0f0f0; }
.data-table td:last-child { font-weight: 600; text-align: right; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const periodSelector = document.getElementById('period_type_selector');
    const customDateContainer = document.getElementById('custom_date_fields_container');
    if (periodSelector) { periodSelector.addEventListener('change', () => customDateContainer.classList.toggle('hidden', periodSelector.value !== 'custom')); }

    const chartColors = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#10b981', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899'];
    Chart.defaults.font.family = 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    // Інциденти за категоріями (кругова діаграма)
    const incidentsCtx = document.getElementById('incidentsByCategoryChart');
    if (incidentsCtx) {
        const incidentsData = <?php echo json_encode($incidents_chart); ?>;
        if (incidentsData.labels.length > 0) {
            new Chart(incidentsCtx, {
                type: 'doughnut',
                data: {
                    labels: incidentsData.labels,
                    datasets: [{ data: incidentsData.data, backgroundColor: chartColors, borderColor: '#fff', borderWidth: 2, hoverOffset: 8 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: { animateScale: true, duration: 1200 },
                    plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 12, padding: 15 } } },
                    cutout: '65%'
                }
            });
        }
    }

    // Навантаження на пости (стовпчикова діаграма)
    const visitorsCtx = document.getElementById('visitorsPerPostChart');
    if (visitorsCtx) {
        const visitorsData = <?php echo json_encode($visitors_chart); ?>;
        if(visitorsData.labels.length > 0) {
            new Chart(visitorsCtx, {
                type: 'bar',
                data: {
                    labels: visitorsData.labels,
                    datasets: [
                        { label: 'У воді', data: visitorsData.water_data, backgroundColor: 'rgba(56, 189, 248, 0.8)' },
                        { label: 'На пляжі', data: visitorsData.beach_data, backgroundColor: 'rgba(96, 165, 250, 0.7)' }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: { duration: 1000 },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'rect' } } }
                }
            });
        }
    }
});
</script>