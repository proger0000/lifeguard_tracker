<?php
// admin/export_points_detail_excel.php

// ✅ ВИПРАВЛЕННЯ: Додано вимкнення виводу помилок для коректної генерації файлу
error_reporting(0);
ini_set('display_errors', 0);

// Підключення Composer для PhpSpreadsheet
require_once dirname(__DIR__) . '/vendor/autoload.php';
// Підключення конфігурації
require_once dirname(__DIR__) . '/config.php';
global $pdo;

// Перевірка прав доступу (тільки для адміна)
require_role('admin');

// Використання класів PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

// --- Отримання та валідація параметрів ---
$detail_year = filter_input(INPUT_GET, 'detail_year', FILTER_VALIDATE_INT, ['options' => ['default' => date('Y')]]);
$detail_month = filter_input(INPUT_GET, 'detail_month', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]); // 0 - означає весь рік
$months_ukrainian_export = [1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень', 5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень', 9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'];

// --- Формування діапазону дат для SQL ---
if ($detail_month > 0 && $detail_month <= 12) {
    $date_from = sprintf('%04d-%02d-01 00:00:00', $detail_year, $detail_month);
    $date_to = date('Y-m-t 23:59:59', strtotime($date_from));
    $filename_period = ($months_ukrainian_export[$detail_month] ?? 'місяць_' . $detail_month) . "_" . $detail_year;
} else {
    $date_from = sprintf('%04d-01-01 00:00:00', $detail_year);
    $date_to = sprintf('%04d-12-31 23:59:59', $detail_year);
    $filename_period = $detail_year . "_рік";
}

// --- Збираємо дані з бази даних ---
try {
    // 1. Отримуємо всі правила, щоб знати назви колонок
    $stmt_rules = $pdo->query("SELECT id_balls, name_balls FROM points ORDER BY id_balls ASC");
    $rules = $stmt_rules->fetchAll(PDO::FETCH_KEY_PAIR); // [id => name]

    // 2. Отримуємо всіх лайфгардів та їх номери договорів
    $stmt_users = $pdo->query("SELECT id, full_name, contract_number FROM users WHERE role = 'lifeguard' ORDER BY full_name ASC");
    $users_data = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // 3. Отримуємо всі нараховані бали за період
    $sql_points = "
        SELECT user_id, rule_id, SUM(points_awarded) as total_points
        FROM lifeguard_shift_points
        WHERE award_datetime BETWEEN :date_from AND :date_to
        GROUP BY user_id, rule_id
    ";
    $stmt_points = $pdo->prepare($sql_points);
    $stmt_points->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $points_raw = $stmt_points->fetchAll(PDO::FETCH_ASSOC);

    // 4. Готуємо дані для таблиці (pivot)
    $table_data = [];
    $user_points = [];
    foreach ($points_raw as $row) {
        $user_points[$row['user_id']][$row['rule_id']] = $row['total_points'];
    }

    foreach ($users_data as $user) {
        $user_row = [
            'contract_number' => $user['contract_number'] ?? '-',
            'full_name' => $user['full_name'],
            'total' => 0
        ];
        foreach ($rules as $rule_id => $rule_name) {
            $points = $user_points[$user['id']][$rule_id] ?? 0;
            $user_row[$rule_id] = $points;
            $user_row['total'] += $points;
        }
        $table_data[] = $user_row;
    }

} catch (Exception $e) {
    die("Помилка підготовки даних: " . $e->getMessage());
}

// --- Створення Excel файлу ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 1. Заголовки
$sheet->setCellValue('A1', '№ Договору');
$sheet->setCellValue('B1', 'ПІБ');
$sheet->setCellValue('C1', 'Всього Балів');
$column_letter = 'D';
foreach ($rules as $rule_name) {
    $sheet->setCellValue($column_letter . '1', $rule_name);
    $column_letter++;
}

// Стилізація заголовків
$header_style = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4A5568']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
];
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($header_style);

// 2. Заповнення даними
$row_num = 2;
foreach ($table_data as $user_row) {
    $sheet->setCellValue('A' . $row_num, $user_row['contract_number']);
    $sheet->setCellValue('B' . $row_num, $user_row['full_name']);
    $sheet->setCellValue('C' . $row_num, $user_row['total']);
    $sheet->getStyle('C' . $row_num)->getFont()->setBold(true);

    $column_letter = 'D';
    foreach ($rules as $rule_id => $rule_name) {
        $sheet->setCellValue($column_letter . $row_num, $user_row[$rule_id]);
        $column_letter++;
    }
    $row_num++;
}

// 3. Автоматична ширина колонок
foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// 4. Відправка файлу в браузер
$filename = "Деталізація_балів_{$filename_period}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>