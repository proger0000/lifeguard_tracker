<?php
// /academy/mark_attendance.php
require_once '../config.php'; // Шлях до config.php
require_once '../includes/functions.php'; // Підключаємо файл з новою функцією
// ВИПРАВЛЕНО: Дозволяємо доступ тренеру АБО адміну
require_roles(['trainer', 'admin']);
global $pdo;

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role']; // Отримуємо роль поточного користувача
$page_title = "Відмітка Відвідуваності";

// --- Використовуємо нову централізовану функцію ---
$group_data = handle_academy_group_access($pdo, 'mark_attendance.php');
$group_id = $group_data['group_id'];
$group_name = $group_data['group_name'];
$all_groups_for_select = $group_data['all_groups_for_select'];


// --- Визначаємо Дату ---
// ... (код для $selected_date залишається без змін) ...
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


// --- Обробка POST запиту для збереження відвідуваності ---
// ... (код обробки POST залишається майже без змін, використовує $current_user_id для marked_by) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Перевірка CSRF, дати, group_id...
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену.');
    } elseif (!isset($_POST['attendance_date']) || $_POST['attendance_date'] !== $selected_date) {
        set_flash_message('помилка', 'Дата відправлених даних не співпадає з обраною.');
    } elseif (!isset($_POST['group_id']) || (int)$_POST['group_id'] !== $group_id) {
         set_flash_message('помилка', 'Некоректний ID групи.');
    } elseif (!$group_id) { // Додаткова перевірка, що група таки вибрана
          set_flash_message('помилка', 'Спочатку оберіть групу.');
    } else {
        $attendance_data = $_POST['attendance'] ?? [];
        $saved_count = 0; $error_count = 0;

        if (!empty($attendance_data)) {
             $sql = "INSERT INTO academy_attendance (candidate_id, attendance_date, status, marked_by_user_id, created_at)
                     VALUES (:candidate_id, :attendance_date, :status, :marked_by, NOW())
                     ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by_user_id = VALUES(marked_by_user_id)";
            $stmt_save = $pdo->prepare($sql);

            $pdo->beginTransaction();
            try {
                 foreach ($attendance_data as $candidate_id => $status) {
                    $candidate_id_int = filter_var($candidate_id, FILTER_VALIDATE_INT);
                    if ($candidate_id_int && in_array($status, ['present', 'absent', 'excused'])) {
                         $stmt_save->bindParam(':candidate_id', $candidate_id_int, PDO::PARAM_INT);
                         $stmt_save->bindParam(':attendance_date', $selected_date);
                         $stmt_save->bindParam(':status', $status);
                         $stmt_save->bindParam(':marked_by', $current_user_id, PDO::PARAM_INT); // ID адміна або тренера
                         if ($stmt_save->execute()) { $saved_count++; } else { $error_count++; }
                    } else { $error_count++; error_log("Invalid attendance data: CID={$candidate_id}, Status={$status}"); }
                 }
                 $pdo->commit();
                 if ($error_count > 0) { set_flash_message('помилка', "Відвідуваність частково збережено ({$saved_count}). Помилок: {$error_count}."); }
                 else { set_flash_message('успіх', "Відвідуваність за {$selected_date} збережено ({$saved_count})."); }
            } catch (PDOException $e) {
                 $pdo->rollBack(); error_log("Attendance Save Error: ".$e->getMessage());
                 set_flash_message('помилка', 'Помилка БД при збереженні.');
            }
        } else { set_flash_message('інфо', 'Не було даних для збереження.'); }
    }
    unset($_SESSION['csrf_token']);
    header("Location: mark_attendance.php?group_id={$group_id}&date={$selected_date}"); exit();
}

// --- Отримуємо список кандидатів групи та їх поточний статус відвідування (ТІЛЬКИ ЯКЩО ГРУПА ВИБРАНА) ---
$candidates = [];
$attendance_today = [];
$fetch_error = ''; // Помилка для даних кандидатів

if ($group_id) { // Виконуємо запити тільки якщо є group_id
    try {
         // Кандидати групи
         $stmt_candidates = $pdo->prepare("SELECT id, full_name FROM academy_candidates WHERE group_id = :group_id AND status = 'active' ORDER BY full_name ASC");
         $stmt_candidates->bindParam(':group_id', $group_id, PDO::PARAM_INT);
         $stmt_candidates->execute();
         $candidates = $stmt_candidates->fetchAll(PDO::FETCH_ASSOC);

         // Відвідуваність за обрану дату
         if (!empty($candidates)) {
             $candidate_ids = array_column($candidates, 'id');
             $placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
             $stmt_attendance = $pdo->prepare("SELECT candidate_id, status FROM academy_attendance WHERE candidate_id IN ($placeholders) AND attendance_date = ?");
             $params = array_merge($candidate_ids, [$selected_date]);
             $stmt_attendance->execute($params);
             $attendance_records = $stmt_attendance->fetchAll(PDO::FETCH_ASSOC);
             foreach ($attendance_records as $record) { $attendance_today[$record['candidate_id']] = $record['status']; }
         }
    } catch (PDOException $e) {
         $fetch_error = "Помилка завантаження даних кандидатів або відвідуваності.";
         error_log("Attendance Mark - Fetch Error: GroupID {$group_id}, Date {$selected_date} | Error: " . $e->getMessage());
         set_flash_message('помилка', $fetch_error);
         $candidates = [];
    }
}


require_once '../includes/header.php';
?>

<section id="mark-attendance-page" class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-user-check text-teal-500 mr-3"></i>
             <?php echo $page_title; ?>
             <?php if ($group_name): ?>
                 <span class="text-xl font-medium text-gray-500 ml-2"> - <?php echo escape($group_name); ?></span>
             <?php endif; ?>
        </h2>
         <?php // Посилання назад залежить від ролі ?>
         <a href="<?php echo ($current_user_role === 'admin' ? '../index.php#admin-academy-content' : 'trainer_dashboard.php'); ?>"
            class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад
         </a>
    </div>

    <?php display_flash_message(); ?>

    <form action="mark_attendance.php" method="GET" class="glass-effect p-4 rounded-xl shadow-lg border border-white/20 flex flex-wrap items-end gap-4">
         <?php // Показуємо вибір групи тільки адміну ?>
         <?php if ($current_user_role === 'admin'): ?>
         <div>
             <label for="group_id_select" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Групу</label>
             <select name="group_id" id="group_id_select" required onchange="this.form.submit()" <?php // Автоматична відправка при зміні ?>
                     class="shadow-sm border border-gray-300 rounded-md py-2 px-3 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                 <option value="">-- Всі Групи --</option> <?php // Або "Оберіть групу..." ?>
                 <?php foreach ($all_groups_for_select as $grp): ?>
                     <option value="<?php echo $grp['id']; ?>" <?php echo ($grp['id'] == $group_id) ? 'selected' : ''; ?>>
                         <?php echo escape($grp['name']); ?>
                     </option>
                 <?php endforeach; ?>
             </select>
         </div>
         <?php else: ?>
             <?php // Тренер не обирає групу, вона вже визначена ?>
             <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
         <?php endif; ?>

         <div>
             <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Оберіть Дату</label>
             <input type="date" name="date" id="date" value="<?php echo escape($selected_date); ?>" required
                    class="shadow-sm border border-gray-300 rounded-md py-2 px-3 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
         </div>
         <button type="submit" class="btn-secondary !py-2 !px-4">
             <i class="fas fa-filter mr-2"></i> Показати
         </button>
    </form>

    <?php if ($group_id && !$fetch_error): ?>
         <?php if (!empty($candidates)): ?>
             <form action="mark_attendance.php?group_id=<?php echo $group_id; ?>&date=<?php echo $selected_date; ?>" method="POST" class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                 <?php echo csrf_input(); ?>
                 <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                 <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">

                 <h3 class="text-lg font-semibold text-gray-700 mb-4">Кандидати групи за <?php echo date("d.m.Y", strtotime($selected_date)); ?></h3>

                 <div class="space-y-4">
                     <?php foreach ($candidates as $candidate): ?>
                         <?php $current_status = $attendance_today[$candidate['id']] ?? ''; ?>
                         <div class="candidate-row grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 sm:gap-6 items-center border-b border-gray-200/50 pb-3 last:border-b-0">
                             <span class="font-medium text-gray-800"><?php echo escape($candidate['full_name']); ?></span>
                             <div class="status-radios flex flex-wrap justify-start sm:justify-end gap-x-4 gap-y-2 text-sm">
                                  <label class="inline-flex items-center cursor-pointer p-1 rounded hover:bg-green-50">
                                     <input type="radio" name="attendance[<?php echo $candidate['id']; ?>]" value="present" class="form-radio text-green-600 focus:ring-green-500 h-4 w-4" <?php echo ($current_status === 'present') ? 'checked' : ''; ?>>
                                     <span class="ml-1.5 text-green-700">Присутній</span>
                                 </label>
                                 <label class="inline-flex items-center cursor-pointer p-1 rounded hover:bg-red-50">
                                     <input type="radio" name="attendance[<?php echo $candidate['id']; ?>]" value="absent" class="form-radio text-red-600 focus:ring-red-500 h-4 w-4" <?php echo ($current_status === 'absent') ? 'checked' : ''; ?>>
                                      <span class="ml-1.5 text-red-700">Відсутній</span>
                                 </label>
                                 <label class="inline-flex items-center cursor-pointer p-1 rounded hover:bg-yellow-50">
                                      <input type="radio" name="attendance[<?php echo $candidate['id']; ?>]" value="excused" class="form-radio text-yellow-600 focus:ring-yellow-500 h-4 w-4" <?php echo ($current_status === 'excused') ? 'checked' : ''; ?>>
                                     <span class="ml-1.5 text-yellow-700">Поважна</span>
                                 </label>
                                  <?php // Не відмічено (якщо немає даних) ?>
                                  <?php if ($current_status === ''): ?>
                                  <label class="inline-flex items-center cursor-pointer p-1 rounded opacity-60">
                                     <input type="radio" name="attendance[<?php echo $candidate['id']; ?>]" value="" class="form-radio text-gray-400 focus:ring-gray-300 h-4 w-4" checked>
                                     <span class="ml-1.5 text-gray-500 italic">Не відм.</span>
                                 </label>
                                 <?php endif; ?>
                             </div>
                         </div>
                     <?php endforeach; ?>
                 </div>

                 <div class="mt-6 flex justify-end">
                     <button type="submit" class="btn-green">
                         <i class="fas fa-save mr-2"></i> Зберегти Відвідуваність
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
            <p class="text-indigo-600 font-semibold"><i class="fas fa-arrow-up mr-2"></i>Будь ласка, оберіть групу вище для перегляду та відмітки відвідуваності.</p>
        </div>
    <?php endif; ?>

</section>

<?php
require_once '../includes/footer.php';
?>