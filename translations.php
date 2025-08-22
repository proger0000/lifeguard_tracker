<?php
// translations.php - PHP версия переводов
// Глобальные переводы для использования в PHP коде

/**
 * Переводы для фото підтвердження та карточок
 */
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

/**
 * PHP функція перекладу
 * @param string $key - ключ перекладу
 * @param string $lang - мова (за замовчуванням 'uk')
 * @return string - переклад або ключ, якщо переклад не знайдено
 */
function tr($key, $lang = 'uk') {
    global $photoApprovalTranslations;
    
    if (isset($photoApprovalTranslations[$key])) {
        return $photoApprovalTranslations[$key];
    }
    
    // Логування відсутніх перекладів для дебагу
    error_log("Translation not found for key: {$key}");
    
    return $key; // Повертаємо ключ, якщо переклад не знайдено
}

/**
 * Безпечна функція отримання значення з масиву
 * @param array $array - масив
 * @param string $key - ключ
 * @param mixed $default - значення за замовчуванням
 * @return mixed
 */
function safe_get($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>