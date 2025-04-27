<?php
require_role('admin'); // Access control
global $pdo, $APP_URL; // Make PDO and APP_URL available
$APP_URL = defined('APP_URL') ? APP_URL : ''; // Ensure $APP_URL is set

$posts = [];
$users = [];
$admin_error = '';

try {
    // --- Fetch All Posts - Сортування за ID ---
    $stmt_posts = $pdo->query("SELECT id, name, location_description FROM posts ORDER BY id ASC");
    $posts = $stmt_posts->fetchAll();
    // Додаємо queryString для симуляції
    // if ($stmt_posts instanceof PDOStatement) { $stmt_posts->queryString = "SELECT ... FROM posts ..."; }

    // --- Fetch All Users - Сортування за ПІБ ---
    $stmt_users = $pdo->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY full_name ASC");
    $users = $stmt_users->fetchAll();
    // Додаємо queryString для симуляції
    // if ($stmt_users instanceof PDOStatement) { $stmt_users->queryString = "SELECT ... FROM users ..."; }


} catch (PDOException $e) {
    // error_log("Admin Panel DB Error: " . $e->getMessage()); // Добре мати для реального логування
    $admin_error = 'Не вдалося завантажити дані адміністрування.';
    // set_flash_message('error', $admin_error); // Використовуємо 'error' замість 'помилка' для консистентності, якщо ваша система очікує 'error'
    set_flash_message('помилка', $admin_error); // Або залишаємо 'помилка', якщо це вірно
}
?>

<section id="admin-section">
    <h2 class="text-2xl font-bold mb-8 text-center text-gray-800">Панель Адміністратора</h2>

    <?php // Відображення загальних помилок завантаження даних ?>
    <?php if ($admin_error): ?>
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <?php echo escape($admin_error); ?>
        </div>
    <?php endif; ?>

    <?php // Відображення flash-повідомлень (якщо є) ?>
    <?php // display_flash_messages(); // Припускаємо, що є функція для відображення ?>

    <div class="mb-6 border-b border-gray-300">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="adminTab" role="tablist">
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 rounded-t-lg border-b-2 text-red-600 border-red-500" id="admin-duty-tab"
                        type="button"
                        role="tab"
                        onclick="showAdminTab('duty')"  aria-controls="admin-duty-content"
                        aria-selected="true"> <i class="fas fa-clipboard-list mr-2" aria-hidden="true"></i> Статус Змін
                </button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 rounded-t-lg border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        id="admin-posts-tab"
                        type="button"
                        role="tab"
                        onclick="showAdminTab('posts')" aria-controls="admin-posts-content"
                        aria-selected="false">
                    <i class="fas fa-map-marker-alt mr-2" aria-hidden="true"></i> Пости
                </button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 rounded-t-lg border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        id="admin-users-tab"
                        type="button"
                        role="tab"
                        onclick="showAdminTab('users')" aria-controls="admin-users-content"
                        aria-selected="false">
                    <i class="fas fa-users-cog mr-2" aria-hidden="true"></i> Користувачі
                </button>
            </li>
        </ul>
    </div>

    <div id="adminTabContent">

        <div class="bg-transparent rounded-lg p-0" id="admin-duty-content" role="tabpanel" aria-labelledby="admin-duty-tab">
            <?php
                // Підключаємо контент для вкладки чергового
                // Переконайтеся, що шлях правильний відносно поточного файлу
                // Якщо admin_panel.php знаходиться в корені папки 'admin', а 'panels' всередині неї:
                // require_once __DIR__ . '/panels/duty_officer_content.php';
                // Якщо admin_panel.php та 'panels' на одному рівні:
                 require_once __DIR__ . '/panels/duty_officer_content.php'; // Або правильний шлях
            ?>
        </div>

        <div class="hidden bg-white rounded-lg shadow-md p-4 sm:p-6" id="admin-posts-content" role="tabpanel" aria-labelledby="admin-posts-tab">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0">Керування Постами</h3>
                <a href="<?php echo rtrim($APP_URL, '/'); ?>/admin/post_create.php" class="btn-green inline-flex items-center self-start sm:self-center">
                    <i class="fas fa-plus mr-2"></i> Додати Пост
                </a>
            </div>
             <div class="overflow-x-auto">
                 <table class="min-w-full bg-white md:table md:border md:border-gray-200 md:border-collapse">
                     <thead class="hidden md:table-header-group">
                         <tr class="bg-gray-100 md:table-row">
                             <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">ID</th>
                             <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">Назва Посту</th>
                             <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">Опис</th>
                             <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">URL для NFC</th>
                             <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Дії</th>
                         </tr>
                     </thead>
                     <tbody class="md:table-row-group">
                         <?php if (empty($posts) && !$admin_error): ?>
                             <tr class="md:table-row">
                                 <td colspan="5" class="text-center py-6 px-4 text-gray-500 md:table-cell md:border-t md:border-gray-200">Пости ще не створено.</td>
                             </tr>
                         <?php elseif (!empty($posts)): ?>
                            <?php foreach ($posts as $post): ?>
                            <tr class="block border rounded-lg bg-white shadow p-4 mb-4 md:table-row md:border-none md:shadow-none md:p-0 md:mb-0">
                                <td data-label="ID:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0">
                                    <?php echo escape($post['id']); ?>
                                </td>
                                <td data-label="Назва Посту:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 text-gray-800 font-semibold md:table-cell md:text-left md:p-0 md:font-normal md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0">
                                    <?php echo escape($post['name']); ?>
                                </td>
                                <td data-label="Опис:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 text-sm break-words md:table-cell md:text-left md:p-0 md:text-base md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0">
                                    <?php echo escape($post['location_description'] ?? '-'); ?>
                                </td>
                                <td data-label="URL для NFC:" class="block relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0">
                                    <div class="flex items-center justify-end md:justify-between">
                                        <span id="nfc-url-<?php echo $post['id']; ?>" class="flex-grow break-all text-xs text-gray-600 text-left pr-2">
                                            <?php
                                                // Генеруємо URL, перевіряючи чи $APP_URL встановлено і не порожнє
                                                $base_url = !empty($APP_URL) ? rtrim($APP_URL, '/') : '';
                                                if (!empty($base_url)) {
                                                    $nfc_url = $base_url . "/scan_handler.php?post_id=" . $post['id'];
                                                } else {
                                                    // Якщо $APP_URL не встановлено, генеруємо відносний шлях або показуємо помилку
                                                    // Краще генерувати відносний, якщо можливо, або чітко вказати на проблему
                                                    $nfc_url = '/scan_handler.php?post_id=' . $post['id'] . ' (Помилка конфігурації URL!)';
                                                    // Можна додати логування помилки тут
                                                    // error_log("APP_URL is not defined or empty in admin_panel.php");
                                                }
                                                echo escape($nfc_url);
                                            ?>
                                        </span>
                                        <button
                                            type="button"
                                            onclick="copyToClipboardEnhanced('nfc-url-<?php echo $post['id']; ?>', this)"
                                            class="p-1.5 text-gray-500 hover:bg-gray-100 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-red-400 rounded-md flex-shrink-0"
                                            title="Копіювати URL">
                                            <svg id="copy-icon-<?php echo $post['id']; ?>" class="w-4 h-4 pointer-events-none inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            <svg id="copied-icon-<?php echo $post['id']; ?>" class="w-4 h-4 text-green-500 hidden pointer-events-none inline-block" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                        </button>
                                    </div>
                                    <span id="copied-message-<?php echo $post['id']; ?>" class="text-xs text-green-600 block text-left hidden mt-1">Скопійовано!</span>
                                </td>
                                <td data-label="Дії:" class="block relative pt-8 pb-2 px-4 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:last:border-r-0 whitespace-nowrap">
                                    <div class="flex items-center justify-end md:justify-start space-x-3">
                                        <a href="<?php echo rtrim($APP_URL, '/'); ?>/admin/post_edit.php?id=<?php echo $post['id']; ?>" class="p-1 text-yellow-600 hover:text-yellow-800 rounded-md hover:bg-gray-100" title="Редагувати"><i class="fas fa-edit fa-fw"></i></a>
                                        <form action="<?php echo rtrim($APP_URL, '/'); ?>/admin/post_delete.php" method="POST" style="display: inline;" onsubmit="return confirm('Видалити пост \'<?php echo escape(addslashes($post['name'])); ?>\'?');">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="p-1 text-red-600 hover:text-red-800 rounded-md hover:bg-gray-100 bg-transparent border-none cursor-pointer" title="Видалити">
                                                <i class="fas fa-trash fa-fw"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                         <?php endif; ?>
                     </tbody>
                 </table>
             </div>
        </div>

        <div class="hidden bg-white rounded-lg shadow-md p-4 sm:p-6" id="admin-users-content" role="tabpanel" aria-labelledby="admin-users-tab">
             <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0">Керування Користувачами</h3>
                <a href="<?php echo rtrim($APP_URL, '/'); ?>/admin/user_create.php" class="btn-green inline-flex items-center self-start sm:self-center">
                     <i class="fas fa-user-plus mr-2"></i> Додати Користувача
                 </a>
             </div>
              <div class="overflow-x-auto">
                  <table class="min-w-full bg-white md:table md:border md:border-gray-200 md:border-collapse">
                      <thead class="hidden md:table-header-group">
                          <tr class="bg-gray-100 md:table-row">
                              <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">ID</th>
                              <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">ПІБ</th>
                              <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">Email</th>
                              <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">Роль</th>
                              <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200 md:border-r">Створено</th>
                              <th class="py-3 px-4 md:py-3 md:px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell border-b border-gray-200">Дії</th>
                          </tr>
                      </thead>
                      <tbody class="md:table-row-group">
                          <?php if (empty($users) && !$admin_error): ?>
                              <tr class="md:table-row"><td colspan="6" class="text-center py-6 px-4 text-gray-500 md:table-cell md:border-t md:border-gray-200">Користувачі ще не створені.</td></tr>
                          <?php elseif (!empty($users)): ?>
                             <?php foreach ($users as $user): ?>
                             <tr class="block border rounded-lg bg-white shadow p-4 mb-4 md:table-row md:border-none md:shadow-none md:p-0 md:mb-0">
                                  <td data-label="ID:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0 text-gray-700"><?php echo escape($user['id']); ?></td>
                                 <td data-label="ПІБ:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0 font-semibold text-gray-800 md:font-normal"><?php echo escape($user['full_name']); ?></td>
                                 <td data-label="Email:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0 text-sm break-all text-gray-700"><?php echo escape($user['email']); ?></td>
                                 <td data-label="Роль:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0 text-gray-700"><?php echo get_role_name_ukrainian($user['role']); ?></td>
                                 <td data-label="Створено:" class="block text-right relative pt-8 pb-2 px-4 border-b border-gray-200 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:border-r md:last:border-r-0 text-xs text-gray-500"><?php echo format_datetime($user['created_at']); ?></td>
                                 <td data-label="Дії:" class="block relative pt-8 pb-2 px-4 md:table-cell md:text-left md:p-0 md:py-3 md:px-4 md:border-t md:border-gray-200 md:border-b-0 md:last:border-r-0 whitespace-nowrap">
                                     <div class="flex items-center justify-end md:justify-start space-x-3">
                                         <a href="<?php echo rtrim($APP_URL, '/'); ?>/admin/user_edit.php?id=<?php echo $user['id']; ?>" class="p-1 text-yellow-600 hover:text-yellow-800 rounded-md hover:bg-gray-100" title="Редагувати"><i class="fas fa-edit fa-fw"></i></a>
                                         <?php // Перевірка, чи користувач не намагається видалити сам себе ?>
                                         <?php if (isset($_SESSION['user_id']) && $user['id'] !== $_SESSION['user_id']): ?>
                                             <form action="<?php echo rtrim($APP_URL, '/'); ?>/admin/user_delete.php" method="POST" style="display: inline;" onsubmit="return confirm('Видалити користувача \'<?php echo escape(addslashes($user['full_name'])); ?>\'?');">
                                                 <?php csrf_input(); ?>
                                                 <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                 <button type="submit" class="p-1 text-red-600 hover:text-red-800 rounded-md hover:bg-gray-100 bg-transparent border-none cursor-pointer" title="Видалити">
                                                     <i class="fas fa-trash fa-fw"></i>
                                                 </button>
                                             </form>
                                         <?php else: ?>
                                              <span class="p-1 text-gray-400 cursor-not-allowed" title="Неможливо видалити себе"><i class="fas fa-trash fa-fw"></i></span>
                                         <?php endif; ?>
                                     </div>
                                 </td>
                             </tr>
                             <?php endforeach; ?>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
        </div>

    </div> </section>