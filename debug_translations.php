<?php
// debug_translations.php - Файл для отладки системы переводов
// Добавьте это в начало duty_officer_content.php для отладки

echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
echo "<h3>Debug Translations:</h3>";

// Проверяем, какие глобальные переменные существуют для переводов
echo "<p><strong>Checking global translation variables:</strong></p>";
echo "<ul>";

if (isset($GLOBALS['translations'])) {
    echo "<li>✅ \$GLOBALS['translations'] exists with " . count($GLOBALS['translations']) . " items</li>";
    echo "<pre>First 5 keys: " . print_r(array_slice(array_keys($GLOBALS['translations']), 0, 5), true) . "</pre>";
} else {
    echo "<li>❌ \$GLOBALS['translations'] does not exist</li>";
}

if (isset($translations)) {
    echo "<li>✅ \$translations exists with " . count($translations) . " items</li>";
    echo "<pre>First 5 keys: " . print_r(array_slice(array_keys($translations), 0, 5), true) . "</pre>";
} else {
    echo "<li>❌ \$translations does not exist</li>";
}

if (isset($lang)) {
    echo "<li>✅ \$lang exists with " . count($lang) . " items</li>";
    echo "<pre>First 5 keys: " . print_r(array_slice(array_keys($lang), 0, 5), true) . "</pre>";
} else {
    echo "<li>❌ \$lang does not exist</li>";
}

echo "</ul>";

// Проверяем, как работает функция t()
echo "<p><strong>Testing t() function:</strong></p>";
echo "<ul>";

$test_key = 'photos_for_approval';
$result = t($test_key);
echo "<li>t('{$test_key}') = '{$result}'</li>";

$test_key2 = 'approve_button';
$result2 = t($test_key2);
echo "<li>t('{$test_key2}') = '{$result2}'</li>";

echo "</ul>";

// Проверяем, существует ли функция t()
if (function_exists('t')) {
    echo "<p>✅ Function t() exists</p>";
    
    // Получаем информацию о функции через рефлексию
    try {
        $reflection = new ReflectionFunction('t');
        echo "<p><strong>Function t() details:</strong></p>";
        echo "<ul>";
        echo "<li>File: " . $reflection->getFileName() . "</li>";
        echo "<li>Line: " . $reflection->getStartLine() . "</li>";
        echo "<li>Parameters: " . count($reflection->getParameters()) . "</li>";
        foreach ($reflection->getParameters() as $param) {
            echo "<li>Parameter: {$param->getName()}" . ($param->isDefaultValueAvailable() ? " (default: " . var_export($param->getDefaultValue(), true) . ")" : "") . "</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p>Could not get function details: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ Function t() does not exist</p>";
}

echo "</div>";
?>