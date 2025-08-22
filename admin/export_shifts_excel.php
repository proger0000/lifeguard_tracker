<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
global $pdo;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// --- Зчитування фільтрів з GET ---
$filter_shift_id = filter_input(INPUT_GET, 's_id', FILTER_VALIDATE_INT);
$filter_year = filter_input(INPUT_GET, 's_year', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_month = filter_input(INPUT_GET, 's_month', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 12]]);
$filter_day = filter_input(INPUT_GET, 's_day', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 31]]);
$filter_post_id = filter_input(INPUT_GET, 's_post_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_user_id = filter_input(INPUT_GET, 's_user_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$filter_status = trim($_GET['s_status'] ?? '');
$search_query = trim($_GET['s_search'] ?? '');

// --- SQL ---
$sql = "SELECT s.id, s.activity_type, s.start_time, s.end_time, s.status, s.post_id, s.user_id,
               u.full_name as lifeguard_name, p.name as post_name, s.lifeguard_assignment_type,
               TIMESTAMPDIFF(SECOND, s.start_time, s.end_time) as duration_seconds
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        JOIN posts p ON s.post_id = p.id
        WHERE 1=1";
$params = [];
if ($filter_shift_id) {
    $sql .= " AND s.id = :s_id";
    $params[':s_id'] = $filter_shift_id;
}
if ($filter_year && $filter_year != 0) {
    $sql .= " AND YEAR(s.start_time) = :s_year";
    $params[':s_year'] = $filter_year;
}
if ($filter_month && $filter_month != 0) {
    $sql .= " AND MONTH(s.start_time) = :s_month";
    $params[':s_month'] = $filter_month;
}
if ($filter_day && $filter_day != 0) {
    $sql .= " AND DAYOFMONTH(s.start_time) = :s_day";
    $params[':s_day'] = $filter_day;
}
if ($filter_post_id && $filter_post_id != 0) {
    $sql .= " AND s.post_id = :s_post_id";
    $params[':s_post_id'] = $filter_post_id;
}
if ($filter_user_id && $filter_user_id != 0) {
    $sql .= " AND s.user_id = :s_user_id";
    $params[':s_user_id'] = $filter_user_id;
}
if (!empty($filter_status)) {
    $sql .= " AND s.status = :s_status";
    $params[':s_status'] = $filter_status;
}
if (!empty($search_query)) {
    $sql .= " AND (u.full_name LIKE :s_search_name OR p.name LIKE :s_search_post OR s.id = :s_search_numeric_id)";
    $params[':s_search_name'] = '%' . $search_query . '%';
    $params[':s_search_post'] = '%' . $search_query . '%';
    $params[':s_search_numeric_id'] = (int)$search_query;
}
$sql .= " ORDER BY s.start_time DESC";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Excel ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Заголовки
$headers = [
    'A' => 'ID',
    'B' => 'Тип',
    'C' => 'Початок',
    'D' => 'Завершення',
    'E' => 'Лайфгард',
    'F' => 'Пост',
    'G' => 'Тривалість (хв)',
    'H' => 'Статус',
];
$rowNum = 1;
foreach ($headers as $col => $title) {
    $sheet->setCellValue($col . $rowNum, $title);
    $sheet->getStyle($col . $rowNum)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle($col . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
// Виділяємо перший стовпчик (ID)
$sheet->getStyle('A')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');

// Дані
$rowNum = 2;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $row['id']);
    $sheet->setCellValue('B' . $rowNum, $row['activity_type'] === 'training' ? 'Тренування' : 'Зміна');
    $sheet->setCellValue('C' . $rowNum, $row['start_time']);
    $sheet->setCellValue('D' . $rowNum, $row['end_time']);
    $sheet->setCellValue('E' . $rowNum, $row['lifeguard_name']);
    $sheet->setCellValue('F' . $rowNum, $row['post_name']);
    $sheet->setCellValue('G' . $rowNum, $row['duration_seconds'] ? round($row['duration_seconds']/60) : '');
    $sheet->setCellValue('H' . $rowNum, $row['status']);
    $rowNum++;
}

// Автоширина
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Віддаємо файл
$filename = 'lifeguard_shifts_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 