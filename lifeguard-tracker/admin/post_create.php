<?php
require_once '../config.php'; // Go up one directory for config
global $pdo;
require_role('admin'); // Only admins can create posts

$page_title = "Додати Новий Пост";
$post_name = '';
$location_description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
         $errors['csrf'] = 'Помилка CSRF токену.';
    } else {
        $post_name = trim($_POST['post_name'] ?? '');
        $location_description = trim($_POST['location_description'] ?? '');

        // Validation
        if (empty($post_name)) {
            $errors['post_name'] = "Назва посту є обов'язковою.";
        } else {
             // Check if post name already exists
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM posts WHERE name = :name");
                $stmt_check->bindParam(':name', $post_name);
                $stmt_check->execute();
                if ($stmt_check->fetch()) {
                    $errors['post_name'] = 'Пост з такою назвою вже існує.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка перевірки назви поста.';
                // error_log("Post Create Check Error: " . $e->getMessage());
            }
        }
        // Location description is optional

        if (empty($errors)) {
             try {
                $stmt = $pdo->prepare("INSERT INTO posts (name, location_description) VALUES (:name, :location_description)");
                $stmt->bindParam(':name', $post_name);
                $stmt->bindParam(':location_description', $location_description);

                if ($stmt->execute()) {
                    set_flash_message('успіх', 'Пост "' . escape($post_name) . '" успішно створено.');
                     unset($_SESSION['csrf_token']); // Clear token on success
                    header('Location: ../index.php#admin-posts-content'); // Redirect back to admin panel, posts tab
                    exit();
                } else {
                    $errors['db'] = 'Не вдалося створити пост.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка бази даних під час створення поста.';
                 // error_log("Post Create DB Error: " . $e->getMessage());
            }
        }
    }
    if (!empty($errors)) {
         unset($_SESSION['csrf_token']); // Regenerate token on error
    }
}


require_once '../includes/header.php'; // Include header
?>

<!-- Form Content (Example using similar style to login) -->
 <div class="max-w-2xl mx-auto bg-white/30 backdrop-blur-md p-8 rounded-lg shadow-xl text-gray-800 mt-6">
    <h2 class="text-2xl font-bold mb-6 text-white"><?php echo $page_title; ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Помилка!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="post_create.php" method="POST">
        <?php csrf_input(); ?>

        <div class="mb-4">
             <label for="post_name" class="block text-white text-sm font-bold mb-2">Назва Посту</label>
             <input type="text" name="post_name" id="post_name" required value="<?php echo escape($post_name); ?>"
                   class="shadow appearance-none border <?php echo isset($errors['post_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
             <?php if (isset($errors['post_name'])): ?>
                <p class="text-red-500 text-xs italic mt-1"><?php echo $errors['post_name']; ?></p>
             <?php endif; ?>
        </div>

        <div class="mb-6">
             <label for="location_description" class="block text-white text-sm font-bold mb-2">Опис Розташування (необов'язково)</label>
             <textarea name="location_description" id="location_description" rows="3"
                      class="shadow appearance-none border border-gray-300 rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo escape($location_description); ?></textarea>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                <i class="fas fa-save mr-2"></i> Зберегти Пост
            </button>
            <a href="../index.php#admin-posts-content" class="inline-block align-baseline font-bold text-sm text-blue-200 hover:text-blue-100">
                Скасувати
            </a>
        </div>
    </form>
 </div>


<?php
require_once '../includes/footer.php'; // Include footer
?>