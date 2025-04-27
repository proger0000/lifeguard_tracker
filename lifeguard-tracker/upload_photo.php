<?php
require_once 'config.php';
require_roles(['lifeguard']);

global $pdo;
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];
$error_message = '';
$upload_dir = __DIR__ . '/uploads/shift_photos/'; // Шлях до папки завантажень
$upload_path_relative = 'uploads/shift_photos/'; // Відносний шлях для запису в БД

if (!$shift_id) {
    set_flash_message('помилка', 'Невірний ID зміни для завантаження фото.');
    header('Location: index.php');
    exit();
}

// Перевірити, чи ця зміна належить користувачу і чи вона активна
try {
    $stmt_check = $pdo->prepare("SELECT id FROM shifts WHERE id = :shift_id AND user_id = :user_id AND status = 'active' AND start_photo_path IS NULL");
    $stmt_check->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    if (!$stmt_check->fetch()) {
        set_flash_message('помилка', 'Зміна не знайдена, не активна, не належить вам, або фото вже завантажено.');
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    // error_log("Upload Photo Shift Check Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка перевірки зміни.');
    header('Location: index.php');
    exit();
}


// Обробка завантаження фото (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
         $error_message = 'Помилка CSRF токену.';
    } elseif (!isset($_FILES['shift_photo']) || $_FILES['shift_photo']['error'] != UPLOAD_ERR_OK) {
        $error_message = 'Помилка завантаження файлу. ';
        switch ($_FILES['shift_photo']['error'] ?? UPLOAD_ERR_NO_FILE) {
             case UPLOAD_ERR_INI_SIZE:
             case UPLOAD_ERR_FORM_SIZE: $error_message .= 'Розмір файлу перевищує ліміт.'; break;
             case UPLOAD_ERR_PARTIAL:   $error_message .= 'Файл завантажено частково.'; break;
             case UPLOAD_ERR_NO_FILE:   $error_message .= 'Файл не було вибрано.'; break;
             default: $error_message .= 'Невідома помилка.';
        }
    } else {
        $file_tmp_path = $_FILES['shift_photo']['tmp_name'];
        $file_name = $_FILES['shift_photo']['name'];
        $file_size = $_FILES['shift_photo']['size'];
        $file_type = $_FILES['shift_photo']['type'];
        $file_ext_arr = explode('.', $file_name);
        $file_ext = strtolower(end($file_ext_arr));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        // --- Перевірки ---
        if (!in_array($file_ext, $allowed_extensions) || !in_array(mime_content_type($file_tmp_path), $allowed_mime_types)) {
             $error_message = 'Неприпустимий тип файлу. Дозволено лише JPG, PNG, WEBP.';
        } elseif ($file_size > $max_file_size) {
             $error_message = 'Розмір файлу перевищує ліміт 5MB.';
        } else {
             // --- Генерація унікального імені та переміщення ---
            $new_file_name = 'shift_' . $shift_id . '_' . uniqid('', true) . '.' . $file_ext;
            $destination_path_absolute = $upload_dir . $new_file_name;
            $destination_path_relative = $upload_path_relative . $new_file_name;

             // Спробуємо створити директорію, якщо її немає
             if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) { // 0755 - типові права, true - рекурсивно
                     $error_message = 'Не вдалося створити директорію для завантажень.';
                     // Тут потрібне логування помилки на сервері
                     // error_log("Failed to create directory: " . $upload_dir);
                }
            }


             if (empty($error_message) && move_uploaded_file($file_tmp_path, $destination_path_absolute)) {
                 // --- Оновлення шляху в БД ---
                try {
                     $stmt_update = $pdo->prepare("UPDATE shifts SET start_photo_path = :path WHERE id = :shift_id AND user_id = :user_id");
                     $stmt_update->bindParam(':path', $destination_path_relative);
                     $stmt_update->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
                     $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT); // Додаткова перевірка

                     if ($stmt_update->execute()) {
                        set_flash_message('успіх', 'Фото успішно завантажено! Ваша зміна активна.');
                        unset($_SESSION['csrf_token']);
                        header('Location: index.php');
                        exit();
                     } else {
                          $error_message = 'Не вдалося зберегти шлях до фото в БД.';
                          // Можливо, видалити щойно завантажений файл?
                          if (file_exists($destination_path_absolute)) unlink($destination_path_absolute);
                     }
                } catch (PDOException $e) {
                    // error_log("Update Photo Path DB Error: " . $e->getMessage());
                    $error_message = 'Помилка БД при збереженні фото.';
                     // Можливо, видалити щойно завантажений файл?
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

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-lg">
    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Завантажити Фото з Посту</h2>

    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-md mb-6 text-sm" role="alert">
         <p><i class="fas fa-info-circle mr-2"></i>Вашу зміну розпочато. Будь ласка, зробіть або виберіть актуальне фото (селфі) на фоні рятувального поста, щоб підтвердити своє місцезнаходження.</p>
     </div>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
            <p><strong class="font-bold">Помилка!</strong> <?php echo escape($error_message); ?></p>
        </div>
    <?php endif; ?>

     <div class="bg-white p-6 rounded-lg shadow-md">
        <form action="upload_photo.php?shift_id=<?php echo $shift_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
             <?php csrf_input(); ?>

            <div class="mb-4">
                <label for="shift_photo" class="block text-sm font-medium text-gray-700 mb-1">Виберіть фото (JPG, PNG, WEBP, макс. 5MB)</label>
                 <!-- Додано capture="user" для прямого запуску фронтальної камери на мобільних -->
                 <input type="file" name="shift_photo" id="shift_photo" accept="image/jpeg, image/png, image/webp" capture="user" required
                        class="block w-full text-sm text-gray-500
                               file:mr-4 file:py-2 file:px-4
                               file:rounded-md file:border-0
                               file:text-sm file:font-semibold
                               file:bg-red-100 file:text-red-700
                               hover:file:bg-red-200
                               border border-gray-300 rounded-md cursor-pointer focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500">
             </div>

             <!-- Можна додати JS для попереднього перегляду фото -->
             <div class="mb-6 text-center">
                  <img id="preview" src="#" alt="Попередній перегляд фото" class="max-w-xs mx-auto rounded-md shadow hidden" style="max-height: 300px;"/>
             </div>


             <div class="flex items-center justify-end">
                <!-- <a href="index.php" class="btn-secondary mr-3">Пропустити (Не рекомендується)</a> --> <!-- Поки прибираємо кнопку "Пропустити" -->
                <button type="submit" class="btn-green inline-flex items-center">
                     <i class="fas fa-upload mr-2"></i> Завантажити Фото
                </button>
             </div>
         </form>
     </div>
</div>

<!-- Скрипт для попереднього перегляду -->
 <script>
    const photoInput = document.getElementById('shift_photo');
    const previewImage = document.getElementById('preview');

    photoInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewImage.classList.remove('hidden'); // Показуємо зображення
            }
            reader.readAsDataURL(file);
        } else {
             previewImage.src = "#";
             previewImage.classList.add('hidden'); // Ховаємо, якщо файл не вибрано
        }
    });
</script>

<?php
require_once 'includes/footer.php';
?>