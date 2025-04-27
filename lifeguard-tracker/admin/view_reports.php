<?php
require_once '../config.php';
require_roles(['admin', 'duty_officer']);
global $pdo;

$report_dates = [];
$selected_date = null;
$reports_on_date = [];
$incidents_by_report = [];
$error_message = '';

try {
    $stmt_dates = $pdo->query("SELECT DISTINCT DATE(report_submitted_at) as report_date FROM shift_reports ORDER BY report_date DESC");
    $report_dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN);

    if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
        $selected_date = $_GET['date'];
        if (!in_array($selected_date, $report_dates)) {
             $selected_date = !empty($report_dates) ? $report_dates[0] : null;
        }
    } elseif (!empty($report_dates)) {
        $selected_date = $report_dates[0];
    }

    if ($selected_date) {
        $date_start = $selected_date . ' 00:00:00';
        $date_end = $selected_date . ' 23:59:59';

        $stmt_reports = $pdo->prepare("
            SELECT
                sr.id as report_id, sr.report_submitted_at, sr.shift_id,
                sr.suspicious_swimmers_count, sr.visitor_inquiries_count, sr.bridge_jumpers_count,
                sr.alcohol_water_prevented_count, sr.alcohol_drinking_prevented_count,
                sr.watercraft_stopped_count, sr.preventive_actions_count, sr.educational_activities_count,
                sr.people_on_beach_estimated, sr.people_in_water_estimated, sr.general_notes,
                s.start_time, s.end_time,
                u.full_name as reporter_name,
                p.name as post_name
            FROM shift_reports sr
            JOIN shifts s ON sr.shift_id = s.id
            JOIN users u ON sr.reporter_user_id = u.id
            JOIN posts p ON s.post_id = p.id
            WHERE sr.report_submitted_at BETWEEN :date_start AND :date_end
            ORDER BY sr.report_submitted_at DESC
        ");
        $stmt_reports->bindParam(':date_start', $date_start);
        $stmt_reports->bindParam(':date_end', $date_end);
        $stmt_reports->execute();
        $reports_on_date = $stmt_reports->fetchAll();

        if (!empty($reports_on_date)) {
            $report_ids_on_date = array_column($reports_on_date, 'report_id');
             $placeholders = implode(',', array_fill(0, count($report_ids_on_date), '?'));

            $stmt_incidents = $pdo->prepare("
                SELECT
                    ri.*,
                    u_involved.full_name as involved_lifeguard_name
                FROM report_incidents ri
                LEFT JOIN users u_involved ON ri.involved_lifeguard_id = u_involved.id
                WHERE ri.shift_report_id IN ($placeholders)
                ORDER BY ri.id ASC
            ");
            $stmt_incidents->execute($report_ids_on_date);
            $all_incidents = $stmt_incidents->fetchAll();

            foreach ($all_incidents as $incident) {
                $incidents_by_report[$incident['shift_report_id']][] = $incident;
            }
        }
    }

} catch (PDOException $e) {
    $error_message = "Помилка БД: " . $e->getMessage(); // Show error for debugging
}

require_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Перегляд Звітів за Зміни</h2>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
            <p><?php echo escape($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="mb-8">
        <label class="block text-lg font-semibold text-gray-700 mb-3">Доступні звіти за дати:</label>
        <?php if (!empty($report_dates)): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($report_dates as $date): ?>
                    <a href="view_reports.php?date=<?php echo $date; ?>"
                       class="<?php echo ($date == $selected_date) ? 'btn-red' : 'btn-secondary'; ?> text-sm !py-1 !px-3">
                        <?php echo date("d.m.Y", strtotime($date)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">Звіти ще не надходили.</p>
        <?php endif; ?>
    </div>

     <?php if ($selected_date): ?>
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Звіти за <?php echo date("d.m.Y", strtotime($selected_date)); ?></h3>
         <div class="bg-white rounded-lg shadow-md p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white md:table md:border-collapse">
                    <thead class="hidden md:table-header-group">
                        <tr class="bg-gray-100 md:table-row">
                             <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Час Звіту</th>
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Пост</th>
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Лайфгард</th>
                             <th class="py-3 px-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Інциденти</th>
                             <th class="py-3 px-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Дії</th>
                         </tr>
                    </thead>
                     <tbody class="md:table-row-group">
                         <?php if (empty($reports_on_date)): ?>
                             <tr class="md:table-row"><td colspan="5" class="text-center py-6 px-4 text-gray-500 md:table-cell md:border-t md:border-gray-200">За обрану дату звітів не знайдено.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reports_on_date as $report):
                                $incident_count = isset($incidents_by_report[$report['report_id']]) ? count($incidents_by_report[$report['report_id']]) : 0;
                             ?>
                                <tr class="block border rounded-lg bg-white shadow p-4 mb-4 md:table-row md:border-none md:shadow-none md:p-0 md:mb-0">
                                    <td data-label="Час Звіту:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r text-sm text-gray-700">
                                        <?php echo date("H:i:s", strtotime($report['report_submitted_at'])); ?>
                                    </td>
                                     <td data-label="Пост:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r font-semibold text-gray-800 md:font-normal">
                                        <?php echo escape($report['post_name']); ?>
                                    </td>
                                     <td data-label="Лайфгард:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r text-sm text-gray-700">
                                        <?php echo escape($report['reporter_name']); ?>
                                    </td>
                                    <td data-label="Інцидентів:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-center md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r text-sm font-medium <?php echo $incident_count > 0 ? 'text-red-600' : 'text-gray-500'; ?>">
                                        <?php echo $incident_count; ?>
                                     </td>
                                    <td data-label="Дії:" class="block relative pt-8 pb-2 px-4 md:table-cell md:text-center md:p-0 md:py-2 md:px-4 md:border-t md:border-gray-200 md:border-b-0">
                                         <div class="flex items-center justify-end md:justify-center">
                                             <button type="button"
                                                    class="btn-secondary !py-1 !px-3 text-xs"
                                                    onclick="openReportModal(<?php echo $report['report_id']; ?>)">
                                                 <i class="fas fa-eye mr-1"></i> Переглянути
                                             </button>
                                         </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                         <?php endif; ?>
                     </tbody>
                 </table>
             </div>
         </div>
    <?php endif; ?>

</div>

<div id="report-modal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div id="modal-overlay" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full">
             <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                 <div class="sm:flex sm:items-start">
                     <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-file-alt text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-bold text-gray-900 mb-2" id="modal-title">
                             Деталі Звіту №<span id="modal-report-id"></span>
                        </h3>
                         <hr class="mb-4">
                         <div id="modal-body" class="mt-2 text-sm text-gray-600 space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                            <div id="modal-general-info" class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1"></div>
                             <hr>
                            <h4 class="font-semibold text-gray-700">Статистика:</h4>
                            <div id="modal-stats" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-2 text-xs"></div>
                             <hr>
                             <div id="modal-general-notes">
                                  <h4 class="font-semibold text-gray-700 mb-1">Загальні нотатки:</h4>
                                 <p class="bg-gray-50 p-2 rounded border border-gray-200 whitespace-pre-wrap"></p>
                             </div>
                            <hr>
                            <div id="modal-incidents">
                                 <h4 class="text-md font-semibold text-gray-700 mb-2">Зафіксовані інциденти:</h4>
                            </div>
                        </div>
                    </div>
                 </div>
             </div>
             <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                 <button type="button" id="modal-close-button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Закрити
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>