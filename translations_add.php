<?php
// translations_add.php - Универсальная система переводов
// Работает с различными структурами данных переводов

// Переводы для фото подтверждения
$photoApprovalTranslations = [
    "photos_for_approval" => "Фото на Підтвердження",
    "no_photos_for_approval_currently" => "Наразі немає фото для підтвердження.",
    
    "lifeguard_label" => "Рятувальник",
    "post_label" => "Пост", 
    "start_time_label" => "Час початку",
    
    "view_photo_title" => "Переглянути фото у новій вкладці",
    "photo_alt_text" => "Фото відкриття зміни",
    "no_photo_short" => "Немає фото",
    
    "assign_lifeguard_type_on_shift_label" => "Тип зміни:",
    "select_type_short" => "Виберіть тип...",
    "lifeguard_l0_label" => "Л0 (Один, 10-19)",
    "lifeguard_l1_label" => "Л1 (Пара, 9-18)", 
    "lifeguard_l2_label" => "Л2 (Пара, 11-20)",
    
    "approve_button" => "Підтвердити",
    "reject_button" => "Відхилити",
    
    "photo_already_approved" => "Фото вже підтверджено",
    "waiting_for_photo_upload" => "Очікується завантаження фото",

    // Повідомлення для approve_photo.php
    "incorrect_request" => "Некоректний запит.",
    "csrf_token_error" => "Помилка CSRF токену.",
    "error_assignment_type_required" => "Будь ласка, виберіть тип призначення рятувальника для підтвердження.",
    "photo_approved_type_assigned_msg" => "Фото для зміни ID %s підтверджено, тип призначення встановлено.",
    "photo_already_approved_msg" => "Фото для зміни ID %s вже було підтверджено.",
    "photo_not_found_or_no_approval_needed_msg" => "Фото для зміни ID %s не знайдено або не потребувало підтвердження.",
    "error_approving_photo_db" => "Не вдалося підтвердити фото (помилка БД).",
    "photo_rejected_msg" => "Фото для зміни ID %s відхилено. Рятувальник має завантажити нове.",
    "shift_not_found_or_no_action_needed_msg" => "Зміна ID %s не знайдена або дія не потрібна.",
    "error_rejecting_photo_db" => "Не вдалося відхилити фото (помилка БД).",
    "db_error_processing_photo" => "Помилка бази даних під час обробки фото.",
    "log_photo_approved_assigned_type" => "Підтвердив фото відкриття та призначив тип Л%s для зміни #%s",
    "log_photo_rejected" => "Відхилив фото відкриття для зміни #%s",
    "error_flash" => "Помилка",
    "success_flash" => "Успіх", 
    "info_flash" => "Інфо"
];

// Добавляем переводы в различные возможные структуры данных
// 1. В глобальную переменную $translations
if (isset($GLOBALS['translations'])) {
    $GLOBALS['translations'] = array_merge($GLOBALS['translations'], $photoApprovalTranslations);
} else {
    $GLOBALS['translations'] = $photoApprovalTranslations;
}

// 2. В локальную переменную $translations
if (isset($translations)) {
    $translations = array_merge($translations, $photoApprovalTranslations);
} else {
    $translations = $photoApprovalTranslations;
}

// 3. В переменную $lang (часто используется в системах переводов)
if (isset($lang)) {
    $lang = array_merge($lang, $photoApprovalTranslations);
} else {
    $lang = $photoApprovalTranslations;
}

// 4. В переменную $LANG (альтернативный вариант)
if (isset($LANG)) {
    $LANG = array_merge($LANG, $photoApprovalTranslations);
} else {
    $LANG = $photoApprovalTranslations;
}

// Безопасная функция получения значения из массива
if (!function_exists('safe_get')) {
    function safe_get($array, $key, $default = null) {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

// Функция форматирования даты и времени
if (!function_exists('format_datetime_user_tz')) {
    function format_datetime_user_tz($datetime) {
        if (empty($datetime)) return '-';
        
        try {
            $dt = new DateTime($datetime);
            return $dt->format('d.m.Y H:i');
        } catch (Exception $e) {
            return $datetime;
        }
    }
}

// Функция перевода с fallback, если основная не работает
if (!function_exists('translate_fallback')) {
    function translate_fallback($key, $default = null) {
        global $translations, $lang, $LANG;
        
        // Проверяем различные глобальные переменные
        $sources = [
            $GLOBALS['translations'] ?? null,
            $translations ?? null,
            $lang ?? null,
            $LANG ?? null
        ];
        
        foreach ($sources as $source) {
            if (is_array($source) && isset($source[$key])) {
                return $source[$key];
            }
        }
        
        return $default ?? $key;
    }
}

// Улучшенная функция перевода, которая проверяет, работает ли основная функция t()
if (!function_exists('t_enhanced')) {
    function t_enhanced($key, $fallback = null) {
        // Сначала пробуем основную функцию t()
        if (function_exists('t')) {
            $result = t($key);
            // Если результат отличается от ключа, значит перевод найден
            if ($result !== $key) {
                return $result;
            }
        }
        
        // Если основная функция не сработала, используем fallback
        return translate_fallback($key, $fallback);
    }
}

// Создаем alias для удобства использования
if (!function_exists('tr')) {
    function tr($key, $fallback = null) {
        return t_enhanced($key, $fallback);
    }
}



?>