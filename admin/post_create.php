<?php
require_once '../config.php'; // Go up one directory for config
global $pdo;
require_role('admin'); // Only admins can create posts
save_current_page_for_redirect();

$page_title = "Додати Новий Пост";
$post_name = '';
$location_description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
         $errors['csrf'] = 'Помилка CSRF токену.';
    } else {
        $post_name = trim($_POST['post_name'] ?? '');
        $location_description = trim($_POST['location_description'] ?? ''); // Може бути порожнім

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
                error_log("Post Create Check Error: " . $e->getMessage());
            }
        }
        // Location description is optional, no validation needed

        if (empty($errors)) {
             try {
                $stmt = $pdo->prepare("INSERT INTO posts (name, location_description) VALUES (:name, :location_description)");
                $stmt->bindParam(':name', $post_name);
                 // Дозволяємо NULL для location_description, якщо воно порожнє
                $locDescValue = !empty($location_description) ? $location_description : null;
                $stmt->bindParam(':location_description', $locDescValue, $locDescValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);


                if ($stmt->execute()) {
                    set_flash_message('успіх', 'Пост "' . escape($post_name) . '" успішно створено.');
                     unset($_SESSION['csrf_token']); // Clear token on success
                    smart_redirect('index.php', [], 'admin-posts-content'); // Redirect back to admin panel, posts tab
                    exit();
                } else {
                    $errors['db'] = 'Не вдалося створити пост.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка бази даних під час створення поста.';
                 error_log("Post Create DB Error: " . $e->getMessage());
                 // Перевірка на дублікат (якщо є UNIQUE індекс)
                if (strpos($e->getCode(), '23000') !== false) { // Integrity constraint violation
                     $errors['post_name'] = 'Пост з такою назвою вже існує (помилка БД).';
                     if(isset($errors['db'])) unset($errors['db']); // Прибираємо загальну помилку, якщо є специфічна
                 }
            }
        }
    }
    if (!empty($errors)) {
         unset($_SESSION['csrf_token']); // Regenerate token on error
    }
}


require_once '../includes/header.php'; // Include header
?>

<div class="max-w-2xl mx-auto bg-white p-4 sm:p-6 rounded-xl shadow-lg mt-6 mb-8 border border-gray-200">

    <div class="mb-4 pb-3 border-b border-gray-200">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-800">
             <i class="fas fa-map-marker-plus mr-2 text-green-600"></i> <?php echo escape($page_title); ?>
        </h2>
    </div>

    <?php if (!empty($errors)): ?>
         <?php
            // Формуємо єдине повідомлення про помилку
            $error_text = "Будь ласка, виправте наступні помилки:\n- " . implode("\n- ", array_values($errors));
            $_SESSION['flash_message'] = ['type' => 'помилка', 'text' => $error_text];
            display_flash_message();
         ?>
    <?php endif; ?>

    <form action="post_create.php" method="POST" class="space-y-4">
        <?php echo csrf_input(); ?>

        <div>
             <label for="post_name" class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">Назва Посту *</label>
             <input type="text" name="post_name" id="post_name" required value="<?php echo escape($post_name); ?>"
                   class="shadow-sm block w-full px-2 py-1 sm:px-3 sm:py-1.5 border <?php echo isset($errors['post_name']) ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'; ?> rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
             <?php if (isset($errors['post_name'])): ?>
                <?php endif; ?>
        </div>

        <div>
            <label for="location_description" class="block text-xs sm:text-sm font-semibold text-gray-700 mb-1">Опис Розташування <span class="text-gray-500 font-normal">(необов'язково)</span></label>
             <textarea name="location_description" id="location_description" rows="3"
                      class="shadow-sm block w-full px-2 py-1 sm:px-3 sm:py-1.5 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                      placeholder="Напр.: біля центрального входу, праворуч від рятувальної станції..."
                      ><?php echo escape($location_description); ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-gray-200 mt-4"> <button type="submit" class="btn-green">
                <i class="fas fa-save mr-1 sm:mr-2"></i> Зберегти Пост
            </button>
            <a href="../index.php#admin-posts-content" class="btn-secondary">
                Скасувати
            </a>
        </div>
    </form>
 </div>


<?php
require_once '../includes/footer.php'; // Include footer
?>