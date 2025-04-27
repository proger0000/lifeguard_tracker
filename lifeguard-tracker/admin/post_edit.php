<?php
// --- Конфігурація та Ініціалізація ---
require_once '../config.php'; // Підключення файлу конфігурації (на рівень вище)
global $pdo; // Доступ до глобального об'єкту PDO
require_role('admin'); // Перевірка, чи користувач має роль 'admin'

$page_title = "Редагувати Пост"; // Назва сторінки

// --- Отримання та валідація ID поста ---
$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$post_id) {
    set_flash_message('помилка', 'Невірний ID поста.');
    header('Location: ../index.php#admin-posts-content'); // Перенаправлення назад до списку постів
    exit();
}

// --- Ініціалізація змінних ---
$post = null; // Для зберігання даних поста з БД
$post_name = ''; // Поточна або відправлена назва поста
$location_description = ''; // Поточний або відправлений опис
$errors = []; // Масив для зберігання помилок валідації

// --- Завантаження Існуючих Даних Поста ---
// Це виконується завжди при завантаженні сторінки (GET)
// та для повторного заповнення форми у випадку помилки (POST)
try {
    $stmt_fetch = $pdo->prepare("SELECT id, name, location_description FROM posts WHERE id = :id");
    $stmt_fetch->bindParam(':id', $post_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $post = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        set_flash_message('помилка', 'Пост з ID ' . htmlspecialchars($post_id) . ' не знайдено.');
        header('Location: ../index.php#admin-posts-content');
        exit();
    }

    // Ініціалізація змінних для форми даними з БД (якщо це не POST-запит з помилкою)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $post_name = $post['name'];
        $location_description = $post['location_description'] ?? '';
    }
    // Якщо це POST-запит з помилкою, $post_name та $location_description будуть перезаписані
    // даними з POST далі в секції обробки POST-запиту

} catch (PDOException $e) {
    // Можна додати логування помилки
    // error_log("Post Edit Fetch Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка завантаження даних поста.');
    header('Location: ../index.php#admin-posts-content');
    exit();
}


// --- Обробка Відправки Форми (POST-запит) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Перевірка CSRF токена
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Помилка CSRF токену. Спробуйте оновити сторінку.';
    } else {
        // Отримання та очищення даних з форми
        $post_name = trim($_POST['post_name'] ?? '');
        $location_description = trim($_POST['location_description'] ?? ''); // Опис може бути порожнім

        // --- Валідація Даних ---
        if (empty($post_name)) {
            $errors['post_name'] = "Назва посту є обов'язковою.";
        } else {
            // Перевірка унікальності назви поста (крім поточного поста)
            try {
                $stmt_check = $pdo->prepare("SELECT id FROM posts WHERE name = :name AND id != :current_id");
                $stmt_check->bindParam(':name', $post_name);
                $stmt_check->bindParam(':current_id', $post_id, PDO::PARAM_INT);
                $stmt_check->execute();
                if ($stmt_check->fetch()) {
                    $errors['post_name'] = 'Пост з такою назвою вже існує.';
                }
            } catch (PDOException $e) {
                $errors['db_check'] = 'Помилка перевірки назви поста в базі даних.';
                // error_log("Post Edit Check Error: " . $e->getMessage());
            }
        }
        // location_description є необов'язковим, тому додаткової валідації немає

        // --- Оновлення даних в БД, якщо немає помилок валідації ---
        if (empty($errors)) {
            try {
                $stmt_update = $pdo->prepare("UPDATE posts SET name = :name, location_description = :location_description WHERE id = :id");
                $stmt_update->bindParam(':name', $post_name);
                // Дозволяємо зберігати порожній рядок або NULL, якщо опис не вказано
                $stmt_update->bindParam(':location_description', $location_description);
                $stmt_update->bindParam(':id', $post_id, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    set_flash_message('успіх', 'Пост "' . escape($post_name) . '" успішно оновлено.');
                    unset($_SESSION['csrf_token']); // Очистити токен після успішної операції
                    header('Location: ../index.php#admin-posts-content'); // Повернення до адмін-панелі, вкладка постів
                    exit();
                } else {
                    $errors['db'] = 'Не вдалося оновити пост в базі даних.';
                }
            } catch (PDOException $e) {
                $errors['db'] = 'Помилка бази даних під час оновлення поста.';
                // error_log("Post Edit DB Error: " . $e->getMessage());
                 // Спроба виявити помилку дублювання назви з повідомлення PDO (якщо є UNIQUE індекс)
                if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'name') !== false) {
                    $errors['post_name'] = 'Пост з такою назвою вже існує (помилка БД).';
                    unset($errors['db']); // Видаляємо загальну помилку БД
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
    <h2 class="text-2xl font-bold mb-6 text-gray-900"><?php echo escape($page_title); ?> (ID: <?php echo escape($post_id); ?>)</h2>

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

    <form action="post_edit.php?id=<?php echo escape($post_id); ?>" method="POST" novalidate>
        <?php csrf_input(); // Функція для генерації прихованого поля CSRF токена ?>

        <div class="mb-4">
            <label for="post_name" class="block text-gray-700 text-sm font-bold mb-2">Назва Посту</label>
            <input type="text" name="post_name" id="post_name" required
                   value="<?php echo escape($post_name); // Використовуємо поточне значення (з БД або з POST при помилці) ?>"
                   class="shadow-sm appearance-none border <?php echo isset($errors['post_name']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php if (isset($errors['post_name'])): ?>
                <p class="text-red-600 text-xs italic mt-1"><?php echo escape($errors['post_name']); ?></p>
            <?php endif; ?>
        </div>

        <div class="mb-6">
            <label for="location_description" class="block text-gray-700 text-sm font-bold mb-2">Опис Розташування <span class="text-gray-500 font-normal">(необов'язково)</span></label>
            <textarea name="location_description" id="location_description" rows="3"
                      class="shadow-sm appearance-none border border-gray-300 rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      ><?php echo escape($location_description); // Використовуємо поточне значення ?></textarea>
            </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-4 focus:ring-indigo-300 transition duration-150 ease-in-out">
                <i class="fas fa-save mr-2"></i> Зберегти Зміни
            </button>
            <a href="../index.php#admin-posts-content" class="inline-block align-baseline font-bold text-sm text-indigo-600 hover:text-indigo-800">
                Скасувати
            </a>
        </div>
    </form>
</div>
<?php
// --- Включення Футера ---
require_once '../includes/footer.php'; // Підключення підвалу сайту
?>