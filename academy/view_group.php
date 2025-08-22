<?php
// /academy/view_group.php
require_once '../config.php'; // Шлях до config.php
require_roles(['trainer', 'admin', 'analyst']);
global $pdo;

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];
$page_title = "Прогрес Групи";
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
$group_name = null;
$all_groups_for_select = [];
$fetch_error = '';
$total_academy_days = 10; // Визначено користувачем

try {
    // --- Логіка визначення групи (залишається без змін) ---
     if (in_array($current_user_role, ['admin', 'analyst'])) { $stmt_all_groups = $pdo->query("SELECT id, name FROM academy_groups ORDER BY name"); $all_groups_for_select = $stmt_all_groups->fetchAll(PDO::FETCH_ASSOC) ?: []; }
     if (!$group_id && $current_user_role === 'trainer') { $stmt_find_group = $pdo->prepare("SELECT id FROM academy_groups WHERE trainer_user_id = :trainer_id LIMIT 1"); $stmt_find_group->bindParam(':trainer_id', $current_user_id, PDO::PARAM_INT); $stmt_find_group->execute(); $group_id = $stmt_find_group->fetchColumn(); if (!$group_id) { set_flash_message('помилка', 'Вас не призначено тренером жодної групи.'); header('Location: trainer_dashboard.php'); exit(); } header("Location: view_group.php?group_id={$group_id}"); exit(); }
     if ($group_id) { $sql_check = "SELECT name FROM academy_groups WHERE id = :group_id"; if ($current_user_role === 'trainer') { $sql_check .= " AND trainer_user_id = :trainer_id"; } $stmt_check_group = $pdo->prepare($sql_check); $stmt_check_group->bindParam(':group_id', $group_id, PDO::PARAM_INT); if ($current_user_role === 'trainer') { $stmt_check_group->bindParam(':trainer_id', $current_user_id, PDO::PARAM_INT); } $stmt_check_group->execute(); $group_name = $stmt_check_group->fetchColumn(); if (!$group_name) { set_flash_message('помилка', 'Група не знайдена або у вас немає до неї доступу.'); $redirect_url = ($current_user_role === 'admin' || $current_user_role === 'analyst') ? 'view_group.php' : 'trainer_dashboard.php'; header("Location: " . $redirect_url); exit(); } $page_title .= ": " . $group_name; }
} catch (PDOException $e) { set_flash_message('помилка', 'Помилка отримання даних груп.'); error_log("View Group - Group Load/Check Error: ".$e->getMessage()); $redirect_url = ($current_user_role === 'admin' || $current_user_role === 'analyst') ? 'view_group.php' : 'trainer_dashboard.php'; header("Location: " . $redirect_url); exit(); }

// --- Отримуємо дані для таблиці прогресу ---
$candidates_progress = [];
$total_standards_count = 0;

if ($group_id) {
    try {
        // --- Запит кількості нормативів (без змін) ---
        $stmt_total_std = $pdo->query("SELECT COUNT(*) FROM academy_standard_types WHERE is_active = TRUE");
        $total_standards_count = $stmt_total_std->fetchColumn();

        // --- Оновлений запит прогресу ---
        $stmt_progress = $pdo->prepare("
            SELECT
                ac.id,
                ac.full_name,
                ac.status,
                -- ВИПРАВЛЕНО: Рахуємо ТІЛЬКИ 'present' для відвідуваності від 10 днів
                (SELECT COUNT(*) FROM academy_attendance aa WHERE aa.candidate_id = ac.id AND aa.status = 'present') AS present_days,
                -- Рахуємо кількість відмічених днів для % від відмічених
                (SELECT COUNT(*) FROM academy_attendance aa WHERE aa.candidate_id = ac.id) AS total_attendance_marked_days,
                (SELECT AVG(at.score) FROM academy_tests at WHERE at.candidate_id = ac.id AND at.score IS NOT NULL) AS avg_test_score,
                (SELECT COUNT(at.id) FROM academy_tests at WHERE at.candidate_id = ac.id AND at.score IS NOT NULL) AS tests_scored_count,
                (SELECT COUNT(DISTINCT asr.standard_type_id) FROM academy_standard_results asr WHERE asr.candidate_id = ac.id AND asr.passed = 1) AS standards_passed_count
            FROM
                academy_candidates ac
            WHERE
                ac.group_id = :group_id
                AND ac.status = 'active' /* Можна змінити, якщо треба показувати не тільки активних */
            GROUP BY
                ac.id, ac.full_name, ac.status
            ORDER BY
                ac.full_name ASC;
        ");
        $stmt_progress->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt_progress->execute();
        $candidates_progress = $stmt_progress->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { /* ... обробка помилки ... */ $fetch_error = "Помилка завантаження даних прогресу групи."; error_log("View Group - Progress Fetch Error: GroupID {$group_id} | Error: " . $e->getMessage()); set_flash_message('помилка', $fetch_error); $candidates_progress = []; }
}

// Функції та підключення хедера (без змін)
if (!function_exists('escape')) { function escape($string) { return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('display_flash_message')) { function display_flash_message() { if(isset($_SESSION['flash_message'])){ $message=$_SESSION['flash_message']; $type=$message['type']??'інфо'; $text=$message['text']??''; echo '<div id="flash-message" class="'.($type==='успіх'?'bg-green-100 border-green-500 text-green-700':'bg-red-100 border-red-500 text-red-700').' border-l-4 p-4 mb-4" role="alert"><p>'.$text.'</p></div>'; unset($_SESSION['flash_message']); } } }
require_once __DIR__ . '/../includes/header.php';

// Визначення масиву статусів (якщо не визначено глобально)
$candidate_statuses = [ 'active' => 'Активний', 'passed' => 'Пройшов', 'failed' => 'Не пройшов', 'dropped' => 'Вибув'];

?>

<section id="view-group-page" class="space-y-6">

    <?php // --- Заголовок та кнопки (без змін) --- ?>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
         <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-chart-line text-purple-500 mr-3"></i>
             <?php echo escape($page_title); ?>
         </h2>
        <a href="<?php echo escape(APP_URL . ($current_user_role === 'admin' || $current_user_role === 'analyst' ? '/index.php#admin-academy-content' : '/academy/trainer_dashboard.php')); ?>"
            class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад
         </a>
    </div>

    <?php display_flash_message(); ?>

    <?php // --- Форма вибору групи для адміна/аналітика (без змін) --- ?>
    <?php if (in_array($current_user_role, ['admin', 'analyst'])): ?>
     <form action="view_group.php" method="GET" class="glass-effect p-4 rounded-xl shadow-lg border border-white/20 flex items-end gap-4">
         <div>
             <label for="group_id_select" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Групу</label>
             <select name="group_id" id="group_id_select" required onchange="this.form.submit()" class="std-select">
                 <option value="">-- Оберіть групу --</option>
                 <?php foreach ($all_groups_for_select as $grp): ?>
                     <option value="<?php echo $grp['id']; ?>" <?php echo ($grp['id'] == $group_id) ? 'selected' : ''; ?>>
                         <?php echo escape($grp['name']); ?>
                     </option>
                 <?php endforeach; ?>
             </select>
         </div>
    </form>
     <?php endif; ?>


    <?php if ($group_id && !$fetch_error): ?>
         <?php if (empty($candidates_progress)): ?>
             <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
                 <p class="text-gray-500 italic "><i class="fas fa-info-circle mr-2"></i>У групі "<?php echo escape($group_name); ?>" немає активних кандидатів або даних про їх прогрес.</p>
            </div>
         <?php else: ?>
            <div class="progress-table glass-effect p-0 rounded-xl shadow-lg border border-white/20 overflow-x-auto">
                <table class="min-w-full">
                     <thead class="bg-gray-100/50">
                         <tr>
                             <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase sticky left-0 bg-gray-100/80 backdrop-blur-sm z-10">Кандидат</th>
                             <?php // --- ЗМІНЕНО ЗАГОЛОВОК ВІДВІДУВАНОСТІ --- ?>
                             <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase" title="Кількість днів з відміткою 'Присутній' / Всього днів академії (<?php echo $total_academy_days; ?>)">Відвід. (днів)</th>
                             <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase" title="Відсоток присутності від кількості днів, коли відвідуваність відмічалась">(%)</th>
                             <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase" title="Середній бал за всі тести, де була виставлена оцінка">Сер. Бал</th>
                             <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase" title="Кількість успішно складених нормативів / Загальна кількість активних нормативів">Нормативи (Скл./Всього)</th>
                             <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase">Статус</th>
                         </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-200/50 bg-white/70">
                         <?php foreach ($candidates_progress as $candidate): ?>
                             <tr class="hover:bg-gray-50/50">
                                 <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 sticky left-0 bg-white/80 backdrop-blur-sm z-10">
                                    <?php echo escape($candidate['full_name']); ?>
                                 </td>
                                  <?php // --- ОНОВЛЕНИЙ СТОВПЧИК ВІДВІДУВАНОСТІ --- ?>
                                  <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                                     <?php echo (int)($candidate['present_days'] ?? 0); ?> / <?php echo $total_academy_days; ?>
                                 </td>
                                 <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-500"> <?php // Другий стовпчик для % від відмічених ?>
                                     <?php
                                         $total_marked = (int)($candidate['total_attendance_marked_days'] ?? 0);
                                         $present = (int)($candidate['present_days'] ?? 0);
                                         $attendance_percent = ($total_marked > 0) ? round(($present / $total_marked) * 100) : 0;
                                         echo $attendance_percent . ' %';
                                     ?>
                                     <span class="text-xs text-gray-400 ml-1">(<?php echo $total_marked; ?> в.)</span>
                                 </td>
                                 <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                                     <?php
                                         $avg_score = $candidate['avg_test_score'];
                                         echo ($avg_score !== null) ? number_format($avg_score, 1, '.', '') : '-';
                                     ?>
                                      <span class="text-xs text-gray-400 ml-1">(<?php echo (int)($candidate['tests_scored_count'] ?? 0); ?>)</span>
                                 </td>
                                  <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                                     <?php
                                         $passed_count = (int)($candidate['standards_passed_count'] ?? 0);
                                         echo $passed_count . ' / ' . $total_standards_count;
                                     ?>
                                 </td>
                                 <td class="px-4 py-3 whitespace-nowrap text-center text-xs">
                                     <?php // Відображення статусу кандидата (без змін)
                                         $status_key = $candidate['status'] ?? 'active'; $status_text = $candidate_statuses[$status_key] ?? 'Невідомо'; $status_class = 'bg-gray-100 text-gray-800'; if ($status_key === 'active') $status_class = 'bg-blue-100 text-blue-800'; elseif ($status_key === 'passed') $status_class = 'bg-green-100 text-green-800'; elseif ($status_key === 'failed') $status_class = 'bg-red-100 text-red-800'; elseif ($status_key === 'dropped') $status_class = 'bg-yellow-100 text-yellow-800';
                                     ?>
                                     <span class="px-2 inline-flex leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                         <?php echo escape($status_text); ?>
                                     </span>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
         <?php endif; ?>
    <?php elseif (($current_user_role === 'admin' || $current_user_role === 'analyst') && !$group_id): ?>
        <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
            <p class="text-indigo-600 font-semibold"><i class="fas fa-arrow-up mr-2"></i>Будь ласка, оберіть групу вище для перегляду прогресу.</p>
        </div>
    <?php endif; ?>

</section>

<?php // --- Стилі (без змін) --- ?>
<style>
    #view-group-page table { border-collapse: separate; border-spacing: 0; }
    #view-group-page th.sticky, #view-group-page td.sticky { position: sticky; left: 0; z-index: 10; }
    #view-group-page thead th.sticky { z-index: 20; }
    #view-group-page td.sticky { background-color: rgba(255, 255, 255, 0.85); }
    #view-group-page tbody tr:hover td.sticky { background-color: rgba(249, 250, 251, 0.85); }
    .std-select { @apply shadow-sm block border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2; }
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>