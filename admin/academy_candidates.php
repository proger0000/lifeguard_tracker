<?php
require_once '../config.php'; // Шлях до config.php
require_role('admin');
global $pdo;
save_current_page_for_redirect();

$page_title = "Керування Кандидатами Академії";

$errors = [];
$input = ['full_name' => '', 'group_id' => '', 'status' => 'active', 'notes' => '', 'linked_user_id' => '']; // Значення за замовчуванням для форми додавання
$edit_input = []; // Для форми редагування
$editing_candidate = null; // Для зберігання даних кандидата, що редагується

// --- Обробка Дій (POST) ---

// -- Додавання Кандидата --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    // (Залишаємо логіку додавання з попереднього кроку, але додаємо обробку notes)
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Помилка CSRF токену.';
    } else {
        $input['full_name'] = trim($_POST['full_name'] ?? '');
        $input['group_id'] = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $input['status'] = $_POST['status'] ?? 'active';
        $input['notes'] = trim($_POST['notes'] ?? ''); // Додано notes

        if (empty($input['full_name'])) $errors['full_name'] = "ПІБ кандидата є обов'язковим.";
        if (empty($input['group_id'])) $errors['group_id'] = "Необхідно вибрати групу.";
        // TODO: Додати валідацію статусу, перевірку існування групи

        if (empty($errors)) {
            try {
                 $sql = "INSERT INTO academy_candidates (full_name, group_id, status, notes, enrollment_date, created_at, updated_at)
                         VALUES (:full_name, :group_id, :status, :notes, CURDATE(), NOW(), NOW())";
                 $stmt = $pdo->prepare($sql);
                 $stmt->bindParam(':full_name', $input['full_name']);
                 $stmt->bindParam(':group_id', $input['group_id'], PDO::PARAM_INT);
                 $stmt->bindParam(':status', $input['status']);
                 $stmt->bindParam(':notes', $input['notes'], PDO::PARAM_STR); // Notes може бути порожнім

                 if ($stmt->execute()) {
                    set_flash_message('успіх', 'Кандидата "' . escape($input['full_name']) . '" успішно додано.');
                    unset($_SESSION['csrf_token']);
                    smart_redirect('admin/academy_candidates.php'); exit();
                 } else { $errors['db'] = 'Не вдалося додати кандидата.'; }
            } catch (PDOException $e) {
                 error_log("Academy Add Candidate Error: " . $e->getMessage());
                 $errors['db'] = 'Помилка БД при додаванні.';
            }
        }
    }
     if (!empty($errors)) {
        set_flash_message('помилка', 'Помилки у формі додавання.');
        unset($_SESSION['csrf_token']);
     }
}

// -- Оновлення Кандидата --
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_candidate'])) {
     if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену.');
    } else {
        $edit_candidate_id = filter_input(INPUT_POST, 'edit_candidate_id', FILTER_VALIDATE_INT);
        $edit_input['full_name'] = trim($_POST['full_name'] ?? '');
        $edit_input['group_id'] = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $edit_input['status'] = $_POST['status'] ?? 'active';
        $edit_input['notes'] = trim($_POST['notes'] ?? '');
        $edit_input['linked_user_id'] = filter_input(INPUT_POST, 'linked_user_id', FILTER_VALIDATE_INT);
        // Якщо вибрано "Не зв'язано", ID буде 0, встановлюємо NULL
        if ($edit_input['linked_user_id'] !== null && $edit_input['linked_user_id'] <= 0) {
             $edit_input['linked_user_id'] = null;
         }

         // Валідація (схожа на додавання)
         if (empty($edit_input['full_name'])) $errors['edit_full_name'] = "ПІБ не може бути порожнім.";
         if (empty($edit_input['group_id'])) $errors['edit_group_id'] = "Група має бути обрана.";
         // TODO: Додати більше валідації (статус, існування групи, існування linked_user_id якщо не null)

        if (!$edit_candidate_id) {
            set_flash_message('помилка', 'Невірний ID кандидата для редагування.');
        } elseif (empty($errors)) {
            try {
                 $sql = "UPDATE academy_candidates SET
                            full_name = :full_name,
                            group_id = :group_id,
                            status = :status,
                            notes = :notes,
                            linked_user_id = :linked_user_id,
                            updated_at = NOW()
                        WHERE id = :id";
                 $stmt = $pdo->prepare($sql);
                 $stmt->bindParam(':full_name', $edit_input['full_name']);
                 $stmt->bindParam(':group_id', $edit_input['group_id'], PDO::PARAM_INT);
                 $stmt->bindParam(':status', $edit_input['status']);
                 $stmt->bindParam(':notes', $edit_input['notes'], PDO::PARAM_STR);
                 $stmt->bindParam(':linked_user_id', $edit_input['linked_user_id'], $edit_input['linked_user_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                 $stmt->bindParam(':id', $edit_candidate_id, PDO::PARAM_INT);

                 if ($stmt->execute()) {
                    set_flash_message('успіх', 'Дані кандидата "' . escape($edit_input['full_name']) . '" оновлено.');
                    unset($_SESSION['csrf_token']);
                    smart_redirect('admin/academy_candidates.php'); exit();
                 } else { $errors['db'] = 'Не вдалося оновити дані кандидата.'; }
            } catch (PDOException $e) {
                error_log("Academy Update Candidate Error: " . $e->getMessage());
                $errors['db'] = 'Помилка БД при оновленні.';
            }
        }
    }
    if (!empty($errors)) {
        set_flash_message('помилка', 'Помилки у формі редагування.');
        // Щоб форма редагування залишилась відкритою з помилками,
        // нам треба знову отримати $edit_candidate_id (можливо з POST)
        // і встановити $action = 'edit' для GET обробки нижче.
        $_GET['action'] = 'edit';
        $_GET['id'] = filter_input(INPUT_POST, 'edit_candidate_id', FILTER_VALIDATE_INT); // Передаємо ID в GET для наступного блоку
        unset($_SESSION['csrf_token']);
     }
}

// -- Видалення Кандидата --
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_candidate'])) {
     if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену.');
    } else {
        $candidate_id_to_delete = filter_input(INPUT_POST, 'candidate_id', FILTER_VALIDATE_INT);
        if (!$candidate_id_to_delete) {
            set_flash_message('помилка', 'Невірний ID кандидата для видалення.');
        } else {
            try {
                // Отримуємо ім'я для повідомлення перед видаленням
                 $stmt_get_name = $pdo->prepare("SELECT full_name FROM academy_candidates WHERE id = :id");
                 $stmt_get_name->bindParam(':id', $candidate_id_to_delete, PDO::PARAM_INT);
                 $stmt_get_name->execute();
                 $candidate_name = $stmt_get_name->fetchColumn();

                 $stmt_delete = $pdo->prepare("DELETE FROM academy_candidates WHERE id = :id");
                 $stmt_delete->bindParam(':id', $candidate_id_to_delete, PDO::PARAM_INT);

                 if ($stmt_delete->execute()) {
                    if ($stmt_delete->rowCount() > 0) {
                        set_flash_message('успіх', 'Кандидата "' . escape($candidate_name ?: 'ID ' . $candidate_id_to_delete) . '" та всі пов\'язані дані видалено.');
                     } else {
                         set_flash_message('помилка', 'Кандидата з ID ' . $candidate_id_to_delete . ' не знайдено.');
                     }
                 } else { set_flash_message('помилка', 'Не вдалося видалити кандидата.'); }
            } catch (PDOException $e) {
                error_log("Academy Delete Candidate Error: " . $e->getMessage());
                set_flash_message('помилка', 'Помилка БД при видаленні.');
            }
        }
    }
    unset($_SESSION['csrf_token']);
    smart_redirect('admin/academy_candidates.php'); exit();
}

// --- Обробка GET-запиту для Редагування ---
$action = $_GET['action'] ?? null;
$edit_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($action === 'edit' && $edit_id) {
    try {
        $stmt_edit = $pdo->prepare("SELECT * FROM academy_candidates WHERE id = :id");
        $stmt_edit->bindParam(':id', $edit_id, PDO::PARAM_INT);
        $stmt_edit->execute();
        $editing_candidate = $stmt_edit->fetch();
        if (!$editing_candidate) {
            set_flash_message('помилка', 'Кандидата з ID ' . $edit_id . ' для редагування не знайдено.');
            $action = null; // Скидаємо дію, якщо не знайдено
        } else {
             // Заповнюємо $edit_input даними з бази (якщо не було помилки POST)
             if (empty($errors)) { // Тільки якщо НЕ було помилки POST
                 $edit_input = $editing_candidate;
                 // linked_user_id може бути NULL, обробимо це
                 $edit_input['linked_user_id'] = $editing_candidate['linked_user_id'] ?? '';
             }
             // Інакше $edit_input вже містить дані з помилкової POST форми
        }
    } catch (PDOException $e) {
        set_flash_message('помилка', 'Помилка завантаження даних кандидата для редагування.');
        error_log("Academy Edit Candidate Fetch Error: " . $e->getMessage());
        $action = null;
    }
}


// --- Отримання даних для відображення ---
$candidates = [];
$groups = [];
$users_list = []; // Для списку користувачів при зв'язуванні
$fetch_error = '';

try {
    // Отримуємо список кандидатів
    $stmt_candidates = $pdo->query("
        SELECT ac.*, ag.name as group_name, u.full_name as linked_user_name
        FROM academy_candidates ac
        LEFT JOIN academy_groups ag ON ac.group_id = ag.id
        LEFT JOIN users u ON ac.linked_user_id = u.id -- Додано JOIN для імені зв'язаного користувача
        ORDER BY ac.full_name ASC
    ");
    $candidates = $stmt_candidates->fetchAll();

    // Отримуємо список груп
    $stmt_groups = $pdo->query("SELECT id, name FROM academy_groups ORDER BY name");
    $groups = $stmt_groups->fetchAll(PDO::FETCH_KEY_PAIR);

    // Отримуємо список всіх користувачів (для зв'язування кандидата)
    // Можна фільтрувати за роллю 'lifeguard', якщо зв'язувати можна тільки з ними
    $stmt_users = $pdo->query("SELECT id, full_name, role FROM users ORDER BY full_name ASC");
    $users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $fetch_error = 'Не вдалося завантажити дані.';
    error_log("Academy Candidates Fetch Error: " . $e->getMessage());
    // Встановлюємо flash тут, бо помилка отримання даних
    set_flash_message('помилка', $fetch_error);
}

// Можливі статуси кандидатів
$candidate_statuses = [
    'active' => 'Активний',
    'passed' => 'Пройшов',
    'failed' => 'Не пройшов',
    'dropped' => 'Вибув'
];

// Підключаємо хедер
require_once '../includes/header.php';
?>

<section id="academy-candidates-page" class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-user-graduate text-indigo-500 mr-3"></i>
             <?php echo $page_title; ?>
        </h2>
         <a href="<?php echo rtrim(APP_URL, '/'); ?>/index.php#admin-academy-content" class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад до Академії
        </a>
    </div>

     <?php display_flash_message(); ?>

    <?php // --- Форма Редагування Кандидата (якщо $action === 'edit') --- ?>
    <?php if ($action === 'edit' && $editing_candidate): ?>
    <div id="edit-candidate-form-section" class="edit-candidate-form glass-effect p-5 rounded-xl shadow-lg border border-blue-200/50 ring-2 ring-blue-500/30">
         <h3 class="text-lg font-semibold text-blue-800 mb-4 border-b border-blue-200 pb-2 flex justify-between items-center">
             <span>Редагування Кандидата: <?php echo escape($editing_candidate['full_name']); ?> (ID: <?php echo $editing_candidate['id']; ?>)</span>
             <a href="academy_candidates.php" class="text-sm text-gray-500 hover:text-red-600" title="Скасувати редагування">&times; Скасувати</a>
         </h3>
         <form action="academy_candidates.php" method="POST" class="space-y-4">
             <?php echo csrf_input(); ?>
             <input type="hidden" name="update_candidate" value="1">
             <input type="hidden" name="edit_candidate_id" value="<?php echo $editing_candidate['id']; ?>">

             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                     <label for="edit_full_name" class="block text-sm font-medium text-gray-700 mb-1">ПІБ Кандидата *</label>
                     <input type="text" name="full_name" id="edit_full_name" value="<?php echo escape($edit_input['full_name'] ?? ''); ?>" required
                            class="shadow-sm block w-full px-3 py-2 border <?php echo isset($errors['edit_full_name']) ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                      <?php if(isset($errors['edit_full_name'])) echo '<p class="text-red-500 text-xs mt-1">'.$errors['edit_full_name'].'</p>'; ?>
                 </div>
                  <div>
                    <label for="edit_group_id" class="block text-sm font-medium text-gray-700 mb-1">Група *</label>
                     <select name="group_id" id="edit_group_id" required class="shadow-sm block w-full px-3 py-2 border <?php echo isset($errors['edit_group_id']) ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                         <option value="">-- Не обрано --</option>
                         <?php foreach ($groups as $id => $name): ?>
                             <option value="<?php echo $id; ?>" <?php echo (($edit_input['group_id'] ?? '') == $id) ? 'selected' : ''; ?>><?php echo escape($name); ?></option>
                         <?php endforeach; ?>
                     </select>
                     <?php if(isset($errors['edit_group_id'])) echo '<p class="text-red-500 text-xs mt-1">'.$errors['edit_group_id'].'</p>'; ?>
                 </div>
                 <div>
                    <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                    <select name="status" id="edit_status" class="shadow-sm block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($candidate_statuses as $key => $value): ?>
                            <option value="<?php echo $key; ?>" <?php echo (($edit_input['status'] ?? 'active') == $key) ? 'selected' : ''; ?>><?php echo escape($value); ?></option>
                         <?php endforeach; ?>
                    </select>
                 </div>
                 <div>
                     <label for="edit_linked_user_id" class="block text-sm font-medium text-gray-700 mb-1">Зв'язаний Користувач (після успіху)</label>
                      <select name="linked_user_id" id="edit_linked_user_id" class="shadow-sm block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                         <option value="0">-- Не зв'язано --</option>
                         <?php foreach ($users_list as $user): ?>
                             <?php // Показуємо роль користувача для ясності ?>
                             <option value="<?php echo $user['id']; ?>" <?php echo (($edit_input['linked_user_id'] ?? '') == $user['id']) ? 'selected' : ''; ?>>
                                 <?php echo escape($user['full_name']); ?> (<?php echo escape(get_role_name_ukrainian($user['role'])); ?>)
                             </option>
                         <?php endforeach; ?>
                     </select>
                     <p class="text-xs text-gray-500 mt-1">Оберіть акаунт користувача, якщо кандидат пройшов академію.</p>
                      <?php if(isset($errors['edit_linked_user_id'])) echo '<p class="text-red-500 text-xs mt-1">'.$errors['edit_linked_user_id'].'</p>'; ?>
                 </div>
                 <div class="md:col-span-2">
                     <label for="edit_notes" class="block text-sm font-medium text-gray-700 mb-1">Нотатки</label>
                     <textarea name="notes" id="edit_notes" rows="3" class="shadow-sm block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo escape($edit_input['notes'] ?? ''); ?></textarea>
                 </div>
             </div>
             <div class="flex justify-end pt-3">
                 <a href="academy_candidates.php" class="btn-secondary mr-3">Скасувати</a>
                 <button type="submit" class="btn-green">
                     <i class="fas fa-save mr-2"></i> Зберегти Зміни
                 </button>
             </div>
         </form>
    </div>
    <?php endif; ?>
    <?php // --- Кінець Форми Редагування --- ?>


    <div id="add-candidate-form-section" class="add-candidate-form glass-effect p-5 rounded-xl shadow-lg border border-white/20 <?php echo ($action === 'edit') ? 'hidden' : ''; ?>">
        <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">Додати Нового Кандидата</h3>
         <form action="academy_candidates.php" method="POST" class="space-y-4">
             <?php echo csrf_input(); ?>
            <input type="hidden" name="add_candidate" value="1">
             <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                 <div>
                    <label for="add_full_name" class="block text-sm font-medium text-gray-700 mb-1">ПІБ Кандидата *</label>
                    <input type="text" name="full_name" id="add_full_name" value="<?php echo escape($input['full_name']); ?>" required
                           class="shadow-sm block w-full px-3 py-2 border <?php echo isset($errors['full_name']) ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <?php if(isset($errors['full_name'])) echo '<p class="text-red-500 text-xs mt-1">'.$errors['full_name'].'</p>'; ?>
                 </div>
                 <div>
                    <label for="add_group_id" class="block text-sm font-medium text-gray-700 mb-1">Група *</label>
                     <select name="group_id" id="add_group_id" required class="shadow-sm block w-full px-3 py-2 border <?php echo isset($errors['group_id']) ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'; ?> rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Не обрано --</option>
                        <?php foreach ($groups as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($input['group_id'] == $id) ? 'selected' : ''; ?>><?php echo escape($name); ?></option>
                         <?php endforeach; ?>
                    </select>
                    <?php if(isset($errors['group_id'])) echo '<p class="text-red-500 text-xs mt-1">'.$errors['group_id'].'</p>'; ?>
                 </div>
                 <div>
                     <label for="add_status" class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
                     <select name="status" id="add_status" class="shadow-sm block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                         <?php foreach ($candidate_statuses as $key => $value): ?>
                             <option value="<?php echo $key; ?>" <?php echo ($input['status'] == $key) ? 'selected' : ''; ?>><?php echo escape($value); ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="md:col-span-3">
                    <label for="add_notes" class="block text-sm font-medium text-gray-700 mb-1">Нотатки</label>
                    <textarea name="notes" id="add_notes" rows="2" placeholder="Додаткова інформація..." class="shadow-sm block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo escape($input['notes'] ?? ''); ?></textarea>
                </div>
             </div>
            <div class="flex justify-end pt-3">
                <button type="submit" class="btn-green">
                    <i class="fas fa-plus mr-2"></i> Додати Кандидата
                </button>
            </div>
        </form>
    </div>


    <div class="candidates-table glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 overflow-x-auto">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Список Кандидатів</h3>
        <?php if (empty($candidates) && empty($fetch_error)): ?>
             <p class="text-center text-gray-500 italic py-6">Кандидати ще не додані.</p>
        <?php elseif (!empty($candidates)): ?>
             <table class="min-w-full divide-y divide-gray-200/50">
                 <thead class="bg-gray-50/50">
                    <tr>
                         <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ID</th>
                         <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ПІБ</th>
                         <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Група</th>
                         <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Статус</th>
                         <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Дата Зарах.</th>
                         <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Зв'язаний Користувач</th>
                         <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Дії</th>
                    </tr>
                </thead>
                 <tbody class="bg-white/70 divide-y divide-gray-200/50">
                     <?php foreach ($candidates as $candidate): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors duration-150">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500"><?php echo $candidate['id']; ?></td>
                             <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 font-medium"><?php echo escape($candidate['full_name']); ?></td>
                             <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo escape($candidate['group_name'] ?? 'Не призн.'); ?></td>
                             <td class="px-4 py-3 whitespace-nowrap text-center text-xs">
                                <?php // ... (код для відображення статусу залишається) ...
                                     $status_key = $candidate['status'] ?? 'active';
                                     $status_text = $candidate_statuses[$status_key] ?? 'Невідомо';
                                     $status_class = 'bg-gray-100 text-gray-800';
                                     if ($status_key === 'active') $status_class = 'bg-blue-100 text-blue-800';
                                     elseif ($status_key === 'passed') $status_class = 'bg-green-100 text-green-800';
                                     elseif ($status_key === 'failed') $status_class = 'bg-red-100 text-red-800';
                                     elseif ($status_key === 'dropped') $status_class = 'bg-yellow-100 text-yellow-800';
                                ?>
                                <span class="px-2 inline-flex leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                    <?php echo escape($status_text); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-600">
                                 <?php echo isset($candidate['enrollment_date']) ? date('d.m.Y', strtotime($candidate['enrollment_date'])) : '-'; ?>
                            </td>
                             <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                 <?php if ($candidate['linked_user_id']): ?>
                                    <i class="fas fa-link text-green-500 mr-1"></i> <?php echo escape($candidate['linked_user_name'] ?? 'ID: ' . $candidate['linked_user_id']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center text-sm space-x-3">
                                <a href="academy_candidates.php?action=edit&id=<?php echo $candidate['id']; ?>#edit-candidate-form-section" class="text-indigo-600 hover:text-indigo-900" title="Редагувати">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="academy_candidates.php" method="POST" class="inline" onsubmit="return confirm('Видалити кандидата \'<?php echo escape(addslashes($candidate['full_name'])); ?>\' та всі його дані з академії?');">
                                     <?php echo csrf_input(); ?>
                                     <input type="hidden" name="delete_candidate" value="1">
                                     <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                     <button type="submit" class="text-red-600 hover:text-red-900 bg-transparent border-none p-0 cursor-pointer" title="Видалити">
                                        <i class="fas fa-trash"></i>
                                     </button>
                                 </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</section>

<?php
require_once '../includes/footer.php';
?>