<?php
// Файл: admin/ajax_get_points_rules.php
// Описание: Генерирует HTML-содержимое для модального окна начисления баллов.
// ВЕРСИЯ С ДИНАМИЧЕСКИМ ОТОБРАЖЕНИЕМ РАССЧИТАННЫХ БАЛЛОВ

require_once '../config.php';
require_once '../includes/helpers.php'; // Подключаем хелперы, включая новую функцию

header('Content-Type: application/json');

// --- Безопасность и Валидация ---
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'duty_officer'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ заборонено']);
    exit;
}

$shift_id = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : 0;
if (!$shift_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Не вказано ID зміни']);
    exit;
}

// --- Основная Логика ---

// 1. Получаем уже выставленные баллы для этой смены (для отметки чекбоксов и комментариев)
$checked = [];
try {
    $stmt_checked = $pdo->prepare("SELECT rule_id, comment FROM lifeguard_shift_points WHERE shift_id = :shift_id");
    $stmt_checked->execute([':shift_id' => $shift_id]);
    foreach ($stmt_checked->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $checked[$row['rule_id']] = ['comment' => $row['comment']];
    }

    // 2. Получаем все правила из БД
    $stmt_rules = $pdo->query("SELECT id_balls, name_balls as name, quantity as points, comment_balls as comment FROM points");
    $rules = $stmt_rules->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("AJAX Get Points Rules DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Помилка бази даних при отриманні правил.']);
    exit;
}


// 3. --- НОВАЯ ЛОГИКА: Получаем РАССЧИТАННЫЕ баллы для смены ---
$calculated_points = get_calculated_points_for_shift($pdo, $shift_id);


// 4. Сортируем правила в нужном порядке (как и было раньше)
$rules_order = [
    'Зміна', 'Вчасно на зміну/зі зміни', 'Правильне селфі', 'Вчасне заповнення звіту', 'Тренування',
    'Зміна на іншому посту', 'Один на зміні', 'Гарячий вихід', 'Додання свідків у протокол',
    'Протокол спекотний день', 'Працював у погану погоду', 'Допомога в організації',
    'Участь у змаганнях', 'Пунктуальність', '5 хв запізнення', '10 хв запізнення',
    '15 хв запізнення', '20 хв запізнення', '25 хв запізнення', '30 і більше хв запізнення',
    'Порушення правил', 'Грубе порушення правил', 'Не вихід без поважної причини',
    'Не вчасно заповнений звіт',
];

$rules_sorted = [];
$found_ids = [];
foreach ($rules_order as $name) {
    foreach ($rules as $key => $rule) {
        if (trim($rule['name']) === trim($name)) {
            $rules_sorted[] = $rule;
            $found_ids[$rule['id_balls']] = true;
            unset($rules[$key]); // Удаляем найденное, чтобы не дублировать
            break;
        }
    }
}
// Добавляем остальные правила, если они есть
$rules_sorted = array_merge($rules_sorted, $rules);


// 5. Генерируем HTML-код для модального окна, используя рассчитанные баллы
ob_start();
?>
<div class="max-h-[60vh] overflow-y-auto pr-2">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <?php foreach ($rules_sorted as $rule): ?>
            <?php
                $rule_id = $rule['id_balls'];
                // --- ИЗМЕНЕНИЕ: Используем рассчитанный балл вместо статического ---
                // Если по какой-то причине балл не рассчитался, вернемся к базовому значению из БД
                $points_to_display = $calculated_points[$rule_id] ?? (float)$rule['points'];
                $points_formatted = ($points_to_display > 0 ? '+' : '') . $points_to_display;

                $checked_attr = isset($checked[$rule_id]) ? 'checked' : '';
                $comment_val = isset($checked[$rule_id]['comment']) ? htmlspecialchars($checked[$rule_id]['comment']) : '';
            ?>
            <div class="mb-4 p-3 border border-gray-200 rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow">
                <label class="flex items-start space-x-3 cursor-pointer">
                    <input type="checkbox" name="points[<?php echo $rule_id; ?>][awarded]" value="1" class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500 mt-0.5" <?php echo $checked_attr; ?>>
                    <div class="flex-1">
                        <div class="flex items-baseline">
                            <span class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($rule['name']); ?></span>
                            <span class="ml-2 text-xs font-semibold <?php echo ($points_to_display < 0 ? 'text-red-600' : 'text-green-700'); ?>">(<?php echo $points_formatted; ?>)</span>
                        </div>
                        <?php if ($rule['comment']): ?>
                            <p class="text-xs text-gray-500 mt-1 leading-snug"><?php echo htmlspecialchars($rule['comment']); ?></p>
                        <?php endif; ?>
                    </div>
                </label>
                <div class="mt-2 pl-8">
                    <input type="text" name="points[<?php echo $rule_id; ?>][comment]" placeholder="Коментар (необов'язково)" value="<?php echo $comment_val; ?>" class="w-full border border-gray-300 rounded-md px-3 py-1 text-xs placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
$html = ob_get_clean();

// 6. Отправляем JSON с готовым HTML и данными для отмеченных чекбоксов
echo json_encode(['success' => true, 'html' => $html, 'checked' => $checked]);
?>