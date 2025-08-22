<?php
/**
 * Скрипт для массового заполнения поля `rounded_work_hours` для уже завершенных смен.
 * Запускать этот скрипт можно один раз из командной строки:
 * php scripts/backfill_rounded_hours.php
 */

// Увеличиваем лимит времени выполнения скрипта, если смен очень много
set_time_limit(0);

// Подключаем необходимые файлы
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

global $pdo;

echo "Начинаем заполнение округленных часов для завершенных смен...\n";

try {
    // Начинаем транзакцию, чтобы обеспечить атомарность операций
    $pdo->beginTransaction();

    // Выбираем все завершенные смены, у которых rounded_work_hours NULL
    $stmt = $pdo->prepare("
        SELECT id, start_time, end_time
        FROM shifts
        WHERE status = 'completed' AND rounded_work_hours IS NULL
    ");
    $stmt->execute();
    $shifts_to_update = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated_count = 0;
    $total_shifts = count($shifts_to_update);

    if ($total_shifts === 0) {
        echo "Нет завершенных смен, требующих обновления округленных часов.\n";
    } else {
        echo "Найдено {$total_shifts} смен для обновления.\n";
        foreach ($shifts_to_update as $shift) {
            $shift_id = $shift['id'];
            $start_time = $shift['start_time'];
            $end_time = $shift['end_time'];

            // Проверяем, что end_time не NULL, так как без него невозможно рассчитать часы
            if ($end_time) {
                $rounded_hours = calculate_rounded_hours($start_time, $end_time);

                $stmt_update = $pdo->prepare("
                    UPDATE shifts
                    SET rounded_work_hours = :rounded_hours
                    WHERE id = :shift_id
                ");
                $stmt_update->execute([
                    ':rounded_hours' => $rounded_hours,
                    ':shift_id' => $shift_id
                ]);
                $updated_count++;
                echo "\rОбновлено {$updated_count}/{$total_shifts} смен (ID: {$shift_id})";
            } else {
                echo "\nПропущена смена ID: {$shift_id} - отсутствует время окончания (end_time).\n";
            }
        }
        echo "\n"; // Новая строка после завершения прогресса
    }

    // Применяем все изменения
    $pdo->commit();

    echo "Завершено! Успешно обновлено {$updated_count} смен.\n";

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Ошибка БД при заполнении округленных часов: " . $e->getMessage());
    echo "Ошибка базы данных: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Общая ошибка при заполнении округленных часов: " . $e->getMessage());
    echo "Произошла ошибка: " . $e->getMessage() . "\n";
}

echo "Скрипт завершен.\n";

?> 