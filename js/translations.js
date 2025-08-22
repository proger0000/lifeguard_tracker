// =======================================================
// Файл: js/translations.js
// Глобальні об'єкти для перекладу назв полів та значень
// =======================================================

/**
 * Переклади для назв полів та можливих значень, що використовуються
 * при відображенні деталей звіту (напр., у модальному вікні).
 */
const incidentFieldTranslations = {
    // --- Назви Полів Інцидентів ---
    'incident_type': 'Тип Інциденту',
    'medical_aid': 'Мед. Допомога',
    'lost_child': 'Загублена Дитина/Особа',
    'critical_swimmer': 'Критичний Плавець',
    'police_call': 'Виклик Поліції',
    'ambulance_call': 'Виклик Швидкої',
    'other': 'Інший Інцидент',
    'incident_time': 'Час інциденту',
    'involved_lifeguard_id': 'Задіяний Лайфгард',
    'involved_lifeguard_name': 'Задіяний Лайфгард',
    'subject_name': 'ПІБ Потерпілого/Особи',
    'subject_age': 'Вік',
    'subject_gender': 'Стать',
    'subject_phone': 'Телефон',
    'cause_details': 'Причина / Обставини',
    'actions_taken': 'Вжиті Заходи',
    'outcome_details': 'Результат / Наслідки',
    'result': 'Результат',
    'witness1_name': 'Свідок 1 (Ім\'я)',
    'witness1_phone': 'Свідок 1 (Телефон)',
    'witness2_name': 'Свідок 2 (Ім\'я)',
    'witness2_phone': 'Свідок 2 (Телефон)',
    'responding_unit_details': 'Дані Поліції/Швидкої',
    'incident_description': 'Детальний Опис Інциденту',

    // --- Переклад Можливих ЗНАЧЕНЬ Полів ---
    'subject_gender_Чоловік': 'Чоловік',
    'subject_gender_Жінка': 'Жінка',
    'subject_gender_Невідомо': 'Невідомо',

    // Medical aid причини
    'cause_details_cut_wound': 'Поріз/поранення',
    'cause_details_dislocation_fracture': 'Вивих/перелом',
    'cause_details_insect_bite': 'Укус комахи',
    'cause_details_loss_consciousness': 'Втрата свідомості',
    'cause_details_sunstroke': 'Сонячний удар',
    'cause_details_heart_disease': 'Серцеві захворювання',
    'cause_details_allergy': 'Алергія',
    'cause_details_lung_respiratory': 'Захворювання легень/дихальних шляхів',
    'cause_details_epilepsy': 'Епілепсія',
    'cause_details_burn': 'Опіки',
    'cause_details_alcohol_poisoning': 'Алкогольне отруєння',
    'cause_details_drug_overdose': 'Передозування наркотиками',
    'cause_details_other': 'Інше',

    // Medical aid результати
    'outcome_details_treated_wound': 'Обробив поріз/поранення',
    'outcome_details_applied_plaster': 'Заклеїв пластирем',
    'outcome_details_applied_bandage': 'Замотав бинтом',
    'outcome_details_called_ambulance': 'Викликав швидку',
    'outcome_details_sent_to_medpoint': 'Провів до медпункту',
    'outcome_details_help_not_needed': 'Допомога не знадобилась',

    // Lost child причини
    'cause_details_reported_by_adult': 'Звернулись дорослі/супроводжуючі',
    'cause_details_stranger_brought': 'Привів сторонній дорослий',
    'cause_details_child_reported': 'Дитина сама звернулась',
    'cause_details_lifeguard_found': 'Рятувальник виявив сам(у)',

    // Lost child обставини
    'outcome_details_adults_distracted': 'Дорослі відволіклися',
    'outcome_details_adults_alcohol': 'Дорослі в стані алк. сп\'яніння',
    'outcome_details_child_ran_away': 'Дитина втекла',
    'outcome_details_inadequate_state': 'Особа в неадекватному стані',

    // Lost child дії
    'actions_taken_search_on_land': 'Пошук на суші/у воді',
    'actions_taken_found_child': 'Виявлення дитини/особи',
    'actions_taken_called_police_20min': 'Виклик поліції (після 20 хв)',

    // Lost child результат
    'result_found': 'Особу знайдено',
    'result_not_found': 'Особу не знайдено (на момент звіту)',

    // Critical swimmer причини
    'cause_details_alcohol': 'Алкогольне сп\'яніння',
    'cause_details_exhaustion': 'Фізичне виснаження',
    'cause_details_forbidden_zone': 'Купання в забороненому місці',
    'cause_details_mental_illness': 'Психічне захворювання',
    'cause_details_cramp': 'Судома',
    'cause_details_water_injury': 'Травмування у воді',
    'cause_details_entangled_seaweed': 'Заплутався у водоростях',
    'cause_details_drowning_swallowed': 'Захлинувся/Потопав',
    'cause_details_rule_violation': 'Порушення правил',
    'cause_details_hypothermia': 'Переохолодження',
    'cause_details_disability': 'Інвалідність',

    // Critical swimmer дії
    'actions_taken_dialogue': 'Діалог з потерпілим',
    'actions_taken_rescue': 'Порятунок',
    'actions_taken_bottom_scan': 'Обстеження дна',
    'actions_taken_medical_aid': 'Надання домедичної допомоги',
    'actions_taken_call_ambulance': 'Виклик швидкої',
    'actions_taken_call_police': 'Виклик поліції',
    'actions_taken_move_to_safe_zone': 'Відведення до безпечної зони',

    // Police call причини
    'cause_details_fight': 'Бійка',
    'cause_details_dog': 'Собака',
    'cause_details_fishing': 'Рибалки',
    'cause_details_alcohol_drinking': 'Розпиття алкоголю',
    'cause_details_smoking': 'Куріння',
    'cause_details_hooliganism': 'Хуліганство',
    'cause_details_theft': 'Крадіжка',
    'cause_details_bridge_jump': 'Стрибки з мосту',
    'cause_details_unattended_minors': 'Малолітні без батьків',
    'cause_details_begging': 'Жебракування',

    // Police call результати
    'outcome_details_police_no_show': 'Поліція не приїхала',
    'outcome_details_offender_left_with_police': 'Порушник поїхав з поліцією',
    'outcome_details_protocol_offender_stayed': 'Склали протокол, порушник залишився',
    'outcome_details_protocol_offender_left': 'Склали протокол, порушник пішов',
    'outcome_details_offender_left_before_police': 'Порушник пішов до приїзду поліції',
    'outcome_details_police_no_action': 'Поліція приїхала, нічого не зробили',

    // Ambulance call причини
    'cause_details_critical_swimmer': 'Критичний плавець',
    'cause_details_trauma_musculoskeletal': 'Травми опорно-рухового апарату',
    'cause_details_psychological_illness': 'Психологічне захворювання',

    // Ambulance call результати
    'outcome_details_taken_by_ambulance': 'Постраждалий поїхав з швидкою',
    'outcome_details_treated_stayed_on_beach': 'Допомогли, залишився на пляжі',
    'outcome_details_treated_left_beach': 'Допомогли, пішов з пляжу',
    'outcome_details_ambulance_no_show': 'Швидка не приїхала'
};

/**
 * Переклади для назв полів статистики звіту
 */
const statsTranslations = {
    'suspicious_swimmers_count': "Підозрілих плавців",
    'visitor_inquiries_count': "Звернень відпочив.",
    'bridge_jumpers_count': "Стрибунів з мосту",
    'alcohol_water_prevented_count': "Недоп. у воду (алко)",
    'alcohol_drinking_prevented_count': "Недоп. розпиття",
    'watercraft_stopped_count': "Зупинено плавзасобів",
    'preventive_actions_count': "Превентивних заходів",
    'educational_activities_count': "Освітньої діяльності",
    'people_on_beach_estimated': "Людей на пляжі (оцінка)",
    'people_in_water_estimated': "Людей у воді (оцінка)"
};

/**
 * Переводы для функционала подтверждения фото и карточек (для JS)
 */
const photoApprovalTranslations = {
    "photos_for_approval": "Фото на Підтвердження",
    "no_photos_for_approval_currently": "Наразі немає фото для підтвердження.",
    "lifeguard_label": "Рятувальник",
    "post_label": "Пост",
    "start_time_label": "Час початку",
    "view_photo_title": "Переглянути фото у новій вкладці",
    "photo_alt_text": "Фото відкриття зміни",
    "no_photo_short": "Немає фото",
    "assign_lifeguard_type_on_shift_label": "Тип зміни:",
    "select_type_short": "Виберіть тип...",
    "lifeguard_l0_label": "Л0 (Один, 10-19)",
    "lifeguard_l1_label": "Л1 (Пара, 9-18)",
    "lifeguard_l2_label": "Л2 (Пара, 11-20)",
    "approve_button": "Підтвердити",
    "reject_button": "Відхилити",
    "photo_already_approved": "Фото вже підтверджено",
    "waiting_for_photo_upload": "Очікується завантаження фото"
};

/**
 * Общие переводы для UI
 */
const generalTranslations = {
    "loading": "Завантаження...",
    "error": "Помилка",
    "success": "Успіх",
    "cancel": "Скасувати",
    "save": "Зберегти",
    "delete": "Видалити",
    "edit": "Редагувати",
    "view": "Переглянути",
    "close": "Закрити",
    "confirm": "Підтвердити",
    "warning": "Попередження",
    "info": "Інформація"
};

/**
 * Глобальная функция перевода для JavaScript
 * @param {string} key - ключ перевода
 * @param {string} lang - язык (по умолчанию 'uk')
 * @returns {string} - перевод или ключ, если перевод не найден
 */
function t(key, lang = null) {
    const currentLang = lang || document.documentElement.lang || 'uk';

    // Проверяем во всех доступных объектах переводов
    const translationObjects = [
        incidentFieldTranslations,
        statsTranslations,
        photoApprovalTranslations,
        generalTranslations
    ];

    for (const translations of translationObjects) {
        if (translations && translations[key]) {
            // Если это объект с языками
            if (typeof translations[key] === 'object' && translations[key][currentLang]) {
                return translations[key][currentLang];
            }
            // Если это простая строка
            if (typeof translations[key] === 'string') {
                return translations[key];
            }
        }
    }

    console.warn(`Translation not found for key: ${key} and lang: ${currentLang}`);
    return key; // Возвращаем ключ, если перевод не найден
}

/**
 * Функция получения перевода с fallback
 * @param {string} key - ключ перевода
 * @param {string} fallback - значение по умолчанию
 * @returns {string}
 */
function tWithFallback(key, fallback = '') {
    const translation = t(key);
    return translation === key ? fallback : translation;
}

/**
 * Функция для безопасного получения значения из объекта
 * @param {Object} obj - объект
 * @param {string} key - ключ
 * @param {*} defaultValue - значение по умолчанию
 * @returns {*}
 */
function safeGet(obj, key, defaultValue = null) {
    return obj && obj.hasOwnProperty(key) ? obj[key] : defaultValue;
}

// Экспорт для Node.js (если нужно)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        t,
        tWithFallback,
        safeGet,
        incidentFieldTranslations,
        statsTranslations,
        photoApprovalTranslations,
        generalTranslations
    };
}