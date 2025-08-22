<?php
// /academy/mark_tests.php
require_once '../config.php'; // Шлях до config.php
require_once '../includes/functions.php'; // Підключаємо файл з новою функцією
require_roles(['trainer', 'admin']); // Доступ для тренера та адміна
global $pdo;

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];
$page_title = "Результати Тестування";

// --- Використовуємо нову централізовану функцію ---
$group_data = handle_academy_group_access($pdo, 'mark_tests.php');
$group_id = $group_data['group_id'];
$group_name = $group_data['group_name'];
$all_groups_for_select = $group_data['all_groups_for_select'];

// --- Визначаємо Дату ---
$today_date = date('Y-m-d');
$selected_date = $today_date;
if (isset($_GET['date'])) {
    $date_from_get = $_GET['date'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_get)) {
         $d = DateTime::createFromFormat('Y-m-d', $date_from_get);
         if ($d && $d->format('Y-m-d') === $date_from_get) {
             $selected_date = $date_from_get;
         }
    }
}

// --- Обробка POST для збереження результатів ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену.');
    } elseif (!isset($_POST['test_date']) || $_POST['test_date'] !== $selected_date) {
        set_flash_message('помилка', 'Дата відправлених даних не співпадає з обраною.');
    } elseif (!isset($_POST['group_id']) || (int)$_POST['group_id'] !== $group_id) {
         set_flash_message('помилка', 'Некоректний ID групи.');
    } elseif (!$group_id) {
          set_flash_message('помилка', 'Спочатку оберіть групу.');
    } else {
        $test_results = $_POST['tests'] ?? []; // Масив [candidate_id => ['score'=>..., 'comments'=>...]]
        $saved_count = 0;
        $error_count = 0;

        if (!empty($test_results)) {
            $sql = "INSERT INTO academy_tests (candidate_id, test_date, score, comments, marked_by_user_id, created_at, updated_at)
                    VALUES (:candidate_id, :test_date, :score, :comments, :marked_by, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE score = VALUES(score), comments = VALUES(comments), marked_by_user_id = VALUES(marked_by_user_id), updated_at = NOW()";
             $stmt_save = $pdo->prepare($sql);

            $pdo->beginTransaction();
            try {
                 foreach ($test_results as $candidate_id => $data) {
                    $candidate_id_int = filter_var($candidate_id, FILTER_VALIDATE_INT);
                    $score_input = trim($data['score'] ?? '');
                    $comments = trim($data['comments'] ?? '');

                    // Валідація балу: або порожній рядок, або число (дозволяємо десяткові)
                     $score = null; // За замовчуванням NULL
                     if ($score_input !== '') {
                         if (is_numeric($score_input) && $score_input >= 0) { // Перевірка на число і невід'ємність
                            // Можна додати перевірку максимального балу, якщо потрібно
                             $score = (float)$score_input; // Зберігаємо як float
                         } else {
                             $error_count++;
                             error_log("Invalid score data: CID={$candidate_id}, Score='{$score_input}'");
                             continue; // Пропускаємо збереження для цього кандидата
                         }
                     }

                    if ($candidate_id_int) {
                         $stmt_save->bindParam(':candidate_id', $candidate_id_int, PDO::PARAM_INT);
                         $stmt_save->bindParam(':test_date', $selected_date);
                         $stmt_save->bindParam(':score', $score, $score === null ? PDO::PARAM_NULL : PDO::PARAM_STR); // Використовуємо PDO::PARAM_STR для DECIMAL/FLOAT
                         $stmt_save->bindParam(':comments', $comments, $comments === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
                         $stmt_save->bindParam(':marked_by', $current_user_id, PDO::PARAM_INT);

                         if ($stmt_save->execute()) { $saved_count++; } else { $error_count++; }
                    } else { $error_count++; error_log("Invalid candidate ID in test results: {$candidate_id}"); }
                 }
                 $pdo->commit();
                 if ($error_count > 0) { set_flash_message('помилка', "Результати тестів частково збережено ({$saved_count}). Помилок: {$error_count}."); }
                 else { set_flash_message('успіх', "Результати тестів за {$selected_date} збережено ({$saved_count})."); }

            } catch (PDOException $e) {
                 $pdo->rollBack(); error_log("Test Save Error: ".$e->getMessage());
                 set_flash_message('помилка', 'Помилка БД при збереженні тестів.');
            }
        } else { set_flash_message('інфо', 'Не було отримано даних для збереження.'); }
    }
    unset($_SESSION['csrf_token']);
    header("Location: mark_tests.php?group_id={$group_id}&date={$selected_date}"); exit();
}

// --- Отримуємо Кандидатів та Існуючі Результати Тестів ---
$candidates = [];
$tests_today = []; // [candidate_id => ['score'=>..., 'comments'=>...]]
$fetch_error = '';

if ($group_id) {
    try {
         // Кандидати групи
         $stmt_candidates = $pdo->prepare("SELECT id, full_name FROM academy_candidates WHERE group_id = :group_id AND status = 'active' ORDER BY full_name ASC");
         $stmt_candidates->bindParam(':group_id', $group_id, PDO::PARAM_INT);
         $stmt_candidates->execute();
         $candidates = $stmt_candidates->fetchAll(PDO::FETCH_ASSOC);

         // Існуючі результати за дату
         if (!empty($candidates)) {
             $candidate_ids = array_column($candidates, 'id');
             $placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
             $stmt_tests = $pdo->prepare("SELECT candidate_id, score, comments FROM academy_tests WHERE candidate_id IN ($placeholders) AND test_date = ?");
             $params = array_merge($candidate_ids, [$selected_date]);
             $stmt_tests->execute($params);
             $test_records = $stmt_tests->fetchAll(PDO::FETCH_ASSOC);
             foreach ($test_records as $record) {
                 $tests_today[$record['candidate_id']] = [
                     'score' => $record['score'], // Може бути null
                     'comments' => $record['comments'] ?? '' // Може бути null
                 ];
             }
         }
    } catch (PDOException $e) {
         $fetch_error = "Помилка завантаження даних кандидатів або результатів тестів.";
         error_log("Mark Tests - Fetch Error: GroupID {$group_id}, Date {$selected_date} | Error: " . $e->getMessage());
         set_flash_message('помилка', $fetch_error);
         $candidates = [];
    }
}

require_once '../includes/header.php';
?>

<section id="mark-tests-page" class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-tasks text-sky-500 mr-3"></i>
             <?php echo $page_title; ?>
             <?php if ($group_name): ?>
                 <span class="text-xl font-medium text-gray-500 ml-2"> - <?php echo escape($group_name); ?></span>
             <?php endif; ?>
        </h2>
        <a href="<?php echo ($current_user_role === 'admin' ? '../index.php#admin-academy-content' : 'trainer_dashboard.php'); ?>"
            class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад
         </a>
    </div>

    <?php display_flash_message(); ?>

    <form action="mark_tests.php" method="GET" class="glass-effect p-4 rounded-xl shadow-lg border border-white/20 flex flex-wrap items-end gap-4">
         <?php if ($current_user_role === 'admin'): ?>
         <div>
             <label for="group_id_select" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Групу</label>
             <select name="group_id" id="group_id_select" required onchange="this.form.submit()"
                     class="shadow-sm border border-gray-300 rounded-md py-2 px-3 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                 <option value="">-- Оберіть групу --</option>
                 <?php foreach ($all_groups_for_select as $grp): ?>
                     <option value="<?php echo $grp['id']; ?>" <?php echo ($grp['id'] == $group_id) ? 'selected' : ''; ?>>
                         <?php echo escape($grp['name']); ?>
                     </option>
                 <?php endforeach; ?>
             </select>
         </div>
         <?php else: ?>
             <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
         <?php endif; ?>

         <div>
             <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Дату Тесту</label>
             <input type="date" name="date" id="date" value="<?php echo escape($selected_date); ?>" required
                    class="shadow-sm border border-gray-300 rounded-md py-2 px-3 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
         </div>
         <button type="submit" class="btn-secondary !py-2 !px-4">
             <i class="fas fa-filter mr-2"></i> Показати
         </button>
    </form>

    <?php if ($group_id && !$fetch_error): ?>
         <?php if (!empty($candidates)): ?>
             <form action="mark_tests.php?group_id=<?php echo $group_id; ?>&date=<?php echo $selected_date; ?>" method="POST" class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                 <?php echo csrf_input(); ?>
                 <input type="hidden" name="test_date" value="<?php echo $selected_date; ?>">
                 <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">

                 <h3 class="text-lg font-semibold text-gray-700 mb-4">Результати за <?php echo date("d.m.Y", strtotime($selected_date)); ?></h3>

                 <div class="space-y-5">
                     <?php foreach ($candidates as $candidate): ?>
                         <?php
                             $current_score = $tests_today[$candidate['id']]['score'] ?? '';
                             $current_comments = $tests_today[$candidate['id']]['comments'] ?? '';
                         ?>
                         <div class="candidate-row grid grid-cols-1 md:grid-cols-[1fr_100px_1fr] gap-4 items-start border-b border-gray-200/50 pb-4 last:border-b-0">
                             <span class="font-medium text-gray-800 pt-2"><?php echo escape($candidate['full_name']); ?></span>
                             <div>
                                 <label for="score_<?php echo $candidate['id']; ?>" class="block text-xs font-medium text-gray-500 mb-1">Бал</label>
                                 <input type="number" step="0.1" min="0" <?php // Додай max, якщо потрібно ?>
                                        name="tests[<?php echo $candidate['id']; ?>][score]"
                                        id="score_<?php echo $candidate['id']; ?>"
                                        value="<?php echo escape($current_score); ?>"
                                        placeholder="0-100"
                                        class="shadow-sm block w-full px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                             </div>
                              <div>
                                 <label for="comments_<?php echo $candidate['id']; ?>" class="block text-xs font-medium text-gray-500 mb-1">Коментар</label>
                                 <textarea name="tests[<?php echo $candidate['id']; ?>][comments]"
                                           id="comments_<?php echo $candidate['id']; ?>"
                                           rows="1" placeholder="Необов'язково"
                                           class="shadow-sm block w-full px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm min-h-[40px]"
                                           ><?php echo escape($current_comments); ?></textarea>
                             </div>
                         </div>
                     <?php endforeach; ?>
                 </div>

                 <div class="mt-6 flex justify-end">
                     <button type="submit" class="btn-green">
                         <i class="fas fa-save mr-2"></i> Зберегти Результати
                     </button>
                 </div>
             </form>
         <?php elseif (empty($candidates) && $group_id): ?>
              <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
                <p class="text-gray-500 italic "><i class="fas fa-info-circle mr-2"></i>У групі "<?php echo escape($group_name); ?>" немає активних кандидатів.</p>
            </div>
         <?php endif; ?>
    <?php elseif ($current_user_role === 'admin' && !$group_id): ?>
         <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
            <p class="text-indigo-600 font-semibold"><i class="fas fa-arrow-up mr-2"></i>Будь ласка, оберіть групу вище для внесення результатів тестів.</p>
        </div>
    <?php endif; ?>

</section>

<?php
require_once '../includes/footer.php';
?>