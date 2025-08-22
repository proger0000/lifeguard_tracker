<?php
// /admin/export_handler.php
require_once '../config.php'; // Підключення конфігурації
require_once dirname(__DIR__) . '/includes/helpers.php'; // Підключення допоміжних функцій, зокрема calculate_salary

// Підключення PhpSpreadsheet (якщо встановлено через Composer)
// Переконайся, що ROOT_PATH визначено в config.php і вказує на корінь твого ПРОЄКТУ
// (тобто на папку lifeguard-tracker)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} else {
    // Якщо PhpSpreadsheet не встановлено, експорт в Excel не спрацює
    if (($_GET['format'] ?? '') === 'excel') {
        header('Content-Type: text/plain; charset=utf-8');
        // Використовуємо escape() для безпечного виведення шляху
        $error_path = function_exists('escape') ? escape(ROOT_PATH . '/vendor/autoload.php') : htmlspecialchars(ROOT_PATH . '/vendor/autoload.php');
        die("ПОМИЛКА: Бібліотека PhpSpreadsheet не знайдена за шляхом: " . $error_path . ". Будь ласка, встановіть її через Composer: composer require phpoffice/phpspreadsheet");
    }
    // Для PDF також потрібна своя бібліотека, тут можна додати аналогічну перевірку
}

// Використання класів PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Перевірка ролі - ОБОВ'ЯЗКОВО розкоментуй для продакшену!
/*
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    if (function_exists('set_flash_message') && function_exists('smart_redirect')) {
        set_flash_message('помилка', 'Доступ заборонено. Потрібні права адміністратора.');
        smart_redirect('index.php'); // Або на сторінку логіну
    } else {
        die("Доступ заборонено. Потрібні права адміністратора.");
    }
    exit();
}
*/
global $pdo; // Робимо PDO доступним

// Отримання параметрів запиту
$format = strtolower(trim($_GET['format'] ?? 'excel'));
$export_target = trim($_GET['export_target'] ?? '');

// Перевірка на валідність формату та цілі експорту
if (($format !== 'excel' && $format !== 'pdf') || empty($export_target)) {
    // Використовуємо escape для безпечного виведення, якщо функції доступні
    $error_format = function_exists('escape') ? escape($format) : htmlspecialchars($format);
    $error_target = function_exists('escape') ? escape($export_target) : htmlspecialchars($export_target);
    die("Непідтримуваний формат ('{$error_format}') або не вказана ціль експорту ('{$error_target}').");
}

// Визначення, чи існує функція format_datetime (має бути з functions.php)
$func_format_datetime_exists = function_exists('format_datetime');

// --- ЛОГІКА ДЛЯ ЕКСПОРТУ ЗАРПЛАТ ---
if ($export_target === 'salary') {
    $current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $month_padded = str_pad($current_month, 2, '0', STR_PAD_LEFT);

    // Calculate start and end dates for the month
    $start_of_month = date('Y-m-01', strtotime("$current_year-$current_month-01"));
    $start_of_next_month = date('Y-m-01', strtotime("$current_year-$current_month-01 +1 month"));

    $lifeguard_salary_data = [];
    try {
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
            ORDER BY
                u.full_name ASC
        ");

        $stmt->execute([
            ':start_of_month_shifts' => $start_of_month,
            ':start_of_next_month_shifts' => $start_of_next_month,
            ':start_of_month_points' => $start_of_month,
            ':start_of_next_month_points' => $start_of_next_month
        ]);
        $lifeguard_salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Export Salary Report DB Error: " . $e->getMessage());
        die("Помилка бази даних при підготовці даних для експорту зарплат.");
    }

    if (empty($lifeguard_salary_data)) {
        echo "<script>alert('Немає даних про зарплату за обраний період для експорту.'); window.close();</script>";
        exit;
    }

    if ($format === 'excel') {
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
             die("ПОМИЛКА: Клас Spreadsheet не знайдено. Перевірте підключення PhpSpreadsheet.");
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Звіт по Зарплатам');

        $months_ukrainian = [
            1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
            5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
            9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'
        ];
        $month_name = $months_ukrainian[$current_month] ?? '';

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'Звіт по Зарплатам Лайфгардів');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('A2', "Період: {$month_name} {$current_year} рік");
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $header_row_num = 4;
        $headers_excel = [
            '№ Договору', 'ПІБ Рятувальника', 'Години', 'Зміни', 'Бали', 'Ставка (грн/год)', 'Брутто ЗП', 'Нетто ЗП'
        ];
        $sheet->fromArray($headers_excel, NULL, 'A'.$header_row_num);

        // Стилизация заголовков
        $header_style = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];
        $sheet->getStyle('A'.$header_row_num.':H'.$header_row_num)->applyFromArray($header_style);
        $sheet->getRowDimension($header_row_num)->setRowHeight(30);

        $row_num = $header_row_num + 1;
        foreach ($lifeguard_salary_data as $data) {
            $total_hours = (int)$data['total_rounded_hours'];
            $base_rate = (float)$data['base_hourly_rate'];
            $salary = calculate_salary($total_hours, $base_rate, 0.23); // Используем новую ставку налога
            
            $row_data = [
                $data['contract_number'] ?? '-',
                $data['full_name'],
                $total_hours,
                (int)$data['total_shifts_count'],
                (int)$data['total_awarded_points'],
                round($base_rate, 2),
                round($salary['gross'], 2),
                round($salary['net'], 2)
            ];
            $sheet->fromArray($row_data, NULL, 'A'.$row_num);

            // Стилизация строк
            $sheet->getStyle('A'.$row_num.':H'.$row_num)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $row_num++;
        }

        // Авторазмер столбцов
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Подготовка к скачиванию файла
        $filename = "Звіт_по_Зарплатам_{$month_name}_{$current_year}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'. $filename .'"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// --- ЛОГІКА ДЛЯ ЕКСПОРТУ АНАЛІТИКИ ПОСТІВ ---
if ($export_target === 'posts_analytics') {
    $current_date_analytics_export = date('Y-m-d');
    $selected_period_type_export = trim($_GET['period_type'] ?? 'today');
    $selected_custom_date_start_export = !empty(trim($_GET['custom_date_start'] ?? '')) ? trim($_GET['custom_date_start']) : null;
    $selected_custom_date_end_export = !empty(trim($_GET['custom_date_end'] ?? '')) ? trim($_GET['custom_date_end']) : null;

    $date_range_start_export = '';
    $date_range_end_export = '';
    $period_label_export = '';

    switch ($selected_period_type_export) {
        case 'week':
            $date_range_start_export = date('Y-m-d', strtotime('monday this week', strtotime($current_date_analytics_export)));
            $date_range_end_export = date('Y-m-d', strtotime('sunday this week', strtotime($current_date_analytics_export)));
            $period_label_export = "Тиждень (" . ($func_format_datetime_exists ? format_datetime($date_range_start_export, 'd.m.Y') : $date_range_start_export) . " - " . ($func_format_datetime_exists ? format_datetime($date_range_end_export, 'd.m.Y') : $date_range_end_export) . ")";
            break;
        case 'month':
            $date_range_start_export = date('Y-m-01', strtotime($current_date_analytics_export));
            $date_range_end_export = date('Y-m-t', strtotime($current_date_analytics_export));
            $period_label_export = "Місяць (" . ($func_format_datetime_exists ? format_datetime($date_range_start_export, 'F Y') : $date_range_start_export) . ")";
            break;
        case 'year':
            $current_year_num_export = date('Y', strtotime($current_date_analytics_export));
            $date_range_start_export = $current_year_num_export . '-01-01';
            $date_range_end_export = $current_year_num_export . '-12-31';
            $period_label_export = "Рік (" . $current_year_num_export . ")";
            break;
        case 'custom':
            if ($selected_custom_date_start_export && $selected_custom_date_end_export) {
                try {
                    $start_dt_custom_export = new DateTime($selected_custom_date_start_export);
                    $end_dt_custom_export = new DateTime($selected_custom_date_end_export);
                    if ($start_dt_custom_export <= $end_dt_custom_export) {
                        $date_range_start_export = $start_dt_custom_export->format('Y-m-d');
                        $date_range_end_export = $end_dt_custom_export->format('Y-m-d');
                        $period_label_export = "Період з " . ($func_format_datetime_exists ? format_datetime($date_range_start_export, 'd.m.Y') : $date_range_start_export) . " по " . ($func_format_datetime_exists ? format_datetime($date_range_end_export, 'd.m.Y') : $date_range_end_export);
                    } else { $selected_period_type_export = 'today'; /* Fallback */ }
                } catch (Exception $e) { $selected_period_type_export = 'today'; /* Fallback */ }
            } else { $selected_period_type_export = 'today'; /* Fallback */ }
            
            if ($selected_period_type_export === 'today' && empty($date_range_start_export)) {
                 $date_range_start_export = $current_date_analytics_export;
                 $date_range_end_export = $current_date_analytics_export;
                 $period_label_export = "Сьогодні (" . ($func_format_datetime_exists ? format_datetime($current_date_analytics_export, 'd.m.Y') : $current_date_analytics_export) . ")";
            }
            break;
        case 'today':
        default:
            $selected_period_type_export = 'today';
            $date_range_start_export = $current_date_analytics_export;
            $date_range_end_export = $current_date_analytics_export;
            $period_label_export = "Сьогодні (" . ($func_format_datetime_exists ? format_datetime($current_date_analytics_export, 'd.m.Y') : $current_date_analytics_export) . ")";
            break;
    }

    $date_range_end_for_sql_export = $date_range_end_export . ' 23:59:59';
    $date_range_start_for_sql_export = $date_range_start_export . ' 00:00:00';

    $posts_analytics_data_export = [];
    try {
        $stmt_all_posts_export = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
        $all_posts_list_export = $stmt_all_posts_export->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_posts_list_export as $post_export) {
            $post_id_export = $post_export['id'];
            $current_post_data = [
                'id' => $post_id_export,
                'name' => $post_export['name'],
                'total_on_beach' => 0, 'total_in_water' => 0,
                'max_on_beach_single_day' => 0, 'max_in_water_single_day' => 0,
                'reports_count' => 0, 'avg_on_beach' => 0, 'avg_in_water' => 0,
            ];

            $stmt_stats_export = $pdo->prepare("
                SELECT
                    SUM(COALESCE(sr.people_on_beach_estimated, 0)) as sum_beach,
                    SUM(COALESCE(sr.people_in_water_estimated, 0)) as sum_water,
                    MAX(sr.people_on_beach_estimated) as max_beach_day,
                    MAX(sr.people_in_water_estimated) as max_water_day,
                    COUNT(sr.id) as count_reports
                FROM shift_reports sr
                JOIN shifts s ON sr.shift_id = s.id
                WHERE s.post_id = :post_id
                  AND s.status = 'completed'
                  AND s.end_time BETWEEN :date_start AND :date_end
            ");
            $stmt_stats_export->bindParam(':post_id', $post_id_export, PDO::PARAM_INT);
            $stmt_stats_export->bindParam(':date_start', $date_range_start_for_sql_export);
            $stmt_stats_export->bindParam(':date_end', $date_range_end_for_sql_export);
            $stmt_stats_export->execute();
            $stats_row_export = $stmt_stats_export->fetch(PDO::FETCH_ASSOC);

            if ($stats_row_export) {
                $current_post_data['total_on_beach'] = (int)($stats_row_export['sum_beach'] ?? 0);
                $current_post_data['total_in_water'] = (int)($stats_row_export['sum_water'] ?? 0);
                $current_post_data['max_on_beach_single_day'] = (int)($stats_row_export['max_beach_day'] ?? 0);
                $current_post_data['max_in_water_single_day'] = (int)($stats_row_export['max_water_day'] ?? 0);
                $current_post_data['reports_count'] = (int)($stats_row_export['count_reports'] ?? 0);
                if ($current_post_data['reports_count'] > 0) {
                    $current_post_data['avg_on_beach'] = round($current_post_data['total_on_beach'] / $current_post_data['reports_count'], 1);
                    $current_post_data['avg_in_water'] = round($current_post_data['total_in_water'] / $current_post_data['reports_count'], 1);
                }
            }
            $posts_analytics_data_export[] = $current_post_data;
        }
    } catch (PDOException $e) {
        error_log("Export Posts Analytics DB Error: " . $e->getMessage());
        die("Помилка бази даних при підготовці даних для експорту аналітики постів.");
    }

    if (empty($posts_analytics_data_export) && $format === 'excel') { // Додав перевірку формату
        echo "<script>alert('Немає даних для експорту аналітики постів за обраними фільтрами.'); window.close();</script>";
        exit;
    }

    if ($format === 'excel') {
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
             die("ПОМИЛКА: Клас Spreadsheet не знайдено. Перевірте підключення PhpSpreadsheet.");
        }
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Аналітика Відвідуваності Постів');

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'Аналітика Відвідуваності Постів');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('A2', 'Період: ' . $period_label_export);
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $header_row_num = 4;
        $headers_excel = [
            'Назва Посту', 'Звітів за період',
            'Всього на пляжі', 'Всього у воді',
            'Ø на пляжі / звіт', 'Ø у воді / звіт',
            'Max на пляжі / звіт', 'Max у воді / звіт'
        ];
        $sheet->fromArray($headers_excel, NULL, 'A'.$header_row_num);
        $header_style_array_excel = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A5568']]
        ];
        $sheet->getStyle('A'.$header_row_num.':H'.$header_row_num)->applyFromArray($header_style_array_excel);
        $sheet->getRowDimension($header_row_num)->setRowHeight(35);

        $row_num_excel = $header_row_num + 1;
        foreach ($posts_analytics_data_export as $data_item) {
            $sheet->setCellValue('A'.$row_num_excel, $data_item['name']);
            $sheet->setCellValue('B'.$row_num_excel, $data_item['reports_count']);
            $sheet->setCellValue('C'.$row_num_excel, $data_item['total_on_beach']);
            $sheet->setCellValue('D'.$row_num_excel, $data_item['total_in_water']);
            $sheet->setCellValue('E'.$row_num_excel, $data_item['avg_on_beach']);
            $sheet->setCellValue('F'.$row_num_excel, $data_item['avg_in_water']);
            $sheet->setCellValue('G'.$row_num_excel, $data_item['max_on_beach_single_day']);
            $sheet->setCellValue('H'.$row_num_excel, $data_item['max_in_water_single_day']);
            $row_num_excel++;
        }

        foreach (range('A', 'H') as $columnID_excel) {
            $sheet->getColumnDimension($columnID_excel)->setAutoSize(true);
        }
        $data_range_excel = 'A'.($header_row_num+1).':H'.($row_num_excel-1);
        if ($row_num_excel > ($header_row_num + 1)) {
            $sheet->getStyle($data_range_excel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B'.($header_row_num+1).':H'.($row_num_excel-1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($data_range_excel)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFDDDDDD'));
        }
        
        $filename_excel = 'posts_analytics_'. str_replace([' ', ':', '(', ')', ',', '.'], '_', preg_replace('/[^a-z0-9_-]/i', '_', $period_label_export)) . '_' . date('YmdHis').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename_excel.'"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        if (ob_get_level() > 0) ob_end_clean(); // Перевіряємо, чи є активний буфер
        $writer->save('php://output');
        exit;

    } elseif ($format === 'pdf') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Експорт Аналітики Постів в PDF для періоду '{$period_label_export}'.\n";
        echo "Дані для експорту:\n";
        print_r($posts_analytics_data_export); // Виводимо дані, щоб переконатися, що вони є
        echo "\n\n(Повноцінна реалізація PDF потребує бібліотеки TCPDF або FPDF та детального кодування таблиці)";
        exit;
    }

} elseif ($export_target === 'shift_history') {
    // ВИПРАВЛЕНО: Отримання параметрів фільтрів для історії змін
    $filter_year_sh = trim($_GET['filter_year'] ?? date('Y')); // Використовуємо значення за замовчуванням, якщо параметр не передано
    $filter_month_sh = trim($_GET['filter_month'] ?? '0');    // 0 для "всі місяці"
    $filter_day_sh = trim($_GET['filter_day'] ?? '0');        // 0 для "всі дні"
    $filter_post_id_sh = trim($_GET['filter_post_id'] ?? '0'); // 0 для "всі пости"
    $filter_user_id_sh = trim($_GET['filter_user_id'] ?? '0'); // 0 для "всі лайфгарди"
    $search_query_sh = trim($_GET['search_query'] ?? '');     // Пошуковий запит

    $sort_column_sh = trim($_GET['sort'] ?? 'start_time');
    $sort_order_sh = (isset($_GET['order']) && strtolower(trim($_GET['order'])) == 'asc') ? 'ASC' : 'DESC';
    $allowed_sort_columns_sh = ['start_time', 'end_time', 'lifeguard_name', 'post_name', 'status', 'duration_seconds'];
    if (!in_array($sort_column_sh, $allowed_sort_columns_sh)) $sort_column_sh = 'start_time';

    $sql_base_sh = "FROM shifts s
                    JOIN users u ON s.user_id = u.id
                    JOIN posts p ON s.post_id = p.id
                    WHERE 1=1";
    $params_sh = [];

    if ($filter_year_sh && $filter_year_sh !== '0') { $sql_base_sh .= " AND YEAR(s.start_time) = :year_sh"; $params_sh[':year_sh'] = $filter_year_sh; }
    if ($filter_month_sh && $filter_month_sh !== '0') { $sql_base_sh .= " AND MONTH(s.start_time) = :month_sh"; $params_sh[':month_sh'] = $filter_month_sh; }
    if ($filter_day_sh && $filter_day_sh !== '0') { $sql_base_sh .= " AND DAYOFMONTH(s.start_time) = :day_sh"; $params_sh[':day_sh'] = $filter_day_sh; }
    if ($filter_post_id_sh && $filter_post_id_sh !== '0') { $sql_base_sh .= " AND s.post_id = :post_id_sh"; $params_sh[':post_id_sh'] = $filter_post_id_sh; }
    if ($filter_user_id_sh && $filter_user_id_sh !== '0') { $sql_base_sh .= " AND s.user_id = :user_id_sh"; $params_sh[':user_id_sh'] = $filter_user_id_sh; }
    if (!empty($search_query_sh)) { $sql_base_sh .= " AND (u.full_name LIKE :search_name_sh OR p.name LIKE :search_post_sh)"; $params_sh[':search_name_sh'] = '%' . $search_query_sh . '%'; $params_sh[':search_post_sh'] = '%' . $search_query_sh . '%';}
    
    $shifts_history_export_data = [];
    try {
        $sql_data_sh = "SELECT s.id, s.start_time, s.end_time, s.status,
                            u.full_name as lifeguard_name,
                            p.name as post_name,
                            TIMESTAMPDIFF(SECOND, s.start_time, s.end_time) as duration_seconds,
                            (SELECT COUNT(sr.id) FROM shift_reports sr WHERE sr.shift_id = s.id) as reports_count
                     " . $sql_base_sh . " ORDER BY {$sort_column_sh} {$sort_order_sh}";
        
        $stmt_export_sh = $pdo->prepare($sql_data_sh);
        foreach ($params_sh as $key_sh => &$val_sh) { // Передача $val_sh за посиланням для bindValue
            $stmt_export_sh->bindValue($key_sh, $val_sh);
        }
        unset($val_sh); // Розриваємо посилання
        $stmt_export_sh->execute();
        $shifts_history_export_data = $stmt_export_sh->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Export Shift History DB Error: " . $e->getMessage() . " SQL: " . $sql_data_sh . " Params: " . print_r($params_sh, true));
        die("Помилка бази даних при підготовці даних для експорту історії змін.");
    }
    
    if (empty($shifts_history_export_data) && $format === 'excel') {
        echo "<script>alert('Немає даних для експорту історії змін за обраними фільтрами.'); window.close();</script>";
        exit;
    }

    if ($format === 'excel') {
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
             die("ПОМИЛКА: Клас Spreadsheet не знайдено. Перевірте підключення PhpSpreadsheet.");
        }
        $spreadsheet_sh = new Spreadsheet();
        $sheet_sh = $spreadsheet_sh->getActiveSheet();
        $sheet_sh->setTitle('Історія Змін');
        
        $sheet_sh->setCellValue('A1', 'Історія Змін');
        $sheet_sh->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        // Тут можна додати інформацію про застосовані фільтри, якщо потрібно

        $header_row_sh_num = 3; // Починаємо заголовки з 3-го рядка
        $headers_sh_excel = ['ID Зміни', 'Дата', 'Лайфгард', 'Пост', 'Час Початку', 'Час Кінця', 'Тривалість', 'Статус', 'Звіт Подано'];
        $sheet_sh->fromArray($headers_sh_excel, NULL, 'A'.$header_row_sh_num);
        $header_style_sh_excel_arr = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A5568']]
        ];
        $sheet_sh->getStyle('A'.$header_row_sh_num.':I'.$header_row_sh_num)->applyFromArray($header_style_sh_excel_arr);
        $sheet_sh->getRowDimension($header_row_sh_num)->setRowHeight(30);

        $row_num_sh_excel_data = $header_row_sh_num + 1;
        foreach ($shifts_history_export_data as $shift_export_item) {
            $sheet_sh->setCellValue('A'.$row_num_sh_excel_data, $shift_export_item['id']);
            $sheet_sh->setCellValue('B'.$row_num_sh_excel_data, $func_format_datetime_exists ? format_datetime($shift_export_item['start_time'], 'd.m.Y') : $shift_export_item['start_time']);
            $sheet_sh->setCellValue('C'.$row_num_sh_excel_data, $shift_export_item['lifeguard_name']);
            $sheet_sh->setCellValue('D'.$row_num_sh_excel_data, $shift_export_item['post_name']);
            $sheet_sh->setCellValue('E'.$row_num_sh_excel_data, $func_format_datetime_exists ? format_datetime($shift_export_item['start_time'], 'H:i:s') : $shift_export_item['start_time']);
            $sheet_sh->setCellValue('F'.$row_num_sh_excel_data, $shift_export_item['end_time'] ? ($func_format_datetime_exists ? format_datetime($shift_export_item['end_time'], 'H:i:s') : $shift_export_item['end_time']) : '-');
            
            $duration_formatted_sh = '-';
            if (isset($shift_export_item['duration_seconds']) && $shift_export_item['duration_seconds'] !== null && $shift_export_item['duration_seconds'] >= 0) {
                 $duration_formatted_sh = function_exists('format_duration_from_seconds') ? format_duration_from_seconds($shift_export_item['duration_seconds']) : ($shift_export_item['duration_seconds'] . ' сек');
            } elseif ($shift_export_item['start_time'] && $shift_export_item['status'] === 'active') {
                 $duration_formatted_sh = function_exists('format_duration') ? format_duration($shift_export_item['start_time'], date('Y-m-d H:i:s')) : 'активна';
            }
            $sheet_sh->setCellValue('G'.$row_num_sh_excel_data, $duration_formatted_sh);
            
            $status_translated_sh = $shift_export_item['status']; // Можна додати масив перекладів статусів
            if(function_exists('get_shift_status_ukrainian')) $status_translated_sh = get_shift_status_ukrainian($shift_export_item['status']);
            $sheet_sh->setCellValue('H'.$row_num_sh_excel_data, $status_translated_sh);
            $sheet_sh->setCellValue('I'.$row_num_sh_excel_data, ($shift_export_item['reports_count'] > 0) ? 'Так' : 'Ні');
            $row_num_sh_excel_data++;
        }
        foreach (range('A', 'I') as $columnID_sh_excel_item) { $sheet_sh->getColumnDimension($columnID_sh_excel_item)->setAutoSize(true); }
        
        $data_range_sh_excel_content = 'A'.($header_row_sh_num+1).':I'.($row_num_sh_excel_data-1);
        if ($row_num_sh_excel_data > ($header_row_sh_num + 1)) {
            $sheet_sh->getStyle($data_range_sh_excel_content)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFDDDDDD'));
        }

        $filename_sh_excel_export = 'shift_history_export_'.date('YmdHis').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filename_sh_excel_export.'"');
        header('Cache-Control: max-age=0');
        $writer_sh_export = new Xlsx($spreadsheet_sh);
        if (ob_get_level() > 0) ob_end_clean();
        $writer_sh_export->save('php://output');
        exit;

    } elseif ($format === 'pdf') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Експорт Історії Змін в PDF.\nДані для експорту:\n";
        print_r($shifts_history_export_data); // Виводимо дані для перевірки
        echo "\n\n(Повноцінна реалізація PDF потребує бібліотеки TCPDF/FPDF та детального кодування таблиці)";
        exit;
    } else {
        die("Непідтримуваний формат експорту для історії змін.");
    }

} else {
    die("Невідома ціль експорту: " . (function_exists('escape') ? escape($export_target) : htmlspecialchars($export_target)));
}
?>