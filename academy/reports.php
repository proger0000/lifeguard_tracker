<?php
// /academy/reports.php
require_once '../config.php'; // Шлях до config.php
// Дозволяємо доступ адміну та аналітику
require_roles(['admin', 'analyst']); // Переконайся, що роль 'analyst' існує або зміни/прибери її
global $pdo;

$page_title = "Звіти та Статистика Академії";
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// --- Змінні для зберігання статистики ---
$candidate_status_counts = ['active' => 0, 'passed' => 0, 'failed' => 0, 'dropped_out' => 0];
$group_stats = []; // [group_id => ['name' => ..., 'attendance_percent' => ..., 'avg_score' => ..., 'standards_passed_count' => ...]]
$total_active_standards = 0;
$fetch_error = '';

try {
    // 1. Отримуємо кількість кандидатів за статусом
    $stmt_status = $pdo->query("SELECT status, COUNT(*) as count FROM academy_candidates GROUP BY status");
    $status_results = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR); // [status => count]
    // Заповнюємо $candidate_status_counts, враховуючи можливу відсутність деяких статусів
    foreach ($candidate_status_counts as $status => $count) {
        $candidate_status_counts[$status] = (int)($status_results[$status] ?? 0);
    }

    // 2. Отримуємо загальну кількість активних нормативів
    $stmt_total_std = $pdo->query("SELECT COUNT(*) FROM academy_standard_types WHERE is_active = TRUE");
    $total_active_standards = (int)$stmt_total_std->fetchColumn();

    // 3. Отримуємо статистику по кожній групі
    $stmt_groups = $pdo->query("
        SELECT
            g.id,
            g.name,
            -- Відвідуваність (розрахунок відсотка)
            COUNT(DISTINCT aa.candidate_id, aa.attendance_date) AS total_marked_entries, -- Кількість унікальних відміток (кандидат-день)
            SUM(CASE WHEN aa.status = 'present' THEN 1 ELSE 0 END) AS total_present_entries,
            -- Середній бал тестів
            AVG(at.score) AS avg_test_score,
             COUNT(at.score) AS total_scores_given, -- Кількість виставлених балів
            -- Нормативи (кількість унікальних успішно складених нормативів кандидатами групи)
            (SELECT COUNT(DISTINCT asr.standard_type_id)
             FROM academy_standard_results asr
             JOIN academy_candidates ac_inner ON asr.candidate_id = ac_inner.id
             WHERE ac_inner.group_id = g.id AND asr.passed = 1) AS distinct_standards_passed_count
        FROM
            academy_groups g
        LEFT JOIN
            academy_candidates c ON c.group_id = g.id AND c.status = 'active' -- Беремо тільки активних кандидатів для статистики?
        LEFT JOIN
            academy_attendance aa ON aa.candidate_id = c.id
        LEFT JOIN
            academy_tests at ON at.candidate_id = c.id AND at.score IS NOT NULL
        GROUP BY
            g.id, g.name
        ORDER BY
            g.name;
    ");

    $group_results = $stmt_groups->fetchAll(PDO::FETCH_ASSOC);

    foreach ($group_results as $row) {
        $marked_entries = (int)($row['total_marked_entries'] ?? 0);
        $present_entries = (int)($row['total_present_entries'] ?? 0);
        $attendance_percent = ($marked_entries > 0) ? round(($present_entries / $marked_entries) * 100) : 0;

        $group_stats[$row['id']] = [
            'name' => $row['name'],
            'attendance_percent' => $attendance_percent,
            'attendance_marked_days' => $marked_entries, // Для інформації
            'avg_score' => ($row['avg_test_score'] !== null) ? round((float)$row['avg_test_score'], 1) : null,
            'tests_count' => (int)($row['total_scores_given'] ?? 0), // Кількість тестів з оцінкою
            'standards_passed_count' => (int)($row['distinct_standards_passed_count'] ?? 0),
        ];
    }

} catch (PDOException $e) {
    $fetch_error = "Помилка завантаження даних для звітів.";
    error_log("Academy Reports Fetch Error: " . $e->getMessage());
    set_flash_message('помилка', $fetch_error);
}

// Функції форматування та допоміжні
if (!function_exists('escape')) { function escape($string) { /* ... */ return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('display_flash_message')) { function display_flash_message() { /* ... */ } }
// Масив для перекладу статусів кандидатів
$candidate_statuses_ukr = [
    'active' => 'Активні', 'passed' => 'Пройшли',
    'failed' => 'Не пройшли', 'dropped_out' => 'Вибули'
];

require_once __DIR__ . '/../includes/header.php';
?>

<section id="academy-reports-page" class="space-y-6 md:space-y-8">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
         <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-chart-pie text-purple-500 mr-3"></i>
             <?php echo escape($page_title); ?>
         </h2>
        <a href="<?php echo escape(APP_URL . '/index.php#admin-academy-content'); ?>"
            class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад до Академії
         </a>
    </div>

    <?php display_flash_message(); ?>
    <?php if ($fetch_error): ?>
         <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
             <p><strong class="font-bold">Помилка!</strong> <?php echo escape($fetch_error); ?></p>
         </div>
    <?php endif; ?>

    <div class="candidate-summary glass-effect p-5 rounded-xl shadow-lg border border-white/20">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Статус Кандидатів (Всього)</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($candidate_status_counts as $status => $count): ?>
                 <div class="stat-card !rounded-lg !shadow-md !border-gray-200/50 p-4 text-center <?php
                     // Додаємо різні кольори для статусів
                     switch ($status) {
                         case 'active': echo 'bg-blue-50/70'; break;
                         case 'passed': echo 'bg-green-50/70'; break;
                         case 'failed': echo 'bg-red-50/70'; break;
                         case 'dropped_out': echo 'bg-yellow-50/70'; break;
                         default: echo 'bg-gray-50/70'; break;
                     }
                 ?>">
                     <div class="text-3xl font-bold text-gray-700" id="stat-count-<?php echo $status; ?>"><?php echo $count; ?></div>
                     <div class="text-sm font-medium text-gray-500 mt-1"><?php echo $candidate_statuses_ukr[$status] ?? escape(ucfirst($status)); ?></div>
                 </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="group-stats glass-effect p-0 rounded-xl shadow-lg border border-white/20 overflow-x-auto">
         <h3 class="text-xl font-semibold text-gray-800 mb-0 p-5 border-b border-gray-200/50">Статистика по Групах</h3>
         <?php if (empty($group_stats) && !$fetch_error): ?>
             <p class="text-center text-gray-500 italic py-10">Немає даних по групах для відображення.</p>
         <?php elseif (!empty($group_stats)): ?>
            <table class="min-w-full">
                 <thead class="bg-gray-100/50">
                     <tr>
                         <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Група</th>
                         <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" title="Середній % присутності від всіх відмічених днів">Відвідуваність (%)</th>
                         <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" title="Середній бал за всі виставлені оцінки тестів">Середній Бал</th>
                         <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider" title="Кількість унікальних складених нормативів / Всього активних нормативів">Нормативи (Скл./Всього)</th>
                         <?php // Можна додати інші колонки ?>
                     </tr>
                 </thead>
                 <tbody class="divide-y divide-gray-200/50 bg-white/70">
                    <?php foreach($group_stats as $group_id => $data): ?>
                         <tr class="hover:bg-gray-50/50">
                             <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800">
                                <a href="view_group.php?group_id=<?php echo $group_id; ?>" class="hover:text-indigo-600 hover:underline" title="Переглянути деталі групи">
                                    <?php echo escape($data['name']); ?>
                                </a>
                             </td>
                             <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                                 <?php echo $data['attendance_percent']; ?> %
                                 <span class="text-xs text-gray-400 ml-1">(<?php echo $data['attendance_marked_days']; ?>)</span>
                             </td>
                             <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                                 <?php echo ($data['avg_score'] !== null) ? number_format($data['avg_score'], 1, '.', '') : '-'; ?>
                                  <span class="text-xs text-gray-400 ml-1">(<?php echo $data['tests_count']; ?>)</span>
                             </td>
                             <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                                 <?php echo $data['standards_passed_count']; ?> / <?php echo $total_active_standards; ?>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                 </tbody>
             </table>
         <?php endif; ?>
    </div>

    
    <div class="charts-section grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
         <div class="chart-card glass-effect p-4 rounded-xl shadow-lg border border-white/20">
             <h4 class="text-md font-semibold text-gray-700 mb-3 text-center">Порівняння Відвідуваності (%)</h4>
             <canvas id="attendanceChart"></canvas>
         </div>
         <div class="chart-card glass-effect p-4 rounded-xl shadow-lg border border-white/20">
              <h4 class="text-md font-semibold text-gray-700 mb-3 text-center">Порівняння Середнього Балу</h4>
              <canvas id="scoreChart"></canvas>
         </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
         // Тут буде JS код для ініціалізації графіків Chart.js
         // Потрібно передати дані з PHP ($group_stats) в JS
         const groupStatsData = <?php echo json_encode(array_values($group_stats), JSON_UNESCAPED_UNICODE); ?>;
         document.addEventListener('DOMContentLoaded', () => {
             // Код для Attendance Chart
             const attendanceCtx = document.getElementById('attendanceChart')?.getContext('2d');
             if (attendanceCtx && groupStatsData.length > 0) {
                 new Chart(attendanceCtx, {
                     type: 'bar', // або 'doughnut'
                     data: {
                         labels: groupStatsData.map(g => g.name),
                         datasets: [{
                             label: 'Відвідуваність %',
                             data: groupStatsData.map(g => g.attendance_percent),
                             backgroundColor: ['rgba(54, 162, 235, 0.6)', 'rgba(75, 192, 192, 0.6)', 'rgba(255, 206, 86, 0.6)', 'rgba(153, 102, 255, 0.6)'],
                             borderColor: ['rgba(54, 162, 235, 1)', 'rgba(75, 192, 192, 1)', 'rgba(255, 206, 86, 1)', 'rgba(153, 102, 255, 1)'],
                             borderWidth: 1
                         }]
                     },
                     options: { scales: { y: { beginAtZero: true, max: 100 } } }
                 });
             }
             // Код для Score Chart (Середній Бал)
              const scoreCtx = document.getElementById('scoreChart')?.getContext('2d');
              if (scoreCtx && groupStatsData.length > 0) {
                 new Chart(scoreCtx, {
                     type: 'bar', // Тип графіка (можна 'line', 'doughnut')
                     data: {
                         labels: groupStatsData.map(g => g.name), // Назви груп
                         datasets: [{
                             label: 'Середній Бал Тестів',
                             data: groupStatsData.map(g => g.avg_score ?? 0), // Середні бали, null замінюємо на 0 для графіка
                             backgroundColor: [ // Масив кольорів для стовпців
                                'rgba(255, 159, 64, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)'
                                // Додай більше кольорів, якщо груп більше 4
                             ],
                             borderColor: [ // Кольори рамок
                                'rgba(255, 159, 64, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
                             ],
                             borderWidth: 1
                         }]
                     },
                     options: {
                         indexAxis: 'y', // Горизонтальні стовпці для кращої читабельності назв груп (опційно)
                         scales: {
                             x: { // Налаштування для осі X (раніше Y, тепер X через indexAxis: 'y')
                                 beginAtZero: true,
                                 suggestedMax: 100 // Припускаємо 100-бальну систему, можна змінити
                             }
                         },
                         plugins: {
                             tooltip: {
                                 callbacks: {
                                     label: function(context) {
                                         let label = context.dataset.label || '';
                                         if (label) { label += ': '; }
                                         if (context.parsed.x !== null) {
                                             // Показуємо бал з одним знаком після коми
                                             label += context.parsed.x.toFixed(1);
                                             // Можна додати кількість тестів
                                             const groupIndex = context.dataIndex;
                                             const testsCount = groupStatsData[groupIndex]?.tests_count || 0;
                                             label += ` (тестів: ${testsCount})`;
                                         }
                                         return label;
                                     }
                                 }
                             }
                         }
                     }
                 });
              }
         });
    </script>
   

</section>

<style>
    #academy-reports-page table { border-collapse: separate; border-spacing: 0; }
    #academy-reports-page th.sticky, #academy-reports-page td.sticky { position: sticky; left: 0; z-index: 10; }
    #academy-reports-page thead th.sticky { z-index: 20; }
    #academy-reports-page td.sticky { background-color: rgba(255, 255, 255, 0.85); }
    #academy-reports-page tbody tr:hover td.sticky { background-color: rgba(249, 250, 251, 0.85); }
     .std-select { @apply shadow-sm block border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2; }
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>