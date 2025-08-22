<?php

require_once dirname(__DIR__) . '/config.php';

global $pdo;

try {
    $sql = "ALTER TABLE users ADD COLUMN contract_number VARCHAR(255) NULL;";
    $pdo->exec($sql);
    echo "Столбец 'contract_number' успешно добавлен в таблицу 'users'.\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Error code for 'Duplicate column name'
        echo "Столбец 'contract_number' уже существует в таблице 'users'. Пропускаем.\n";
    } else {
        error_log("Ошибка добавления столбца 'contract_number': " . $e->getMessage());
        echo "Ошибка при добавлении столбца 'contract_number': " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    error_log("Общая ошибка: " . $e->getMessage());
    echo "Произошла ошибка: " . $e->getMessage() . "\n";
}

?> 