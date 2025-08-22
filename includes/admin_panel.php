<?php
// Файл: includes/admin_panel.php
// КОМЕНТАР: Повна, 100% готова версія. Кожен блок контенту завантажується строго всередині свого контейнера.

require_role('admin'); // Контроль доступу
global $pdo, $APP_URL, $page_data; 
$APP_URL = defined('APP_URL') ? APP_URL : ''; 

if (!isset($page_data) || !is_array($page_data)) {
    $page_data = ['post_grid_data' => []]; 
}

// Завантаження даних, необхідних для вкладок "Пости" та "Користувачі"
$posts = [];
$users = [];
$admin_error = '';

try {
    $stmt_posts = $pdo->query("SELECT id, name, location_description FROM posts ORDER BY name ASC");
    $posts = $stmt_posts->fetchAll() ?: [];
    $stmt_users = $pdo->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY full_name ASC");
    $users = $stmt_users->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log("Admin Panel Main Data Fetch Error: " . $e->getMessage());
    $admin_error = 'Не вдалося завантажити основні дані адміністрування.';
}

// Допоміжні функції
if (!function_exists('escape')) { function escape($string) { return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('format_datetime')) { function format_datetime($datetime_string, $format = 'd.m.Y H:i') { if (empty($datetime_string)) return '-'; try { $date = new DateTime($datetime_string); return $date->format($format); } catch (Exception $e) { return 'Нев.'; } } }
if (!function_exists('get_role_name_ukrainian')) { function get_role_name_ukrainian($role_code) { $roles = ['admin'=>'Адміністратор','director'=>'Директор','duty_officer'=>'Черговий','lifeguard'=>'Рятувальник','trainer'=>'Тренер','analyst'=>'Аналітик']; return $roles[$role_code] ?? 'Невідома роль'; } }
if (!function_exists('csrf_input')) { function csrf_input() { echo '<input type="hidden" name="csrf_token" value="'.(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '').'">'; } }

?>

<section id="admin-section" class="space-y-4 md:space-y-6">

    <div class="px-4 py-3 sm:px-6 panel-header-gradient text-white rounded-xl shadow-lg flex items-center justify-between">
        <h2 class="text-lg sm:text-xl leading-6 font-semibold flex items-center font-comfortaa">
            <i class="fas fa-user-shield mr-2 text-xl"></i> Панель Адміністратора
        </h2>
    </div>

    <?php if(function_exists('display_flash_message')) display_flash_message(); ?>

    <div class="border-b border-gray-200/80 mb-4">
        <nav aria-label="Адмін панель">
            <ul class="-mb-px flex flex-wrap items-center gap-2 p-2" id="adminTab" role="tablist">
                <?php
                $admin_tabs_config = [
                    'duty'           => ['label' => 'Статус Змін', 'icon' => 'fa-clipboard-list', 'type' => 'tab'],
                    'manage_shifts'  => ['label' => 'Керування Змінами', 'icon' => 'fa-tools', 'type' => 'link', 'url' => rtrim(APP_URL, '/') . '/admin/manage_shifts.php'],
                    'shift_history'  => ['label' => 'Історія Змін', 'icon' => 'fa-history', 'type' => 'tab'],
                    'posts-analytics'=> ['label' => 'Аналітика Постів', 'icon' => 'fa-chart-pie', 'type' => 'tab'],
                    'payroll_rating' => ['label' => 'Рейтинг', 'icon' => 'fa-star', 'type' => 'tab'],
                    'salary_report'  => ['label' => 'Зарплати', 'icon' => 'fa-money-bill-wave', 'type' => 'tab'],
                    'divider1'       => ['type' => 'divider'],
                    'posts'          => ['label' => 'Кер. Постами', 'icon' => 'fa-map-marker-alt', 'type' => 'tab'],
                    'users'          => ['label' => 'Користувачі', 'icon' => 'fa-users-cog', 'type' => 'tab'],
                    'divider2'       => ['type' => 'divider'],
                    'applications'   => ['label' => 'Заявки', 'icon' => 'fa-inbox', 'type' => 'tab'],
                    'academy'        => ['label' => 'Академія', 'icon' => 'fa-graduation-cap', 'type' => 'tab']
                ];

                foreach ($admin_tabs_config as $key => $tab) {
                    if ($tab['type'] === 'divider') {
                        echo '<li class="flex items-center mx-2"><span class="h-6 w-px bg-white/30" aria-hidden="true"></span></li>';
                        continue;
                    }

                    echo '<li role="presentation" class="flex-shrink-0">';

                    if ($tab['type'] === 'tab') {
                        echo '<button class="admin-nav-button group" id="admin-' . $key . '-tab" type="button" role="tab" title="' . escape($tab['label']) . '" onclick="showAdminTab(\'' . $key . '\')" aria-controls="admin-' . $key . '-content">';
                        echo '<i class="fas ' . $tab['icon'] . '"></i>';
                        echo '<span>' . escape($tab['label']) . '</span>';
                        echo '</button>';
                    } elseif ($tab['type'] === 'link') {
                        echo '<a href="' . escape($tab['url']) . '" class="admin-nav-button group" title="' . escape($tab['label']) . '">';
                        echo '<i class="fas ' . $tab['icon'] . '"></i>';
                        echo '<span>' . escape($tab['label']) . '</span>';
                        echo '</a>';
                    }
                    
                    echo '</li>';
                }
                ?>
            </ul>
        </nav>
    </div>

    <div id="adminTabContent">
    
        <div class="admin-tab-content" id="admin-duty-content" role="tabpanel" aria-labelledby="admin-duty-tab">
            <?php require __DIR__ . '/panels/duty_officer_content.php'; ?>
        </div>
        
        <div class="admin-tab-content hidden" id="admin-shift_history-content" role="tabpanel" aria-labelledby="admin-shift_history-tab">
            <?php require __DIR__ . '/panels/admin_shift_history_content.php'; ?>
        </div>
        
        <div class="admin-tab-content hidden" id="admin-posts-analytics-content" role="tabpanel" aria-labelledby="admin-posts-analytics-tab">
            <?php require __DIR__ . '/panels/admin_posts_analytics_content.php'; ?>
        </div>
        
        <div class="admin-tab-content hidden" id="admin-payroll_rating-content" role="tabpanel" aria-labelledby="admin-payroll_rating-tab">
            <?php require __DIR__ . '/panels/admin_payroll_rating_content.php'; ?>
        </div>

        <div class="admin-tab-content hidden" id="admin-salary_report-content" role="tabpanel" aria-labelledby="admin-salary_report-tab">
            <?php require __DIR__ . '/panels/admin_salary_report_content.php'; ?>
        </div>

        <div class="admin-tab-content hidden" id="admin-posts-content" role="tabpanel" aria-labelledby="admin-posts-tab">
             <div class="posts-container glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 space-y-6">
                 <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-gray-200/50">
                    <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0 flex items-center font-comfortaa"><i class="fas fa-map-marker-alt mr-3 text-indigo-500"></i>Керування Постами</h3>
                    <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/post_create.php" class="btn-green inline-flex items-center self-start sm:self-center transform hover:scale-105 transition-transform"><i class="fas fa-plus mr-2"></i> Додати Пост</a>
                </div>
                <?php if (empty($posts) && !$admin_error): ?>
                    <div class="text-center py-10 px-4"><i class="fas fa-map-signs text-4xl text-gray-400 mb-3"></i><p class="text-gray-500 italic">Пости ще не створено.</p><p class="mt-2 text-sm text-gray-600">Додайте свій перший пост.</p></div>
                <?php elseif ($admin_error): ?>
                    <p class="text-center text-red-500 py-10 px-4"><?php echo escape($admin_error); ?></p>
                <?php else: ?>
                    <div class="overflow-x-auto posts-list"><table class="min-w-full">
                            <thead class="hidden md:table-header-group bg-gray-50/30">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Назва Посту</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Опис</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">URL для NFC</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Дії</th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent">
                                <?php foreach ($posts as $post): ?>
                                    <?php $nfc_url = rtrim(APP_URL, '/') . "/scan_handler.php?post_id=" . $post['id']; ?>
                                    <tr class="post-item block md:table-row mb-4 md:mb-0 border md:border-0 border-gray-200/50 rounded-lg md:rounded-none shadow-md md:shadow-none overflow-hidden md:overflow-visible bg-white/70 md:bg-transparent hover:bg-gray-50/50 md:hover:bg-gray-50/30 transition-colors duration-150">
                                        <td data-label="ID:" class="px-4 py-2 md:py-3 whitespace-nowrap text-sm text-gray-500 border-b md:border-b-0 border-gray-200/30 font-mono"><?php echo escape($post['id']); ?></td>
                                        <td data-label="Назва:" class="px-4 py-2 md:py-3 whitespace-nowrap text-sm text-gray-800 font-medium border-b md:border-b-0 border-gray-200/30"><?php echo escape($post['name']); ?></td>
                                        <td data-label="Опис:" class="px-4 py-2 md:py-3 text-sm text-gray-600 md:max-w-xs md:truncate border-b md:border-b-0 border-gray-200/30" title="<?php echo escape($post['location_description'] ?? ''); ?>"><?php echo escape($post['location_description'] ?? '-'); ?></td>
                                        <td data-label="URL для NFC:" class="px-4 py-2 md:py-3 text-xs text-gray-600 border-b md:border-b-0 border-gray-200/30">
                                            <div class="flex items-center justify-end md:justify-start space-x-2">
                                                <span id="nfc-url-<?php echo $post['id']; ?>" class="truncate flex-grow pr-1 md:pr-2 hidden sm:inline font-mono" title="<?php echo escape($nfc_url); ?>"><?php echo escape($nfc_url); ?></span>
                                                <span id="copied-message-<?php echo $post['id']; ?>" class="text-green-600 text-xs italic hidden">Скопійовано!</span>
                                                <button type="button" onclick="copyToClipboardEnhanced('nfc-url-<?php echo $post['id']; ?>', this)" class="btn-secondary !p-1 !text-xs flex-shrink-0" title="Копіювати URL">
                                                    <i id="copy-icon-<?php echo $post['id']; ?>" class="far fa-copy"></i><i id="copied-icon-<?php echo $post['id']; ?>" class="fas fa-check text-green-500 hidden"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td data-label="Дії:" class="px-4 py-3 md:py-3 whitespace-nowrap text-center text-sm space-x-3">
                                            <div class="flex justify-end md:justify-center items-center space-x-3">
                                                <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/post_edit.php?id=<?php echo $post['id']; ?>" class="text-indigo-600 hover:text-indigo-800 transition-colors" title="Редагувати"><i class="fas fa-edit fa-fw"></i></a>
                                                <form action="<?php echo rtrim(APP_URL, '/'); ?>/admin/post_delete.php" method="POST" class="inline" onsubmit="return confirm('Видалити пост \'<?php echo escape(addslashes($post['name'])); ?>\'? Ця дія також може видалити пов\'язані зміни!');">
                                                    <?php csrf_input(); ?> <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 bg-transparent border-none p-0 cursor-pointer transition-colors" title="Видалити"><i class="fas fa-trash fa-fw"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-tab-content hidden" id="admin-users-content" role="tabpanel" aria-labelledby="admin-users-tab">
             <div class="users-container glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 space-y-6">
                 <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-gray-200/50">
                    <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0 flex items-center font-comfortaa"><i class="fas fa-users-cog mr-3 text-indigo-500"></i>Керування Користувачами</h3>
                    <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/user_create.php" class="btn-green inline-flex items-center self-start sm:self-center transform hover:scale-105 transition-transform"><i class="fas fa-user-plus mr-2"></i> Додати Користувача</a>
                 </div>
                  <?php if (empty($users) && !$admin_error): ?>
                    <div class="text-center py-10 px-4"><i class="fas fa-users-slash text-4xl text-gray-400 mb-3"></i><p class="text-gray-500 italic">Користувачі ще не створені.</p></div>
                  <?php elseif ($admin_error): ?>
                    <p class="text-center text-red-500 py-10 px-4"><?php echo escape($admin_error); ?></p>
                 <?php else: ?>
                    <div class="overflow-x-auto users-list"><table class="min-w-full">
                             <thead class="hidden md:table-header-group bg-gray-50/30">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ПІБ</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Роль</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Створено</th>
                                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Дії</th>
                                </tr>
                            </thead>
                            <tbody class="bg-transparent">
                                <?php foreach ($users as $user): ?>
                                <tr class="user-item block md:table-row mb-4 md:mb-0 border md:border-0 border-gray-200/50 rounded-lg md:rounded-none shadow-md md:shadow-none overflow-hidden md:overflow-visible bg-white/70 md:bg-transparent hover:bg-gray-50/50 md:hover:bg-gray-50/30 transition-colors duration-150">
                                    <td data-label="ID:" class="px-4 py-2 md:py-3 whitespace-nowrap text-sm text-gray-500 border-b md:border-b-0 border-gray-200/30 font-mono"><?php echo escape($user['id']); ?></td>
                                    <td data-label="ПІБ:" class="px-4 py-2 md:py-3 whitespace-nowrap text-sm text-gray-800 font-medium border-b md:border-b-0 border-gray-200/30"><?php echo escape($user['full_name']); ?></td>
                                    <td data-label="Email:" class="px-4 py-2 md:py-3 whitespace-nowrap text-sm text-gray-600 border-b md:border-b-0 border-gray-200/30"><?php echo escape($user['email']); ?></td>
                                    <td data-label="Роль:" class="px-4 py-2 md:py-3 whitespace-nowrap text-sm text-gray-600 border-b md:border-b-0 border-gray-200/30">
                                        <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full <?php switch ($user['role']) { case 'admin': echo 'bg-red-100 text-red-800'; break; case 'duty_officer': echo 'bg-yellow-100 text-yellow-800'; break; case 'lifeguard': echo 'bg-blue-100 text-blue-800'; break; case 'trainer': echo 'bg-purple-100 text-purple-800'; break; default: echo 'bg-gray-100 text-gray-800'; } ?>">
                                            <?php echo get_role_name_ukrainian($user['role']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Створено:" class="px-4 py-2 md:py-3 whitespace-nowrap text-center text-xs text-gray-500 border-b md:border-b-0 border-gray-200/30"><?php echo format_datetime($user['created_at'], 'd.m.Y'); ?></td>
                                    <td data-label="Дії:" class="px-4 py-3 md:py-3 whitespace-nowrap text-center text-sm space-x-3">
                                         <div class="flex justify-end md:justify-center items-center space-x-3">
                                            <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/user_edit.php?id=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-800 transition-colors" title="Редагувати"><i class="fas fa-edit fa-fw"></i></a>
                                            <?php if (isset($_SESSION['user_id']) && $user['id'] !== $_SESSION['user_id']): ?>
                                            <form action="<?php echo rtrim(APP_URL, '/'); ?>/admin/user_delete.php" method="POST" class="inline" onsubmit="return confirm('Видалити користувача \'<?php echo escape(addslashes($user['full_name'])); ?>\'? Ця дія необоротна!');">
                                                 <?php csrf_input(); ?><input type="hidden" name="id" value="<?php echo $user['id']; ?>"><button type="submit" class="text-red-600 hover:text-red-800 bg-transparent border-none p-0 cursor-pointer transition-colors" title="Видалити"><i class="fas fa-trash fa-fw"></i></button>
                                             </form>
                                             <?php else: ?>
                                                 <span class="text-gray-400 cursor-not-allowed" title="Неможливо видалити себе"><i class="fas fa-trash fa-fw"></i></span>
                                             <?php endif; ?>
                                         </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table></div>
                <?php endif; ?>
             </div>
        </div>
        
        <div class="admin-tab-content hidden" id="admin-applications-content" role="tabpanel" aria-labelledby="admin-applications-tab">
            <?php require __DIR__ . '/panels/admin_applications_content.php'; ?>
        </div>

        <div class="admin-tab-content hidden" id="admin-academy-content" role="tabpanel" aria-labelledby="admin-academy-tab">
             <div class="academy-container glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 space-y-6">
                 <div class="pb-4 border-b border-gray-200/50">
                    <h3 class="text-xl font-semibold text-gray-800 flex items-center font-comfortaa"><i class="fas fa-graduation-cap mr-3 text-indigo-500"></i>Лайфгард Академія</h3>
                    <p class="text-sm text-gray-600 mt-1">Управління навчальним процесом кандидатів у рятувальники.</p>
                 </div>
                 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 academy-links">
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/academy_groups.php" class="academy-link-card"> <i class="fas fa-users text-indigo-500"></i> <span>Групи та Тренери</span> <p>Призначення тренерів до груп</p> </a>
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/academy_candidates.php" class="academy-link-card"> <i class="fas fa-user-graduate text-teal-500"></i> <span>Кандидати</span> <p>Додавання та керування</p> </a>
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/academy/mark_attendance.php" class="academy-link-card"> <i class="fas fa-user-check text-amber-500"></i> <span>Відвідуваність</span> <p>Відмітка та перегляд</p> </a>
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/academy/mark_tests.php" class="academy-link-card"> <i class="fas fa-tasks text-sky-500"></i> <span>Тести</span> <p>Внесення результатів</p> </a>
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/academy/mark_standards.php" class="academy-link-card"> <i class="fas fa-award text-rose-500"></i> <span>Нормативи</span> <p>Внесення результатів</p> </a>
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/academy/view_group.php" class="academy-link-card"> <i class="fas fa-chart-line text-lime-500"></i> <span>Прогрес Групи</span> <p>Огляд прогресу кандидатів</p> </a>
                     <a href="<?php echo rtrim(APP_URL, '/'); ?>/academy/reports.php" class="academy-link-card"> <i class="fas fa-chart-pie text-purple-500"></i> <span>Звіти Академії</span> <p>Загальна статистика</p> </a>
                 </div>
             </div>
        </div>

    </div> 
</section>

<style>
    .admin-nav-button {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 2px 10px 0 rgba(0, 0, 0, 0.1);
        padding: 0.75rem 1rem;
        color: #ffffff;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        min-width: 2.5rem;
        height: 2.5rem;
        font-size: 0.875rem;
    }
    .admin-nav-button:hover {
        background: rgba(255, 255, 255, 0.25);
        box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 0.15);
        transform: translateY(-1px);
        color: #ffffff;
    }
    .admin-nav-button[aria-selected="true"] {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.4);
        color: #ffffff;
        box-shadow: 0 4px 15px 0 rgba(0, 0, 0, 0.2);
    }
    .admin-nav-button i {
        font-size: 1rem;
        margin-right: 0.5rem;
        color: #ffffff;
        width: 1rem;
        text-align: center;
    }
    .admin-nav-button span {
        display: none;
        color: #ffffff;
        font-weight: 500;
    }
    @media (min-width: 768px) {
        .admin-nav-button {
            min-width: auto;
            padding: 0.75rem 1.25rem;
        }
        .admin-nav-button span {
            display: inline;
        }
    }
    @media (max-width: 767px) {
        .admin-nav-button {
            min-width: 2.5rem;
            padding: 0.5rem;
        }
        .admin-nav-button i {
            margin-right: 0;
        }
    }

    .admin-tab-content.hidden { display: none !important; }
    .admin-tab-content { animation: fadeIn 0.4s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    .academy-link-card { @apply block p-4 rounded-lg border transition duration-300 ease-in-out hover:shadow-lg hover:scale-[1.03]; background-color: rgba(255, 255, 255, 0.8); border-color: rgba(209, 213, 219, 0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
    .academy-link-card i { @apply text-2xl mb-2; }
    .academy-link-card span { @apply font-semibold text-gray-800 block font-comfortaa; }
    .academy-link-card p { @apply text-xs text-gray-600 mt-1; }
    .panel-header-gradient { background: linear-gradient(90deg, rgba(220, 38, 38, 0.8), rgba(249, 115, 22, 0.8)); }
</style>

