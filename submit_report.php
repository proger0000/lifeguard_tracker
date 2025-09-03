<?php
require_once 'config.php';
require_roles(['lifeguard', 'admin', 'duty_officer']); // Доступ для рятувальників, адміна та оперативного

$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
$shift_info = null;
$reporter_user_id = $_SESSION['user_id'];
$form_errors = []; // Для помилок окремих полів
$error_message = ''; // Загальне повідомлення про помилку
$input = []; // Для збереження даних форми при помилці

// 0. Перевіряємо shift_id на валідність
if (!$shift_id) {
    set_flash_message('помилка', 'Невірний або відсутній ID зміни.');
    smart_redirect('lifeguard_history.php');
    exit();
}

// 1. Перевірити, чи звіт для цієї зміни вже існує
try {
    $stmt_check = $pdo->prepare("SELECT id FROM shift_reports WHERE shift_id = :shift_id");
    $stmt_check->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_check->execute();
    if ($stmt_check->fetch()) {
        set_flash_message('інфо', 'Звіт для зміни ID ' . $shift_id . ' вже було подано.');
        smart_redirect('lifeguard_history.php'); // Або на сторінку історії змін лайфгарда
        exit();
    }
} catch (PDOException $e) {
    // error_log("Report Check DB Error: " . $e->getMessage());
    set_flash_message('помилка', 'Помилка перевірки існуючого звіту.');
    smart_redirect('lifeguard_history.php');
    exit();
}

// 2. Отримати інформацію про зміну для відображення
$user_role = $_SESSION['user_role'] ?? '';
if (in_array($user_role, ['admin', 'duty_officer'])) {
    // Адмін та оперативний можуть подавати за будь-яку завершену зміну
    $stmt_shift = $pdo->prepare("
        SELECT s.id, s.start_time, s.end_time, p.name as post_name, u.full_name as lifeguard_name, s.user_id
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        JOIN users u ON s.user_id = u.id
        WHERE s.id = :shift_id AND s.status = 'completed'
    ");
    $stmt_shift->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_shift->execute();
    $shift_info = $stmt_shift->fetch();
} else {
    // Лайфгард — тільки за свою завершену зміну
    $stmt_shift = $pdo->prepare("
        SELECT s.id, s.start_time, s.end_time, p.name as post_name, u.full_name as lifeguard_name, s.user_id
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        JOIN users u ON s.user_id = u.id
        WHERE s.id = :shift_id AND s.user_id = :user_id AND s.status = 'completed'
    ");
    $stmt_shift->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    $stmt_shift->bindParam(':user_id', $reporter_user_id, PDO::PARAM_INT);
    $stmt_shift->execute();
    $shift_info = $stmt_shift->fetch();
}

if (!$shift_info) {
    set_flash_message('помилка', 'Зміну не знайдено, або вона не завершена.');
    smart_redirect('lifeguard_history.php');
    exit();
}

// 3. Обробка POST-запиту форми звіту
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Помилка CSRF токену.';
    } else {
        // Зберігаємо всі надіслані дані для можливості їх повторного відображення у формі
        $input = $_POST;

        // Валідація числових полів статистики
        $numeric_stat_fields = [
            'suspicious_swimmers' => 'Підозрілих плавців',
            'visitor_inquiries' => 'Звернень відпочиваючих',
            'bridge_jumpers' => 'Стрибунів з мосту',
            'alcohol_water' => 'Недоп. у воду (алкоголь)',
            'alcohol_drinking' => 'Недоп. розпиття алкоголю',
            'watercraft_stopped' => 'Зупинено плавзасобів',
            'preventive_actions' => 'Превентивних заходів',
            'educational_activities' => 'Освітньої діяльності'
        ];

        foreach ($numeric_stat_fields as $field => $label) {
            // Перевіряємо чи поле було надіслане
            if (!isset($_POST[$field])) {
                 $form_errors[$field] = "Поле '$label' є обов'язковим.";
                 $input[$field] = 0; // Встановлюємо 0 при помилці
                 continue;
             }
            $value = filter_input(INPUT_POST, $field, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($value === false || $value === null) { // 0 - це валідне значення
                $form_errors[$field] = "Значення для поля \"$label\" має бути цілим невід'ємним числом.";
                $input[$field] = 0; // Встановлюємо 0 при помилці
            } else {
                $input[$field] = $value; // Зберігаємо валідне числове значення
            }
        }

        // Валідація оціночних полів (якщо не порожні, то мають бути числами)
        if (isset($_POST['people_on_beach']) && $_POST['people_on_beach'] !== '') {
             $value = filter_input(INPUT_POST, 'people_on_beach', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
             if ($value === false || $value === null) {
                $form_errors['people_on_beach'] = "Оцінка людей на пляжі має бути цілим невід'ємним числом.";
             } else {
                 $input['people_on_beach'] = $value;
             }
        } else {
            $input['people_on_beach'] = null; // Дозволяємо NULL
        }
         if (isset($_POST['people_in_water']) && $_POST['people_in_water'] !== '') {
             $value = filter_input(INPUT_POST, 'people_in_water', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($value === false || $value === null) {
                $form_errors['people_in_water'] = "Оцінка людей у воді має бути цілим невід'ємним числом.";
             } else {
                 $input['people_in_water'] = $value;
             }
         } else {
              $input['people_in_water'] = null; // Дозволяємо NULL
         }

        $input['general_notes'] = trim($_POST['general_notes'] ?? '');
        $incidents_data = $_POST['incidents'] ?? []; // Отримуємо масив інцидентів

        // --- Зберігання в БД, якщо немає помилок ВАЛІДАЦІЇ СТАТИСТИКИ ---
        if (empty($form_errors)) {
            $pdo->beginTransaction(); // Починаємо транзакцію
            try {
                 // 1. Вставляємо основний звіт
                $stmt_report = $pdo->prepare("
                    INSERT INTO shift_reports (
                        shift_id, reporter_user_id, suspicious_swimmers_count, visitor_inquiries_count, bridge_jumpers_count,
                        alcohol_water_prevented_count, alcohol_drinking_prevented_count, watercraft_stopped_count,
                        preventive_actions_count, educational_activities_count, people_on_beach_estimated,
                        people_in_water_estimated, general_notes
                    ) VALUES (
                        :shift_id, :reporter_user_id, :suspicious_swimmers, :visitor_inquiries, :bridge_jumpers,
                        :alcohol_water, :alcohol_drinking, :watercraft_stopped, :preventive_actions,
                        :educational_activities, :people_on_beach, :people_in_water, :general_notes
                    )
                ");

                 // Біндінг параметрів для основного звіту
                 $stmt_report->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
                 $stmt_report->bindParam(':reporter_user_id', $reporter_user_id, PDO::PARAM_INT);
                 $stmt_report->bindParam(':suspicious_swimmers', $input['suspicious_swimmers'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':visitor_inquiries', $input['visitor_inquiries'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':bridge_jumpers', $input['bridge_jumpers'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':alcohol_water', $input['alcohol_water'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':alcohol_drinking', $input['alcohol_drinking'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':watercraft_stopped', $input['watercraft_stopped'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':preventive_actions', $input['preventive_actions'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':educational_activities', $input['educational_activities'], PDO::PARAM_INT);
                 $stmt_report->bindParam(':people_on_beach', $input['people_on_beach'], $input['people_on_beach'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                 $stmt_report->bindParam(':people_in_water', $input['people_in_water'], $input['people_in_water'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                 $generalNotes = !empty($input['general_notes']) ? $input['general_notes'] : null;
                 $stmt_report->bindParam(':general_notes', $generalNotes, $generalNotes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);


                if (!$stmt_report->execute()) {
                     throw new PDOException("Не вдалося зберегти основний звіт.");
                }
                 $shift_report_id = $pdo->lastInsertId(); // Отримуємо ID основного звіту

                 // 2. Обробляємо та вставляємо деталізовані інциденти
                 // (Переконуємось, що $incidents_data є масивом)
                if (is_array($incidents_data) && !empty($incidents_data)) {
                     $stmt_incident = $pdo->prepare("
                         INSERT INTO report_incidents (
                             shift_report_id, incident_type, incident_time, involved_lifeguard_id,
                             subject_name, subject_age, subject_gender, subject_phone,
                             cause_details, actions_taken, outcome_details,
                             witness1_name, witness1_phone, witness2_name, witness2_phone,
                             responding_unit_details, incident_description
                         ) VALUES (
                             :shift_report_id, :incident_type, :incident_time, :involved_lifeguard_id,
                             :subject_name, :subject_age, :subject_gender, :subject_phone,
                             :cause_details, :actions_taken, :outcome_details,
                             :witness1_name, :witness1_phone, :witness2_name, :witness2_phone,
                             :responding_unit_details, :incident_description
                         )
                     ");

                     foreach ($incidents_data as $type => $incidents_of_type) {
                         if(!is_array($incidents_of_type)) continue; // Пропускаємо, якщо це не масив інцидентів

                         foreach ($incidents_of_type as $index => $details) {
                              if(!is_array($details)) continue; // Пропускаємо, якщо це не масив деталей

                             // Отримуємо та очищаємо дані конкретного інциденту
                             // Додаємо перевірки isset та значення за замовчуванням (NULL або порожній рядок)
                             $incident_time = !empty($details['incident_time']) ? $details['incident_time'] : null;
                             $involved_lifeguard_id = filter_var($details['involved_lifeguard_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                             $subject_name = !empty($details['subject_name']) ? trim($details['subject_name']) : null;
                             $subject_age = filter_var($details['subject_age'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) ?: null;
                             $subject_gender = isset($details['subject_gender']) && in_array($details['subject_gender'], ['Чоловік', 'Жінка', 'Невідомо']) ? $details['subject_gender'] : null;
                             $subject_phone = !empty($details['subject_phone']) ? trim($details['subject_phone']) : null;
                             // Для полів, які можуть бути масивами (чекбокси): зберігаємо як JSON або через кому
                             // Ensure cause_details is always stored as a valid JSON array
                             $cause_array = isset($details['cause']) ? (array)$details['cause'] : [];
                             $cause_details = json_encode(array_values($cause_array), JSON_UNESCAPED_UNICODE);
                             if ($cause_details === false) {
                                 $cause_details = '[]';
                             }
                             $actions_taken = !empty($details['actions']) ? json_encode($details['actions']) : null; // Приклад для чекбоксів
                             $outcome_details = !empty($details['outcome']) ? trim($details['outcome']) : null; // Якщо це одне значення (radio/text)
                             $witness1_name = !empty($details['witness1_name']) ? trim($details['witness1_name']) : null;
                             $witness1_phone = !empty($details['witness1_phone']) ? trim($details['witness1_phone']) : null;
                             $witness2_name = !empty($details['witness2_name']) ? trim($details['witness2_name']) : null;
                             $witness2_phone = !empty($details['witness2_phone']) ? trim($details['witness2_phone']) : null;
                             $responding_unit_details = !empty($details['responding_unit_details']) ? trim($details['responding_unit_details']) : null;
                             $description = !empty($details['incident_description']) ? trim($details['incident_description']) : null;

                             // Біндінг параметрів для інциденту (використовуючи PDO::PARAM_NULL)
                             $stmt_incident->bindParam(':shift_report_id', $shift_report_id, PDO::PARAM_INT);
                             $stmt_incident->bindParam(':incident_type', $type, PDO::PARAM_STR); // Тип беремо з ключа масиву
                             $stmt_incident->bindParam(':incident_time', $incident_time, $incident_time !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':involved_lifeguard_id', $involved_lifeguard_id, $involved_lifeguard_id !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':subject_name', $subject_name, $subject_name !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':subject_age', $subject_age, $subject_age !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':subject_gender', $subject_gender, $subject_gender !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':subject_phone', $subject_phone, $subject_phone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':cause_details', $cause_details, PDO::PARAM_STR);
                             $stmt_incident->bindParam(':actions_taken', $actions_taken, $actions_taken !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':outcome_details', $outcome_details, $outcome_details !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':witness1_name', $witness1_name, $witness1_name !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':witness1_phone', $witness1_phone, $witness1_phone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':witness2_name', $witness2_name, $witness2_name !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':witness2_phone', $witness2_phone, $witness2_phone !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':responding_unit_details', $responding_unit_details, $responding_unit_details !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                             $stmt_incident->bindParam(':incident_description', $description, $description !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

                             if (!$stmt_incident->execute()) {
                                 throw new PDOException("Не вдалося зберегти деталі інциденту типу '$type', індекс $index.");
                             }
                         }
                     }
                 }

                 $pdo->commit(); // Коміт, якщо все пройшло успішно
                 set_flash_message('успіх', 'Звіт за зміну успішно подано.');
                 unset($_SESSION['csrf_token']);
                 smart_redirect('lifeguard_history.php');
                 exit();

             } catch (PDOException $e) {
                 $pdo->rollBack(); // Відкат транзакції
                 // error_log("Submit Report Transaction Error: ShiftReportID:{$shift_report_id} | Error: " . $e->getMessage());
                 $error_message = 'Помилка бази даних під час збереження звіту. Зверніться до адміністратора.';
                 // Не показуємо деталі помилки користувачу: $e->getMessage();
                 if (isset($shift_report_id)) { // Якщо основний звіт вже був створений
                     // Можна спробувати видалити його, щоб уникнути часткових даних, АЛЕ обережно!
                 }

             }
        } else {
             $error_message = 'Будь ласка, виправте помилки у формі.';
        }
    }
     // Регенерувати CSRF токен при помилці
    if ($error_message || !empty($form_errors)) {
         unset($_SESSION['csrf_token']);
    }
}

require_once 'includes/header.php';

// Отримуємо список лайфгардів для випадаючих списків у формі інцидентів
$lifeguards_list = [];
try {
    $stmt_lg = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lifeguard' ORDER BY full_name");
    $lifeguards_list = $stmt_lg->fetchAll(PDO::FETCH_KEY_PAIR); // Отримуємо як [id => full_name]
} catch(PDOException $e) {
    // Не критично, можна продовжити без списку або показати помилку
     $error_message = ($error_message ? $error_message . ' ' : '') . 'Не вдалося завантажити список лайфгардів.';
}


?>

<div class="container mx-auto px-4 py-8 max-w-4xl"> <!-- Збільшено ширину -->
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Звіт за Зміну №<?php echo escape($shift_info['id']); ?></h2>

    <!-- Інформація про зміну -->
    <div class="bg-gray-50 p-4 rounded-lg shadow-sm mb-6 border border-gray-200 text-sm text-gray-700">
        <p><strong>Лайфгард:</strong> <?php echo escape($shift_info['lifeguard_name']); ?></p>
        <p><strong>Пост:</strong> <?php echo escape($shift_info['post_name']); ?></p>
        <p><strong>Початок зміни:</strong> <?php echo format_datetime($shift_info['start_time']); ?></p>
        <p><strong>Кінець зміни:</strong> <?php echo format_datetime($shift_info['end_time']); ?></p>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
            <p><strong class="font-bold">Помилка!</strong> <?php echo escape($error_message); ?></p>
            <!-- Виводимо помилки валідації полів, якщо вони є і це не помилка CSRF -->
             <?php if (!empty($form_errors)): ?>
                 <ul class="list-disc list-inside mt-2 text-sm">
                    <?php foreach ($form_errors as $field_error): ?>
                         <li><?php echo escape($field_error); ?></li>
                     <?php endforeach; ?>
                 </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <form id="report-form" action="submit_report.php?shift_id=<?php echo $shift_id; ?>" method="POST" class="bg-white p-6 rounded-lg shadow-md space-y-8" novalidate> <!-- Збільшено space-y -->
        <?php echo csrf_input(); ?>

        <!-- Розділ Статистики -->
<fieldset>
    <legend class="text-xl font-semibold text-gray-800 border-b pb-3 mb-5">Статистика за зміну</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
        <?php
        // === ВИЗНАЧЕННЯ МАСИВУ ДЛЯ ФОРМИ ===
        $stat_fields = [
            'suspicious_swimmers' => "Підозрілих плавців",
            'visitor_inquiries' => "Звернень відпочив.",
            'bridge_jumpers' => "Стрибунів з мосту",
            'alcohol_water' => "Недоп. у воду (алко)",
            'alcohol_drinking' => "Недоп. розпиття",
            'watercraft_stopped' => "Зупинено плавз.",
            'preventive_actions' => "Превентив. заходів",
            'educational_activities' => "Освітньої діяльн."
        ];
        // =====================================
        ?>
        <?php foreach ($stat_fields as $name => $label): // <-- ПЕРЕВІРТЕ НАЗВУ ТУТ! Має бути $stat_fields ?>
            <div>
                <label for="<?php echo $name; ?>" class="block text-sm font-medium text-gray-700 mb-1"><?php echo $label; ?> *</label>
                <input type="number" min="0" step="1" name="<?php echo $name; ?>" id="<?php echo $name; ?>" value="<?php echo escape($input[$name] ?? '0'); ?>" required
                       class="mt-1 shadow-sm incident-field <?php echo isset($form_errors[$name]) ? 'border-red-500' : 'border-gray-300'; ?>">
                 <?php if (isset($form_errors[$name])): ?>
                     <p class="text-red-500 text-xs italic mt-1"><?php echo $form_errors[$name]; ?></p>
                 <?php endif; ?>
            </div>
        <?php endforeach; ?>
                 <!-- Оціночні поля -->
                  <div>
                        <label for="people_on_beach" class="block text-sm font-medium text-gray-700 mb-1">Людей на пляжі <span class="text-gray-500 text-xs">(оцінка)</span></label>
                        <input type="number" min="0" step="1" name="people_on_beach" id="people_on_beach" value="<?php echo escape($input['people_on_beach'] ?? ''); ?>" placeholder="Необов'язково"
                               class="mt-1 shadow-sm appearance-none block w-full px-3 py-2 border <?php echo isset($form_errors['people_on_beach']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                         <?php if (isset($form_errors['people_on_beach'])): ?>
                             <p class="text-red-500 text-xs italic mt-1"><?php echo $form_errors['people_on_beach']; ?></p>
                         <?php endif; ?>
                    </div>
                 <div>
                        <label for="people_in_water" class="block text-sm font-medium text-gray-700 mb-1">Людей у воді <span class="text-gray-500 text-xs">(оцінка)</span></label>
                        <input type="number" min="0" step="1" name="people_in_water" id="people_in_water" value="<?php echo escape($input['people_in_water'] ?? ''); ?>" placeholder="Необов'язково"
                               class="mt-1 shadow-sm appearance-none block w-full px-3 py-2 border <?php echo isset($form_errors['people_in_water']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-md placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 sm:text-sm">
                          <?php if (isset($form_errors['people_in_water'])): ?>
                             <p class="text-red-500 text-xs italic mt-1"><?php echo $form_errors['people_in_water']; ?></p>
                         <?php endif; ?>
                    </div>
            </div>
        </fieldset>

        <!-- Розділ Деталізації Інцидентів -->
         <fieldset>
            <legend class="text-xl font-semibold text-gray-800 border-b pb-3 mb-5">Деталізація Інцидентів</legend>
             <p class="text-sm text-gray-500 mb-5 -mt-3">Натисніть відповідну кнопку "+ Додати Випадок", якщо протягом зміни були зафіксовані інциденти.</p>

             <?php
             // Список інцидентів для генерації секцій
             $incident_sections = [
                  'medical_aid' => 'Надання Медичної Допомоги',
                  'lost_child' => 'Загублена Дитина/Особа',
                  'critical_swimmer' => 'Критичний Плавець',
                  'police_call' => 'Виклик Поліції',
                  'ambulance_call' => 'Виклик Швидкої Допомоги',
                  'other' => 'Інший Інцидент' // Додамо тип "Інше"
             ];
             ?>

             <?php foreach ($incident_sections as $type => $title): ?>
                 <div class="mb-6 p-4 border rounded-md bg-gray-50 relative" id="incidents-<?php echo $type; ?>-container">
                     <h4 class="font-semibold text-gray-700 mb-3"><?php echo $title; ?></h4>
                     <div id="incidents-<?php echo $type; ?>-list" class="space-y-5"> <!-- Збільшено відступ між елементами -->
                        <!-- Сюди JS додасть поля -->
                        <!-- Відображення раніше введених даних при помилці форми -->
                        <?php if (isset($input['incidents'][$type]) && is_array($input['incidents'][$type])): ?>
                            <?php foreach ($input['incidents'][$type] as $index => $incident_input): ?>
                                <?php
                                // Викликаємо JS для додавання цього блоку ПРИ ПОМИЛЦІ (складніший варіант)
                                // АБО: Генеруємо HTML на сервері тут (простіший, але дублювання) - обрано цей варіант
                                $index_display = $index + 1;
                                ?>
                                 <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-3 relative" data-index="<?php echo $index; ?>">
                                    <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
                                    <h5 class="text-md font-medium text-gray-800">Випадок #<?php echo $index_display; ?></h5>
                                     <!-- TODO: Вивести ВСІ поля для цього типу інциденту з даними з $incident_input -->
                                     <div>
                                         <label for="incidents[<?php echo $type; ?>][<?php echo $index; ?>][incident_time]" class="block text-sm font-medium text-gray-700 mb-1">Час (приблизно)</label>
                                         <input type="time" name="incidents[<?php echo $type; ?>][<?php echo $index; ?>][incident_time]" value="<?php echo escape($incident_input['incident_time'] ?? ''); ?>" class="incident-field mt-1">
                                     </div>
                                     <div>
                                         <label for="incidents[<?php echo $type; ?>][<?php echo $index; ?>][subject_name]" class="block text-sm font-medium text-gray-700 mb-1">ПІБ Особи (якщо відомо)</label>
                                         <input type="text" name="incidents[<?php echo $type; ?>][<?php echo $index; ?>][subject_name]" value="<?php echo escape($incident_input['subject_name'] ?? ''); ?>" class="incident-field mt-1">
                                     </div>
                                     <!-- Потрібно додати решту полів тут, аналогічно -->
                                     <div>
                                        <label for="incidents[<?php echo $type; ?>][<?php echo $index; ?>][incident_description]" class="block text-sm font-medium text-gray-700 mb-1">Опис випадку</label>
                                        <textarea name="incidents[<?php echo $type; ?>][<?php echo $index; ?>][incident_description]" rows="3" class="incident-field mt-1"><?php echo escape($incident_input['incident_description'] ?? ''); ?></textarea>
                                     </div>
                                 </div>
                             <?php endforeach; ?>
                         <?php else: ?>
                             <p class="text-sm text-gray-500 italic initial-text">Не було зафіксовано випадків цього типу.</p>
                         <?php endif; ?>
                    </div>
                     <button type="button" onclick="addIncident('<?php echo $type; ?>')" class="mt-3 text-sm btn-secondary !py-1 !px-2">
                         <i class="fas fa-plus mr-1"></i> Додати Випадок <?php echo $title; ?>
                     </button>
                 </div>
             <?php endforeach; ?>

         </fieldset>

        <!-- Розділ Загальні нотатки -->
          <div>
                <label for="general_notes" class="block text-lg font-semibold text-gray-700 mb-2">Загальні Нотатки / Коментарі</label>
                <textarea id="general_notes" name="general_notes" rows="4"
                          class="shadow-sm block w-full border border-gray-300 rounded-md focus:ring-1 focus:ring-red-500 focus:border-red-500 sm:text-sm p-2"
                          placeholder="Загальна інформація про зміну, не пов'язана з конкретними інцидентами (необов'язково)"
                          ><?php echo escape($input['general_notes'] ?? ''); ?></textarea>
            </div>


                <!-- Кнопки Відправки та Скасування (Адаптивні) -->
        <div class="pt-6 border-t border-gray-200 mt-6"> <!-- Додано відступ та лінію -->
            <!--
                - За замовчуванням (мобільний): кнопки в стовпчик, full-width, з відступом між ними (space-y-3).
                - На екранах sm і більше: кнопки в ряд (sm:flex), вирівняні праворуч (sm:justify-end),
                  вертикальний відступ зникає (sm:space-y-0), додається горизонтальний (sm:space-x-3),
                  ширина стає автоматичною (sm:w-auto).
            -->
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end space-y-3 sm:space-y-0 sm:space-x-3 space-y-reverse"> <!-- flex-col-reverse, щоб червона була знизу на моб -->
                <button type="submit" class="btn-red w-full sm:w-auto justify-center"> <!-- justify-center для іконки+тексту -->
                    <i class="fas fa-paper-plane mr-2"></i> Надіслати Звіт
                </button>
                <a href="index.php" class="btn-secondary w-full sm:w-auto justify-center mr-0 sm:mr-0"> <!-- Забрав mr-4 -->
                    <i class="fas fa-times mr-2"></i>Скасувати
                </a>
            </div>
        </div>
    </form>

</div>


<!-- ================================== -->
<!--   HTML ШАБЛОНИ для Інцидентів     -->
<!-- ================================== -->

<!-- Шаблон Надання Мед Допомоги -->
<template id="template-medical_aid">
     <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-3 relative" data-index="{index}">
         <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
        <h5 class="text-md font-medium text-gray-800">Надання Мед Допомоги # {index_display}</h5>
         <!-- Блок полів -->
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div>
                 <label class="block text-xs font-medium text-gray-600 mb-1">Час (приблизно)</label>
                 <input type="time" name="incidents[medical_aid][{index}][incident_time]" class="incident-field">
             </div>
              <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Потерпілого</label>
                 <input type="text" name="incidents[medical_aid][{index}][subject_name]" placeholder="Якщо відомо" class="incident-field">
             </div>
              <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Вік (років)</label>
                 <input type="number" min="0" max="120" name="incidents[medical_aid][{index}][subject_age]" class="incident-field">
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Стать</label>
                  <select name="incidents[medical_aid][{index}][subject_gender]" class="incident-field">
                     <option value="">Не обрано</option>
                     <option value="Чоловік">Чоловік</option>
                     <option value="Жінка">Жінка</option>
                  </select>
              </div>
         </div>
         <div>
            <span class="block text-xs font-medium text-gray-600 mb-1">Причина *</span>
             <!-- Чекбокси причин мед допомоги -->
             <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="cut_wound" class="mr-1 form-checkbox">Поріз/поранення</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="dislocation_fracture" class="mr-1 form-checkbox">Вивих/перелом</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="insect_bite" class="mr-1 form-checkbox">Укус комахи</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="loss_consciousness" class="mr-1 form-checkbox">Втрата свідомості</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="sunstroke" class="mr-1 form-checkbox">Сонячний удар</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="heart_disease" class="mr-1 form-checkbox">Серцеві захворювання</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="allergy" class="mr-1 form-checkbox">Алергія</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="lung_respiratory" class="mr-1 form-checkbox">Захв. легень/дих. шляхів</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="epilepsy" class="mr-1 form-checkbox">Епілепсія</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="burn" class="mr-1 form-checkbox">Опіки</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="alcohol_poisoning" class="mr-1 form-checkbox">Алкогольне отруєння</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="drug_overdose" class="mr-1 form-checkbox">Передоз. наркотиками</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[medical_aid][{index}][cause][]" value="other" class="mr-1 form-checkbox">Інше (вказати в описі)</label>
              </div>
          </div>
          <div>
            <span class="block text-xs font-medium text-gray-600 mb-1">Чим закінчилось *</span>
             <!-- Радіокнопки результату -->
            <div class="space-y-1 text-sm">
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="treated_wound" class="mr-1 form-radio">Обробив поріз/поранення</label>
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="applied_plaster" class="mr-1 form-radio">Заклеїв пластирем</label>
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="applied_bandage" class="mr-1 form-radio">Замотав бинтом</label>
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="called_ambulance" class="mr-1 form-radio">Викликав швидку</label>
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="sent_to_medpoint" class="mr-1 form-radio">Провів до медпункту</label>
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="help_not_needed" class="mr-1 form-radio">Допомога не знадобилась</label>
                <label class="flex items-center"><input type="radio" name="incidents[medical_aid][{index}][outcome]" value="other" class="mr-1 form-radio">Інше (вказати в описі)</label>
            </div>
          </div>
         <div>
             <label class="block text-xs font-medium text-gray-600 mb-1">Опис випадку</label>
             <textarea name="incidents[medical_aid][{index}][incident_description]" rows="2" class="incident-field"></textarea>
         </div>
         <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Лайфгарда (що надавав допомогу)</label>
            <select name="incidents[medical_aid][{index}][involved_lifeguard_id]" class="incident-field">
                 <option value="">Не обрано</option>
                 <?php foreach ($lifeguards_list as $lg_id => $lg_name): ?>
                    <option value="<?php echo $lg_id; ?>"><?php echo escape($lg_name); ?></option>
                 <?php endforeach; ?>
            </select>
         </div>
    </div>
 </template>

 <!-- Шаблон Загублена Дитина/Особа -->
<template id="template-lost_child">
     <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-3 relative" data-index="{index}">
         <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
         <h5 class="text-md font-medium text-gray-800">Загублена Дитина/Особа # {index_display}</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Час Початку Пошуку</label>
                 <input type="time" name="incidents[lost_child][{index}][incident_time]" class="incident-field">
             </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Особи</label>
                 <input type="text" name="incidents[lost_child][{index}][subject_name]" class="incident-field">
             </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Вік</label>
                <input type="number" min="0" name="incidents[lost_child][{index}][subject_age]" class="incident-field">
             </div>
             <div>
                 <label class="block text-xs font-medium text-gray-600 mb-1">Стать</label>
                 <select name="incidents[lost_child][{index}][subject_gender]" class="incident-field">
                     <option value="">Не обрано</option>
                    <option value="Чоловік">Чоловік</option>
                     <option value="Жінка">Жінка</option>
                 </select>
             </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Телефон</label>
                 <input type="tel" name="incidents[lost_child][{index}][subject_phone]" class="incident-field">
             </div>
        </div>
        <div>
             <span class="block text-xs font-medium text-gray-600 mb-1">Причина початку пошуку *</span>
            <div class="space-y-1 text-sm">
                 <label class="flex items-center"><input type="radio" name="incidents[lost_child][{index}][cause]" value="reported_by_adult" class="mr-1 form-radio">Звернулись дорослі/супроводжуючі</label>
                 <label class="flex items-center"><input type="radio" name="incidents[lost_child][{index}][cause]" value="stranger_brought" class="mr-1 form-radio">Привів сторонній дорослий</label>
                 <label class="flex items-center"><input type="radio" name="incidents[lost_child][{index}][cause]" value="child_reported" class="mr-1 form-radio">Дитина сама звернулась</label>
                 <label class="flex items-center"><input type="radio" name="incidents[lost_child][{index}][cause]" value="lifeguard_found" class="mr-1 form-radio">Рятувальник виявив сам(у)</label>
             </div>
        </div>
         <div>
            <span class="block text-xs font-medium text-gray-600 mb-1">Причини зникнення (можна декілька) *</span>
             <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                 <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][outcome_details][]" value="adults_distracted" class="mr-1 form-checkbox">Дорослі відволіклися</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][outcome_details][]" value="adults_alcohol" class="mr-1 form-checkbox">Дорослі в стані алк. сп'яніння</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][outcome_details][]" value="child_ran_away" class="mr-1 form-checkbox">Дитина втекла</label>
                 <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][outcome_details][]" value="inadequate_state" class="mr-1 form-checkbox">Особа в неадекватному стані</label>
              </div>
         </div>
         <div>
            <span class="block text-xs font-medium text-gray-600 mb-1">Вжиті дії (можна декілька) *</span>
             <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][actions][]" value="search_on_land" class="mr-1 form-checkbox">Пошук на суші/у воді</label>
                <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][actions][]" value="found_child" class="mr-1 form-checkbox">Виявлення дитини/особи</label>
                <label class="flex items-center"><input type="checkbox" name="incidents[lost_child][{index}][actions][]" value="called_police_20min" class="mr-1 form-checkbox">Виклик поліції (після 20 хв)</label>
             </div>
         </div>
         <div>
             <span class="block text-xs font-medium text-gray-600 mb-1">Результат *</span>
             <div class="space-y-1 text-sm">
                 <label class="flex items-center"><input type="radio" name="incidents[lost_child][{index}][result]" value="found" class="mr-1 form-radio">Особу знайдено</label>
                 <label class="flex items-center"><input type="radio" name="incidents[lost_child][{index}][result]" value="not_found" class="mr-1 form-radio">Особу не знайдено (на момент звіту)</label>
            </div>
         </div>
        <!-- Свідки та опис -->
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div>
                 <label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 1</label>
                 <input type="text" name="incidents[lost_child][{index}][witness1_name]" class="incident-field">
             </div>
             <div>
                 <label class="block text-xs font-medium text-gray-600 mb-1">Телефон свідка 1</label>
                 <input type="tel" name="incidents[lost_child][{index}][witness1_phone]" class="incident-field">
             </div>
            <!-- Можна додати свідка 2 аналогічно -->
         </div>
         <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Опис випадку</label>
            <textarea name="incidents[lost_child][{index}][incident_description]" rows="2" class="incident-field"></textarea>
         </div>
     </div>
 </template>

<!-- Шаблон Критичний Плавець -->
<template id="template-critical_swimmer">
    <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-4 relative" data-index="{index}">
        <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
        <h5 class="text-md font-medium text-gray-800">Критичний Плавець # {index_display}</h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div>
                 <label class="block text-xs font-medium text-gray-600 mb-1">Час рятування *</label>
                 <input type="time" name="incidents[critical_swimmer][{index}][incident_time]" required class="incident-field">
             </div>
              <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">ПІБ потерпілого *</label>
                 <input type="text" name="incidents[critical_swimmer][{index}][subject_name]" required class="incident-field">
             </div>
              <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Вік потерпілого *</label>
                 <input type="number" min="0" name="incidents[critical_swimmer][{index}][subject_age]" required class="incident-field">
              </div>
              <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Стать потерпілого *</label>
                  <select name="incidents[critical_swimmer][{index}][subject_gender]" required class="incident-field">
                     <option value="">Не обрано</option> <option value="Чоловік">Чоловік</option> <option value="Жінка">Жінка</option>
                  </select>
              </div>
               <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Телефон потерпілого</label>
                 <input type="tel" name="incidents[critical_swimmer][{index}][subject_phone]" class="incident-field">
              </div>
              <div>
                 <label class="block text-xs font-medium text-gray-600 mb-1">Лайфгард, що надавав допомогу *</label>
                 <select name="incidents[critical_swimmer][{index}][involved_lifeguard_id]" required class="incident-field">
                    <option value="">Не обрано</option>
                     <?php foreach ($lifeguards_list as $lg_id => $lg_name): ?> <option value="<?php echo $lg_id; ?>"><?php echo escape($lg_name); ?></option> <?php endforeach; ?>
                 </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div>
                <span class="block text-xs font-medium text-gray-600 mb-1">Причина появи *</span>
                <div class="space-y-1 text-sm">
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="alcohol" class="mr-1 form-checkbox">Алк. сп'яніння</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="exhaustion" class="mr-1 form-checkbox">Фіз. виснаження</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="forbidden_zone" class="mr-1 form-checkbox">Купання в забор. місці</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="mental_illness" class="mr-1 form-checkbox">Псих. захворювання</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="cramp" class="mr-1 form-checkbox">Судома</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="water_injury" class="mr-1 form-checkbox">Травмування у воді</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="entangled_seaweed" class="mr-1 form-checkbox">Заплутався у водоростях</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="drowning_swallowed" class="mr-1 form-checkbox">Захлинувся</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="rule_violation" class="mr-1 form-checkbox">Порушення правил</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="hypothermia" class="mr-1 form-checkbox">Переохолодження</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="disability" class="mr-1 form-checkbox">Інвалідність</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][cause][]" value="other" class="mr-1 form-checkbox">Інше</label>
                </div>
            </div>
             <div>
                <span class="block text-xs font-medium text-gray-600 mb-1">Дії рятувальника *</span>
                 <div class="space-y-1 text-sm">
                     <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="dialogue" class="mr-1 form-checkbox">Діалог з потерпілим</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="rescue" class="mr-1 form-checkbox">Порятунок</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="bottom_scan" class="mr-1 form-checkbox">Обстеження дна</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="medical_aid" class="mr-1 form-checkbox">Надання домед. допомоги</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="call_ambulance" class="mr-1 form-checkbox">Виклик швидкої</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="call_police" class="mr-1 form-checkbox">Виклик поліції</label>
                    <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="move_to_safe_zone" class="mr-1 form-checkbox">Відведення до безп. зони</label>
                     <label class="flex items-center"><input type="checkbox" name="incidents[critical_swimmer][{index}][actions][]" value="other" class="mr-1 form-checkbox">Інше</label>
                 </div>
             </div>
        </div>
        <!-- Свідки та опис -->
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
              <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 1</label><input type="text" name="incidents[critical_swimmer][{index}][witness1_name]" class="incident-field"></div>
             <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон Свідка 1</label><input type="tel" name="incidents[critical_swimmer][{index}][witness1_phone]" class="incident-field"></div>
              <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 2</label><input type="text" name="incidents[critical_swimmer][{index}][witness2_name]" class="incident-field"></div>
              <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон Свідка 2</label><input type="tel" name="incidents[critical_swimmer][{index}][witness2_phone]" class="incident-field"></div>
         </div>
          <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ/номер поліцейського (якщо викликали)</label><input type="text" name="incidents[critical_swimmer][{index}][responding_unit_details]" class="incident-field"></div>
          <div><label class="block text-xs font-medium text-gray-600 mb-1">Опис випадку</label><textarea name="incidents[critical_swimmer][{index}][incident_description]" rows="2" class="incident-field"></textarea></div>
    </div>
 </template>

 <!-- Шаблон Виклик Поліції -->
 <template id="template-police_call">
    <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-4 relative" data-index="{index}">
        <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
        <h5 class="text-md font-medium text-gray-800">Виклик Поліції # {index_display}</h5>
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Час виклику *</label><input type="time" name="incidents[police_call][{index}][incident_time]" required class="incident-field"></div>
             <div><label class="block text-xs font-medium text-gray-600 mb-1">Вік правопорушника *</label><input type="number" min="0" name="incidents[police_call][{index}][subject_age]" required class="incident-field"></div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Стать правопорушника *</label>
                 <select name="incidents[police_call][{index}][subject_gender]" required class="incident-field"><option value="">Не обрано</option><option value="Чоловік">Чоловік</option><option value="Жінка">Жінка</option></select>
            </div>
             <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон правопорушника</label><input type="tel" name="incidents[police_call][{index}][subject_phone]" class="incident-field"></div>
         </div>
         <div>
            <span class="block text-xs font-medium text-gray-600 mb-1">Причина виклику *</span>
             <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="fight" class="mr-1 form-radio">Бійка</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="dog" class="mr-1 form-radio">Собака</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="fishing" class="mr-1 form-radio">Рибалки</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="alcohol_drinking" class="mr-1 form-radio">Розпиття алкоголю</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="smoking" class="mr-1 form-radio">Куріння</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="hooliganism" class="mr-1 form-radio">Хуліганство</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="theft" class="mr-1 form-radio">Крадіжка</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="bridge_jump" class="mr-1 form-radio">Стрибки з мосту</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="unattended_minors" class="mr-1 form-radio">Малолітні без батьків</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="begging" class="mr-1 form-radio">Жебракування</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][cause]" value="other" class="mr-1 form-radio">Інше</label>
            </div>
        </div>
         <div>
             <span class="block text-xs font-medium text-gray-600 mb-1">Чим закінчилось *</span>
            <div class="space-y-1 text-sm">
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][outcome]" value="police_no_show" class="mr-1 form-radio">Поліція не приїхала</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][outcome]" value="offender_left_with_police" class="mr-1 form-radio">Порушник поїхав з поліцією</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][outcome]" value="protocol_offender_stayed" class="mr-1 form-radio">Склали протокол, порушник залишився</label>
                <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][outcome]" value="protocol_offender_left" class="mr-1 form-radio">Склали протокол, порушник пішов</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][outcome]" value="offender_left_before_police" class="mr-1 form-radio">Порушник пішов до приїзду поліції</label>
                 <label class="flex items-center"><input type="radio" name="incidents[police_call][{index}][outcome]" value="police_no_action" class="mr-1 form-radio">Поліція приїхала, нічого не зробили</label>
            </div>
         </div>
         <!-- ПІБ поліцейського та свідки -->
          <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ та номер поліцейського, що приїхав</label><input type="text" name="incidents[police_call][{index}][responding_unit_details]" class="incident-field"></div>
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 1</label><input type="text" name="incidents[police_call][{index}][witness1_name]" class="incident-field"></div>
              <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон Свідка 1</label><input type="tel" name="incidents[police_call][{index}][witness1_phone]" class="incident-field"></div>
              <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 2</label><input type="text" name="incidents[police_call][{index}][witness2_name]" class="incident-field"></div>
              <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон Свідка 2</label><input type="tel" name="incidents[police_call][{index}][witness2_phone]" class="incident-field"></div>
         </div>
          <div><label class="block text-xs font-medium text-gray-600 mb-1">Опис випадку</label><textarea name="incidents[police_call][{index}][incident_description]" rows="2" class="incident-field"></textarea></div>
    </div>
 </template>

<!-- Шаблон Виклик Швидкої -->
<template id="template-ambulance_call">
     <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-4 relative" data-index="{index}">
         <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
         <h5 class="text-md font-medium text-gray-800">Виклик Швидкої # {index_display}</h5>
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Час виклику *</label><input type="time" name="incidents[ambulance_call][{index}][incident_time]" required class="incident-field"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ особи *</label><input type="text" name="incidents[ambulance_call][{index}][subject_name]" required class="incident-field"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Вік особи *</label><input type="number" min="0" name="incidents[ambulance_call][{index}][subject_age]" required class="incident-field"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Стать особи *</label><select name="incidents[ambulance_call][{index}][subject_gender]" required class="incident-field"><option value="">Не обрано</option><option value="Чоловік">Чоловік</option><option value="Жінка">Жінка</option></select></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон особи</label><input type="tel" name="incidents[ambulance_call][{index}][subject_phone]" class="incident-field"></div>
         </div>
          <div>
            <span class="block text-xs font-medium text-gray-600 mb-1">Причина виклику *</span>
             <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="critical_swimmer" class="mr-1 form-radio">Критичний плавець</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="loss_consciousness" class="mr-1 form-radio">Втрата свідомості</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="heart_disease" class="mr-1 form-radio">Хвороби серця</label>
                <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="lung_respiratory" class="mr-1 form-radio">Хвороби легень/дих.шл.</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="drug_overdose" class="mr-1 form-radio">Передоз. наркотиками</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="alcohol_poisoning" class="mr-1 form-radio">Передоз. алкоголем</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="allergy" class="mr-1 form-radio">Алергія</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="burn" class="mr-1 form-radio">Опіки</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="trauma_musculoskeletal" class="mr-1 form-radio">Травми опорно-рух.</label>
                <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="sunstroke" class="mr-1 form-radio">Сонячний удар</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="psychological_illness" class="mr-1 form-radio">Психол. захворювання</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][cause]" value="other" class="mr-1 form-radio">Інше</label>
            </div>
        </div>
         <div>
             <span class="block text-xs font-medium text-gray-600 mb-1">Чим закінчилось *</span>
            <div class="space-y-1 text-sm">
                <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][outcome]" value="taken_by_ambulance" class="mr-1 form-radio">Постраждалий поїхав з швидкою</label>
                <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][outcome]" value="treated_stayed_on_beach" class="mr-1 form-radio">Допомогли, залишився на пляжі</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][outcome]" value="treated_left_beach" class="mr-1 form-radio">Допомогли, пішов з пляжу</label>
                 <label class="flex items-center"><input type="radio" name="incidents[ambulance_call][{index}][outcome]" value="ambulance_no_show" class="mr-1 form-radio">Швидка не приїхала</label>
             </div>
        </div>
        <!-- ПІБ Бригади, Свідки, Опис -->
         <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ та номер бригади швидкої</label><input type="text" name="incidents[ambulance_call][{index}][responding_unit_details]" class="incident-field"></div>
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
            <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 1</label><input type="text" name="incidents[ambulance_call][{index}][witness1_name]" class="incident-field"></div>
             <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон Свідка 1</label><input type="tel" name="incidents[ambulance_call][{index}][witness1_phone]" class="incident-field"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">ПІБ Свідка 2</label><input type="text" name="incidents[ambulance_call][{index}][witness2_name]" class="incident-field"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Телефон Свідка 2</label><input type="tel" name="incidents[ambulance_call][{index}][witness2_phone]" class="incident-field"></div>
        </div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">Опис випадку</label><textarea name="incidents[ambulance_call][{index}][incident_description]" rows="2" class="incident-field"></textarea></div>
    </div>
</template>

<!-- Шаблон Інший Інцидент -->
 <template id="template-other">
    <div class="incident-item border-t border-gray-300 pt-4 mt-4 space-y-4 relative" data-index="{index}">
        <button type="button" onclick="removeIncident(this)" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold" title="Видалити цей випадок">×</button>
         <h5 class="text-md font-medium text-gray-800">Інший Інцидент # {index_display}</h5>
         <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
             <div><label class="block text-xs font-medium text-gray-600 mb-1">Час (приблизно)</label><input type="time" name="incidents[other][{index}][incident_time]" class="incident-field"></div>
         </div>
        <div>
             <label class="block text-xs font-medium text-gray-600 mb-1">Детальний опис інциденту *</label>
            <textarea name="incidents[other][{index}][incident_description]" rows="3" required class="incident-field"></textarea>
        </div>
     </div>
 </template>

<!-- Кінець файлу, викликається footer.php -->
<?php
require_once 'includes/footer.php';
?>