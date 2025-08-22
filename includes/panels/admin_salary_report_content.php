<?php
require_once dirname(__DIR__) . '/../config.php';
require_once dirname(__DIR__) . '/helpers.php';

require_role('admin'); // Только для администраторов

global $pdo, $APP_URL;
$APP_URL = defined('APP_URL') ? APP_URL : '';

$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$lifeguard_salary_data = [];
$error_message = '';

try {
    // Получаем данные о лайфгардах, их часах и баллах за выбранный месяц
    $stmt = $pdo->prepare("
        SELECT
            u.id as user_id,
            u.full_name,
            u.contract_number,
            u.base_hourly_rate,
            COALESCE(ss.total_rounded_hours, 0) as total_rounded_hours,
            COALESCE(ss.total_shifts_count, 0) as total_shifts_count,
            COALESCE(ps.total_awarded_points, 0) as total_awarded_points
        FROM
            users u
        LEFT JOIN
            (
                SELECT
                    s.user_id,
                    SUM(s.rounded_work_hours) as total_rounded_hours,
                    COUNT(s.id) as total_shifts_count
                FROM
                    shifts s
                WHERE
                    s.status = 'completed' AND s.end_time IS NOT NULL AND s.end_time >= :start_of_month_shifts AND s.end_time < :start_of_next_month_shifts
                GROUP BY
                    s.user_id
            ) as ss ON u.id = ss.user_id
        LEFT JOIN
            (
                SELECT
                    lsp.user_id,
                    SUM(lsp.points_awarded) as total_awarded_points
                FROM
                    lifeguard_shift_points lsp
                WHERE
                    lsp.award_datetime IS NOT NULL AND lsp.award_datetime >= :start_of_month_points AND lsp.award_datetime < :start_of_next_month_points
                GROUP BY
                    lsp.user_id
            ) as ps ON u.id = ps.user_id
        WHERE
            u.role = 'lifeguard'
        GROUP BY
            u.id, u.full_name, u.contract_number, u.base_hourly_rate
        HAVING
            total_rounded_hours > 0
        ORDER BY
            u.full_name ASC
    ");

    $month_padded = str_pad($current_month, 2, '0', STR_PAD_LEFT);
    
    // Calculate start and end dates for the month
    $start_of_month = date('Y-m-01', strtotime("$current_year-$current_month-01"));
    $start_of_next_month = date('Y-m-01', strtotime("$current_year-$current_month-01 +1 month"));

    $stmt->execute([
        ':start_of_month_shifts' => $start_of_month,
        ':start_of_next_month_shifts' => $start_of_next_month,
        ':start_of_month_points' => $start_of_month,
        ':start_of_next_month_points' => $start_of_next_month
    ]);
    $lifeguard_salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error fetching salary report data: " . $e->getMessage());
    $error_message = 'Помилка бази даних: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General error fetching salary report data: " . $e->getMessage());
    $error_message = 'Внутрішня помилка сервера при завантаженні звіту із зарплатами.';
}

$months_ukrainian = [
    1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
    5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
    9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'
];

// Генерация списка годов (например, от 2020 до текущего + 1)
$year_options = range(2020, date('Y') + 1);

?>

<div class="salary-report-container glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-gray-200/50">
        <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0 flex items-center font-comfortaa"><i class="fas fa-money-bill-wave mr-3 text-green-600"></i>Звіт із Зарплатами</h3>
        <button type="button" id="exportSalaryData" class="btn-indigo inline-flex items-center self-start sm:self-center transform hover:scale-105 transition-transform">
            <i class="fas fa-file-excel mr-2"></i> Експорт в Excel
        </button>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4 bg-gray-50/50 p-3 rounded-md shadow-sm">
        <input type="hidden" name="tab" value="salary_report"> <!-- Для сохранения активной вкладки -->
        <div>
            <label for="month-select" class="block text-sm font-medium text-gray-700">Місяць:</label>
            <select name="month" id="month-select" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <?php foreach ($months_ukrainian as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo ($current_month == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="year-select" class="block text-sm font-medium text-gray-700">Рік:</label>
            <select name="year" id="year-select" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <?php foreach ($year_options as $year_val): ?>
                    <option value="<?php echo $year_val; ?>" <?php echo ($current_year == $year_val) ? 'selected' : ''; ?>><?php echo $year_val; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary py-2 px-4 inline-flex items-center">
                <i class="fas fa-filter mr-2"></i> Застосувати
            </button>
        </div>
    </form>

    <?php if ($error_message): ?>
        <p class="text-red-500 text-center"><?php echo escape($error_message); ?></p>
    <?php elseif (empty($lifeguard_salary_data)): ?>
        <div class="text-center py-10 px-4">
            <i class="fas fa-info-circle text-4xl text-gray-400 mb-3"></i>
            <p class="text-gray-500 italic">Немає даних про зарплату за вибраний період.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">№ Договору</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ПІБ Рятувальника</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Години</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Зміни</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Бали</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ставка</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Брутто ЗП</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Нетто ЗП</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($lifeguard_salary_data as $data): ?>
                        <?php 
                            $total_hours = (int)$data['total_rounded_hours'];
                            $base_rate = (float)$data['base_hourly_rate'];
                            $salary = calculate_salary($total_hours, $base_rate);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo escape($data['contract_number'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo escape($data['full_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $total_hours; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo (int)$data['total_shifts_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo (int)$data['total_awarded_points']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo format_money($base_rate); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold"><?php echo format_money($salary['gross']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold text-green-700"><?php echo format_money($salary['net']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('exportSalaryData').addEventListener('click', function() {
        const month = document.getElementById('month-select').value;
        const year = document.getElementById('year-select').value;
        const url = `<?php echo $APP_URL; ?>/admin/export_handler.php?export_target=salary&month=${month}&year=${year}&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>`;
        window.location.href = url;
    });
</script> 