<?php
// Файл: includes/header.php

// Припускаємо, що config.php (який запускає сесію) вже підключено
// викликаючим скриптом (index.php, login.php тощо).

// Визначаємо APP_URL, якщо ще не визначено (для безпеки посилань у хедері)
if (!defined('APP_URL')) {
    // Базове припущення, якщо запускається в корені або /lifeguard-tracker/
    // Визначаємо базовий шлях динамічно - налаштуйте за потреби
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // Перевіряємо наявність /lifeguard-tracker/ у URI
    // Обережніше з DOCUMENT_ROOT, якщо є аліаси або символічні посилання
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME']); // Шлях до поточного скрипта
    $base_dir = $_SERVER['DOCUMENT_ROOT']; // Корінь веб-сервера

    // Спробуємо знайти /lifeguard-tracker/ у шляху скрипта відносно кореня
    $relative_path = str_replace($base_dir, '', $script_dir);
    $tracker_pos = strpos($relative_path, '/lifeguard-tracker');

    if ($tracker_pos !== false) {
        // Беремо частину шляху до /lifeguard-tracker/ включно
        $app_path_segment = substr($relative_path, 0, $tracker_pos + strlen('/lifeguard-tracker'));
        define('APP_URL', $protocol . $host . $app_path_segment);
    } else {
         // Якщо не знайдено, припускаємо корінь або іншу конфігурацію
         // Можливо, краще встановити це жорстко в config.php, якщо можливо
        define('APP_URL', $protocol . $host);
    }

}
// Переконуємося, що немає слеша в кінці для консистентності
$base_url = rtrim(APP_URL, '/');

// Допоміжні функції, якщо вони ще не визначені (наприклад, у config.php)
// (Якщо вони гарантовано визначені у config.php, ці блоки можна прибрати)

if (!function_exists('display_flash_message')) {
    // Функція для відображення flash-повідомлень
    function display_flash_message() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $message['type'] ?? 'інфо';
            $text = $message['text'] ?? 'Повідомлення відсутнє.';
            $baseClasses = 'border-l-4 p-4 mb-4 rounded-md shadow-sm relative overflow-hidden'; // Додано relative overflow-hidden
            $typeClasses = ''; $icon = 'fa-info-circle'; $title = 'Інформація';

            switch ($type) {
                case 'успіх':
                    $typeClasses = 'bg-green-50 border-green-500 text-green-700';
                    $icon = 'fa-check-circle'; $title = 'Успіх'; break;
                case 'помилка':
                    $typeClasses = 'bg-red-50 border-red-500 text-red-700';
                    $icon = 'fa-exclamation-triangle'; $title = 'Помилка'; break;
                case 'інфо': default:
                    $typeClasses = 'bg-blue-50 border-blue-500 text-blue-700';
                    $icon = 'fa-info-circle'; $title = 'Інформація'; break;
            }

            // --- ДОДАНО ID ---
            echo '<div id="flash-message" class="' . $baseClasses . ' ' . $typeClasses . '" role="alert">';
            // Анімаційний елемент для зникнення (опційно)
            echo '<div class="flash-timer absolute bottom-0 left-0 h-1 bg-white/50"></div>';
            echo '<strong class="font-bold flex items-center"><i class="fas ' . $icon . ' mr-2"></i>' . htmlspecialchars($title) . '</strong>';
            echo '<span class="block sm:inline mt-1 text-sm">' . htmlspecialchars($text) . '</span>';
            // Кнопка закриття (опційно)
            echo '<button type="button" onclick="this.parentElement.remove()" class="absolute top-1 right-1 p-1 text-inherit opacity-70 hover:opacity-100">&times;</button>';
            echo '</div>';

            unset($_SESSION['flash_message']);
        }
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        // Перевіряє, чи є 'user_id' у сесії
        return isset($_SESSION['user_id']);
    }
}
if (!function_exists('escape')) {
    // Проста функція екранування HTML
    function escape($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('get_role_name_ukrainian')) {
    // Повертає українську назву ролі
    function get_role_name_ukrainian($role_code) {
        $roles = ['admin' => 'Адміністратор', 'duty_officer' => 'Черговий', 'lifeguard' => 'Лайфгард', 'director' => 'Директор', 'trainer' => 'Тренер'];
        return $roles[$role_code] ?? 'Невідома роль';
    }
}

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title><?php echo isset($page_title) ? escape($page_title) : 'Кабінет Лайфгарда'; ?></title>
    <link rel="manifest" href="<?php echo $base_url; ?>/manifest.json">
    <meta name="description" content="Веб-додаток для обліку чергувань Лайфгардів.">
    <meta name="theme-color" content="#DC2626">
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>/icons/favicon.ico">

    <!-- iOS PWA мета-теги -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Кабінет">
    <link rel="apple-touch-icon" href="<?php echo $base_url; ?>/icons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $base_url; ?>/icons/apple-touch-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="167x167" href="<?php echo $base_url; ?>/icons/apple-touch-icon-167x167.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>/icons/apple-touch-icon-180x180.png">

    <!-- iOS сплеш-скрини -->
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-640x1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-1125x2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-828x1792.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-1242x2688.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-1536x2048.png" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-1668x2224.png" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-1668x2388.png" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-2048x2732.png" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
    <link href="<?php echo $base_url; ?>/icons/splash/ios-splash-2436x1125.png" media="(device-width: 812px) and (device-height: 375px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js" defer></script>
    <style>
        :root {
            --safe-area-inset-top: env(safe-area-inset-top, 0px);
            --safe-area-inset-bottom: env(safe-area-inset-bottom, 0px);
            --safe-area-inset-left: env(safe-area-inset-left, 0px);
            --safe-area-inset-right: env(safe-area-inset-right, 0px);
            --header-height: 3rem; /* Зменшено висоту хедера */
            --footer-height: 3.5rem;
        }

        html {
            height: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Comfortaa', sans-serif;
            font-size: 14px;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            background-color: #f8fafc;
            position: relative;
            display: flex;
            flex-direction: column;
            padding-top: var(--safe-area-inset-top);
            padding-bottom: var(--safe-area-inset-bottom);
            padding-left: var(--safe-area-inset-left);
            padding-right: var(--safe-area-inset-right);
        }

        /* Оновлена фіксована шапка */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            z-index: 50;
            display: flex;
            align-items: center;
            padding: 0 1rem; /* Адаптивний відступ */
            color: white; /* Колір тексту та іконок */
        }

        .fixed-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(220, 38, 38, 0.75); /* Напівпрозорий червоний */
            border-bottom: 1px solid rgba(255, 255, 255, 0.2); /* Світла межа для ефекту скла */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            filter: url(#fluid-glass-bar);
            z-index: -1;
            border-radius: 0 0 0.75rem 0.75rem;
        }

        .fixed-header a, .fixed-header button {
            color: white; /* Забезпечуємо білий колір для посилань та кнопок */
        }

        /* Контейнер для контента шапки */
        .header-content {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        /* Основной контент */
        main {
            flex: 1;
            /* Відступ зверху тепер забезпечується блоком-розпіркою */
            position: relative;
            z-index: 1;
        }

        /* Футер */
        .fixed-footer {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-left: var(--safe-area-inset-left);
            padding-right: var(--safe-area-inset-right);
            padding-bottom: 2px;
            margin-top: auto; /* Додає відступ зверху, щоб футер прилипав до низу */
        }

        .fixed-footer::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            filter: url(#fluid-glass-bar);
            z-index: -1;
            border-radius: 0.75rem 0.75rem 0 0;
        }

        /* Адаптация для iOS с "монобровью" */
        @supports (-webkit-touch-callout: none) {
            .fixed-header {
                height: calc(var(--header-height) + var(--safe-area-inset-top));
                padding-top: var(--safe-area-inset-top);
            }
            .fixed-footer {
                height: calc(var(--footer-height) + var(--safe-area-inset-bottom));
                padding-bottom: var(--safe-area-inset-bottom);
            }
            main {
                padding-left: calc(1rem + var(--safe-area-inset-left));
                padding-right: calc(1rem + var(--safe-area-inset-right));
            }
            #flash-messages-container {
                top: calc(var(--header-height) + var(--safe-area-inset-top) + 1rem);
            }
            /* Правило для #side-menu видалено */
        }

        /* Стили для flash-сообщений */
        .flash-timer {
            animation: shrink 3s linear forwards;
        }
        @keyframes shrink {
            from { width: 100%; }
            to { width: 0%; }
        }

        /* Загрузочный оверлей */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        #loading-overlay.is-active {
            opacity: 1;
            pointer-events: auto;
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #DC2626; /* red-600 */
            animation: spin 1s ease infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ========== Fluid Glass Bar (override) ========== */
        .fixed-header { --bar-tint: rgba(220, 38, 38, 0.6); }
        .fixed-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(var(--bar-tint), var(--bar-tint)),
                linear-gradient(100deg, rgba(255,255,255,0.28) 0%, rgba(255,255,255,0.06) 35%, rgba(255,255,255,0.28) 70%, rgba(255,255,255,0.06) 100%),
                radial-gradient(120% 180% at 15% 0%, rgba(255,255,255,0.35) 0%, rgba(255,255,255,0.08) 60%, rgba(255,255,255,0) 100%);
            background-size: auto, 300% 100%, 200% 100%;
            background-position: center, 0% 50%, 0% 50%;
            border-bottom: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            backdrop-filter: blur(12px) saturate(160%);
            -webkit-backdrop-filter: blur(12px) saturate(160%);
            filter: url(#fluid-glass-bar);
            z-index: -1;
            border-radius: 0 0 0.75rem 0.75rem;
            animation: glassShine 12s ease-in-out infinite;
        }

        .fixed-footer { --bar-tint: rgba(15, 23, 42, 0.55); }
        .fixed-footer::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(var(--bar-tint), var(--bar-tint)),
                linear-gradient(100deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.06) 35%, rgba(255,255,255,0.22) 70%, rgba(255,255,255,0.06) 100%),
                radial-gradient(120% 180% at 85% 100%, rgba(255,255,255,0.28) 0%, rgba(255,255,255,0.08) 60%, rgba(255,255,255,0) 100%);
            background-size: auto, 300% 100%, 200% 100%;
            background-position: center, 0% 50%, 100% 50%;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 -6px 18px rgba(0, 0, 0, 0.12);
            backdrop-filter: blur(12px) saturate(160%);
            -webkit-backdrop-filter: blur(12px) saturate(160%);
            filter: url(#fluid-glass-bar);
            z-index: -1;
            border-radius: 0.75rem 0.75rem 0 0;
            animation: glassShine 12s ease-in-out infinite;
        }

        @keyframes glassShine {
            0% {
                background-position: center, 0% 50%, 0% 50%;
            }
            50% {
                background-position: center, 100% 50%, 100% 50%;
            }
            100% {
                background-position: center, 0% 50%, 0% 50%;
            }
        }

    </style>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(APP_URL, '/'); ?>/css/main-styles.css">
    <link rel="stylesheet" href="<?php echo rtrim(APP_URL, '/'); ?>/css/photo-approval-styles.css">
    <link rel="stylesheet" href="<?php echo rtrim(APP_URL, '/'); ?>/css/custom-styles.css">
    <!-- Скрипт mobile-menu.js видалено -->
    <script src="<?php echo rtrim(APP_URL, '/'); ?>/js/notifications.js" defer></script>
    <script src="<?php echo rtrim(APP_URL, '/'); ?>/js/app.js" defer></script>
    <script src="<?php echo rtrim(APP_URL, '/'); ?>/js/translations.js" defer></script>
</head>
    <body class="text-gray-800 flex flex-col min-h-screen font-comfortaa" style="padding-top: var(--safe-area-inset-top); padding-bottom: var(--safe-area-inset-bottom); padding-left: var(--safe-area-inset-left); padding-right: var(--safe-area-inset-right);">

    <!-- SVG filter for fluid glass effect -->
    <svg style="position: fixed; width: 0; height: 0;">
        <filter id="fluid-glass-bar">
            <feTurbulence type="fractalNoise" baseFrequency="0.2" numOctaves="1" seed="2" result="noise">
                <animate attributeName="baseFrequency" dur="20s" values="0.2;0.25;0.2" repeatCount="indefinite" />
            </feTurbulence>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="12" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </svg>

    <?php if (is_logged_in()): ?>
    <header class="fixed-header">
        <div class="header-content container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <!-- SVG іконка рятувального круга -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 sm:h-7 sm:w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="12" r="4"></circle>
                    <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"></line>
                    <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"></line>
                    <line x1="14.83" y1="9.17" x2="19.07" y2="4.93"></line>
                    <line x1="9.17" y1="14.83" x2="4.93" y2="19.07"></line>
                </svg>
                <a href="<?php echo $base_url; ?>/index.php" class="text-base sm:text-lg font-bold">Кабінет Лайфгарда</a>
            </div>
            <nav class="flex items-center space-x-1 sm:space-x-2 md:space-x-4">
                <!-- Навігаційні посилання видалено згідно з вимогою -->
            </nav>
            <div class="flex items-center space-x-2 sm:space-x-3">
                <a href="<?php echo $base_url; ?>/profile.php" class="flex items-center hover:text-gray-200">
                    <i class="fas fa-user-circle text-base sm:text-lg"></i> 
                    <span class="hidden sm:inline ml-2 text-xs sm:text-sm font-semibold"><?php echo escape($_SESSION['full_name'] ?? 'Профіль'); ?></span>
                </a>
                <a href="<?php echo $base_url; ?>/logout.php" class="hover:text-red-100 p-1 rounded-md hover:bg-red-700 transition-colors duration-200" title="Вийти"><i class="fas fa-sign-out-alt text-base sm:text-lg"></i></a>
            </div>
        </div>
    </header>

    <!-- Невидимий блок-розпірка для компенсації висоти фіксованого хедера -->
    <div style="height: var(--header-height);"></div>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto p-4">
        <!-- Flash messages container -->
        <div id="flash-messages-container" class="fixed left-1/2 -translate-x-1/2 w-11/12 max-w-md" style="top: calc(var(--header-height) + var(--safe-area-inset-top, 0px) + 1rem); z-index: 9999;">
             <?php display_flash_message(); ?>
        </div>
    <?php endif; ?>
</body>
</html>
