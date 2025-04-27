<?php
require_role('lifeguard'); // Access control
global $pdo;

$user_id = $_SESSION['user_id'];
$current_shift = null;
$shift_history = [];
$lifeguard_error = ''; // Variable for potential errors

try {
    // --- Fetch Current Active Shift ---
    $stmt_current = $pdo->prepare("
        SELECT s.id, s.start_time, s.post_id, p.name as post_name
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.user_id = :user_id AND s.status = 'active'
        ORDER BY s.start_time DESC
        LIMIT 1
    ");
    $stmt_current->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_current->execute();
    $current_shift = $stmt_current->fetch();

    // --- Fetch Shift History (Last 10 completed/cancelled) ---
    $stmt_history = $pdo->prepare("
        SELECT s.start_time, s.end_time, s.status, p.name as post_name
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.user_id = :user_id AND s.status != 'active'
        ORDER BY s.start_time DESC
        LIMIT 10
    ");
     $stmt_history->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_history->execute();
    $shift_history = $stmt_history->fetchAll();

} catch (PDOException $e) {
    // error_log("Lifeguard Panel DB Error: " . $e->getMessage());
    $lifeguard_error = 'Не вдалося завантажити дані зміни.'; // Set error message
    // Show error via flash (optional) or display in the panel
    // set_flash_message('помилка', $lifeguard_error);
}
?>

<!-- Змінено id секції для ясності -->
<section id="lifeguard-dashboard">
    <h2 class="text-2xl font-semibold mb-6 text-center text-gray-800">Панель Лайфгарда</h2>

    <!-- Tabs Navigation -->
    <div class="mb-4 border-b border-gray-300">
         <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="lifeguardTab" role="tablist">
            <li class="mr-2" role="presentation">
                <!-- Поточна зміна/Дії - активна за замовчуванням -->
                <button class="inline-block p-4 rounded-t-lg border-b-2 text-red-600 border-red-500" id="lifeguard-current-tab" type="button" role="tab" onclick="showLifeguardTab('lifeguard-current')">
                    <i class="fas fa-play-circle mr-2"></i> Поточна Зміна / Дії
                </button>
            </li>
            <li class="mr-2" role="presentation">
                 <!-- Історія - неактивна -->
                <button class="inline-block p-4 rounded-t-lg border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" id="lifeguard-history-tab" type="button" role="tab" onclick="showLifeguardTab('lifeguard-history')">
                   <i class="fas fa-history mr-2"></i> Історія Змін
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Content Container -->
    <div id="lifeguardTabContent">

        <?php if ($lifeguard_error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
                <p><strong class="font-bold">Помилка!</strong> <?php echo escape($lifeguard_error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Content for "Поточна Зміна / Дії" Tab -->
        <div id="lifeguard-current-content" role="tabpanel" class="space-y-6"> <!-- Додано space-y для відступів -->

             <!-- Попередження про очікування сканування -->
             <?php if (isset($_SESSION['action_pending'])): ?>
                 <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md" role-alert>
                    <p class="font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i> Очікується сканування мітки поста для дії: <strong><?php echo escape($_SESSION['action_pending']); ?></strong></p>
                 </div>
             <?php endif; ?>

             <!-- Сітка для Кнопок Дій та Статусу -->
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Action Buttons Block -->
                 <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-xl font-semibold mb-4 text-gray-900">Дії зі Зміною</h3>
                    <p class="mb-4 text-sm text-gray-600">Натисніть кнопку, а потім скануйте NFC-мітку відповідного поста.</p>
                    <div class="space-x-4">
                         <?php if (!$current_shift && !isset($_SESSION['action_pending'])): // Show "Start" only if no active shift AND no pending action ?>
                             <a href="set_action.php?action=start" class="btn-green inline-flex items-center">
                                 <i class="fas fa-play mr-2"></i> Почати Зміну
                             </a>
                         <?php elseif ($current_shift && !isset($_SESSION['action_pending'])): // Show "End" only if active shift AND no pending action ?>
                              <a href="set_action.php?action=end" class="btn-red inline-flex items-center">
                                 <i class="fas fa-stop mr-2"></i> Завершити Зміну
                             </a>
                         <?php elseif (isset($_SESSION['action_pending'])): ?>
                              <!-- Показувати якусь інформацію або нічого, поки очікується сканування -->
                              <p class="text-gray-500 italic">Скануйте мітку...</p>
                         <?php else: ?>
                            <!-- Якщо є зміна, але і є очікування (нелогічна ситуація, але про всяк) -->
                            <p class="text-gray-500 italic">Очікується дія...</p>
                         <?php endif; ?>
                    </div>
                </div>

                <!-- Current Shift Status Block -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold mb-4 text-gray-900"><i class="fas fa-clock mr-2"></i> Поточний Статус</h3>
                    <?php if ($current_shift): ?>
                        <div class="space-y-2 text-gray-700">
                             <p><span class="font-semibold">Статус:</span> <span class="text-green-600 font-bold">Активна</span></p>
                            <p><span class="font-semibold">Пост:</span> <?php echo escape($current_shift['post_name']); ?></p>
                            <p><span class="font-semibold">Початок:</span> <?php echo format_datetime($current_shift['start_time']); ?></p>
                            <p><span class="font-semibold">Тривалість:</span> <?php echo format_duration($current_shift['start_time']); ?></p>
                        </div>
                    <?php else: ?>
                         <p class="text-gray-500 italic">Немає активної зміни.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Content for "Історія Змін" Tab - HIDDEN BY DEFAULT -->
        <div id="lifeguard-history-content" role="tabpanel" class="hidden bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4 text-gray-800"><i class="fas fa-history mr-2"></i> Історія Останніх Змін</h3>
             <div class="overflow-x-auto">
                 <!-- Таблиця історії з адаптивністю -->
                <table class="min-w-full bg-white md:table">
                    <thead class="md:table-header-group"> <!-- Заголовки видно тільки на md+ -->
                        <tr class="bg-gray-100 md:table-row">
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell">Пост</th>
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell">Початок</th>
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell">Кінець</th>
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell">Тривалість</th>
                            <th class="py-3 px-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider md:table-cell">Статус</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 md:table-row-group">
                        <?php if (empty($shift_history)): ?>
                            <tr class="md:table-row">
                                <td colspan="5" class="border-t border-gray-200 py-4 px-4 text-center text-gray-500 md:table-cell">Історія змін відсутня.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shift_history as $shift): ?>
                                <tr class="md:table-row">
                                    <td data-label="Пост:" class="border-t border-gray-200 py-3 px-4 text-gray-800 md:table-cell"><?php echo escape($shift['post_name']); ?></td>
                                    <td data-label="Початок:" class="border-t border-gray-200 py-3 px-4 text-sm md:table-cell"><?php echo format_datetime($shift['start_time']); ?></td>
                                    <td data-label="Кінець:" class="border-t border-gray-200 py-3 px-4 text-sm md:table-cell"><?php echo format_datetime($shift['end_time']); ?></td>
                                    <td data-label="Тривалість:" class="border-t border-gray-200 py-3 px-4 text-sm md:table-cell"><?php echo format_duration($shift['start_time'], $shift['end_time']); ?></td>
                                    <td data-label="Статус:" class="border-t border-gray-200 py-3 px-4 md:table-cell">
                                        <?php
                                        // ... (ваш switch для статусів) ...
                                         $status_text = '';
                                         $status_class = '';
                                         switch ($shift['status']) {
                                             case 'completed': $status_text = 'Завершено'; $status_class = 'bg-gray-500'; break;
                                             case 'cancelled': $status_text = 'Скасовано'; $status_class = 'bg-orange-500'; break;
                                             default: $status_text = escape(ucfirst($shift['status'])); $status_class = 'bg-blue-500';
                                         }
                                        ?>
                                        <span class="<?php echo $status_class; ?> px-2 py-1 rounded-full text-xs text-white"><?php echo $status_text; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             <?php if (!empty($shift_history)): ?>
             <p class="mt-4 text-sm text-gray-500">Показано останні <?php echo count($shift_history); ?> змін.</p>
             <?php endif; ?>
        </div> <!-- кінець lifeguard-history-content -->

    </div> <!-- кінець lifeguardTabContent -->

</section>