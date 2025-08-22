<?php
// /includes/duty_officer_dashboard.php

if (session_status() == PHP_SESSION_NONE) {
    // Це не повинно відбуватися, якщо config.php відпрацював правильно в index.php
    // error_log("ПОПЕРЕДЖЕННЯ: Сесія не активна на початку duty_officer_dashboard.php");
    session_start();
}

// Перевіряємо роль
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'duty_officer') {
    // Функції set_flash_message та smart_redirect можуть бути ще не підключені,
    // якщо цей файл викликається напряму без попереднього підключення config.php -> functions.php.
    // Але оскільки він підключається з index.php, де config.php вже підключено, вони мають бути доступні.
    if (function_exists('set_flash_message') && function_exists('smart_redirect')) {
        set_flash_message('помилка', 'Доступ заборонено (dash).');
        smart_redirect('index.php');
    } else {
        // Абсолютний мінімум, якщо щось зовсім не так з підключеннями
        $_SESSION['flash_message_manual'] = ['type' => 'помилка', 'text' => 'Доступ заборонено (d_dash_fallback).'];
        $app_url_fallback_dash = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        // Спроба визначити базовий шлях проєкту для коректного редиректу
        $ds_dash = DIRECTORY_SEPARATOR;
        $project_path_parts_dash = explode($ds_dash, __DIR__); 
        $base_path_index_dash = array_search('lifeguard-tracker', $project_path_parts_dash);
        if ($base_path_index_dash !== false) {
            $project_base_segments_dash = array_slice($project_path_parts_dash, 0, $base_path_index_dash + 1);
            $app_url_fallback_dash .= str_replace($_SERVER['DOCUMENT_ROOT'], '', implode($ds_dash, $project_base_segments_dash));
        } else {
             // Якщо lifeguard-tracker не знайдено у шляху, можемо припустити, що це корінь
             // Або додати /lifeguard-tracker, якщо це завжди частина URL
             // Поки що залишимо як є, або додамо відомий префікс
             // $app_url_fallback_dash .= '/lifeguard-tracker'; // якщо це завжди так
        }
        header('Location: ' . rtrim($app_url_fallback_dash, '/') . '/index.php');
    }
    exit();
}

// Глобальні змінні, які мають бути передані з index.php або визначені в config.php
global $pdo, $APP_URL, $page_data;

// Перевірка та встановлення APP_URL, якщо не визначено
if (!isset($APP_URL) || empty($APP_URL)) {
    if (defined('APP_URL') && APP_URL) {
        $APP_URL = APP_URL;
    } else {
        $protocol_dash = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host_dash = $_SERVER['HTTP_HOST'];
        $script_dir_from_root_dash = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
        $project_root_from_web_dash = dirname($script_dir_from_root_dash);
        $APP_URL = rtrim($protocol_dash . $host_dash . $project_root_from_web_dash, '/');
        // Якщо константа APP_URL не була визначена, можна спробувати її визначити тут, але це не найкраща практика
        // if (!defined('APP_URL')) define('APP_URL', $APP_URL_local_dash);
    }
}
$APP_URL = rtrim($APP_URL, '/'); // Переконуємось, що немає слеша в кінці


if (!isset($page_data) || !is_array($page_data)) {
    $page_data = ['post_grid_data' => []];
} elseif (!isset($page_data['post_grid_data'])) {
    $page_data['post_grid_data'] = [];
}

// --- ВИЗНАЧЕННЯ КЛЮЧОВИХ ЗМІННИХ ДЛЯ ПАНЕЛІ ---
$today_date = date('Y-m-d');

$operational_selected_date = $today_date;
if (isset($_GET['date']) && empty($_GET['filter_year']) && empty($_GET['filter_month']) && empty($_GET['filter_day']) && empty($_GET['filter_post_id']) && empty($_GET['filter_user_id'])) {
    $date_from_get_op_dashboard = $_GET['date'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_get_op_dashboard) && $date_from_get_op_dashboard <= $today_date) {
        try {
            $d_op_dashboard = new DateTime($date_from_get_op_dashboard); // Використовуємо DateTime для надійності
            if ($d_op_dashboard && $d_op_dashboard->format('Y-m-d') === $date_from_get_op_dashboard) {
                $operational_selected_date = $date_from_get_op_dashboard;
            }
        } catch (Exception $e) {
            // error_log("Invalid date format in GET['date'] for operational panel: " . $date_from_get_op_dashboard);
        }
    }
}
$operational_date_start_for_content = $operational_selected_date . ' 00:00:00';
$operational_date_end_for_content = $operational_selected_date . ' 23:59:59';

// Визначаємо активну вкладку
$active_duty_tab = 'operational';
if (isset($_GET['tab_duty']) && in_array($_GET['tab_duty'], ['operational', 'history'])) {
    $active_duty_tab = $_GET['tab_duty'];
} else {
    $current_url_hash_for_duty_tab = '';
     if (isset($_SERVER['REQUEST_URI'])) { // Використовуємо REQUEST_URI, бо реферер може бути не завжди
        $url_components_for_duty = parse_url($_SERVER['REQUEST_URI']);
        $current_url_hash_for_duty_tab = $url_components_for_duty['fragment'] ?? '';
    }

    if ($current_url_hash_for_duty_tab && strpos($current_url_hash_for_duty_tab, 'duty-') === 0) {
        $hash_tab_duty_check = substr($current_url_hash_for_duty_tab, 5);
        if (in_array($hash_tab_duty_check, ['operational', 'history'])) {
            $active_duty_tab = $hash_tab_duty_check;
        }
    }
}
?>

<section id="duty-officer-dashboard" class="space-y-4 md:space-y-6">

    <div class="px-4 py-3 sm:px-6 panel-header-gradient text-white rounded-xl shadow-lg flex items-center justify-between">
        <h2 class="text-lg sm:text-xl leading-6 font-semibold flex items-center font-comfortaa">
            <i class="fas fa-user-tie mr-2 text-xl"></i> Панель Чергового
        </h2>
    </div>

    <?php if(function_exists('display_flash_message')) display_flash_message(); ?>

    <div class="border-b border-gray-200/80">
        <nav aria-label="Панель чергового">
            <ul class="-mb-px flex flex-wrap space-x-1 sm:space-x-2" id="dutyOfficerTab" role="tablist">
                <li role="presentation" class="flex-shrink-0">
                    <button class="duty-officer-tab-button group inline-flex items-center justify-center pt-3 pb-2 px-3 sm:px-4 border-b-2 font-medium text-sm focus:outline-none focus:ring-1 focus:ring-sky-400 focus:ring-offset-0 rounded-t-md <?php echo $active_duty_tab === 'operational' ? 'border-sky-500 text-sky-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            id="duty-operational-tab"
                            type="button" role="tab"
                            title="Оперативна Панель"
                            onclick="showDutyOfficerTab('operational')"
                            aria-controls="duty-operational-content"
                            aria-selected="<?php echo $active_duty_tab === 'operational' ? 'true' : 'false'; ?>">
                        <i class="fas fa-binoculars text-base mr-0 md:mr-2 <?php echo $active_duty_tab === 'operational' ? 'text-sky-600' : 'text-gray-400 group-hover:text-gray-500'; ?>"></i>
                        <span class="hidden md:inline <?php echo $active_duty_tab === 'operational' ? 'text-sky-600 font-semibold' : 'text-gray-500 group-hover:text-gray-700'; ?>">Оперативна Панель</span>
                    </button>
                </li>
                <li class="flex-shrink-0">
                    <a href="<?php echo rtrim((defined('APP_URL') ? APP_URL : (isset($base_url) ? $base_url : '')), '/'); ?>/admin/manage_shifts.php" class="duty-officer-tab-button group inline-flex items-center justify-center pt-3 pb-2 px-3 sm:px-4 border-b-2 font-medium text-sm focus:outline-none focus:ring-1 focus:ring-sky-400 focus:ring-offset-0 rounded-t-md border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" title="Керування змінами">
                        <i class="fas fa-tasks text-base mr-0 md:mr-2 text-gray-400 group-hover:text-gray-500"></i>
                        <span class="hidden md:inline text-gray-500 group-hover:text-gray-700">Керування змінами</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div id="dutyOfficerTabContent">
        <div class="duty-officer-tab-content <?php echo $active_duty_tab === 'operational' ? 'block' : 'hidden'; ?>" id="duty-operational-content" role="tabpanel" aria-labelledby="duty-operational-tab">
            <?php
            $file_to_include_op_panel = __DIR__ . '/panels/duty_officer_content.php';
            if (file_exists($file_to_include_op_panel)) {
                require $file_to_include_op_panel;
            } else {
                echo "<p class='text-red-500 p-4'>Помилка: Не вдалося знайти файл панелі duty_officer_content.php за шляхом: " . htmlspecialchars($file_to_include_op_panel) . "</p>";
                error_log("CRITICAL: duty_officer_content.php not found at " . $file_to_include_op_panel);
            }
            ?>
        </div>
    </div>
</section>

<style>
    .duty-officer-tab-button {
        @apply inline-flex items-center justify-center px-3 py-3 border-b-2 font-medium text-sm focus:outline-none focus:ring-1 focus:ring-offset-0 focus:ring-sky-400 rounded-t-md transition-colors duration-200;
    }
    .duty-officer-tab-button:not([aria-selected="true"]) { 
        @apply border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300;
    }
    .duty-officer-tab-button:not([aria-selected="true"]) i {
         @apply text-gray-400 group-hover:text-gray-500;
    }
     .duty-officer-tab-button:not([aria-selected="true"]) span { 
         @apply text-gray-500 group-hover:text-gray-700;
    }
    .duty-officer-tab-button[aria-selected="true"] { 
        @apply border-sky-500 text-sky-600 font-semibold;
    }
    .duty-officer-tab-button[aria-selected="true"] i { 
        @apply text-sky-600;
    }
     .duty-officer-tab-button[aria-selected="true"] span { 
        @apply text-sky-600 font-semibold;
    }
    .duty-officer-tab-button span {
        @apply ml-2 hidden md:inline;
    }
     .duty-officer-tab-content.block {
        animation: fadeInDutyTab 0.3s ease-out;
     }
     .duty-officer-tab-content.hidden {
        animation: none; 
     }
     @keyframes fadeInDutyTab {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
     }
    .panel-header-gradient {
        background: linear-gradient(90deg, #dc2626 0%, #f97316 100%);
    }
</style>
<script>
function showDutyOfficerTab(tabName) {
    const tabMap = {
        'operational': { contentId: 'duty-operational-content', buttonId: 'duty-operational-tab' },
        'history':     { contentId: 'duty-history-content',     buttonId: 'duty-history-tab' }
    };
    const activeTabInfo = tabMap[tabName];
    if (!activeTabInfo) {
        console.error('Unknown duty officer tab name:', tabName);
        return;
    }
    document.querySelectorAll('.duty-officer-tab-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('block');
    });
    document.querySelectorAll('.duty-officer-tab-button').forEach(button => {
        button.setAttribute('aria-selected', 'false');
        button.classList.remove('border-sky-500', 'text-sky-600', 'font-semibold');
        button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        const icon = button.querySelector('i');
        const span = button.querySelector('span');
        if (icon) {
            icon.classList.remove('text-sky-600');
            icon.classList.add('text-gray-400', 'group-hover:text-gray-500');
        }
        if (span) {
            span.classList.remove('text-sky-600', 'font-semibold');
            span.classList.add('text-gray-500', 'group-hover:text-gray-700');
        }
    });
    const activeContent = document.getElementById(activeTabInfo.contentId);
    if (activeContent) {
        activeContent.classList.remove('hidden');
        activeContent.classList.add('block'); 
        activeContent.style.animation = 'none';
        void activeContent.offsetWidth; 
        activeContent.style.animation = 'fadeInDutyTab 0.3s ease-out';
    }
    const activeButton = document.getElementById(activeTabInfo.buttonId);
    if (activeButton) {
        activeButton.setAttribute('aria-selected', 'true');
        activeButton.classList.add('border-sky-500', 'text-sky-600', 'font-semibold');
        activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        const icon = activeButton.querySelector('i');
        const span = activeButton.querySelector('span');
        if (icon) {
            icon.classList.add('text-sky-600');
            icon.classList.remove('text-gray-400', 'group-hover:text-gray-500');
        }
         if (span) {
            span.classList.add('text-sky-600', 'font-semibold');
            span.classList.remove('text-gray-500', 'group-hover:text-gray-700');
        }
    }
    try {
        const currentUrl = new URL(window.location.href.split('#')[0]);
        currentUrl.searchParams.set('tab_duty', tabName);
        currentUrl.hash = 'duty-' + tabName; // Використовуємо 'duty-' як префікс для якоря
        history.replaceState(null, '', currentUrl.toString());
    } catch (e) {
        try { history.replaceState(null, '', '#duty-' + tabName); }
        catch (eInner) { console.warn("Could not update URL hash for duty officer tab:", eInner); }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const currentUrlParams = new URLSearchParams(window.location.search);
    const hash = window.location.hash;
    let initialDutyTab = currentUrlParams.get('tab_duty') || 'operational';

    if (hash.startsWith('#duty-')) {
        const tabNameFromHash = hash.substring(6); // '#duty-' -> 6 символів
        if (document.getElementById('duty-' + tabNameFromHash + '-tab')) {
            initialDutyTab = tabNameFromHash;
        }
    }
    
    showDutyOfficerTab(initialDutyTab);

    if (historyFiltersForm) {
    const currentAction = historyFiltersForm.getAttribute('action');
    let newAction = currentAction;
    let targetAnchor = '';
    
    // Визначаємо, на якій "основній" сторінці/панелі ми
    if (document.getElementById('dutyOfficerTabContent')) { 
         targetAnchor = '#duty-history-content'; // Якір для вкладки історії у чергового
    } else if (document.getElementById('adminTabContent')) { 
        targetAnchor = '#admin-shift-history-content'; // Якір для вкладки історії у адміна
    }

    if (targetAnchor) { // Якщо ми визначили, де знаходимось
         if (currentAction) {
            const actionParts = currentAction.split('#'); // Розділяємо URL і старий якір
            // Формуємо новий URL з правильним якорем, зберігаючи GET-параметри, якщо вони були в action
            // Зазвичай action для форми не містить GET-параметрів, вони додаються при відправці.
            // Тому беремо лише шлях до скрипта.
            let basePathForAction = actionParts[0];
            // Якщо basePathForAction порожній або не містить імені файлу, встановлюємо index.php
            if (!basePathForAction || !basePathForAction.includes('.php')) {
                basePathForAction = 'index.php';
            }
            newAction = basePathForAction + targetAnchor;
        } else {
            // Якщо action порожній, встановлюємо index.php з якорем
            newAction = 'index.php' + targetAnchor;
        }
        historyFiltersForm.setAttribute('action', newAction);
    }
}
});
</script>