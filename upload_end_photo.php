<?php
// /upload_end_photo.php
require_once 'config.php';
require_roles(['lifeguard']); // Доступ тільки для рятувальників

global $pdo;
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$error_message = '';
$upload_dir_abs = ROOT_PATH . '/uploads/shift_photos/'; // Абсолютний шлях
$upload_path_relative = 'uploads/shift_photos/';     // Відносний шлях для запису в БД

// Перевірка, чи передано коректний ID зміни
if (!$shift_id) {
    set_flash_message('помилка', 'Невірний ID зміни для завантаження фото завершення.');
    header('Location: index.php');
    exit();
}

// Перевірка, чи ця зміна належить користувачу, чи вона завершена, і чи фото ще не завантажено
$shift_details = null;
try {
    $stmt_check = $pdo->prepare("
        SELECT s.id, s.status, p.name as post_name
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.id = :shift_id AND s.user_id = :user_id
    ");
    $stmt_check->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $shift_details = $stmt_check->fetch();

    if (!$shift_details) {
        set_flash_message('помилка', 'Зміну не знайдено або вона не належить вам.');
        header('Location: index.php');
        exit();
    }

    if ($shift_details['status'] !== 'completed') {
        set_flash_message('помилка', 'Фото завершення можна завантажити лише для завершеної зміни.');
        header('Location: index.php');
        exit();
    }

    // Перевірка, чи фото завершення вже було завантажено (щоб уникнути перезапису)
    $stmt_photo_check = $pdo->prepare("SELECT photo_close_path FROM shifts WHERE id = :shift_id");
    $stmt_photo_check->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_photo_check->execute();
    $existing_photo_path = $stmt_photo_check->fetchColumn();

    if (!empty($existing_photo_path)) {
        set_flash_message('інфо', 'Фото завершення для цієї зміни вже було завантажено.');
        header('Location: index.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Upload End Photo - Shift Check Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка перевірки даних зміни.');
    header('Location: index.php');
    exit();
}

// Обробка завантаження фото (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Помилка CSRF токену.';
    } elseif (!isset($_FILES['shift_end_photo']) || $_FILES['shift_end_photo']['error'] != UPLOAD_ERR_OK) {
        $error_message = 'Помилка завантаження файлу. ';
        switch ($_FILES['shift_end_photo']['error'] ?? UPLOAD_ERR_NO_FILE) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: $error_message .= 'Розмір файлу перевищує ліміт.'; break;
            case UPLOAD_ERR_PARTIAL:   $error_message .= 'Файл завантажено частково.'; break;
            case UPLOAD_ERR_NO_FILE:   $error_message .= 'Файл не було вибрано.'; break;
            default: $error_message .= 'Невідома помилка.';
        }
    } else {
        $file_tmp_path = $_FILES['shift_end_photo']['tmp_name'];
        $file_name = $_FILES['shift_end_photo']['name'];
        $file_size = $_FILES['shift_end_photo']['size'];
        // $file_type = $_FILES['shift_end_photo']['type']; // mime_content_type надійніше
        $file_ext_arr = explode('.', $file_name);
        $file_ext = strtolower(end($file_ext_arr));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        // Перевірки файлу
        if (!in_array($file_ext, $allowed_extensions) || !in_array(mime_content_type($file_tmp_path), $allowed_mime_types)) {
            $error_message = 'Неприпустимий тип файлу. Дозволено лише JPG, PNG, WEBP.';
        } elseif ($file_size > $max_file_size) {
            $error_message = 'Розмір файлу перевищує ліміт 5MB.';
        } else {
            // Генерація унікального імені та переміщення
            $new_file_name = 'end_shift_' . $shift_id . '_' . uniqid('', true) . '.' . $file_ext;
            $destination_path_absolute = $upload_dir_abs . $new_file_name;
            $destination_path_relative_for_db = $upload_path_relative . $new_file_name;

            // Створення директорії, якщо її немає
            if (!is_dir($upload_dir_abs)) {
                if (!mkdir($upload_dir_abs, 0755, true)) {
                    $error_message = 'Не вдалося створити директорію для завантажень.';
                    error_log("Failed to create directory: " . $upload_dir_abs);
                }
            }

            if (empty($error_message) && move_uploaded_file($file_tmp_path, $destination_path_absolute)) {
                // Оновлення шляху в БД
                try {
                    $stmt_update = $pdo->prepare("
                        UPDATE shifts 
                        SET photo_close_path = :path, 
                            photo_close_uploaded_at = NOW(),
                            photo_close_approved = 1
                        WHERE id = :shift_id AND user_id = :user_id AND status = 'completed'
                    ");
                    $stmt_update->bindParam(':path', $destination_path_relative_for_db);
                    $stmt_update->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
                    $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        set_flash_message('успіх', 'Фото завершення зміни успішно завантажено!');
                        unset($_SESSION['csrf_token']);
                        // Редирект на сторінку, де рятувальник може подати звіт, якщо потрібно
                        // Або на головну панель, якщо звіт не потрібен для цього типу зміни
                        // Поки що просто на index.php
                        header('Location: index.php');
                        exit();
                    } else {
                        $error_message = 'Не вдалося зберегти шлях до фото в БД.';
                        if (file_exists($destination_path_absolute)) unlink($destination_path_absolute);
                    }
                } catch (PDOException $e) {
                    error_log("Update End Photo Path DB Error: " . $e->getMessage());
                    $error_message = 'Помилка БД при збереженні фото завершення.';
                    if (file_exists($destination_path_absolute)) unlink($destination_path_absolute);
                }
            } elseif (empty($error_message)) {
                $error_message = 'Не вдалося перемістити завантажений файл.';
            }
        }
    }
    // Регенерувати CSRF токен при помилці
    if ($error_message) {
        unset($_SESSION['csrf_token']);
    }
}

// Включаємо хедер
require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-lg">
    <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">Фото Завершення Зміни</h2>
    <p class="text-sm text-gray-600 text-center mb-6">Зміна #<?php echo escape($shift_id); ?> на посту "<?php echo escape($shift_details['post_name'] ?? 'Невідомий пост'); ?>"</p>

    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md mb-6 text-sm" role="alert">
        <p><i class="fas fa-info-circle mr-2"></i>Вашу зміну завершено. Будь ласка, зробіть або виберіть актуальне фото (селфі) на фоні рятувального поста для фіксації завершення чергування.</p>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
            <p><strong class="font-bold">Помилка!</strong> <?php echo escape($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <form action="upload_end_photo.php?shift_id=<?php echo $shift_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
            <?php echo csrf_input(); ?>

            <div class="mb-4">
                <label for="shift_end_photo" class="block text-sm font-medium text-gray-700 mb-1">Виберіть фото (JPG, PNG, WEBP, макс. 5MB)</label>
                <input type="file" name="shift_end_photo" id="shift_end_photo" accept="image/jpeg, image/png, image/webp" capture="user" required
                       class="block w-full text-sm text-gray-500
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-md file:border-0
                              file:text-sm file:font-semibold
                              file:bg-red-100 file:text-red-700
                              hover:file:bg-red-200
                              border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
            </div>

            <div class="mb-6 text-center">
                <img id="preview_end_photo" src="#" alt="Попередній перегляд фото завершення" class="max-w-xs mx-auto rounded-md shadow hidden" style="max-height: 300px;"/>
            </div>

            <div class="flex items-center justify-end">
                <a href="index.php" class="btn-secondary mr-3">Пропустити та повернутись</a>
                <button type="submit" class="btn-green inline-flex items-center">
                    <i class="fas fa-upload mr-2"></i> Завантажити Фото
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const photoInputEnd = document.getElementById('shift_end_photo');
    const previewImageEnd = document.getElementById('preview_end_photo');

    if (photoInputEnd && previewImageEnd) {
        photoInputEnd.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImageEnd.src = e.target.result;
                    previewImageEnd.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            } else {
                previewImageEnd.src = "#";
                previewImageEnd.classList.add('hidden');
            }
        });
    }
</script>

<?php
require_once 'includes/footer.php';
?>