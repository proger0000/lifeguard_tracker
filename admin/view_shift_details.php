<?php
// /admin/view_shift_details.php
require_once '../config.php'; // Підключення конфігурації
require_once '../translations.php'; // Підключення перекладів
require_roles(['admin', 'duty_officer']); // Доступ для адміна та чергового
global $pdo; //

// Function to get translations
function getTranslation($key, $fallback = null) {
    global $translations;
    return isset($translations[$key]) ? $translations[$key] : ($fallback !== null ? $fallback : $key);
}

$page_title = "Деталі Зміни"; //
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT); //

if (!$shift_id) { //
    set_flash_message('помилка', 'Невірний ID зміни.'); //
    smart_redirect('admin/manage_shifts.php'); // Повертаємо на керування змінами //
    exit(); //
}

$shift_details = null; //
$report_details = null; //
$report_incidents = []; //

// Функція для отримання назви типу рятувальника
function get_lifeguard_assignment_type_name($type_code) {
    $types = [
        0 => 'L0 (Один, 10-19)',
        1 => 'L1 (Пара, 9-18)',
        2 => 'L2 (Пара, 11-20)'
    ];
    return $types[$type_code] ?? 'Не визначено';
}

try {
    // Отримуємо основні дані зміни, включаючи нові поля
    $stmt_shift = $pdo->prepare("
        SELECT
            s.id as shift_id, s.start_time, s.end_time, s.status,
            s.start_photo_path, s.start_photo_approved_at,
            s.photo_close_path, s.photo_close_uploaded_at, -- Нове поле
            s.lifeguard_assignment_type, -- Нове поле
            u.full_name as lifeguard_name, u.email as lifeguard_email,
            p.name as post_name, p.location_description as post_location,
            approved_by_user.full_name as photo_approved_by_name,
            manual_opened.full_name as manual_opened_by_name, -- Для ручного відкриття
            manual_closed.full_name as manual_closed_by_name, -- Для ручного закриття
            s.manual_close_comment -- Коментар ручного закриття
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        JOIN posts p ON s.post_id = p.id
        LEFT JOIN users approved_by_user ON s.start_photo_approved_by = approved_by_user.id
        LEFT JOIN users manual_opened ON s.manual_opened_by = manual_opened.id
        LEFT JOIN users manual_closed ON s.manual_closed_by = manual_closed.id
        WHERE s.id = :shift_id
    "); //
    $stmt_shift->bindParam(':shift_id', $shift_id, PDO::PARAM_INT); //
    $stmt_shift->execute(); //
    $shift_details = $stmt_shift->fetch(PDO::FETCH_ASSOC); //

    if (!$shift_details) { //
        set_flash_message('помилка', 'Зміну з ID ' . escape($shift_id) . ' не знайдено.'); //
        smart_redirect('admin/manage_shifts.php'); //
        exit(); //
    }

    $page_title .= " №" . escape($shift_details['shift_id']); //

    // ... (решта коду для отримання звіту та інцидентів залишається без змін) ...
    $stmt_report = $pdo->prepare("
        SELECT sr.*
        FROM shift_reports sr
        WHERE sr.shift_id = :shift_id
        LIMIT 1
    "); //
    $stmt_report->bindParam(':shift_id', $shift_id, PDO::PARAM_INT); //
    $stmt_report->execute(); //
    $report_details = $stmt_report->fetch(PDO::FETCH_ASSOC); //

    if ($report_details) { //
        $stmt_incidents = $pdo->prepare("
            SELECT ri.*, u_involved.full_name as involved_lifeguard_name
            FROM report_incidents ri
            LEFT JOIN users u_involved ON ri.involved_lifeguard_id = u_involved.id
            WHERE ri.shift_report_id = :report_id
            ORDER BY ri.incident_time ASC, ri.id ASC
        "); //
        $stmt_incidents->bindParam(':report_id', $report_details['id'], PDO::PARAM_INT); //
        $stmt_incidents->execute(); //
        $report_incidents = $stmt_incidents->fetchAll(PDO::FETCH_ASSOC); //
    }


} catch (PDOException $e) {
    error_log("View Shift Details Error (Shift ID: {$shift_id}): " . $e->getMessage()); //
    set_flash_message('помилка', 'Помилка завантаження деталей зміни.'); //
    smart_redirect('admin/manage_shifts.php'); //
    exit(); //
}

require_once '../includes/header.php'; //
?>

<div class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-2 sm:mb-0 flex items-center">
            <i class="fas fa-clipboard-check mr-3 text-indigo-600"></i> <?php echo escape($page_title); ?>
        </h2>
        <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/manage_shifts.php" class="btn-secondary !text-xs !py-1.5 !px-3 self-start sm:self-center">
            <i class="fas fa-arrow-left mr-1"></i> До керування змінами
        </a>
    </div>

    <?php display_flash_message(); ?>

    <?php if ($shift_details): ?>
        <div class="space-y-6">
            <div class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b border-gray-200/50 pb-2">Загальна інформація</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="font-medium text-gray-500">ID Зміни:</dt><dd class="text-gray-900 font-mono"><?php echo escape($shift_details['shift_id']); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Статус зміни:</dt><dd class="text-gray-900">
                        <?php
                            $status_text = ''; $status_class_badge = ''; //
                            switch ($shift_details['status']) { //
                                case 'completed': $status_text = 'Завершено'; $status_class_badge = 'bg-green-100 text-green-800'; break; //
                                case 'active':    $status_text = 'Активна';   $status_class_badge = 'bg-yellow-100 text-yellow-800 animate-pulse'; break; //
                                case 'cancelled': $status_text = 'Скасовано'; $status_class_badge = 'bg-red-100 text-red-800';   break; //
                                default:          $status_text = escape(ucfirst($shift_details['status'])); $status_class_badge = 'bg-gray-100 text-gray-800'; //
                            }
                        ?>
                        <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class_badge; ?>"><?php echo $status_text; ?></span>
                    </dd></div>
                    <div><dt class="font-medium text-gray-500">Лайфгард:</dt><dd class="text-gray-900 font-semibold"><?php echo escape($shift_details['lifeguard_name']); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Тип зміни лайфгарда:</dt><dd class="text-gray-900 font-semibold"><?php echo escape(get_lifeguard_assignment_type_name($shift_details['lifeguard_assignment_type'])); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Email лайфгарда:</dt><dd class="text-gray-900"><?php echo escape($shift_details['lifeguard_email']); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Пост:</dt><dd class="text-gray-900"><?php echo escape($shift_details['post_name']); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Локація поста:</dt><dd class="text-gray-900"><?php echo escape($shift_details['post_location'] ?? '-'); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Час початку:</dt><dd class="text-gray-900 font-mono"><?php echo format_datetime($shift_details['start_time']); ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Час завершення:</dt><dd class="text-gray-900 font-mono"><?php echo $shift_details['end_time'] ? format_datetime($shift_details['end_time']) : '<i>Ще не завершено</i>'; ?></dd></div>
                    <div><dt class="font-medium text-gray-500">Тривалість:</dt><dd class="text-gray-900 font-mono"><?php echo format_duration($shift_details['start_time'], $shift_details['end_time']); ?></dd></div>
                     <?php if ($shift_details['manual_opened_by_name']): ?>
                        <div><dt class="font-medium text-gray-500">Зміну відкрито вручну:</dt><dd class="text-gray-900 font-semibold text-blue-600"><?php echo escape($shift_details['manual_opened_by_name']); ?></dd></div>
                    <?php endif; ?>
                    <?php if ($shift_details['manual_closed_by_name']): ?>
                        <div><dt class="font-medium text-gray-500">Зміну закрито вручну:</dt><dd class="text-gray-900 font-semibold text-orange-600"><?php echo escape($shift_details['manual_closed_by_name']); ?></dd></div>
                        <?php if ($shift_details['manual_close_comment']): ?>
                            <div><dt class="font-medium text-gray-500">Коментар закриття:</dt><dd class="text-gray-900 italic">"<?php echo escape($shift_details['manual_close_comment']); ?>"</dd></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b border-gray-200/50 pb-2">Фото Відкриття Зміни</h3>
                    <?php if (!empty($shift_details['start_photo_path'])): ?>
                        <div class="mb-2">
                            <a href="<?php echo rtrim(APP_URL, '/') . '/' . ltrim(escape($shift_details['start_photo_path']), '/'); ?>" target="_blank" class="block w-full max-w-xs mx-auto rounded-md overflow-hidden shadow-md hover:shadow-lg transition-shadow">
                                <img src="<?php echo rtrim(APP_URL, '/') . '/' . ltrim(escape($shift_details['start_photo_path']), '/'); ?>" alt="Фото початку зміни" class="w-full h-auto object-cover">
                            </a>
                        </div>
                        <p class="text-xs text-center text-gray-600">
                            Статус:
                            <?php if ($shift_details['start_photo_approved_at']): ?>
                                <span class="font-semibold text-green-600">Підтверджено</span>
                                <?php echo format_datetime($shift_details['start_photo_approved_at']); ?>
                                <?php if ($shift_details['photo_approved_by_name']): ?>
                                    (<?php echo escape($shift_details['photo_approved_by_name']); ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="font-semibold text-orange-600">Очікує підтвердження</span>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic">Фото початку зміни не було завантажено.</p>
                    <?php endif; ?>
                </div>

                <div class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b border-gray-200/50 pb-2">Фото Завершення Зміни</h3>
                    <?php if (!empty($shift_details['photo_close_path'])): ?>
                        <div class="mb-2">
                            <a href="<?php echo rtrim(APP_URL, '/') . '/' . ltrim(escape($shift_details['photo_close_path']), '/'); ?>" target="_blank" class="block w-full max-w-xs mx-auto rounded-md overflow-hidden shadow-md hover:shadow-lg transition-shadow">
                                <img src="<?php echo rtrim(APP_URL, '/') . '/' . ltrim(escape($shift_details['photo_close_path']), '/'); ?>" alt="Фото завершення зміни" class="w-full h-auto object-cover">
                            </a>
                        </div>
                        <p class="text-xs text-center text-gray-600">
                            Завантажено: <?php echo format_datetime($shift_details['photo_close_uploaded_at']); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic">Фото завершення зміни не було завантажено.</p>
                         <?php if ($shift_details['status'] === 'completed' && !isset($shift_details['manual_closed_by'])): // Якщо зміна завершена, але не вручну, і фото немає ?>
                            <p class="text-xs text-orange-600 italic mt-1">Рятувальник не завантажив фото після завершення зміни.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($report_details): ?>
                 <div class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b border-gray-200/50 pb-2">Дані зі Звіту (ID: <?php echo $report_details['id']; ?>)</h3>
                    <div class="space-y-3">
                        <div>
                            <h4 class="font-medium text-gray-600 mb-1">Статистика:</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-3 gap-y-2 text-xs">
                                <?php
                                $stat_labels_report = [ //
                                    'suspicious_swimmers_count' => "Підозрілих плавців", //
                                    'visitor_inquiries_count' => "Звернень відпочив.", //
                                    'bridge_jumpers_count' => "Стрибунів з мосту", //
                                    'alcohol_water_prevented_count' => "Недоп. у воду (алко)", //
                                    'alcohol_drinking_prevented_count' => "Недоп. розпиття", //
                                    'watercraft_stopped_count' => "Зупинено плавз.", //
                                    'preventive_actions_count' => "Превентивних заходів", //
                                    'educational_activities_count' => "Освітньої діяльності", //
                                    'people_on_beach_estimated' => "Людей на пляжі", //
                                    'people_in_water_estimated' => "Людей у воді" //
                                ];
                                foreach ($stat_labels_report as $key => $label): ?>
                                    <div class="bg-gray-50 p-2 rounded border border-gray-200">
                                        <span class="font-semibold text-gray-700 block"><?php echo escape($label); ?>:</span>
                                        <span class="text-gray-900"><?php echo escape($report_details[$key] ?? '0'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (!empty($report_details['general_notes'])): ?>
                        <div>
                            <h4 class="font-medium text-gray-600 mb-1">Загальні нотатки звіту:</h4>
                            <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded border border-gray-200 whitespace-pre-wrap"><?php echo escape($report_details['general_notes']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($report_incidents)): ?>
                        <div>
                            <h4 class="font-medium text-gray-600 mb-2 pt-2 border-t border-gray-200/50">Зафіксовані інциденти:</h4>
                            <div class="space-y-4">
                                <?php foreach ($report_incidents as $index => $incident): ?>
                                    <div class="incident-item bg-white/50 p-3 rounded-lg border border-gray-200 shadow-sm">
                                        <h5 class="text-sm font-semibold text-indigo-700 mb-2">
                                            Інцидент #<?php echo $index + 1; ?>: <?php echo escape(getTranslation($incident['incident_type'], ucfirst(str_replace('_', ' ', $incident['incident_type'])))); ?>
                                            <?php if ($incident['incident_time']): ?>
                                                <span class="text-xs text-gray-500 font-normal">(<?php echo format_datetime($incident['incident_time'], 'H:i'); ?>)</span>
                                            <?php endif; ?>
                                        </h5>
                                        <dl class="space-y-1 text-xs">
                                            <?php
                                            $fields_to_skip = ['id', 'shift_report_id', 'incident_type']; //
                                            foreach ($incident as $key => $value): //
                                                if (in_array($key, $fields_to_skip) || $value === null || (is_string($value) && trim($value) === '')) continue; //

                                                $display_label = getTranslation($key, ucfirst(str_replace('_', ' ', $key))); //
                                                $display_value = ''; //

                                                if (is_string($value) && (strpos($value, '[') === 0 && strpos($value, ']') === (strlen($value) - 1))) { // Схоже на JSON масив //
                                                    $decoded_values = json_decode($value, true); //
                                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_values)) { //
                                                        $translated_items = array_map(function($item) use ($key) { //
                                                            return escape(getTranslation($key . '_' . $item, $item)); //
                                                        }, $decoded_values); //
                                                        $display_value = !empty($translated_items) ? '<ul><li class="ml-3 list-disc">' . implode('</li><li class="ml-3 list-disc">', $translated_items) . '</li></ul>' : '-'; //
                                                    } else {
                                                        $display_value = escape($value); // Не вдалося розпарсити, показуємо як є //
                                                    }
                                                } elseif ($key === 'subject_gender' || $key === 'result') { // Для полів з фіксованими значеннями //
                                                     $display_value = escape(getTranslation($key . '_' . $value, $value)); //
                                                } elseif ($key === 'involved_lifeguard_name' && $value) { //
                                                    $display_label = getTranslation('involved_lifeguard_id'); // Використовуємо переклад для ID //
                                                    $display_value = escape($value); //
                                                } elseif ($key === 'involved_lifeguard_id' && !$incident['involved_lifeguard_name']) { // Якщо імені немає, показуємо ID //
                                                    $display_value = "ID: " . escape($value); //
                                                } else {
                                                    $display_value = escape($value); //
                                                }
                                            ?>
                                            <div class="flex"><dt class="w-2/5 font-medium text-gray-500 shrink-0 pr-1"><?php echo $display_label; ?>:</dt><dd class="w-3/5 text-gray-800 break-words"><?php echo $display_value; ?></dd></div>
                                            <?php endforeach; ?>
                                        </dl>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php elseif ($report_details): ?>
                            <p class="text-sm text-gray-500 italic mt-2">Деталізованих інцидентів у цьому звіті не зафіксовано.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Звіт за зміну</h3>
                    <p class="text-sm text-gray-500 italic">Звіт для цієї зміни ще не було подано.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-red-500">Не вдалося завантажити інформацію про зміну.</p>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php'; //
?>