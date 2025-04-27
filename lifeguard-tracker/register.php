<?php
require_once 'config.php'; // Підключення конфігурації (включає session_start() та хелпери)
global $pdo; // Доступ до PDO

// Перенаправлення, якщо користувач вже увійшов
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Ініціалізація змінних
$error_message = ''; // Загальне повідомлення про помилку
$errors = []; // Для помилок конкретних полів
$input = [];    // Для збереження введених даних у формі (sticky form)

// Обробка POST-запиту
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Збереження введених даних для повторного відображення
    $input['full_name'] = trim($_POST['full_name'] ?? '');
    $input['email'] = trim($_POST['email'] ?? '');
    // Пароль не зберігаємо для повторного відображення
    // Роль за замовчуванням для реєстрації
    $input['role'] = 'lifeguard';

    // Перевірка CSRF токена
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Помилка CSRF токену. Будь ласка, спробуйте знову.';
    } else {
        // Отримання паролів (не для repopulate)
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // --- Валідація ---
        if (empty($input['full_name'])) {
            $errors['full_name'] = "ПІБ є обов'язковим.";
        }
        if (empty($input['email'])) {
            $errors['email'] = "Email є обов'язковим.";
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Невірний формат Email.";
        } else {
            // Перевірка, чи існує email
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->bindParam(':email', $input['email']);
                $stmt->execute();
                if ($stmt->fetch()) {
                    $errors['email'] = 'Користувач з таким Email вже існує.';
                }
            } catch (PDOException $e) {
                $error_message = 'Помилка під час перевірки Email. Спробуйте пізніше.';
                // error_log("Register Email Check Error: " . $e->getMessage());
            }
        }
        if (empty($password)) {
            $errors['password'] = "Пароль є обов'язковим.";
        } elseif (mb_strlen($password) < 6) { // Використовуємо mb_strlen
            $errors['password'] = 'Пароль повинен містити щонайменше 6 символів.';
        }
        if (empty($confirm_password)) {
             $errors['confirm_password'] = "Підтвердження пароля є обов'язковим.";
        } elseif ($password !== $confirm_password) {
             $errors['confirm_password'] = 'Паролі не співпадають.';
        }

        // --- Обробка, якщо немає помилок ---
        if (empty($errors) && empty($error_message)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $default_role = 'lifeguard'; // Явно вказуємо роль для INSERT

            try {
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role)");
                $stmt->bindParam(':full_name', $input['full_name']);
                $stmt->bindParam(':email', $input['email']);
                $stmt->bindParam(':password_hash', $password_hash);
                $stmt->bindParam(':role', $default_role);

                if ($stmt->execute()) {
                    set_flash_message('успіх', 'Реєстрація успішна! Тепер ви можете увійти.');
                    unset($_SESSION['csrf_token']); // Очистити CSRF токен після успішної реєстрації
                    header('Location: login.php');
                    exit();
                } else {
                    $error_message = 'Не вдалося створити акаунт. Спробуйте пізніше.';
                }
            } catch (PDOException $e) {
                // Перевіряємо на дублікат email, який міг виникнути між перевіркою і вставкою (race condition)
                if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                     $errors['email'] = 'Користувач з таким Email вже існує (помилка БД).';
                 } else {
                     $error_message = 'Виникла помилка бази даних під час реєстрації.';
                    // error_log("Register DB Error: " . $e->getMessage());
                 }
            }
        } elseif (empty($error_message)) {
             // Якщо є помилки в $errors, але немає загальної $error_message
            $error_message = 'Будь ласка, виправте помилки у формі.';
        }
    }
    // Регенерація CSRF токена при невдалій спробі
    if ($error_message || !empty($errors)) {
        unset($_SESSION['csrf_token']);
    }
}

require_once 'includes/header.php'; // Підключення шапки сайту
?>

<div class="flex items-center justify-center min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-gray-50 p-8 sm:p-10 rounded-xl shadow-lg">
        <div>
            <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">
                Реєстрація нового користувача
            </h2>
        </div>

        <?php if ($error_message || !empty($errors)): ?>
            <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Помилка!</strong>
                <?php if ($error_message): ?>
                    <p><?php echo escape($error_message); ?></p>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        <?php foreach ($errors as $field_error): ?>
                            <li><?php echo escape($field_error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <form class="mt-8 space-y-6" action="register.php" method="POST" novalidate>
            <?php csrf_input(); ?>

            <div class="mb-4">
                 <label for="full_name" class="block text-gray-700 text-sm font-bold mb-2">ПІБ (Прізвище Ім'я По батькові)</label>
                 <input type="text" name="full_name" id="full_name" required placeholder="Введіть повне ім'я"
                        class="shadow-sm appearance-none border <?php echo isset($errors['full_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        value="<?php echo escape($input['full_name'] ?? ''); ?>">
                 <?php if (isset($errors['full_name'])): ?>
                     <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['full_name']); ?></p>
                 <?php endif; ?>
            </div>

            <div class="mb-4">
                 <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                 <input type="email" name="email" id="email" required placeholder="Введіть email адресу"
                        class="shadow-sm appearance-none border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        value="<?php echo escape($input['email'] ?? ''); ?>">
                 <?php if (isset($errors['email'])): ?>
                     <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['email']); ?></p>
                 <?php endif; ?>
            </div>

             <div class="mb-4">
                 <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Пароль</label>
                 <input type="password" name="password" id="password" required placeholder="Введіть пароль (мін. 6 символів)"
                        class="shadow-sm appearance-none border <?php echo isset($errors['password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                 <?php if (isset($errors['password'])): ?>
                     <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['password']); ?></p>
                 <?php endif; ?>
            </div>

            <div class="mb-6"> <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Підтвердіть Пароль</label>
                 <input type="password" name="confirm_password" id="confirm_password" required placeholder="Повторіть пароль"
                        class="shadow-sm appearance-none border <?php echo isset($errors['confirm_password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                 <?php if (isset($errors['confirm_password'])): ?>
                     <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['confirm_password']); ?></p>
                 <?php endif; ?>
            </div>


            <div>
                 <button type="submit" class="w-full flex justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-4 focus:ring-indigo-300 transition duration-150 ease-in-out">
                     Зареєструватися
                 </button>
            </div>

            <p class="mt-4 text-center text-sm text-gray-600">
                 Вже маєте акаунт?
                 <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-800">
                     Увійти
                 </a>
            </p>
        </form>
    </div>
</div>

<?php
require_once 'includes/footer.php'; // Підключення підвалу сайту
?>