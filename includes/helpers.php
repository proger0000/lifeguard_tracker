<?php
/**
 * Файл: includes/helpers.php
 * Описание: Вспомогательные функции для работы с баллами, расчетами зарплат и прочим.
 * ВЕРСИЯ С НОВОЙ ФУНКЦИЕЙ ДЛЯ РАСЧЕТА БАЛЛОВ
 */

/**
 * Рассчитывает актуальные баллы для всех правил по указанной смене.
 * Учитывает особые правила, такие как "Зміна", балл за которую зависит от коэффициента поста.
 *
 * @param PDO $pdo Объект PDO для работы с БД.
 * @param int $shift_id ID смены.
 * @return array Ассоциативный массив [rule_id => calculated_points].
 */
function get_calculated_points_for_shift($pdo, $shift_id) {
    try {
        // 1. Получаем коэффициент сложности поста для данной смены
        $stmt_shift = $pdo->prepare(
            "SELECT p.complexity_coefficient
             FROM shifts s
             JOIN posts p ON s.post_id = p.id
             WHERE s.id = :shift_id"
        );
        $stmt_shift->execute([':shift_id' => $shift_id]);
        $complexity_coefficient = $stmt_shift->fetchColumn();

        // Если по какой-то причине коэффициент не найден, используем значение по умолчанию 1.0
        if ($complexity_coefficient === false) {
            $complexity_coefficient = 1.0;
        }

        // 2. Получаем все базовые правила и их баллы из таблицы `points`
        $stmt_rules = $pdo->query("SELECT id_balls, quantity FROM points");
        // fetchAll с PDO::FETCH_KEY_PAIR очень удобен для создания ассоциативного массива [id => quantity]
        $all_rules = $stmt_rules->fetchAll(PDO::FETCH_KEY_PAIR);

        $calculated_points = [];
        // 3. Перебираем все правила и рассчитываем финальный балл
        foreach ($all_rules as $rule_id => $base_points) {
            // --- ОСОБОЕ ПРАВИЛО: "Зміна" (предполагаем, что у него ID = 1) ---
            if ($rule_id == 1) {
                // Балл за смену РАВЕН коэффициенту сложности поста
                $calculated_points[$rule_id] = (float)$complexity_coefficient;
            } else {
                // Для всех остальных правил балл остается базовым (без применения коэффициента)
                $calculated_points[$rule_id] = (float)$base_points;
            }
        }
        return $calculated_points;

    } catch (Exception $e) {
        error_log("Error in get_calculated_points_for_shift: " . $e->getMessage());
        // В случае ошибки возвращаем пустой массив, чтобы не сломать логику дальше
        return [];
    }
}


/**
 * Рассчитывает округленные часы работы на основе времени начала и окончания смены.
 * (Код без изменений)
 * @param string $start_time Время начала смены
 * @param string $end_time Время окончания смены
 * @return int Округленное количество часов
 */
function calculate_rounded_hours($start_time, $end_time) {
    if (!$start_time || !$end_time) {
        return 0;
    }

    try {
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $interval = $start->diff($end);

        $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        $hours = floor($total_minutes / 60);

        // Если остаток минут >= 30, добавляем еще час
        if (($total_minutes % 60) >= 30) {
            $hours++;
        }
        return (int)$hours;

    } catch (Exception $e) {
        error_log("Error calculating rounded hours: " . $e->getMessage());
        return 0;
    }
}

/**
 * Рассчитывает зарплату на основе отработанных часов и базовой ставки.
 * (Код без изменений)
 * @param float $hours Отработанные часы
 * @param float $base_rate Базовая ставка за час
 * @param float $tax_rate Ставка налога (по умолчанию 0.23 или 23%)
 * @return array Массив с информацией о зарплате (брутто и нетто)
 */
function calculate_salary($hours, $base_rate, $tax_rate = 0.23) {
    $gross = $hours * $base_rate;
    $net = $gross * (1 - $tax_rate);

    return [
        'gross' => round($gross, 2),
        'net' => round($net, 2)
    ];
}

/**
 * Форматирует денежную сумму для отображения.
 * (Код без изменений)
 * @param float $amount Сумма
 * @return string Отформатированная сумма
 */
function format_money($amount) {
    return number_format((float)$amount, 2, '.', ' ') . ' грн';
}

// Другие ваши вспомогательные функции могут быть здесь...
// ...
?>