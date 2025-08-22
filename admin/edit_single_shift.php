<?php
/**
 * admin/edit_single_shift.php
 * Сторінка для редагування окремої зміни.
 */

require_once '../config.php'; // Йдемо на рівень вище до config.php
global $pdo;
require_roles(['admin', 'duty_officer']);
//save_current_page_for_redirect(); // Зберігаємо для повернення на manage_shifts.php з фільтрами

$page_title = "Редагувати Зміну";
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
$shift_data = null;
$form_errors = [];
$input_data = []; // Для збереження даних форми

// Отримання даних для випадаючих списків
$posts_list_edit = [];
$lifeguards_list_edit = [];
$statuses_list_edit = [
    'active' => 'Активна',
    'completed' => 'Завершено',
    'cancelled' => 'Скасовано',
    'pending_photo_open' => 'Очікує фото відкриття',
    'active_manual' => 'Відкрито вручну'
];
$assignment_types_edit = [
    0 => 'L0 (Один, стандарт)',
    1 => 'L1 (Пара, помічник)',
    2 => 'L2 (Пара, старший)'
];

if (!$shift_id) {
    set_flash_message('помилка', 'Невірний ID зміни для редагування.');
    smart_redirect('manage_shifts.php'); // Повертаємо на сторінку керування змінами
    exit();
}

try {
    // Завантажуємо дані для постів та лайфгардів
    $stmt_posts = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
    $posts_list_edit = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

    $stmt_lifeguards = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lifeguard' ORDER BY full_name ASC");
    $lifeguards_list_edit = $stmt_lifeguards->fetchAll(PDO::FETCH_ASSOC);

    // Завантажуємо дані поточної зміни
    $stmt_shift = $pdo->prepare("SELECT * FROM shifts WHERE id = :shift_id");
    $stmt_shift->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_shift->execute();
    $shift_data = $stmt_shift->fetch(PDO::FETCH_ASSOC);

    if (!$shift_data) {
        set_flash_message('помилка', "Зміну з ID {$shift_id} не знайдено.");
        smart_redirect('manage_shifts.php');
        exit();
    }
    // Ініціалізуємо форму даними з БД (якщо це не POST з помилкою)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($form_errors)) { // Якщо GET або POST з помилками, беремо з БД
        $input_data = $shift_data;
        // Форматуємо дати для datetime-local інпутів
        if (!empty($input_data['start_time'])) {
            $input_data['start_time_html'] = (new DateTime($input_data['start_time']))->format('Y-m-d\TH:i');
        }
        if (!empty($input_data['end_time'])) {
            $input_data['end_time_html'] = (new DateTime($input_data['end_time']))->format('Y-m-d\TH:i');
        }
    }


} catch (PDOException $e) {
    set_flash_message('помилка', 'Помилка завантаження даних для редагування зміни: ' . $e->getMessage());
    error_log("Edit Single Shift - Data Load Error: " . $e->getMessage());
    smart_redirect('manage_shifts.php');
    exit();
}


// Обробка POST-запиту для збереження змін
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену.');
        smart_redirect('manage_shifts.php'); // Або на поточну сторінку редагування з помилкою
        exit();
    }

    // Отримуємо та валідуємо дані з форми
    $input_data['user_id'] = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $input_data['post_id'] = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $input_data['start_time_html'] = $_POST['start_time'] ?? ''; // Зберігаємо HTML значення для форми
    $input_data['end_time_html'] = $_POST['end_time'] ?? '';     // Зберігаємо HTML значення для форми
    $input_data['status'] = $_POST['status'] ?? '';
    $input_data['lifeguard_assignment_type'] = filter_input(INPUT_POST, 'lifeguard_assignment_type', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
    $input_data['manual_close_comment'] = trim($_POST['manual_close_comment'] ?? ($shift_data['manual_close_comment'] ?? null));
    $input_data['activity_type'] = $_POST['activity_type'] ?? ($shift_data['activity_type'] ?? 'shift');


    // Валідація
    if (empty($input_data['user_id'])) $form_errors['user_id'] = 'Необхідно обрати лайфгарда.';
    if (empty($input_data['post_id'])) $form_errors['post_id'] = 'Необхідно обрати пост.';
    if (empty($input_data['start_time_html'])) $form_errors['start_time'] = 'Час початку є обов\'язковим.';
    if (empty($input_data['status']) || !array_key_exists($input_data['status'], $statuses_list_edit)) {
        $form_errors['status'] = 'Необхідно обрати дійсний статус.';
    }
    if ($input_data['lifeguard_assignment_type'] !== null && !array_key_exists($input_data['lifeguard_assignment_type'], $assignment_types_edit)) {
         $form_errors['lifeguard_assignment_type'] = 'Обрано недійсний тип призначення.';
    }
    
    // Конвертація дат
    $start_time_sql = null;
    $end_time_sql = null;

    try {
        if (!empty($input_data['start_time_html'])) {
            $start_time_dt = new DateTime($input_data['start_time_html']);
            $start_time_sql = $start_time_dt->format('Y-m-d H:i:s');
        }
    } catch (Exception $e) {
        $form_errors['start_time'] = 'Некоректний формат часу початку.';
    }

    if (!empty($input_data['end_time_html'])) {
        try {
            $end_time_dt = new DateTime($input_data['end_time_html']);
            $end_time_sql = $end_time_dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $form_errors['end_time'] = 'Некоректний формат часу завершення.';
        }
    } else {
        // Якщо статус 'completed' або 'cancelled', а час завершення порожній - це помилка (або встановлюємо NOW())
        if (in_array($input_data['status'], ['completed', 'cancelled']) && empty($end_time_sql)) {
            // Можна встановити поточний час, або видати помилку. Для ручного редагування - краще помилку.
            // $form_errors['end_time'] = 'Час завершення є обов\'язковим для статусів "Завершено" або "Скасовано".';
            // АБО:
            $end_time_sql = ($input_data['status'] === 'completed' || $input_data['status'] === 'cancelled') ? date('Y-m-d H:i:s') : null;
            if ($input_data['status'] === 'active' || $input_data['status'] === 'pending_photo_open' || $input_data['status'] === 'active_manual') { // Якщо статус активний, час кінця має бути NULL
                $end_time_sql = null;
            }
        }
    }


    if ($start_time_sql && $end_time_sql && $end_time_sql < $start_time_sql) {
        $form_errors['end_time'] = 'Час завершення не може бути раніше часу початку.';
    }
    
    // Якщо статус не "active", "pending_photo_open" або "active_manual", то end_time має бути встановлено
    if (!in_array($input_data['status'], ['active', 'pending_photo_open', 'active_manual']) && empty($end_time_sql)) {
        $form_errors['end_time'] = "Для статусу '".escape($statuses_list_edit[$input_data['status']])."' час завершення є обов'язковим.";
    }
    // Якщо статус активний, час кінця має бути NULL
    if (in_array($input_data['status'], ['active', 'pending_photo_open', 'active_manual'])) {
        $end_time_sql = null;
        $input_data['end_time_html'] = ''; // Очищаємо для форми
    }


    if (empty($form_errors)) {
        try {
            $sql_update = "UPDATE shifts SET 
                                user_id = :user_id, 
                                post_id = :post_id, 
                                start_time = :start_time, 
                                end_time = :end_time, 
                                status = :status, 
                                lifeguard_assignment_type = :assignment_type,
                                manual_close_comment = :manual_close_comment,
                                activity_type = :activity_type,
                                updated_at = NOW() 
                           WHERE id = :shift_id";
            
            $stmt_save = $pdo->prepare($sql_update);
            $stmt_save->bindParam(':user_id', $input_data['user_id'], PDO::PARAM_INT);
            $stmt_save->bindParam(':post_id', $input_data['post_id'], PDO::PARAM_INT);
            $stmt_save->bindParam(':start_time', $start_time_sql);
            $stmt_save->bindParam(':end_time', $end_time_sql, $end_time_sql === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_save->bindParam(':status', $input_data['status']);
            $stmt_save->bindParam(':assignment_type', $input_data['lifeguard_assignment_type'], $input_data['lifeguard_assignment_type'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt_save->bindParam(':manual_close_comment', $input_data['manual_close_comment'], empty($input_data['manual_close_comment']) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt_save->bindParam(':activity_type', $input_data['activity_type']);
            $stmt_save->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);

            if ($stmt_save->execute()) {
                log_action($pdo, $_SESSION['user_id'], "Відредагував зміну #{$shift_id}", $shift_id, "Нові дані: " . http_build_query($input_data));
                set_flash_message('успіх', "Дані зміни #{$shift_id} успішно оновлено.");
                smart_redirect($_SESSION['previous_page'] ?? 'manage_shifts.php');
                exit();
            } else {
                set_flash_message('помилка', 'Не вдалося оновити дані зміни.');
            }
        } catch (PDOException $e) {
            set_flash_message('помилка', 'Помилка бази даних при оновленні зміни: ' . $e->getMessage());
            error_log("Edit Single Shift - Save Error: " . $e->getMessage());
        }
    } else {
        set_flash_message('помилка', 'Будь ласка, виправте помилки у формі.');
    }
    // Регенеруємо CSRF токен у випадку помилки для наступної спроби
    unset($_SESSION['csrf_token']);
}


require_once '../includes/header.php';
?>

<div class="container mx-auto px-3 sm:px-4 py-4 sm:py-6 max-w-2xl">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
            <i class="fas fa-edit mr-3 text-indigo-600"></i> <?php echo escape($page_title); ?> #<?php echo escape($shift_id); ?>
        </h2>
        <a href="<?php echo htmlspecialchars($_SESSION['previous_page'] ?? 'manage_shifts.php'); ?>" class="btn-secondary !text-xs !py-1.5 !px-3">
            <i class="fas fa-arrow-left mr-1"></i> До Списку Змін
        </a>
    </div>

    <?php display_flash_message(); ?>

    <?php if ($shift_data): ?>
    <form action="edit_single_shift.php?shift_id=<?php echo $shift_id; ?><?php echo '&'.http_build_query(array_intersect_key($_GET, array_flip(['s_id','s_year','s_month','s_day','s_post_id','s_user_id','s_status','s_search','s_page','s_per_page','s_sort','s_order']))); ?>" method="POST" class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 space-y-4">
        <?php echo csrf_input(); ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="user_id_edit" class="block text-sm font-medium text-gray-700 mb-1">Лайфгард *</label>
                <select name="user_id" id="user_id_edit" required class="std-select w-full <?php if(isset($form_errors['user_id'])) echo 'border-red-500'; ?>">
                    <?php foreach ($lifeguards_list_edit as $lifeguard): ?>
                        <option value="<?php echo $lifeguard['id']; ?>" <?php echo ($input_data['user_id'] == $lifeguard['id']) ? 'selected' : ''; ?>>
                            <?php echo escape($lifeguard['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if(isset($form_errors['user_id'])): ?><p class="text-red-500 text-xs mt-1"><?php echo $form_errors['user_id']; ?></p><?php endif; ?>
            </div>

            <div>
                <label for="post_id_edit" class="block text-sm font-medium text-gray-700 mb-1">Пост *</label>
                <select name="post_id" id="post_id_edit" required class="std-select w-full <?php if(isset($form_errors['post_id'])) echo 'border-red-500'; ?>">
                    <?php foreach ($posts_list_edit as $post): ?>
                        <option value="<?php echo $post['id']; ?>" <?php echo ($input_data['post_id'] == $post['id']) ? 'selected' : ''; ?>>
                            <?php echo escape($post['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if(isset($form_errors['post_id'])): ?><p class="text-red-500 text-xs mt-1"><?php echo $form_errors['post_id']; ?></p><?php endif; ?>
            </div>

            <div>
                <label for="start_time_edit" class="block text-sm font-medium text-gray-700 mb-1">Час Початку *</label>
                <input type="datetime-local" name="start_time" id="start_time_edit" 
                       value="<?php echo escape($input_data['start_time_html'] ?? ''); ?>" required 
                       class="std-input w-full <?php if(isset($form_errors['start_time'])) echo 'border-red-500'; ?>">
                <?php if(isset($form_errors['start_time'])): ?><p class="text-red-500 text-xs mt-1"><?php echo $form_errors['start_time']; ?></p><?php endif; ?>
            </div>

            <div>
                <label for="end_time_edit" class="block text-sm font-medium text-gray-700 mb-1">Час Завершення</label>
                <input type="datetime-local" name="end_time" id="end_time_edit" 
                       value="<?php echo escape($input_data['end_time_html'] ?? ''); ?>" 
                       class="std-input w-full <?php if(isset($form_errors['end_time'])) echo 'border-red-500'; ?>">
                <?php if(isset($form_errors['end_time'])): ?><p class="text-red-500 text-xs mt-1"><?php echo $form_errors['end_time']; ?></p><?php endif; ?>
            </div>

            <div>
                <label for="status_edit" class="block text-sm font-medium text-gray-700 mb-1">Статус Зміни *</label>
                <select name="status" id="status_edit" required class="std-select w-full <?php if(isset($form_errors['status'])) echo 'border-red-500'; ?>">
                    <?php foreach ($statuses_list_edit as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($input_data['status'] == $key) ? 'selected' : ''; ?>>
                            <?php echo escape($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if(isset($form_errors['status'])): ?><p class="text-red-500 text-xs mt-1"><?php echo $form_errors['status']; ?></p><?php endif; ?>
            </div>
            
            <div>
                <label for="assignment_type_edit" class="block text-sm font-medium text-gray-700 mb-1">Тип Призначення (L0/L1/L2)</label>
                <select name="lifeguard_assignment_type" id="assignment_type_edit" class="std-select w-full <?php if(isset($form_errors['lifeguard_assignment_type'])) echo 'border-red-500'; ?>">
                    <option value="">-- Не призначено --</option>
                    <?php foreach ($assignment_types_edit as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo (isset($input_data['lifeguard_assignment_type']) && $input_data['lifeguard_assignment_type'] == $key && $input_data['lifeguard_assignment_type'] !== null) ? 'selected' : ''; ?>>
                            <?php echo escape($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                 <?php if(isset($form_errors['lifeguard_assignment_type'])): ?><p class="text-red-500 text-xs mt-1"><?php echo $form_errors['lifeguard_assignment_type']; ?></p><?php endif; ?>
            </div>

            <div>
                <label for="activity_type_edit" class="block text-xs font-medium text-gray-600 mb-0.5">Тип активності *</label>
                <select name="activity_type" id="activity_type_edit" required class="std-select w-full !text-sm">
                    <option value="shift" <?php if(($input_data['activity_type'] ?? 'shift') === 'shift') echo 'selected'; ?>>Зміна</option>
                    <option value="training" <?php if(($input_data['activity_type'] ?? 'shift') === 'training') echo 'selected'; ?>>Тренування</option>
                </select>
            </div>
        </div>
        
        <div>
            <label for="manual_close_comment_edit" class="block text-sm font-medium text-gray-700 mb-1">Коментар ручного закриття (якщо застосовно)</label>
            <textarea name="manual_close_comment" id="manual_close_comment_edit" rows="2" class="std-input w-full" 
                      placeholder="Залиште порожнім, якщо не застосовно"><?php echo escape($input_data['manual_close_comment'] ?? ''); ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200/50 mt-6">
            <a href="<?php echo htmlspecialchars($_SESSION['previous_page'] ?? 'manage_shifts.php'); ?>" class="btn-red">Скасувати</a>
            <button type="submit" class="btn-green">
                <i class="fas fa-save mr-2"></i> Зберегти Зміни
            </button>
        </div>
    </form>
    <?php else: ?>
        <p class="text-center text-red-500">Не вдалося завантажити дані зміни для редагування.</p>
    <?php endif; ?>
</div>

<script>
// Можна додати JS для динамічної поведінки, якщо потрібно
// Наприклад, якщо статус "active", то поле "end_time" можна деактивувати або очищати
document.addEventListener('DOMContentLoaded', () => {
    const statusSelect = document.getElementById('status_edit');
    const endTimeInput = document.getElementById('end_time_edit');

    function toggleEndTimeField() {
        if (statusSelect && endTimeInput) {
            if (statusSelect.value === 'active' || statusSelect.value === 'pending_photo_open' || statusSelect.value === 'active_manual') {
                endTimeInput.value = ''; // Очищаємо, якщо статус активний
                endTimeInput.disabled = true;
                endTimeInput.classList.add('bg-gray-100', 'cursor-not-allowed');
            } else {
                endTimeInput.disabled = false;
                endTimeInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
            }
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', toggleEndTimeField);
        toggleEndTimeField(); // Викликаємо при завантаженні для ініціалізації
    }
});
</script>
<style>
    .std-select, .std-input {
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        border: 1px solid #d1d5db; 
        border-radius: 0.375rem; 
        font-size: 0.875rem; 
        padding: 0.5rem 0.75rem;
    }
    .std-select:focus, .std-input:focus {
        outline: none;
        border-color: #4f46e5; 
        box-shadow: 0 0 0 1px #4f46e5;
    }
    .std-input.border-red-500, .std-select.border-red-500 {
        border-color: #ef4444; /* Tailwind red-500 */
    }
     .std-input.border-red-500:focus, .std-select.border-red-500:focus {
        border-color: #ef4444;
        box-shadow: 0 0 0 1px #ef4444;
    }
</style>

<?php
require_once '../includes/footer.php';
?>