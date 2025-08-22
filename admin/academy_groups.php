<?php
require_once '../config.php'; // Шлях до config.php (2 рівні вище)
require_role('admin'); // Доступ тільки для адміністраторів
global $pdo;
save_current_page_for_redirect();
$page_title = "Керування Групами Академії";

// --- Обробка POST-запиту для призначення тренера ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_trainer'])) {
    // Перевірка CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('помилка', 'Помилка CSRF токену.');
    } else {
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        // Отримуємо trainer_id. Якщо вибрано "Не призначено", ID буде 0 або пустим.
        $trainer_id_input = filter_input(INPUT_POST, 'trainer_user_id', FILTER_VALIDATE_INT);
        // Якщо ID > 0, використовуємо його, інакше встановлюємо NULL
        $trainer_user_id = ($trainer_id_input && $trainer_id_input > 0) ? $trainer_id_input : null;

        if (!$group_id) {
            set_flash_message('помилка', 'Невірний ID групи.');
        } else {
            try {
                // Перевіряємо, чи існує такий тренер (якщо ID не NULL)
                $trainer_exists = false;
                if ($trainer_user_id !== null) {
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE id = :user_id AND role = 'trainer'");
                    $stmt_check->bindParam(':user_id', $trainer_user_id, PDO::PARAM_INT);
                    $stmt_check->execute();
                    if ($stmt_check->fetch()) {
                        $trainer_exists = true;
                    } else {
                        set_flash_message('помилка', 'Обраний користувач не є тренером або не існує.');
                    }
                } else {
                    $trainer_exists = true; // Дозволяємо встановити NULL (не призначено)
                }

                // Якщо тренер валідний (або NULL), оновлюємо групу
                if ($trainer_exists) {
                    $stmt_update = $pdo->prepare("UPDATE academy_groups SET trainer_user_id = :trainer_id WHERE id = :group_id");
                    $stmt_update->bindParam(':trainer_id', $trainer_user_id, $trainer_user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmt_update->bindParam(':group_id', $group_id, PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        set_flash_message('успіх', 'Тренера для групи успішно оновлено.');
                    } else {
                        set_flash_message('помилка', 'Не вдалося оновити тренера для групи.');
                    }
                }
            } catch (PDOException $e) {
                error_log("Academy Assign Trainer Error: " . $e->getMessage());
                set_flash_message('помилка', 'Помилка бази даних при призначенні тренера.');
            }
        }
    }
    unset($_SESSION['csrf_token']); // Регенеруємо токен
    smart_redirect('admin/academy_groups.php'); // Перезавантажуємо, щоб уникнути повторної відправки форми
    exit();
}


// --- Отримання даних для відображення ---
$groups_data = [];
$trainers = []; // Список користувачів з роллю 'trainer'
$fetch_error = '';

try {
    // Отримуємо список груп з іменами призначених тренерів
    $stmt_groups = $pdo->query("
        SELECT ag.id, ag.name, ag.trainer_user_id, u.full_name as trainer_name
        FROM academy_groups ag
        LEFT JOIN users u ON ag.trainer_user_id = u.id AND u.role = 'trainer' -- Додано перевірку ролі тренера при JOIN
        ORDER BY ag.id ASC
    ");
    $groups_data = $stmt_groups->fetchAll();

    // Отримуємо список всіх користувачів з роллю 'trainer' для випадаючих списків
    $stmt_trainers = $pdo->query("SELECT id, full_name FROM users WHERE role = 'trainer' ORDER BY full_name ASC");
    $trainers = $stmt_trainers->fetchAll(PDO::FETCH_ASSOC); // Отримуємо масив [ ['id'=>X, 'full_name'=>Y], ... ]

} catch (PDOException $e) {
    $fetch_error = 'Не вдалося завантажити дані груп або тренерів.';
    error_log("Academy Groups Fetch Error: " . $e->getMessage());
    set_flash_message('помилка', $fetch_error);
    $groups_data = [];
    $trainers = [];
}

// Підключаємо хедер (шлях '../../')
require_once '../includes/header.php';
?>

<section id="academy-groups-page" class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center mb-3 sm:mb-0">
             <i class="fas fa-users-cog text-indigo-500 mr-3"></i>
             <?php echo $page_title; ?>
        </h2>
         <a href="<?php echo rtrim(APP_URL, '/'); ?>/index.php#admin-academy-content" class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
             <i class="fas fa-arrow-left mr-1"></i> Назад до Академії
        </a>
    </div>

    <?php display_flash_message(); // Відображаємо повідомлення ?>

    <?php if ($fetch_error): ?>
        <p class="text-red-500"><?php echo $fetch_error; ?></p>
     <?php elseif (empty($groups_data)): ?>
        <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
            <p class="text-gray-500 italic "><i class="fas fa-info-circle mr-2"></i>Групи ще не створені в базі даних.</p>
             <p class="mt-2 text-sm text-gray-600">Спробуйте виконати SQL-запит для їх створення:</p>
             <code class="block bg-gray-100 p-2 rounded text-xs text-left mt-1">INSERT INTO `academy_groups` (`name`) VALUES ('Група 1'), ('Група 2'), ('Група 3'), ('Група 4');</code>
        </div>
    <?php else: ?>
         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($groups_data as $group): ?>
                <div class="group-card glass-effect p-5 rounded-xl shadow-lg border border-white/20 flex flex-col justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo escape($group['name']); ?></h3>
                        <p class="text-sm text-gray-600 mb-4">
                             Поточний тренер:
                             <strong class="<?php echo $group['trainer_name'] ? 'text-indigo-700' : 'text-gray-500 italic'; ?>">
                                <?php echo escape($group['trainer_name'] ?? 'Не призначено'); ?>
                            </strong>
                        </p>
                    </div>
                    <form action="academy_groups.php" method="POST" class="mt-auto pt-4 border-t border-gray-200/50">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                         <input type="hidden" name="assign_trainer" value="1">
                         <label for="trainer_user_id_<?php echo $group['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Призначити тренера:</label>
                         <div class="flex items-center space-x-2">
                            <select name="trainer_user_id" id="trainer_user_id_<?php echo $group['id']; ?>" class="flex-grow shadow-sm border border-gray-300 rounded-md py-2 px-3 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                 <option value="0">-- Не призначено --</option> <?php // Значення 0 або "" для "Не призначено" ?>
                                 <?php foreach ($trainers as $trainer): ?>
                                    <option value="<?php echo $trainer['id']; ?>" <?php echo ($group['trainer_user_id'] == $trainer['id']) ? 'selected' : ''; ?>>
                                         <?php echo escape($trainer['full_name']); ?> (ID: <?php echo $trainer['id']; ?>)
                                     </option>
                                 <?php endforeach; ?>
                            </select>
                             <button type="submit" class="btn-green !py-2 flex-shrink-0" title="Зберегти тренера для цієї групи">
                                 <i class="fas fa-save"></i>
                             </button>
                         </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
     <?php endif; ?>

</section>

<?php
// Підключаємо футер (шлях '../../')
require_once '../includes/footer.php';
?>