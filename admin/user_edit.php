<?php
// --- Конфігурація та Ініціалізація ---
require_once '../config.php'; // Підключення файлу конфігурації (на рівень вище)
global $pdo; // Доступ до глобального об'єкту PDO
require_role('admin'); // Перевірка, чи користувач має роль 'admin'
save_current_page_for_redirect();
$page_title = "Редагувати Користувача"; // Назва сторінки

// --- Отримання та валідація ID користувача ---
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    set_flash_message('помилка', 'Невірний ID користувача.');
    smart_redirect('index.php', $_GET, 'admin-users-content');
    exit();
}

// --- Ініціалізація змінних ---
$user = null; // Для зберігання даних користувача з БД
$input = ['full_name' => '', 'email' => '', 'role' => '']; // Для поточних даних форми (з БД або POST)
$errors = []; // Масив для зберігання помилок валідації
$available_roles = [ // Доступні ролі
    'admin' => 'Адміністратор',
    'director' => 'Директор',
    'duty_officer' => 'Черговий',
    'lifeguard' => 'Рятувальник',
    'trainer' => 'Тренер',
];

// --- Завантаження Існуючих Даних Користувача ---
try {
    $stmt_fetch = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE id = :id");
    $stmt_fetch->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $user = $stmt_fetch->fetch(PDO::FETCH_ASSOC); // Використовуємо FETCH_ASSOC

    if (!$user) {
        set_flash_message('помилка', 'Користувача з ID ' . htmlspecialchars($user_id) . ' не знайдено.');
        smart_redirect('index.php', $_GET, 'admin-users-content');        exit();
    }

    // Ініціалізація полів форми даними з бази даних
    $input['full_name'] = $user['full_name'];
    $input['email'] = $user['email'];
    $input['role'] = $user['role'];

} catch (PDOException $e) {
    // Можна додати логування помилки
    // error_log("User Edit Fetch Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка завантаження даних користувача.');
    smart_redirect('index.php', $_GET, 'admin-users-content');    exit();
}


// --- Обробка Відправки Форми (POST-запит) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Перевірка CSRF токена
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Помилка CSRF токену. Спробуйте оновити сторінку.';
    } else {
        // Отримання та очищення даних з форми
        $input['full_name'] = trim($_POST['full_name'] ?? '');
        $input['email'] = trim($_POST['email'] ?? '');
        // Роль береться з POST, якщо вона не заблокована (не редагування себе)
        // Якщо поле ролі заблоковане, воно не прийде в POST, тому беремо поточне значення
        $submitted_role = $_POST['role'] ?? null;
        $input['role'] = ($user_id == $_SESSION['user_id']) ? $user['role'] : ($submitted_role ?? ''); // Зберігаємо поточну роль, якщо редагуємо себе, інакше - з POST

        $password = $_POST['password'] ?? ''; // Не trim(), бо пароль може мати пробіли
        $confirm_password = $_POST['confirm_password'] ?? '';

        // --- Валідація Даних ---
        if (empty($input['full_name'])) {
            $errors['full_name'] = "ПІБ є обов'язковим.";
        }
        if (empty($input['email'])) {
            $errors['email'] = "Email є обов'язковим.";
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Невірний формат Email.";
        } else {
            // Перевірка унікальності Email (крім поточного користувача)
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :current_id");
                $stmt_check->bindParam(':email', $input['email']);
                $stmt_check->bindParam(':current_id', $user_id, PDO::PARAM_INT);
                $stmt_check->execute();
                if ($stmt_check->fetch()) {
                    $errors['email'] = 'Інший користувач з таким Email вже існує.';
                }
            } catch (PDOException $e) {
                $errors['db_check'] = 'Помилка перевірки Email в базі даних.';
                // error_log("User Edit Email Check Error: " . $e->getMessage());
            }
        }
        // Перевірка валідності ролі
        if (empty($input['role']) || !array_key_exists($input['role'], $available_roles)) {
            // Ця помилка не повинна виникати, якщо користувач не редагує себе,
            // бо select не дозволить вибрати неіснуючу роль.
            // Актуально, якщо з POST приходить щось не те або при редагуванні себе щось пішло не так.
             if ($user_id != $_SESSION['user_id']) { // Показуємо помилку тільки якщо роль можна було змінити
                 $errors['role'] = "Необхідно вибрати дійсну роль.";
             }
        }

        // Валідація пароля ТІЛЬКИ якщо введено новий
        $update_password = false;
        if (!empty($password)) {
            if (mb_strlen($password) < 6) { // Використовуємо mb_strlen для багатобайтних символів (про всяк випадок)
                $errors['password'] = 'Новий пароль повинен містити щонайменше 6 символів.';
            } elseif ($password !== $confirm_password) {
                $errors['confirm_password'] = 'Паролі не співпадають.';
            } else {
                $update_password = true; // Позначка, що пароль потрібно оновити
            }
        }

        // --- Оновлення даних в БД, якщо немає помилок валідації ---
        if (empty($errors)) {
            try {
                // Базовий SQL запит
                $sql = "UPDATE users SET full_name = :full_name, email = :email, role = :role";
                $params = [
                    ':full_name' => $input['full_name'],
                    ':email' => $input['email'],
                    ':role' => $input['role'],
                    ':id' => $user_id // ID для умови WHERE
                ];

                // Додавання оновлення пароля, якщо потрібно
                if ($update_password) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash = :password_hash";
                    $params[':password_hash'] = $password_hash;
                }

                $sql .= " WHERE id = :id"; // Додаємо умову WHERE

                $stmt_update = $pdo->prepare($sql);

                // Виконання запиту з передачею параметрів
                if ($stmt_update->execute($params)) {
                    set_flash_message('успіх', 'Дані користувача "' . escape($input['full_name']) . '" успішно оновлено.');

                    // Оновлення даних сесії, якщо адміністратор редагує власний профіль
                    if ($user_id == $_SESSION['user_id']) {
                        $_SESSION['full_name'] = $input['full_name'];
                        $_SESSION['email'] = $input['email'];
                        // Зміна ролі для себе може вимагати повторного входу або перевірки прав доступу
                        // Поточний код оновлює роль в сесії, але доступ може контролюватися інакше
                        $_SESSION['user_role'] = $input['role'];
                    }

                    unset($_SESSION['csrf_token']); // Очистити токен після успішної операції
                    smart_redirect('index.php', $_GET, 'admin-users-content');                    exit();
                } else {
                    $errors['db'] = 'Не вдалося оновити дані користувача в базі даних.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка бази даних під час оновлення користувача.';
                // error_log("User Edit DB Error: " . $e->getMessage());
                // Спроба виявити помилку дублювання email з повідомлення PDO
                if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                    $errors['email'] = 'Інший користувач з таким Email вже існує (помилка БД).';
                    // Видаляємо загальну помилку БД, якщо є специфічна
                    unset($errors['db']);
                }
            }
        }
    }

    // Якщо були помилки валідації або БД, CSRF токен потрібно згенерувати заново для наступної спроби
    if (!empty($errors)) {
        unset($_SESSION['csrf_token']); // Видаляємо старий токен
    }
}

// --- Включення Хедера ---
require_once '../includes/header.php'; // Підключення шапки сайту
?>

<div class="max-w-2xl mx-auto bg-gray-50 p-8 rounded-lg shadow-md mt-8 mb-8">
    <h2 class="text-2xl font-bold mb-6 text-gray-900"><?php echo escape($page_title); ?> (ID: <?php echo escape($user_id); ?>)</h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Виявлено помилки:</strong>
            <ul class="mt-2 list-disc list-inside text-sm">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="user_edit.php?id=<?php echo escape($user_id); ?>" method="POST" novalidate>
        <?php echo csrf_input(); // Функція для генерації прихованого поля CSRF токена ?>
        <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SESSION['previous_page'] ?? '../index.php#admin-users-content'); ?>">
        <div class="mb-4">
            <label for="full_name" class="block text-gray-700 text-sm font-bold mb-2">ПІБ</label>
            <input type="text" name="full_name" id="full_name" required
                   value="<?php echo escape($input['full_name']); ?>"
                   class="shadow-sm appearance-none border <?php echo isset($errors['full_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['full_name'])): ?>
                <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['full_name']); ?></p>
            <?php endif; ?>
        </div>

        <div class="mb-4">
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
            <input type="email" name="email" id="email" required
                   value="<?php echo escape($input['email']); ?>"
                   class="shadow-sm appearance-none border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['email'])): ?>
                <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['email']); ?></p>
            <?php endif; ?>
        </div>

        <div class="mb-4">
            <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Роль</label>
            <select name="role" id="role" required
                    class="shadow-sm border <?php echo isset($errors['role']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?php echo ($user_id == $_SESSION['user_id']) ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                    <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled title="Зміна власної ролі не рекомендується і може вимагати повторного входу."' : ''; // Блокування зміни власної ролі ?> >
                <?php foreach ($available_roles as $role_code => $role_name): ?>
                    <option value="<?php echo escape($role_code); ?>" <?php echo ($input['role'] === $role_code) ? 'selected' : ''; ?>>
                        <?php echo escape($role_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($user_id == $_SESSION['user_id']): ?>
                <input type="hidden" name="role" value="<?php echo escape($input['role']); ?>" />
                <p class="text-sm text-yellow-700 mt-1 bg-yellow-50 p-2 rounded">Зміна власної ролі не рекомендується або може вимагати повторного входу.</p>
            <?php endif; ?>
            <?php if (isset($errors['role'])): ?>
                <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['role']); ?></p>
            <?php endif; ?>
        </div>

        <hr class="my-6 border-gray-300">

        <p class="text-sm text-gray-600 mb-4">Змінити пароль (залиште порожнім, щоб не змінювати):</p>

        <div class="mb-4">
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Новий Пароль</label>
            <input type="password" name="password" id="password"
                   class="shadow-sm appearance-none border <?php echo isset($errors['password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['password'])): ?>
                <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['password']); ?></p>
            <?php endif; ?>
        </div>

        <div class="mb-6">
            <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Підтвердіть Новий Пароль</label>
            <input type="password" name="confirm_password" id="confirm_password"
                   class="shadow-sm appearance-none border <?php echo isset($errors['confirm_password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['confirm_password'])): ?>
                <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['confirm_password']); ?></p>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-4 focus:ring-indigo-300 transition duration-150 ease-in-out">
                <i class="fas fa-save mr-2"></i> Зберегти Зміни
            </button>
            <a href="../index.php#admin-users-content" class="inline-block align-baseline font-bold text-sm text-indigo-600 hover:text-indigo-800">
                Скасувати
            </a>
        </div>
    </form>
</div>
<?php
// --- Включення Футера ---
require_once '../includes/footer.php'; // Підключення підвалу сайту
?>