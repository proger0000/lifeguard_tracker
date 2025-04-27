// =============================================
// Файл: js/translations.js
// Визначення Глобальних Об'єктів Перекладів
// =============================================

/**
 * Переклади для назв полів та значень інцидентів
 * Ключі для значень будуються за принципом: "назваПоля_значенняValue"
 * (наприклад, cause_details_insect_bite)
 */
const incidentFieldTranslations = {
    // --- Назви Полів ---
    incident_type: 'Тип Інциденту', medical_aid: 'Мед. Допомога', lost_child: 'Загублена Дитина/Особа',
    critical_swimmer: 'Критичний Плавець', police_call: 'Виклик Поліції', ambulance_call: 'Виклик Швидкої',
    other: 'Інший Інцидент', incident_time: 'Час', involved_lifeguard_id: 'Лайфгард', involved_lifeguard_name: 'Лайфгард',
    subject_name: 'ПІБ Особи', subject_age: 'Вік', subject_gender: 'Стать', subject_phone: 'Телефон',
    cause_details: 'Причина(и)', actions_taken: 'Вжиті дії', outcome_details: 'Результат', result: 'Результат', // Додано 'result' для загублених
    witness1_name: 'Свідок 1 (Ім\'я)', witness1_phone: 'Свідок 1 (Тел)', witness2_name: 'Свідок 2 (Ім\'я)',
    witness2_phone: 'Свідок 2 (Тел)', responding_unit_details: 'Поліція/Швидка', incident_description: 'Опис Інциденту',

    // --- Значення для Полів ---
    // Стать
    'Чоловік': 'Чоловік', 'Жінка': 'Жінка', 'Невідомо': 'Невідомо',

    // Значення 'medical_aid' [cause_details]
    cause_details_cut_wound: 'Поріз/поранення', cause_details_dislocation_fracture: 'Вивих/перелом',
    cause_details_insect_bite: 'Укус комахи', cause_details_loss_consciousness: 'Втрата свідомості',
    cause_details_sunstroke: 'Сонячний удар', cause_details_heart_disease: 'Серцеві захворювання',
    cause_details_allergy: 'Алергія', cause_details_lung_respiratory: 'Захв. легень/дих. шляхів',
    cause_details_epilepsy: 'Епілепсія', cause_details_burn: 'Опіки',
    cause_details_alcohol_poisoning: 'Алкогольне отруєння', cause_details_drug_overdose: 'Передоз. наркотиками',
    cause_details_other: 'Інше (причина мед.доп.)',

    // Значення 'medical_aid' [outcome_details]
    outcome_details_treated_wound: 'Обробив поріз/поранення', outcome_details_applied_plaster: 'Заклеїв пластирем',
    outcome_details_applied_bandage: 'Замотав бинтом', outcome_details_called_ambulance: 'Викликав швидку',
    outcome_details_sent_to_medpoint: 'Провів до медпункту', outcome_details_help_not_needed: 'Допомога не знадобилась',
    outcome_details_other: 'Інше (результат мед.доп.)',

    // Значення 'lost_child' [cause_details] (Використовувалося радіо, а не чекбокс?)
    cause_details_reported_by_adult: 'Звернулись дорослі/супроводжуючі',
    cause_details_stranger_brought: 'Привів сторонній дорослий',
    cause_details_child_reported: 'Дитина сама звернулась',
    cause_details_lifeguard_found: 'Рятувальник виявив сам(у)',

    // Значення 'lost_child' - причини зникнення (чекбокси, мабуть мали б інший ключ в інциденті?)
    // Якщо name="incidents[lost_child][{index}][disappearance_reason][]"
    // то ключі будуть disappearance_reason_adults_distracted і т.д. Поки залишимо з cause_details_
     cause_details_adults_distracted: 'Дорослі відволіклися',
     cause_details_adults_alcohol: 'Дорослі в стані алк. сп\'яніння',
     cause_details_child_ran_away: 'Дитина втекла',
     cause_details_inadequate_state: 'Особа в неадекватному стані',

    // Значення 'lost_child' [actions_taken]
     actions_taken_search_on_land: 'Пошук на суші/воді',
     actions_taken_found_child: 'Виявлення дитини/особи',
     actions_taken_called_police_20min: 'Виклик поліції (після 20 хв)',

    // Значення 'lost_child' [result]
     result_found: 'Особу знайдено',
     result_not_found: 'Особу не знайдено',

    // Значення 'critical_swimmer' [cause_details]
    cause_details_alcohol: 'Алк. сп\'яніння', cause_details_exhaustion: 'Фіз. виснаження', cause_details_forbidden_zone: 'Купання в забор. місці',
    cause_details_mental_illness: 'Псих. захворювання', cause_details_cramp: 'Судома', cause_details_water_injury: 'Травмування у воді',
    cause_details_entangled_seaweed: 'Заплутався у водоростях', cause_details_drowning_swallowed: 'Захлинувся',
    cause_details_rule_violation: 'Порушення правил', cause_details_hypothermia: 'Переохолодження',
    cause_details_disability: 'Інвалідність', cause_details_other: 'Інше (прич. крит.пл.)',

    // Значення 'critical_swimmer' [actions_taken]
    actions_taken_dialogue: 'Діалог з потерпілим', actions_taken_rescue: 'Порятунок', actions_taken_bottom_scan: 'Обстеження дна',
    actions_taken_medical_aid: 'Надання домед. допомоги', actions_taken_call_ambulance: 'Виклик швидкої',
    actions_taken_call_police: 'Виклик поліції', actions_taken_move_to_safe_zone: 'Відведення до безп. зони',
    actions_taken_other: 'Інше (дії крит.пл.)',

    // Значення 'police_call' [cause_details]
    cause_details_fight: 'Бійка', cause_details_dog: 'Собака', cause_details_fishing: 'Рибалки',
    cause_details_alcohol_drinking: 'Розпиття алкоголю', cause_details_smoking: 'Куріння', cause_details_hooliganism: 'Хуліганство',
    cause_details_theft: 'Крадіжка', cause_details_bridge_jump: 'Стрибки з мосту', cause_details_unattended_minors: 'Малолітні без батьків',
    cause_details_begging: 'Жебракування', cause_details_other: 'Інше (причина поліції)',

    // Значення 'police_call' [outcome_details]
    outcome_details_police_no_show: 'Поліція не приїхала', outcome_details_offender_left_with_police: 'Порушник поїхав з поліцією',
    outcome_details_protocol_offender_stayed: 'Склали протокол, порушник залишився',
    outcome_details_protocol_offender_left: 'Склали протокол, порушник пішов',
    outcome_details_offender_left_before_police: 'Порушник пішов до приїзду поліції',
    outcome_details_police_no_action: 'Поліція приїхала, нічого не зробили',

    // Значення 'ambulance_call' [cause_details] (Деякі повторюються з medical_aid)
     cause_details_critical_swimmer: 'Критичний плавець', // Вже є
     cause_details_loss_consciousness: 'Втрата свідомості', // Вже є
     cause_details_heart_disease: 'Хвороби серця', // Вже є
     cause_details_lung_respiratory: 'Хвороби легень/дих.шл.', // Вже є
     cause_details_drug_overdose: 'Передоз. наркотиками', // Вже є
     cause_details_alcohol_poisoning: 'Передоз. алкоголем', // Вже є
     cause_details_allergy: 'Алергія', // Вже є
     cause_details_burn: 'Опіки', // Вже є
     cause_details_trauma_musculoskeletal: 'Травми опорно-рухового апарату',
     cause_details_sunstroke: 'Сонячний удар', // Вже є
     cause_details_psychological_illness: 'Психол. захворювання',
     cause_details_other: 'Інше (причина швидкої)',

    // Значення 'ambulance_call' [outcome_details]
    outcome_details_taken_by_ambulance: 'Постраждалий поїхав з швидкою',
    outcome_details_treated_stayed_on_beach: 'Допомогли, залишився на пляжі',
    outcome_details_treated_left_beach: 'Допомогли, пішов з пляжу',
    outcome_details_ambulance_no_show: 'Швидка не приїхала'
};

// Переклади для назв полів статистики
const statsTranslations = {
    suspicious_swimmers_count: "Підозрілих плавців", visitor_inquiries_count: "Звернень відпочив.",
    bridge_jumpers_count: "Стрибунів з мосту", alcohol_water_prevented_count: "Недоп. у воду (алко)",
    alcohol_drinking_prevented_count: "Недоп. розпиття", watercraft_stopped_count: "Зупинено плавз.",
    preventive_actions_count: "Превентив. заходів", educational_activities_count: "Освітньої діяльн.",
    people_on_beach_estimated: "Людей на пляжі (оц.)", people_in_water_estimated: "Людей у воді (оц.)"
};

// console.log('translations.js executed');