<?php
// This file assumes config.php (which starts session) is already included
// by the calling script (like index.php, login.php etc.)
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кабінет лайфгарда</title>

    <!-- PWA & SEO -->
    <link rel="manifest" href="/lifeguard-tracker/manifest.json">
    <meta name="description" content="Веб-додаток для обліку чергувань рятувальників.">
    <meta name="theme-color" content="#DC2626"> <!-- Червоний колір теми -->

    <!-- Favicons & Apple Touch Icons -->
    <link rel="icon" type="image/x-icon" href="/lifeguard-tracker/icons/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="/lifeguard-tracker/icons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/lifeguard-tracker/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Кабінет">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <!-- CSS & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* Застосовуємо шрифт Comfortaa до всього тіла */
        body {
            font-family: 'Comfortaa', sans-serif;
        }

        /* --- ВЛАСНІ СТИЛІ --- */
        /* Цей клас .wave-bg більше не потрібен, якщо фон сірий */
        /* .wave-bg { ... } */

        .btn-red {
            display: inline-flex; /* Для вирівнювання іконок */
            align-items: center;
            justify-content: center;
            background-color: #e53e3e; /* Tailwind bg-red-600 */
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.375rem; /* Tailwind rounded-md */
            font-weight: 700;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); /* Tailwind shadow-md */
            transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            text-decoration: none; /* Прибираємо підкреслення для <a> */
        }
        .btn-red:hover {
            background-color: #c53030; /* Tailwind bg-red-700 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* Tailwind shadow-lg */
        }

        .btn-green {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #38a169; /* Tailwind bg-green-600 */
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.375rem;
            font-weight: 700;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            text-decoration: none;
        }
        .btn-green:hover {
            background-color: #2f855a; /* Tailwind bg-green-700 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #ffffff; /* Tailwind bg-white */
            color: #4a5568; /* Tailwind text-gray-700 */
            padding: 0.6rem 1.2rem;
            border-radius: 0.375rem;
            font-weight: 700;
            border: 1px solid #e2e8f0; /* Tailwind border-gray-300 */
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background-color: #f7fafc; /* Tailwind bg-gray-100 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Стилі для Toast */
        .toast {
            position: fixed; bottom: 20px; right: 20px; background-color: #333; color: #fff; padding: 15px; border-radius: 0.375rem; /* rounded-md */ z-index: 1000; opacity: 0; transition: opacity 0.5s ease-in-out; font-family: sans-serif; /* Системний шрифт для сповіщень */
        }
        .toast.show { opacity: 1; }
                /* --- Mobile Table Styles --- */
        @media (max-width: 767px) { /* Tailwind 'md' breakpoint */

            /* Apply to both admin tables */
            #admin-posts-content table,
            #admin-users-content table,
            /* Add similar selectors for tables in lifeguard/duty officer panels if needed */
            #lifeguard-section table, /* Example for lifeguard history */
            #duty-section table /* Unlikely needed for duty officer cards, but just in case */
            {
                border: 0; /* Remove table border */
            }

            #admin-posts-content table thead,
            #admin-users-content table thead,
            #lifeguard-section table thead {
                display: none; /* Hide headers on mobile */
            }

            #admin-posts-content table tr,
            #admin-users-content table tr,
            #lifeguard-section table tr {
                display: block; /* Rows become blocks (cards) */
                margin-bottom: 1rem; /* Space between cards */
                border: 1px solid #e2e8f0; /* Border around card */
                border-radius: 0.375rem; /* Rounded corners */
                background-color: #ffffff; /* White background for card */
                padding: 0.75rem; /* Padding inside card */
                box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06); /* Shadow */
            }

            #admin-posts-content table td,
            #admin-users-content table td,
            #lifeguard-section table td {
                display: block; /* Cells become blocks */
                text-align: right; /* Align cell content to the right */
                padding-left: 50%; /* Reserve space for the label */
                position: relative; /* Needed for absolute positioning of ::before */
                border-bottom: 1px dotted #e2e8f0; /* Separator line between "cells" */
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
                font-size: 0.875rem; /* text-sm */
            }

            /* Remove border from the last cell in a card */
            #admin-posts-content table td:last-child,
            #admin-users-content table td:last-child,
            #lifeguard-section table td:last-child {
                border-bottom: 0;
            }

            /* Add the label before the cell content */
            #admin-posts-content table td::before,
            #admin-users-content table td::before,
            #lifeguard-section table td::before {
                content: attr(data-label); /* Get text from data-label attribute */
                position: absolute;
                left: 0.75rem; /* Position label on the left */
                width: calc(50% - 1.5rem); /* Calculate label width */
                padding-right: 0.5rem;
                font-weight: 700; /* Make label bold */
                text-align: left; /* Align label text left */
                white-space: nowrap;
                color: #4a5568; /* text-gray-700 for label */
            }

            /* Specific adjustments for the "Actions" cell */
            #admin-posts-content table td[data-label="Дії:"],
            #admin-users-content table td[data-label="Дії:"] {
                text-align: left; /* Align actions left */
                padding-left: 0.75rem; /* Reset padding */
                 border-bottom: 0; /* Often the last cell, remove border */
            }
            #admin-posts-content table td[data-label="Дії:"]::before,
            #admin-users-content table td[data-label="Дії:"]::before {
                content: ""; /* Hide the "Дії:" label itself */
            }

             /* Specific adjustments for the "URL для NFC" cell */
            #admin-posts-content table td[data-label="URL для NFC:"] {
                 padding-left: 0.75rem; /* Reset padding for better layout */
                 text-align: left;
            }
             #admin-posts-content table td[data-label="URL для NFC:"]::before {
                width: 100%; /* Let label take full width */
                position: relative; /* Change position */
                display: block; /* Make label a block */
                text-align: left;
                margin-bottom: 0.25rem; /* Space below label */
                padding-right: 0;
                left: 0;
            }
             #admin-posts-content table td[data-label="URL для NFC:"] span {
                word-break: break-all; /* Allow URL to wrap */
                display: block; /* Make span a block */
                text-align: left;
                width: 100%;
                padding-right: 30px; /* Space for the copy button */
                font-size: 0.75rem; /* Make URL smaller */
            }
            #admin-posts-content table td[data-label="URL для NFC:"] button {
               position: absolute; /* Position copy button */
               right: 0.75rem;
               top: 50%;
               transform: translateY(-50%);
               margin-left: 0; /* Reset margin */
            }
        }
        /* --- End Mobile Table Styles --- */
        
        /* Modal styles */
#report-modal .inline-block { /* Для вертикального центрування */
  vertical-align: middle;
}
#report-modal dt { /* Стилі для назв полів в модалці */
    white-space: nowrap;
    padding-right: 0.5rem; /* відступ від значення */
}
#report-modal dd { /* Стилі для значень в модалці */
    /* Можна додати font-medium */
}
/* Класи для JSON-полів в модалці (необов'язково) */
#report-modal .incident-detail-item ul {
  padding-left: 1.5rem;
  list-style: disc;
}
#report-modal .incident-detail-item li {
  margin-bottom: 0.1rem;
}
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex flex-col min-h-screen"> <!-- Додано flex для притискання футера -->
    <header class="bg-red-600 text-white p-4 shadow-md sticky top-0 z-50"> <!-- Додано sticky top-0 z-50 -->
        <div class="container mx-auto flex justify-between items-center">
            <!-- Використовуємо посилання на головну для логотипу -->
            <a href="<?php echo defined('APP_URL') ? rtrim(APP_URL, '/') : '/'; ?>/index.php" class="text-xl font-bold hover:text-red-100">
                 <i class="fas fa-life-ring mr-2"></i> KLS кабінет
            </a>
            <nav class="flex items-center space-x-4"> <!-- Використовуємо flex для навігації -->
                <?php if (is_logged_in()): ?>
                    <!-- === ПОСИЛАННЯ НА ПЕРЕГЛЯД ЗВІТІВ === -->
                    <?php if (in_array($_SESSION['user_role'], ['admin', 'duty_officer'])): ?>
                        <a href="<?php echo defined('APP_URL') ? rtrim(APP_URL, '/') : '/'; ?>/admin/view_reports.php" class="hover:text-red-100" title="Перегляд Звітів">
                            <i class="fas fa-file-alt mr-1"></i>
                            <span class="hidden sm:inline">Звіти</span> <!-- Показувати текст на sm+ екранах -->
                        </a>
                    <?php endif; ?>
                     <!-- === КІНЕЦЬ ПОСИЛАННЯ НА ЗВІТИ === -->

                    <!-- Посилання на профіль для всіх ролей -->
                     <a href="<?php echo defined('APP_URL') ? rtrim(APP_URL, '/') : '/'; ?>/profile.php" class="hover:text-red-100" title="Ваш профіль">
                        <i class="fas fa-user mr-1"></i>
                        <!-- Можна відобразити ПІБ, але може бути задовго для хедера -->
                        <span class="hidden sm:inline"><?php echo escape($_SESSION['full_name'] ?? 'Профіль'); ?></span>
                     </a>

                    <!-- Відображення ролі (опціонально, може займати місце) -->
                    <!-- <span class="hidden md:inline text-sm text-red-200">(<?php echo get_role_name_ukrainian($_SESSION['user_role']); ?>)</span> -->

                    <!-- Кнопка Вийти -->
                    <a href="/lifeguard-tracker/logout.php" class="hover:text-red-100" title="Вийти">
                        <i class="fas fa-sign-out-alt mr-1"></i>
                        <span class="hidden sm:inline">Вийти</span>
                    </a>
                
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Flash Message Display Area -->
    <div class="container mx-auto px-4 py-2">
        <?php display_flash_message(); /* Переконайтесь, що стилі тут відповідають гамі */ ?>
    </div>

    <!-- Start of main content area -->
    <!-- Додано flex-grow, щоб main займав доступний простір -->
    <main class="flex-grow bg-gray-100 text-gray-800 p-6 container mx-auto rounded-lg mb-6">
        <!-- Main content will be injected here by index.php -->