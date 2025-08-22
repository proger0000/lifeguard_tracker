<?php
require_once 'config.php'; // Підключає налаштування, сесію та допоміжні функції

// Перенаправлення, якщо користувач вже увійшов
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$input_email = ''; // Для повторного заповнення поля email

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_email = trim($_POST['email'] ?? ''); // Зберігаємо email

    // Перевірка CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Помилка CSRF токену. Будь ласка, спробуйте знову.';
    } else {
        $email = $input_email;
        $password = $_POST['password'] ?? '';

        // Валідація
        if (empty($email) || empty($password)) {
            $error_message = 'Будь ласка, заповніть поля Email та Пароль.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Невірний формат Email.';
        } else {
            // Перевірка в базі даних
            try {
                $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role FROM users WHERE email = :email");
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                $user = $stmt->fetch();

                // Перевірка пароля та встановлення сесії
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    session_regenerate_id(true); // Важливо для безпеки
                    unset($_SESSION['action_pending']); // Очистити незавершену дію
                    // Встановлюємо flash-повідомлення типу 'успіх'
                    set_flash_message('успіх', 'Ви успішно увійшли в систему.');
                    header('Location: index.php');
                    exit();
                } else {
                    $error_message = 'Невірний Email або Пароль.';
                }
            } catch (PDOException $e) {
                // error_log("Login DB Error: " . $e->getMessage()); // Рекомендується логувати помилки
                $error_message = 'Виникла помилка бази даних. Спробуйте пізніше.';
            }
        }
    }
    
}

// Підключаємо хедер
require_once 'includes/header.php';
?>

<main class="flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8 text-white">
    <div class="max-w-md w-full space-y-6 glass-effect p-6 sm:p-8">
        <div>
            <h2 class="mt-4 text-center text-2xl sm:text-3xl font-bold text-white">
                Вхід до Системи
            </h2>
        </div>

        <?php if ($error_message): ?>
            <?php
                // Встановлюємо тимчасове flash-повідомлення типу 'помилка' для відображення
                $_SESSION['flash_message'] = ['type' => 'помилка', 'text' => $error_message];
                display_flash_message();
            ?>
        <?php endif; ?>

        <form class="mt-6 space-y-4" action="login.php" method="POST" novalidate>
            <?php echo csrf_input(); // Додаємо приховане поле CSRF ?>
            <input type="hidden" name="remember" value="true">
            <div class="rounded-md shadow-sm">
                <div class="mb-3"> <label for="email" class="sr-only">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="shadow-sm appearance-none relative block w-full px-3 py-2 border <?php echo (!empty($error_message) && (strpos($error_message, 'Email') !== false || strpos($error_message, 'заповніть') !== false)) ? 'border-red-500' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 focus:z-10 text-sm sm:text-base"
                           placeholder="Email" value="<?php echo escape($input_email); ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Пароль</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="shadow-sm appearance-none relative block w-full px-3 py-2 border <?php echo (!empty($error_message) && (strpos($error_message, 'Пароль') !== false || strpos($error_message, 'заповніть') !== false)) ? 'border-red-500' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 focus:z-10 text-sm sm:text-base"
                           placeholder="Пароль">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center btn-red"> <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-lock h-4 w-4 sm:h-5 sm:w-5 text-red-200 group-hover:text-red-100"></i>
                    </span>
                    Увійти
                </button>
            </div>
            <p class="mt-4 text-center text-xs sm:text-sm text-gray-200">
                 Немає акаунту?
                 <a href="register.php" class="font-medium text-red-400 hover:text-red-300">
                     Зареєструватися
                 </a>
            </p>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>

