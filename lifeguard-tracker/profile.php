<?php
require_once 'config.php'; // Підключення конфігурації та функцій
require_login(); // Доступно для всіх залогінених

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'];
$user_email = $_SESSION['email'];
$user_role_ukrainian = get_role_name_ukrainian($_SESSION['user_role']);

$password_error_message = '';
$password_success_message = '';
$password_errors = [];

// Обробка POST-запиту для зміни пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (ваш існуючий код обробки зміни пароля залишається тут) ...
     if (isset($_POST['change_password'])) { // Перевірка, що це запит саме на зміну пароля
        if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
            $password_error_message = 'Помилка CSRF токену. Спробуйте знову.';
        } else {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';

            if (empty($current_password)) $password_errors['current'] = 'Поточний пароль є обов\'язковим.';
            if (empty($new_password)) $password_errors['new'] = 'Новий пароль є обов\'язковим.';
            elseif (strlen($new_password) < 6) $password_errors['new'] = 'Новий пароль повинен містити щонайменше 6 символів.';
            if ($new_password !== $confirm_new_password) $password_errors['confirm'] = 'Нові паролі не співпадають.';

            if (empty($password_errors)) {
                try {
                    $stmt_get = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
                    $stmt_get->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $stmt_get->execute();
                    $current_user = $stmt_get->fetch();

                    if (!$current_user) {
                         $password_error_message = 'Помилка: Користувача не знайдено.';
                    } elseif (password_verify($current_password, $current_user['password_hash'])) {
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt_update = $pdo->prepare("UPDATE users SET password_hash = :new_hash WHERE id = :id");
                        $stmt_update->bindParam(':new_hash', $new_password_hash);
                        $stmt_update->bindParam(':id', $user_id, PDO::PARAM_INT);

                        if ($stmt_update->execute()) {
                            $password_success_message = 'Пароль успішно змінено!';
                            unset($_SESSION['csrf_token']);
                        } else {
                            $password_error_message = 'Не вдалося оновити пароль. Спробуйте пізніше.';
                        }
                    } else {
                         $password_errors['current'] = 'Поточний пароль введено неправильно.';
                    }
                } catch (PDOException $e) {
                    // error_log("Password Change DB Error: " . $e->getMessage());
                    $password_error_message = 'Виникла помилка бази даних під час зміни пароля.';
                }
            } else {
                $password_error_message = 'Будь ласка, виправте помилки у формі.';
            }
        }
        if ($password_error_message || !empty($password_errors)) {
             unset($_SESSION['csrf_token']);
        }
    }
}


// Включаємо хедер
require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-2xl"> <!-- Обмежено ширину -->
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Ваш Профіль</h2>

    <!-- === Секція Інформації Профілю (Додано/Оновлено) === -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-3 mb-4">Інформація</h3>
        <dl class="space-y-2"> <!-- Використання списку визначень для кращої семантики -->
            <div class="flex flex-col sm:flex-row sm:items-center">
                <dt class="sm:w-1/4 font-semibold text-gray-600">ПІБ:</dt>
                <dd class="text-gray-800 mt-1 sm:mt-0 sm:w-3/4"><?php echo escape($user_full_name); ?></dd>
            </div>
             <div class="flex flex-col sm:flex-row sm:items-center">
                <dt class="sm:w-1/4 font-semibold text-gray-600">Email:</dt>
                <dd class="text-gray-800 mt-1 sm:mt-0 sm:w-3/4"><?php echo escape($user_email); ?></dd>
             </div>
             <div class="flex flex-col sm:flex-row sm:items-center">
                 <dt class="sm:w-1/4 font-semibold text-gray-600">Роль:</dt>
                <dd class="text-gray-800 mt-1 sm:mt-0 sm:w-3/4"><?php echo escape($user_role_ukrainian); ?></dd>
            </div>
        </dl>
    </div>
    <!-- ================================================ -->


    <!-- Секція Зміни Пароля (залишається як була) -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-700 border-b border-gray-200 pb-3 mb-4">Зміна Пароля</h3>

        <?php if ($password_success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4" role="alert">
                <p><?php echo escape($password_success_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($password_error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4" role="alert">
                <p><strong class="font-bold">Помилка!</strong> <?php echo escape($password_error_message); ?></p>
            </div>
        <?php endif; ?>

        <form action="profile.php" method="POST" class="space-y-4" novalidate>
             <?php csrf_input(); ?>
            <input type="hidden" name="change_password" value="1">

             <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Поточний пароль</label>
                <input type="password" name="current_password" id="current_password" required
                       class="shadow-sm appearance-none block w-full px-3 py-2 border <?php echo isset($password_errors['current']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                 <?php if (isset($password_errors['current'])): ?>
                    <p class="text-red-500 text-xs italic mt-1"><?php echo $password_errors['current']; ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">Новий пароль (мін. 6 символів)</label>
                <input type="password" name="new_password" id="new_password" required
                       class="shadow-sm appearance-none block w-full px-3 py-2 border <?php echo isset($password_errors['new']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                 <?php if (isset($password_errors['new'])): ?>
                    <p class="text-red-500 text-xs italic mt-1"><?php echo $password_errors['new']; ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="confirm_new_password" class="block text-sm font-medium text-gray-700 mb-1">Підтвердіть новий пароль</label>
                <input type="password" name="confirm_new_password" id="confirm_new_password" required
                       class="shadow-sm appearance-none block w-full px-3 py-2 border <?php echo isset($password_errors['confirm']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                 <?php if (isset($password_errors['confirm'])): ?>
                    <p class="text-red-500 text-xs italic mt-1"><?php echo $password_errors['confirm']; ?></p>
                <?php endif; ?>
            </div>

            <div class="pt-2"> <!-- Невеликий відступ перед кнопкою -->
                <button type="submit" class="btn-red inline-flex items-center">
                    <i class="fas fa-save mr-2"></i>Зберегти Пароль
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Включаємо футер
require_once 'includes/footer.php';
?>