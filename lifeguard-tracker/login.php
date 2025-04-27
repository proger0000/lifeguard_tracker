<?php
require_once 'config.php'; // Includes session_start() and helpers

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$input_email = ''; // To repopulate email field

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_email = trim($_POST['email'] ?? ''); // Store email for repopulation

    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
         $error_message = 'Помилка CSRF токену. Будь ласка, спробуйте знову.';
    } else {
        $email = $input_email;
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error_message = 'Будь ласка, заповніть поля Email та Пароль.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Невірний формат Email.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role FROM users WHERE email = :email");
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->execute();
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Password is correct, set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    session_regenerate_id(true); // Regenerate session ID
                    unset($_SESSION['action_pending']); // Clear any pending action
                    set_flash_message('успіх', 'Ви успішно увійшли в систему.');
                    header('Location: index.php');
                    exit();
                } else {
                    $error_message = 'Невірний Email або Пароль.';
                }
            } catch (PDOException $e) {
                // error_log("Login DB Error: " . $e->getMessage());
                $error_message = 'Виникла помилка бази даних. Спробуйте пізніше.';
            }
        }
    }
    // Regenerate CSRF token after failed attempt
    unset($_SESSION['csrf_token']);
}

// Include header (DOCTYPE, head, styles, header navigation)
require_once 'includes/header.php';
?>

<!-- Login Form Content - оновлено стилі -->
<!-- Використовуємо flex для центрування на сторінці -->
<div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8" style="min-height: calc(100vh - 150px);"> <!-- Віднімаємо приблизну висоту хедера+футера -->
    <div class="max-w-md w-full space-y-8 bg-white p-8 sm:p-10 rounded-xl shadow-lg"> <!-- Білий фон, тінь, заокруглення -->
        <div>
            <h2 class="mt-6 text-center text-3xl font-bold text-gray-900"> <!-- Темний заголовок -->
                Вхід до Системи
            </h2>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert"> <!-- Оновлений стиль помилки -->
                <p><strong class="font-bold">Помилка!</strong> <?php echo escape($error_message); ?></p>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST" novalidate>
            <?php csrf_input(); // Add CSRF token field ?>
            <input type="hidden" name="remember" value="true">
            <div class="rounded-md shadow-sm -space-y-px">
                <div class="mb-4"> <!-- Додано відступ -->
                    <label for="email" class="sr-only">Email</label> <!-- sr-only для доступності, якщо видимий лейбл не потрібен -->
                     <!-- Оновлено стилі поля вводу -->
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="appearance-none relative block w-full px-3 py-2 border <?php echo (!empty($error_message) && (strpos($error_message, 'Email') !== false || strpos($error_message, 'заповніть') !== false)) ? 'border-red-500' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 focus:z-10 sm:text-sm shadow-sm"
                           placeholder="Email" value="<?php echo escape($input_email); // Repopulate email ?>">
                </div>
                <div>
                    <label for="password" class="sr-only">Пароль</label>
                     <!-- Оновлено стилі поля вводу -->
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="appearance-none relative block w-full px-3 py-2 border <?php echo (!empty($error_message) && (strpos($error_message, 'Пароль') !== false || strpos($error_message, 'заповніть') !== false)) ? 'border-red-500' : 'border-gray-300'; ?> placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 focus:z-10 sm:text-sm shadow-sm"
                           placeholder="Пароль">
                </div>
            </div>

            <!-- Пам'ятати мене та Забули пароль (опціонально) -->
            <!-- <div class="flex items-center justify-between text-sm">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-gray-900"> Запам'ятати мене </label>
                </div>
                <div class="font-medium text-red-600 hover:text-red-500">
                    <a href="#"> Забули пароль? </a>
                </div>
            </div> -->

            <div>
                 <!-- Застосовано клас .btn-red (або еквівалент Tailwind) -->
                <button type="submit" class="group relative w-full flex justify-center btn-red">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-lock h-5 w-5 text-red-200 group-hover:text-red-100"></i>
                    </span>
                    Увійти
                </button>
            </div>
             <p class="mt-2 text-center text-sm text-gray-600">
                 Немає акаунту?
                 <!-- Оновлено стиль посилання -->
                 <a href="register.php" class="font-medium text-red-600 hover:text-red-500">
                     Зареєструватися
                 </a>
            </p>
        </form>
    </div>
</div>

<?php
// Include footer (closing main, footer, scripts, closing html)
require_once 'includes/footer.php';
?>