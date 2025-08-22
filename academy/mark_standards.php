<?php
// /academy/mark_standards.php
// ВАЖЛИВО: Переконайся, що цей файл знаходиться в папці /academy/
// і що config.php знаходиться на рівень вище (в корені проекту)
require_once __DIR__ . '/../config.php'; // Більш надійний шлях до config.php
require_once __DIR__ . '/../includes/functions.php'; // Підключаємо наш файл з функціями
require_roles(['trainer', 'admin']); // Доступ для тренера та адміна
global $pdo;

// Перевірка наявності сесії (хоча require_roles має це робити)
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Перевірка наявності необхідних ключів сесії
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    set_flash_message('помилка', 'Сесія користувача недійсна або пошкоджена.');
    header('Location: ' . APP_URL . '/login.php'); // Використовуємо APP_URL
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];
$page_title = "Результати Нормативів";

// --- Використовуємо нову централізовану функцію ---
$group_data = handle_academy_group_access($pdo, 'mark_standards.php');
$group_id = $group_data['group_id'];
$group_name = $group_data['group_name'];
$all_groups_for_select = $group_data['all_groups_for_select'];

// --- Визначаємо Дату Спроби ---
$today_date = date('Y-m-d'); $selected_date = $today_date; if (isset($_GET['date'])) { $date_from_get = $_GET['date']; if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_from_get)) { $d = DateTime::createFromFormat('Y-m-d', $date_from_get); if ($d && $d->format('Y-m-d') === $date_from_get) { $selected_date = $date_from_get; } } }

// --- Обробка POST для Збереження з Модалки ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_candidate_standards') {
    // ... (Логіка обробки POST залишається як у попередній відповіді) ...
    // Переконайся, що всі змінні ($modal_candidate_id і т.д.) правильно отримуються і валідуються
     if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { set_flash_message('помилка', 'Помилка CSRF токену.'); }
     else { /* ... решта POST логіки ... */
        $modal_candidate_id = filter_input(INPUT_POST, 'modal_candidate_id', FILTER_VALIDATE_INT); $modal_standard_type_id = filter_input(INPUT_POST, 'modal_standard_type_id', FILTER_VALIDATE_INT); $modal_attempt_date = $_POST['modal_attempt_date'] ?? ''; $modal_group_id = filter_input(INPUT_POST, 'modal_group_id', FILTER_VALIDATE_INT); $modal_standard_results = $_POST['modal_standards'] ?? [];
        if ($modal_candidate_id && $modal_attempt_date && $modal_group_id && is_array($modal_standard_results)) {
            $can_edit = false; if ($current_user_role === 'admin') { $can_edit = true; } else { $stmt_verify = $pdo->prepare("SELECT id FROM academy_groups WHERE id = :group_id AND trainer_user_id = :trainer_id"); $stmt_verify->bindParam(':group_id', $modal_group_id, PDO::PARAM_INT); $stmt_verify->bindParam(':trainer_id', $current_user_id, PDO::PARAM_INT); $stmt_verify->execute(); if ($stmt_verify->fetch()) { $can_edit = true; } }
            if ($can_edit) { $saved_count = 0; $error_count = 0; $pdo->beginTransaction(); try { $sql = "INSERT INTO academy_standard_results (candidate_id, standard_type_id, attempt_date, result_value, passed, comments, marked_by_user_id, created_at, updated_at) VALUES (:candidate_id, :standard_type_id, :attempt_date, :result_value, :passed, :comments, :marked_by, NOW(), NOW()) ON DUPLICATE KEY UPDATE result_value = VALUES(result_value), passed = VALUES(passed), comments = VALUES(comments), marked_by_user_id = VALUES(marked_by_user_id), updated_at = NOW()"; $stmt_save = $pdo->prepare($sql); foreach ($modal_standard_results as $standard_type_id => $result_data) { $standard_type_id_int = filter_var($standard_type_id, FILTER_VALIDATE_INT); if (!$standard_type_id_int) continue; $result_value = isset($result_data['result_value']) ? trim($result_data['result_value']) : null; $passed_input = isset($result_data['passed']) ? $result_data['passed'] : null; $comments = isset($result_data['comments']) ? trim($result_data['comments']) : null; if ($result_value !== '' || $comments !== '' || $passed_input === 'yes' || $passed_input === 'no') { $passed = null; if ($passed_input === 'yes') $passed = 1; elseif ($passed_input === 'no') $passed = 0; $stmt_save->bindParam(':candidate_id', $modal_candidate_id, PDO::PARAM_INT); $stmt_save->bindParam(':standard_type_id', $standard_type_id_int, PDO::PARAM_INT); $stmt_save->bindParam(':attempt_date', $modal_attempt_date); $stmt_save->bindParam(':result_value', $result_value, ($result_value === '' || $result_value === null) ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_save->bindParam(':passed', $passed, $passed === null ? PDO::PARAM_NULL : PDO::PARAM_BOOL); $stmt_save->bindParam(':comments', $comments, ($comments === '' || $comments === null) ? PDO::PARAM_NULL : PDO::PARAM_STR); $stmt_save->bindParam(':marked_by', $current_user_id, PDO::PARAM_INT); if ($stmt_save->execute()) { $saved_count++; } else { $error_count++; } } } $pdo->commit(); if ($error_count > 0) { set_flash_message('помилка', "Результати частково збережено ({$saved_count}). Помилок: {$error_count}."); } elseif ($saved_count > 0) { set_flash_message('успіх', "Результати нормативів збережено ({$saved_count})."); } else { set_flash_message('інфо', 'Не було внесено нових або змінено існуючих результатів.'); } } catch (PDOException $e) { $pdo->rollBack(); error_log("Standard Save Modal Error: ".$e->getMessage()); set_flash_message('помилка', 'Помилка БД при збереженні нормативів.'); } } else { set_flash_message('помилка', 'У вас немає прав на редагування цієї групи.'); } } else { set_flash_message('помилка', 'Некоректні дані для збереження результату.'); }
     }
    unset($_SESSION['csrf_token']);
    header("Location: mark_standards.php?group_id={$modal_group_id}&date={$modal_attempt_date}"); exit();
}

// --- Отримуємо Дані для Відображення Таблиці ---
$candidates = []; $standard_types = []; $results_grid = []; $fetch_error = '';
if ($group_id) { try { /* ... код отримання $candidates, $standard_types, $results_grid ... */
    $stmt_candidates = $pdo->prepare("SELECT id, full_name FROM academy_candidates WHERE group_id = :group_id AND status = 'active' ORDER BY full_name ASC"); $stmt_candidates->bindParam(':group_id', $group_id, PDO::PARAM_INT); $stmt_candidates->execute(); $candidates = $stmt_candidates->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $stmt_std_types = $pdo->query("SELECT id, name, pass_criteria FROM academy_standard_types WHERE is_active = TRUE ORDER BY name ASC"); $standard_types = $stmt_std_types->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!empty($candidates) && !empty($standard_types)) { $candidate_ids = array_column($candidates, 'id'); $placeholders = implode(',', array_fill(0, count($candidate_ids), '?')); $stmt_results = $pdo->prepare("SELECT candidate_id, standard_type_id, result_value, passed, comments FROM academy_standard_results WHERE candidate_id IN ($placeholders) AND attempt_date = ?"); $params = array_merge($candidate_ids, [$selected_date]); $stmt_results->execute($params); $result_records = $stmt_results->fetchAll(PDO::FETCH_ASSOC); foreach ($result_records as $record) { $results_grid[$record['candidate_id']][$record['standard_type_id']] = [ 'result_value' => $record['result_value'] ?? '', 'passed' => $record['passed'], 'comments' => $record['comments'] ?? '' ]; } }
    } catch (PDOException $e) { /* ... обробка помилки ... */ $fetch_error = "Помилка завантаження даних."; error_log("Mark Standards - Fetch Error: GroupID {$group_id}, Date {$selected_date} | Error: " . $e->getMessage()); set_flash_message('помилка', $fetch_error); $candidates = []; $standard_types = []; }
}

// Функції форматування (мають бути в functions.php)
if (!function_exists('escape')) { function escape($string) { return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('csrf_input')) { function csrf_input() { /* ... код генерації ... */ echo '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">'; } }
if (!function_exists('validate_csrf_token')) { function validate_csrf_token($token) { /* ... */ return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token); } }
if (!function_exists('set_flash_message')) { function set_flash_message($type, $message) { /* ... */ $_SESSION['flash_message'] = ['type' => $type, 'text' => $message]; } }
if (!function_exists('display_flash_message')) { function display_flash_message() { /* ... */ if(isset($_SESSION['flash_message'])){ /* ... HTML код повідомлення ... */ unset($_SESSION['flash_message']); } } }

// Підключаємо хедер
// Переконайся, що APP_URL визначено в config.php
$header_path = __DIR__ . '/../includes/header.php';
if (file_exists($header_path)) {
    require_once $header_path;
} else {
    die("Critical Error: Header file not found at " . $header_path);
}
?>

<section id="mark-standards-page" class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
         <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-award text-rose-500 mr-3"></i>
             <?php echo escape($page_title); ?>
             <?php if ($group_name): ?>
                 <span class="text-xl font-medium text-gray-500 ml-2"> - <?php echo escape($group_name); ?></span>
             <?php endif; ?>
         </h2>
        <a href="<?php echo escape(APP_URL . ($current_user_role === 'admin' ? '/index.php#admin-academy-content' : '/academy/trainer_dashboard.php')); ?>"
            class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад
         </a>
    </div>

    <?php display_flash_message(); ?>

    <form action="mark_standards.php" method="GET" class="glass-effect p-4 rounded-xl shadow-lg border border-white/20 flex flex-wrap items-end gap-4">
         <?php if ($current_user_role === 'admin'): ?>
         <div>
             <label for="group_id_select" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Групу</label>
             <select name="group_id" id="group_id_select" required onchange="this.form.submit()" class="std-select">
                 <option value="">-- Оберіть групу --</option>
                 <?php foreach ($all_groups_for_select as $grp): ?>
                     <option value="<?php echo $grp['id']; ?>" <?php echo ($grp['id'] == $group_id) ? 'selected' : ''; ?>><?php echo escape($grp['name']); ?></option>
                 <?php endforeach; ?>
             </select>
         </div>
         <?php else: ?>
             <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
         <?php endif; ?>
         <div>
             <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Дату Спроби</label>
             <input type="date" name="date" id="date" value="<?php echo escape($selected_date); ?>" required class="std-input">
         </div>
         <button type="submit" class="btn-secondary !py-2 !px-4">
             <i class="fas fa-filter mr-2"></i> Показати
         </button>
    </form>

    <?php if ($group_id && !$fetch_error): ?>
         <?php if (empty($standard_types)): ?>
             <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
                 <p class="text-orange-600 font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>У системі не додано типів нормативів.</p>
             </div>
         <?php elseif (empty($candidates)): ?>
              <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
                <p class="text-gray-500 italic "><i class="fas fa-info-circle mr-2"></i>У групі "<?php echo escape($group_name); ?>" немає активних кандидатів.</p>
            </div>
         <?php else: ?>
            <div class="candidates-table glass-effect p-0 rounded-xl shadow-lg border border-white/20 overflow-x-auto">
                 <h3 class="text-lg font-semibold text-gray-700 p-4">Результати нормативів за <?php echo date("d.m.Y", strtotime($selected_date)); ?></h3>
                 <table class="min-w-full">
                     <thead class="bg-gray-100/50">
                         <tr>
                             <th class="px-3 py-3 text-left text-xs font-bold text-gray-600 uppercase sticky left-0 bg-gray-100/80 backdrop-blur-sm z-10">Кандидат</th>
                             <?php foreach ($standard_types as $std_type): ?>
                                 <th class="px-3 py-3 text-center text-xs font-bold text-gray-600 uppercase whitespace-nowrap" title="<?php echo escape($std_type['pass_criteria'] ?? ''); ?>">
                                    <?php echo escape($std_type['name']); ?>
                                 </th>
                             <?php endforeach; ?>
                             <th class="px-3 py-3 text-center text-xs font-bold text-gray-600 uppercase sticky right-0 bg-gray-100/80 backdrop-blur-sm z-10">Дії</th>
                         </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-200/50 bg-white/70">
                         <?php foreach ($candidates as $candidate): ?>
                             <tr>
                                 <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-800 sticky left-0 bg-white/80 backdrop-blur-sm z-10">
                                    <?php echo escape($candidate['full_name']); ?>
                                 </td>
                                 <?php foreach ($standard_types as $std_type): ?>
                                     <?php
                                         $result = $results_grid[$candidate['id']][$std_type['id']] ?? null;
                                         $res_passed = $result['passed'] ?? null;
                                         $display_class = '';
                                         $display_icon = '<i class="far fa-circle text-gray-300 text-xs" title="Не здано/Не відмічено"></i>';
                                         if ($res_passed === 1 || $res_passed === true) { $display_class = 'text-green-600'; $display_icon = '<i class="fas fa-check-circle text-green-500" title="Склав"></i>'; }
                                         elseif ($res_passed === 0 || $res_passed === false) { $display_class = 'text-red-600'; $display_icon = '<i class="fas fa-times-circle text-red-500" title="Не склав"></i>'; }
                                         $res_value_display = escape($result['result_value'] ?? '-');
                                     ?>
                                     <td class="px-3 py-2 whitespace-nowrap text-center text-sm <?php echo $display_class; ?>" title="<?php echo escape($result['comments'] ?? ''); ?>">
                                         <?php echo $display_icon; ?>
                                         <span class="ml-1 text-xs"><?php echo $res_value_display; ?></span>
                                     </td>
                                 <?php endforeach; ?>
                                  <td class="px-3 py-2 whitespace-nowrap text-center text-sm sticky right-0 bg-white/80 backdrop-blur-sm z-10">
                                       <?php // Кнопка викликає модалку ?>
                                       <button type="button"
                                               class="btn-secondary !text-xs !py-0.5 !px-1.5"
                                               title="Редагувати результати для <?php echo escape($candidate['full_name']); ?>"
                                               onclick='openCandidateStandardsModal(<?php echo $candidate['id']; ?>, "<?php echo escape(addslashes($candidate['full_name'])); ?>", "<?php echo escape($selected_date); ?>", <?php echo json_encode($results_grid[$candidate['id']] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                               > <?php // Передаємо результати прямо в JS виклик ?>
                                           <i class="fas fa-edit"></i> Ред.
                                       </button>
                                  </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
        <?php endif; ?>
    <?php elseif ($current_user_role === 'admin' && !$group_id): ?>
        <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
            <p class="text-indigo-600 font-semibold"><i class="fas fa-arrow-up mr-2"></i>Будь ласка, оберіть групу.</p>
        </div>
    <?php endif; ?>

</section>

<div id="candidate-standards-modal" class="fixed inset-0 z-[70] hidden overflow-y-auto" aria-labelledby="candidate-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div id="candidate-modal-overlay" class="fixed inset-0 bg-gray-800/75 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full border border-gray-300">
            <form id="candidate-modal-form" action="mark_standards.php" method="POST">
                <?php csrf_input(); // Генеруємо токен ?>
                 <input type="hidden" name="action" value="save_candidate_standards">
                 <input type="hidden" id="modal_candidate_id" name="modal_candidate_id" value="">
                 <input type="hidden" id="modal_attempt_date" name="modal_attempt_date" value="">
                 <input type="hidden" id="modal_group_id" name="modal_group_id" value="<?php echo $group_id; ?>">

                <div class="bg-gray-50 px-4 py-3 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="candidate-modal-title">Результати Нормативів</h3>
                     <p id="modal-candidate-info" class="text-sm text-gray-600 mt-1"></p>
                </div>
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4 overflow-y-auto" style="max-height: 60vh;">
                     <div id="modal-standards-container">
                        <?php // JS заповнить цей блок ?>
                     </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200">
                    <button type="submit" class="btn-green w-full sm:ml-3 sm:w-auto">Зберегти Всі Результати</button>
                    <button type="button" onclick="closeCandidateStandardsModal()" class="mt-3 w-full btn-secondary sm:mt-0 sm:w-auto">Закрити</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* ... (стилі .std-input, .std-select, sticky table columns) ... */
     .std-input, .std-select { @apply shadow-sm block border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm; } .std-input { @apply px-2 py-1.5; } .std-select { @apply px-3 py-2; } #mark-standards-page table { border-collapse: separate; border-spacing: 0; } #mark-standards-page th.sticky, #mark-standards-page td.sticky { position: sticky; left: 0; z-index: 10; } #mark-standards-page thead th.sticky { z-index: 20; } #mark-standards-page td.sticky { background-color: rgba(255, 255, 255, 0.85); } #mark-standards-page tbody tr:hover td.sticky { background-color: rgba(249, 250, 251, 0.85); }
</style>

<script>
     const candidateModal = document.getElementById('candidate-standards-modal');
     const candidateModalOverlay = document.getElementById('candidate-modal-overlay');
     const candidateModalForm = document.getElementById('candidate-modal-form');
     const candidateModalTitle = document.getElementById('candidate-modal-title');
     const candidateModalInfo = document.getElementById('modal-candidate-info');
     const candidateModalCandidateIdInput = document.getElementById('modal_candidate_id');
     const candidateModalAttemptDateInput = document.getElementById('modal_attempt_date');
     const candidateModalGroupIdInput = document.getElementById('modal_group_id');
     const modalStandardsContainer = document.getElementById('modal-standards-container'); // Контейнер для полів нормативів

      // Зберігаємо всі типи стандартів в JS (має бути заповнено PHP)
      const allStandardTypes = <?php echo json_encode($standard_types ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

     function openCandidateStandardsModal(candidateId, candidateName, attemptDate, existingResults) {
          if (!candidateModal || !candidateModalForm || !modalStandardsContainer) { console.error("Candidate modal elements not found!"); return; }

          // Заповнюємо видимі дані та приховані поля
          candidateModalTitle.textContent = `Нормативи: ${candidateName}`;
          candidateModalInfo.textContent = `Дата спроби: ${new Date(attemptDate).toLocaleDateString('uk-UA')}`;
          candidateModalCandidateIdInput.value = candidateId;
          candidateModalAttemptDateInput.value = attemptDate;
          const urlParams = new URLSearchParams(window.location.search);
          candidateModalGroupIdInput.value = urlParams.get('group_id') || ''; // Зберігаємо поточну групу

          // Динамічно генеруємо поля в модалці
          modalStandardsContainer.innerHTML = ''; // Очищуємо попередні
          if(allStandardTypes.length === 0) {
              modalStandardsContainer.innerHTML = '<p>Типи нормативів не завантажено.</p>';
          }

          allStandardTypes.forEach(stdType => {
              const stdId = stdType.id;
              const result = existingResults[stdId] || {}; // Результати для цього нормативу
              const resValue = result.result_value || '';
              const resPassed = result.passed; // null, 0, 1
              const resComments = result.comments || '';
              const passCriteria = stdType.pass_criteria || '';

               const entryDiv = document.createElement('div');
               entryDiv.className = 'standard-entry border-b border-gray-200 pb-3 mb-3 last:border-b-0';
               entryDiv.innerHTML = `
                    <h4 class="text-md font-semibold text-indigo-700 mb-1">
                        ${escapeHtml(stdType.name)}
                        ${passCriteria ? `<span class="text-xs font-normal text-gray-500 ml-2">(Критерій: ${escapeHtml(passCriteria)})</span>` : ''}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-x-4 gap-y-2 items-center">
                        <div>
                            <label for="modal_std_${stdId}_result" class="block text-xs font-medium text-gray-500 mb-0.5">Результат</label>
                            <input type="text" name="modal_standards[${stdId}][result_value]" id="modal_std_${stdId}_result" value="${escapeHtml(resValue)}" placeholder="Час, бал, тощо" class="std-input !py-1 !text-sm w-full">
                        </div>
                        <div class="self-end">
                            <label class="block text-xs font-medium text-gray-500 mb-0.5">Статус</label>
                            <div class="flex items-center space-x-3">
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="radio" name="modal_standards[${stdId}][passed]" value="yes" class="form-radio h-4 w-4 text-green-600" ${resPassed === 1 || resPassed === true ? 'checked' : ''}>
                                    <span class="ml-1.5 text-green-700">Склав</span>
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="radio" name="modal_standards[${stdId}][passed]" value="no" class="form-radio h-4 w-4 text-red-600" ${resPassed === 0 || resPassed === false ? 'checked' : ''}>
                                    <span class="ml-1.5 text-red-700">Не склав</span>
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="radio" name="modal_standards[${stdId}][passed]" value="" class="form-radio h-4 w-4 text-gray-400" ${resPassed === null ? 'checked' : ''}>
                                    <span class="ml-1.5 text-gray-500">?</span>
                                </label>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                             <label for="modal_std_${stdId}_comments" class="block text-xs font-medium text-gray-500 mb-0.5">Коментар</label>
                             <textarea name="modal_standards[${stdId}][comments]" id="modal_std_${stdId}_comments" rows="1" placeholder="Необов'язково" class="std-input !py-1 !text-sm w-full min-h-[30px]">${escapeHtml(resComments)}</textarea>
                        </div>
                    </div>
               `;
               modalStandardsContainer.appendChild(entryDiv);
           });

          candidateModal.classList.remove('hidden');
          candidateModalOverlay?.addEventListener('click', closeCandidateStandardsModal);
           // Фокус на перше поле результату
           const firstResultInput = modalStandardsContainer.querySelector('input[type="text"]');
           firstResultInput?.focus();
     }

     function closeCandidateStandardsModal() {
         if (candidateModal) {
             candidateModal.classList.add('hidden');
             candidateModalOverlay?.removeEventListener('click', closeCandidateStandardsModal);
             // Очищуємо контейнер з полями нормативів, щоб вони генерувались заново
             if(modalStandardsContainer) modalStandardsContainer.innerHTML = '';
         }
     }

     // Закриття модалки по Escape
     document.addEventListener('keydown', (event) => {
         if (event.key === 'Escape' && candidateModal && !candidateModal.classList.contains('hidden')) {
             closeCandidateStandardsModal();
         }
     });

      // Проста функція escapeHtml для JS (якщо її немає в app.js)
      if (typeof escapeHtml === 'undefined') {
         function escapeHtml(str) { /* ... код функції escapeHtml ... */ if (str === null || typeof str === 'undefined') return ''; const stringValue = String(str); const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}; const regex = /[&<>"']/g; return stringValue.replace(regex, (match) => map[match]); }
     }

 </script>

<?php
require_once '../includes/footer.php';
?>