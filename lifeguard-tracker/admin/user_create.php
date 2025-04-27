<?php
require_once '../config.php'; // Go up one directory for config
global $pdo;
require_role('admin'); // Only admins

$page_title = "Додати Нового Користувача";
$input = ['full_name' => '', 'email' => '', 'role' => 'lifeguard']; // Default role
$errors = [];
$available_roles = [ // Define available roles for the dropdown
    'admin' => 'Адміністратор',
    'duty_officer' => 'Черговий',
    'lifeguard' => 'Рятувальник',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
         $errors['csrf'] = 'Помилка CSRF токену.';
    } else {
        // Get submitted data
        $input['full_name'] = trim($_POST['full_name'] ?? '');
        $input['email'] = trim($_POST['email'] ?? '');
        $input['role'] = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // --- Validation ---
        if (empty($input['full_name'])) {
            $errors['full_name'] = "ПІБ є обов'язковим.";
        }
        if (empty($input['email'])) {
            $errors['email'] = "Email є обов'язковим.";
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Невірний формат Email.";
        } else {
            // Check email uniqueness
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt_check->bindParam(':email', $input['email']);
                $stmt_check->execute();
                if ($stmt_check->fetch()) {
                    $errors['email'] = 'Користувач з таким Email вже існує.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка перевірки Email.';
                // error_log("User Create Email Check Error: " . $e->getMessage());
            }
        }
        if (empty($password)) {
            $errors['password'] = "Пароль є обов'язковим.";
        } elseif (strlen($password) < 6) { // Basic length check
             $errors['password'] = 'Пароль повинен містити щонайменше 6 символів.';
        }
        if ($password !== $confirm_password) {
             $errors['confirm_password'] = 'Паролі не співпадають.';
        }
        if (empty($input['role']) || !array_key_exists($input['role'], $available_roles)) {
            $errors['role'] = "Необхідно вибрати дійсну роль.";
        }

        // --- Insert if no errors ---
        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
             try {
                $stmt_insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (:full_name, :email, :password_hash, :role)");
                $stmt_insert->bindParam(':full_name', $input['full_name']);
                $stmt_insert->bindParam(':email', $input['email']);
                $stmt_insert->bindParam(':password_hash', $password_hash);
                $stmt_insert->bindParam(':role', $input['role']);

                if ($stmt_insert->execute()) {
                    set_flash_message('успіх', 'Користувача "' . escape($input['full_name']) . '" успішно створено.');
                    unset($_SESSION['csrf_token']); // Clear token on success
                    header('Location: ../index.php#admin-users-content');
                    exit();
                } else {
                    $errors['db'] = 'Не вдалося створити користувача.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка бази даних під час створення користувача.';
                // error_log("User Create DB Error: " . $e->getMessage());
                if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email') !== false) {
                    $errors['email'] = 'Користувач з таким Email вже існує (помилка БД).'; // Catch potential race condition
                }
            }
        }
    }
    // Regenerate CSRF token on error
     if (!empty($errors)) {
         unset($_SESSION['csrf_token']);
    }
}

// Include header - він має містити нові стилі та шрифт
require_once '../includes/header.php';
?>

<!-- Оновлено стилі контейнера форми -->
<div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-xl mt-6">
    <!-- Оновлено колір заголовка -->
    <h2 class="text-2xl font-bold mb-6 text-gray-800"><?php echo $page_title; ?></h2>

    <?php if (!empty($errors)): ?>
        <!-- Стилі для блоку помилок можна залишити як є, вони вже підходять -->
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Помилка!</strong>
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $field => $error_msg): ?>
                    <li><?php echo escape($error_msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="user_create.php" method="POST" novalidate>
        <?php csrf_input(); ?>

        <div class="mb-4">
             <!-- Оновлено колір лейбла -->
            <label for="full_name" class="block text-gray-700 text-sm font-bold mb-2">ПІБ (Прізвище Ім'я По батькові)</label>
            <!-- Оновлено стилі поля вводу (додано фокус) -->
            <input type="text" name="full_name" id="full_name" required value="<?php echo escape($input['full_name']); ?>"
                   class="shadow-sm appearance-none border <?php echo isset($errors['full_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
            <?php if (isset($errors['full_name'])): ?>
                <p class="text-red-500 text-xs italic mt-1"><?php echo $errors['full_name']; ?></p>
            <?php endif; ?>
        </div>

        <div class="mb-4">
             <!-- Оновлено колір лейбла -->
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
             <!-- Оновлено стилі поля вводу -->
            <input type="email" name="email" id="email" required value="<?php echo escape($input['email']); ?>"
                   class="shadow-sm appearance-none border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
             <?php if (isset($errors['email'])): ?>
                <p class="text-red-500 text-xs italic mt-1"><?php echo $errors['email']; ?></p>
            <?php endif; ?>
        </div>

         <div class="mb-4">
              <!-- Оновлено колір лейбла -->
             <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Роль</label>
              <!-- Оновлено стилі поля вибору -->
             <select name="role" id="role" required class="shadow-sm border <?php echo isset($errors['role']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
                 <?php foreach ($available_roles as $role_code => $role_name): ?>
                     <option value="<?php echo $role_code; ?>" <?php echo ($input['role'] === $role_code) ? 'selected' : ''; ?>>
                         <?php echo escape($role_name); ?>
                     </option>
                 <?php endforeach; ?>
             </select>
             <?php if (isset($errors['role'])): ?>
                <p class="text-red-500 text-xs italic mt-1"><?php echo $errors['role']; ?></p>
            <?php endif; ?>
         </div>

         <div class="mb-4">
              <!-- Оновлено колір лейбла -->
             <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Пароль</label>
              <!-- Оновлено стилі поля вводу -->
             <input type="password" name="password" id="password" required
                   class="shadow-sm appearance-none border <?php echo isset($errors['password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
             <?php if (isset($errors['password'])): ?>
                <p class="text-red-500 text-xs italic mt-1"><?php echo $errors['password']; ?></p>
            <?php endif; ?>
        </div>

        <div class="mb-6">
              <!-- Оновлено колір лейбла -->
             <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Підтвердіть Пароль</label>
              <!-- Оновлено стилі поля вводу -->
             <input type="password" name="confirm_password" id="confirm_password" required
                   class="shadow-sm appearance-none border <?php echo isset($errors['confirm_password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
            <?php if (isset($errors['confirm_password'])): ?>
                <p class="text-red-500 text-xs italic mt-1"><?php echo $errors['confirm_password']; ?></p>
            <?php endif; ?>
        </div>


        <div class="flex items-center justify-between mt-6"> <!-- Додано відступ зверху -->
             <!-- Застосовано клас .btn-green або його еквівалент Tailwind -->
            <button type="submit" class="btn-green inline-flex items-center">
                <i class="fas fa-user-plus mr-2"></i> Створити Користувача
            </button>
             <!-- Оновлено стиль посилання "Скасувати" -->
             <a href="../index.php#admin-users-content" class="text-gray-600 hover:text-red-600 text-sm font-semibold">
                Скасувати
            </a>
        </div>
    </form>
 </div>


<?php
require_once '../includes/footer.php'; // Include footer
?>