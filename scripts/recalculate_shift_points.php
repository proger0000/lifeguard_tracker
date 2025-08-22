<?php
/**
 * Файл: scripts/recalculate_shift_points.php
 * Описание: Единоразовый скрипт для перерасчета баллов за завершенные смены (правило "Зміна", ID=1)
 * на основе коэффициента сложности поста.
 *
 * ЗАПУСКАТЬ ИЗ КОМАНДНОЙ СТРОКИ: php scripts/recalculate_shift_points.php
 */

// Увеличиваем лимит времени выполнения, на случай если смен очень много
set_time_limit(0);
// Включаем вывод ошибок в консоль
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Подключаем конфигурацию для доступа к БД
require_once dirname(__DIR__) . '/config.php';

global $pdo;

echo "--- Запуск скрипта перерасчета баллов за смену ---\n";

try {
    // Начинаем транзакцию, чтобы в случае ошибки можно было все откатить
    $pdo->beginTransaction();

    // 1. Находим все записи в 'lifeguard_shift_points', которые относятся к правилу "Зміна" (ID=1)
    // и связаны с завершенными сменами.
    $stmt_find_points = $pdo->prepare("
        SELECT
            lsp.id as point_id,
            lsp.shift_id,
            lsp.points_awarded as old_points,
            s.post_id,
            p.complexity_coefficient
        FROM
            lifeguard_shift_points lsp
        JOIN
            shifts s ON lsp.shift_id = s.id
        JOIN
            posts p ON s.post_id = p.id
        WHERE
            lsp.rule_id = 1
            AND s.status = 'completed'
    ");
    $stmt_find_points->execute();
    $points_to_update = $stmt_find_points->fetchAll(PDO::FETCH_ASSOC);

    $total_to_check = count($points_to_update);
    if ($total_to_check === 0) {
        echo "Не найдено записей для правила 'Зміна', требующих перерасчета.\n";
        $pdo->commit(); // Завершаем транзакцию, хоть и ничего не делали
        exit;
    }

    echo "Найдено {$total_to_check} записей для проверки и возможного обновления.\n";

    // 2. Готовим запрос на обновление
    $stmt_update = $pdo->prepare("
        UPDATE lifeguard_shift_points
        SET
            points_awarded = :new_points,
            base_points_from_rule = :new_points, -- Обновляем и базовый балл для консистентности
            coefficient_applied = 1.00 -- Коэффициент уже учтен в самом балле
        WHERE
            id = :point_id
    ");

    $updated_count = 0;
    // 3. Перебираем найденные записи и обновляем их
    foreach ($points_to_update as $point_entry) {
        $point_id = $point_entry['point_id'];
        $old_points = (float)$point_entry['old_points'];
        $new_points = (float)($point_entry['complexity_coefficient'] ?? 1.0);

        // Обновляем только если старое значение отличается от нового
        // Это предотвращает лишние записи в БД и делает скрипт идемпотентным
        if ($old_points !== $new_points) {
            $stmt_update->execute([
                ':new_points' => $new_points,
                ':point_id' => $point_id
            ]);
            $updated_count++;
            echo "Обновлен балл для смены #{$point_entry['shift_id']}: было {$old_points}, стало {$new_points}\n";
        }
    }

    // 4. Завершаем транзакцию, применяя все изменения
    $pdo->commit();

    echo "\n--- Перерасчет завершен ---\n";
    echo "Всего проверено записей: {$total_to_check}\n";
    echo "Фактически обновлено записей: {$updated_count}\n";

} catch (Exception $e) {
    // В случае любой ошибки откатываем все изменения
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Выводим сообщение об ошибке
    error_log("Ошибка при перерасчете баллов: " . $e->getMessage());
    echo "\n!!! ПРОИЗОШЛА ОШИБКА: " . $e->getMessage() . " !!!\n";
    echo "Все изменения были отменены.\n";
}

?>