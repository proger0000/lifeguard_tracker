<?php
// Початок буферизації виводу для запобігання пошкодженню файлу
ob_start();

// Вимикаємо вивід помилок на випадок, якщо ob_start не спрацює
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
global $pdo;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

$detail_year = (int)($_GET['detail_year'] ?? date('Y'));
$detail_month = (int)($_GET['detail_month'] ?? 0);
$date_from = sprintf('%04d-%02d-01 00:00:00', $detail_year, $detail_month ?: 1);
$date_to = $detail_month ? date('Y-m-t 23:59:59', strtotime($date_from)) : sprintf('%04d-12-31 23:59:59', $detail_year);
$rule_ids = [1,2,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24];
$rule_names = [
    1=>'Зміна',2=>'Вчасно на зміну/зі зміни',4=>'Правильне селфі',5=>'Вчасне заповнення звіту',6=>'Тренування',7=>'Зміна на іншому посту',8=>'Один на зміні',9=>'Гарячий вихід',10=>'Додання свідків у протокол',11=>'Протокол спекотний день',12=>'Працював у погану погоду',13=>'Допомога в організації',14=>'Участь у змаганнях',15=>'Пунктуальність',16=>'5 хв запізнення',17=>'10 хв запізнення',18=>'15 хв запізнення',19=>'20 хв запізнення',20=>'25 хв запізнення',21=>'30 і більше хв запізнення',22=>'Порушення правил',23=>'Грубе порушення правил',24=>'Не вихід без поважної причини',25=>'Не вчасно заповнений звіт'];
$sql = "SELECT u.full_name, lsp.rule_id, SUM(lsp.points_awarded) as points
        FROM users u
        LEFT JOIN lifeguard_shift_points lsp ON u.id = lsp.user_id
            AND lsp.award_datetime >= :date_from AND lsp.award_datetime <= :date_to
        WHERE u.role = 'lifeguard'
        GROUP BY u.id, lsp.rule_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':date_from'=>$date_from, ':date_to'=>$date_to]);
$raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$table = [];
foreach ($raw as $row) {
    $fio = $row['full_name'];
    $rid = (int)$row['rule_id'];
    $pts = (int)$row['points'];
    if (!isset($table[$fio])) $table[$fio] = array_fill_keys($rule_ids, 0);
    if ($rid) $table[$fio][$rid] = $pts;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Заголовки
$sheet->setCellValue('A1', 'ПІБ');
$col = 'B';
foreach ($rule_ids as $rid) {
    $sheet->setCellValue($col.'1', $rule_names[$rid]);
    $col++;
}
$sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A')->getFont()->setBold(true);
$sheet->getStyle('A')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E7FF');

// Дані
$rowNum = 2;
foreach ($table as $fio => $row) {
    $sheet->setCellValue('A'.$rowNum, $fio);
    $col = 'B';
    foreach ($rule_ids as $rid) {
        $sheet->setCellValue($col.$rowNum, $row[$rid] ?? 0);
        $col++;
    }
    $rowNum++;
}
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Очищення буфера перед відправкою заголовків
ob_clean();

$filename = 'lifeguard_points_detail_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

// Завершення роботи скрипта
exit; 