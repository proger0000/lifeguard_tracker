<?php
/**
 * admin/manage_shift_points.php
 * Страница для управления баллами смены
 */

require_once '../config.php';
require_once '../includes/helpers.php';
global $pdo;
require_roles(['admin', 'duty_officer']);
save_current_page_for_redirect();

$page_title = "Управление Баллами Смены";
$shift_id = filter_input(INPUT_GET, 'shift_id', FILTER_VALIDATE_INT);
$shift_data = null;
$points_rules = [];
$current_points = [];
$form_errors = [];
$success_message = '';

if (!$shift_id) {
    set_flash_message('помилка', 'Не указан ID смены.');
    smart_redirect('index.php', [], 'admin-shift-history-content');
    exit();
}

try {
    // Получаем информацию о смене
    $stmt_shift = $pdo->prepare("
        SELECT 
            s.id, s.start_time, s.end_time, s.status, s.post_id,
            u.id as user_id, u.full_name as lifeguard_name,
            p.name as post_name,
            s.rounded_work_hours
        FROM shifts s
        JOIN users u ON s.user_id = u.id
        JOIN posts p ON s.post_id = p.id
        WHERE s.id = :shift_id
    ");
    $stmt_shift->execute([':shift_id' => $shift_id]);
    $shift_data = $stmt_shift->fetch(PDO::FETCH_ASSOC);

    if (!$shift_data) {
        set_flash_message('помилка', 'Смена не найдена.');
        smart_redirect('index.php', [], 'admin-shift-history-content');
        exit();
    }

    // Получаем правила начисления баллов
    $stmt_rules = $pdo->query("
        SELECT id_balls, rule_name, points, rule_type 
        FROM points 
        WHERE rule_type != 'shift_completion' 
        ORDER BY rule_type, points DESC
    ");
    $points_rules = $stmt_rules->fetchAll(PDO::FETCH_ASSOC);

    // Получаем текущие баллы за смену
    $stmt_current = $pdo->prepare("
        SELECT 
            lsp.id, lsp.rule_id, lsp.points_awarded, lsp.comment,
            p.rule_name, p.points as base_points
        FROM lifeguard_shift_points lsp
        JOIN points p ON lsp.rule_id = p.id_balls
        WHERE lsp.shift_id = :shift_id
    ");
    $stmt_current->execute([':shift_id' => $shift_id]);
    $current_points = $stmt_current->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error loading shift points data: " . $e->getMessage());
    set_flash_message('помилка', 'Ошибка загрузки данных.');
}

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $form_errors[] = 'Ошибка CSRF токена.';
    } else {
        try {
            $pdo->beginTransaction();

            // Удаляем все существующие баллы за смену
            $stmt_delete = $pdo->prepare("
                DELETE FROM lifeguard_shift_points 
                WHERE shift_id = :shift_id
            ");
            $stmt_delete->execute([':shift_id' => $shift_id]);

            // Получаем сумму удаленных баллов
            $stmt_sum = $pdo->prepare("
                SELECT SUM(points_awarded) as total 
                FROM lifeguard_shift_points 
                WHERE shift_id = :shift_id
            ");
            $stmt_sum->execute([':shift_id' => $shift_id]);
            $deleted_points = $stmt_sum->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            // Добавляем новые баллы
            $total_points = 0;
            foreach ($_POST['points'] ?? [] as $rule_id => $data) {
                if (!empty($data['awarded']) && $data['awarded'] == '1') {
                    $stmt_rule = $pdo->prepare("SELECT points FROM points WHERE id_balls = :rule_id");
                    $stmt_rule->execute([':rule_id' => $rule_id]);
                    $rule_data = $stmt_rule->fetch(PDO::FETCH_ASSOC);

                    if ($rule_data) {
                        $points_data = calculate_shift_points($pdo, $shift_id, $rule_data['points']);
                        
                        $stmt_insert = $pdo->prepare("
                            INSERT INTO lifeguard_shift_points 
                            (shift_id, user_id, rule_id, points_awarded, base_points_from_rule, 
                             coefficient_applied, awarded_by_user_id, comment)
                            VALUES 
                            (:shift_id, :user_id, :rule_id, :points_awarded, :base_points,
                             :coefficient, :awarded_by, :comment)
                        ");

                        $stmt_insert->execute([
                            ':shift_id' => $shift_id,
                            ':user_id' => $shift_data['user_id'],
                            ':rule_id' => $rule_id,
                            ':points_awarded' => $points_data['points_awarded'],
                            ':base_points' => $points_data['base_points'],
                            ':coefficient' => $points_data['coefficient'],
                            ':awarded_by' => $_SESSION['user_id'],
                            ':comment' => $data['comment'] ?? null
                        ]);

                        $total_points += $points_data['points_awarded'];
                    }
                }
            }

            $pdo->commit();
            $success_message = 'Баллы успешно обновлены.';
            
            // Перезагружаем текущие баллы
            $stmt_current->execute([':shift_id' => $shift_id]);
            $current_points = $stmt_current->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error updating shift points: " . $e->getMessage());
            $form_errors[] = 'Ошибка при обновлении баллов.';
        }
    }
}

// Группируем правила по типам
$grouped_rules = [];
foreach ($points_rules as $rule) {
    $grouped_rules[$rule['rule_type']][] = $rule;
}

// Группируем текущие баллы по правилам
$current_points_by_rule = [];
foreach ($current_points as $point) {
    $current_points_by_rule[$point['rule_id']] = $point;
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo rtrim(APP_URL, '/'); ?>/assets/css/styles.css">
</head>
<body>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo escape($page_title); ?></h1>
                    <a href="<?php echo rtrim(APP_URL, '/'); ?>/admin/index.php" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>Назад
                    </a>
                </div>

                <?php if (!empty($form_errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <ul class="list-disc list-inside">
                            <?php foreach ($form_errors as $error): ?>
                                <li><?php echo escape($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo escape($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Информация о смене -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Информация о смене</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600">Лайфгард:</p>
                            <p class="font-medium"><?php echo escape($shift_data['lifeguard_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Пост:</p>
                            <p class="font-medium"><?php echo escape($shift_data['post_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Начало смены:</p>
                            <p class="font-medium"><?php echo format_datetime($shift_data['start_time']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Окончание смены:</p>
                            <p class="font-medium"><?php echo format_datetime($shift_data['end_time']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Отработано часов:</p>
                            <p class="font-medium"><?php echo $shift_data['rounded_work_hours']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Форма управления баллами -->
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <?php foreach ($grouped_rules as $rule_type => $rules): ?>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <h3 class="text-lg font-semibold mb-4"><?php echo escape(ucfirst($rule_type)); ?></h3>
                            <div class="space-y-4">
                                <?php foreach ($rules as $rule): ?>
                                    <div class="flex items-start space-x-4">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" 
                                                   name="points[<?php echo $rule['id_balls']; ?>][awarded]" 
                                                   value="1"
                                                   id="rule_<?php echo $rule['id_balls']; ?>"
                                                   <?php echo isset($current_points_by_rule[$rule['id_balls']]) ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        </div>
                                        <div class="flex-1">
                                            <label for="rule_<?php echo $rule['id_balls']; ?>" class="font-medium">
                                                <?php echo escape($rule['rule_name']); ?>
                                                <span class="text-sm text-gray-500">
                                                    (<?php echo $rule['points']; ?> баллов)
                                                </span>
                                            </label>
                                            <input type="text" 
                                                   name="points[<?php echo $rule['id_balls']; ?>][comment]"
                                                   placeholder="Комментарий (необязательно)"
                                                   value="<?php echo escape($current_points_by_rule[$rule['id_balls']]['comment'] ?? ''); ?>"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($rule_type === 'bonus'): ?>
                                    <div class="flex items-start space-x-4 bg-blue-50 rounded p-3 border border-blue-200">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" 
                                                   name="points[10][awarded]" 
                                                   value="1"
                                                   id="rule_10"
                                                   <?php echo isset($current_points_by_rule[10]) ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        </div>
                                        <div class="flex-1">
                                            <label for="rule_10" class="font-medium">
                                                Додання свідків у протокол
                                                <span class="text-sm text-gray-500">(3 бали)</span>
                                            </label>
                                            <div class="text-xs text-gray-500 mb-1">Мається на увазі додання ПІБ та номеру</div>
                                            <input type="text" 
                                                   name="points[10][comment]"
                                                   placeholder="Вкажіть ПІБ та номер свідків (за потреби)"
                                                   value="<?php echo escape($current_points_by_rule[10]['comment'] ?? ''); ?>"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo rtrim(APP_URL, '/'); ?>/assets/js/main.js"></script>
</body>
</html> 