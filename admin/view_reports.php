<?php
require_once '../config.php'; // Підключення конфігурації
require_roles(['admin', 'duty_officer']); // Перевірка ролі
global $pdo; // Глобальний PDO

// --- (PHP код для отримання даних - залишається як у вашому прикладі) ---
$report_dates = [];
$selected_date = null;
$reports_on_date = [];
$incidents_by_report = [];
$error_message = '';
$today_date = date('Y-m-d'); // Потрібно для max у date input

try {
    // Отримуємо список унікальних дат звітів
    $stmt_dates = $pdo->query("SELECT DISTINCT DATE(report_submitted_at) as report_date
                              FROM shift_reports ORDER BY report_date DESC");
    $report_dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN);

    // Визначаємо обрану дату
    $selected_date_from_get = null;
    if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
        $potential_date = $_GET['date'];
        if (in_array($potential_date, $report_dates)) {
            $selected_date_from_get = $potential_date;
        }
    }
    // Якщо дата не обрана або невалідна, беремо найновішу
    $selected_date = $selected_date_from_get ?? (!empty($report_dates) ? $report_dates[0] : null);

    // Отримуємо звіти та інциденти, якщо дата обрана
    if ($selected_date) {
        $date_start = $selected_date . ' 00:00:00';
        $date_end = $selected_date . ' 23:59:59';

        // Запит звітів
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

        // Запит інцидентів
        if (!empty($reports_on_date)) {
            $report_ids_on_date = array_column($reports_on_date, 'report_id');
            if(!empty($report_ids_on_date)) { // Перевірка, що масив ID не порожній
                 $placeholders = implode(',', array_fill(0, count($report_ids_on_date), '?'));
                 $stmt_incidents = $pdo->prepare("
                    SELECT ri.*, u_involved.full_name as involved_lifeguard_name
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
    }
} catch (PDOException $e) {
    $error_message = "Помилка бази даних. Спробуйте пізніше.";
    error_log("View Reports DB Error: " . $e->getMessage());
}

// Підключаємо хедер
require_once '../includes/header.php';
?>

<div class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2 sm:mb-0">
            <i class="fas fa-file-alt mr-2 text-red-600"></i> Перегляд Звітів
        </h2>
         <div class="relative inline-block w-full sm:w-auto">
            <label for="report_date_select" class="sr-only">Оберіть дату звіту</label> <?php if (!empty($report_dates)): ?>
                <select id="report_date_select" name="date"
                        class="block appearance-none w-full sm:w-56 bg-white border border-gray-300 hover:border-gray-400 px-3 py-1.5 pr-8 rounded-md shadow-sm leading-tight focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 text-sm"
                        onchange="if(this.value) window.location.href = 'view_reports.php?date=' + this.value;">
                    <?php foreach ($report_dates as $date): ?>
                        <?php $formatted_date = date("d.m.Y", strtotime($date)); ?>
                        <option value="<?php echo $date; ?>" <?php echo ($date == $selected_date) ? 'selected' : ''; ?>>
                            <?php echo $formatted_date; ?> <?php echo ($date == $today_date) ? '(Сьогодні)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                </div>
            <?php else: ?>
                 <p class="text-gray-500 italic text-sm">Звіти ще не надходили.</p>
            <?php endif; ?>
        </div>
    </div>


    <?php if ($error_message): ?>
         <?php
            $_SESSION['flash_message'] = ['type' => 'помилка', 'text' => $error_message];
            display_flash_message();
         ?>
    <?php endif; ?>

    <?php if ($selected_date): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
            <table id="view-reports-table" class="min-w-full bg-white">
                <thead class="hidden md:table-header-group">
                    <tr class="bg-gray-50">
                         <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-r border-gray-200">Час</th>
                        <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-r border-gray-200">Пост</th>
                        <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-r border-gray-200">Лайфгард</th>
                         <th class="py-2 px-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-r border-gray-200">Інциденти</th>
                         <th class="py-2 px-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">Дії</th>
                     </tr>
                </thead>
                 <tbody class="text-sm divide-y divide-gray-200 md:divide-y-0"> <?php if (empty($reports_on_date)): ?>
                         <tr><td colspan="5" class="text-center py-10 px-4 text-gray-500 text-base">За обрану дату звітів не знайдено.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reports_on_date as $report):
                            $incident_count = isset($incidents_by_report[$report['report_id']]) ? count($incidents_by_report[$report['report_id']]) : 0;
                         ?>
                            <tr class="report-row">
                                <td class="report-cell time-cell px-3 md:px-3 py-1 md:py-2 whitespace-nowrap">
                                    <span class="label md:hidden">Час:</span>
                                    <span class="value"><?php echo date("H:i", strtotime($report['report_submitted_at'])); ?></span>
                                </td>
                                 <td class="report-cell post-cell px-3 md:px-3 py-1 md:py-2">
                                     <span class="label md:hidden">Пост:</span>
                                     <span class="value font-medium text-gray-900"><?php echo escape($report['post_name']); ?></span>
                                 </td>
                                 <td class="report-cell lifeguard-cell px-3 md:px-3 py-1 md:py-2">
                                     <span class="label md:hidden">Лайфгард:</span>
                                     <span class="value text-gray-700"><?php echo escape($report['reporter_name']); ?></span>
                                 </td>
                                <td class="report-cell incidents-cell px-3 md:px-3 py-1 md:py-2">
                                    <span class="label md:hidden">Інциденти:</span>
                                    <span class="value font-medium <?php echo $incident_count > 0 ? 'text-red-600' : 'text-gray-500'; ?>">
                                        <?php echo $incident_count; ?>
                                    </span>
                                 </td>
                                <td class="report-cell actions-cell px-3 md:px-3 py-1 md:py-2">
                                    <span class="label md:hidden" style="display: none;">Дії:</span>
                                     <button type="button"
                                            class="btn-secondary !py-1 !px-2 text-xs"
                                            title="Переглянути деталі звіту"
                                            onclick="openReportModal(<?php echo $report['report_id']; ?>)">
                                         <i class="fas fa-eye mr-1"></i>
                                         <span class="hidden sm:inline">Переглянути</span>
                                     </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                     <?php endif; ?>
                 </tbody>
             </table>
             <?php if (!empty($reports_on_date)): ?>
                <div class="px-3 py-2 bg-gray-50 text-xs text-gray-500 border-t border-gray-200">
                    Всього звітів за <?php echo date("d.m.Y", strtotime($selected_date)); ?>: <?php echo count($reports_on_date); ?>
                </div>
             <?php endif; ?>
         </div>
    <?php elseif (empty($report_dates)): ?>
         <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-center text-blue-700">
              <p><i class="fas fa-info-circle mr-2"></i>Звіти ще не надходили до системи.</p>
          </div>
    <?php endif; ?>

</div>

<div id="report-modal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
         <div id="modal-overlay" class="fixed inset-0 bg-gray-800 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
         <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
         <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl md:max-w-4xl lg:max-w-5xl w-full">
              <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                  <div class="sm:flex sm:items-start">
                      <div class="mx-auto flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-red-100 sm:mx-0 sm:h-8 sm:w-8"> <i class="fas fa-file-alt text-red-600 text-lg"></i>
                      </div>
                      <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                          <h3 class="text-base sm:text-lg leading-6 font-bold text-gray-900 mb-2" id="modal-title">
                               Деталі Звіту №<span id="modal-report-id"></span>
                          </h3>
                          <hr class="mb-3 sm:mb-4">
                          <div id="modal-body" class="modal-content-container mt-2 text-xs sm:text-sm text-gray-600 space-y-3 sm:space-y-4 pr-1 sm:pr-2">
                              <div id="modal-general-info" class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1"></div>
                               <hr>
                              <h4 class="font-semibold text-gray-700">Статистика:</h4>
                              <div id="modal-stats" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-3 gap-y-2 text-xs"></div> <hr>
                               <div id="modal-general-notes" style="display: none;"> <h4 class="font-semibold text-gray-700 mb-1">Загальні нотатки:</h4>
                                  <p class="bg-gray-50 p-2 rounded border border-gray-200 whitespace-pre-wrap text-xs sm:text-sm"></p> </div>
                               <hr id="modal-notes-hr" style="display: none;"> <div id="modal-incidents">
                                   <h4 class="text-sm sm:text-base font-semibold text-gray-700 mb-2">Зафіксовані інциденти:</h4> </div>
                          </div>
                      </div>
                  </div>
              </div>
              <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                   <button type="button" id="modal-close-button" class="btn-secondary mt-3 w-full sm:mt-0 sm:ml-3 sm:w-auto"> Закрити
                   </button>
               </div>
          </div>
     </div>
</div>
<?php
// Підключаємо футер (містить JavaScript для модалки та дані $reports_on_date, $incidents_by_report)
require_once '../includes/footer.php';
?>