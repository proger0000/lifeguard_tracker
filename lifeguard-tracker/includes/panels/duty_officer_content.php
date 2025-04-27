<?php
global $pdo;

$active_shifts_by_post = [];
$stats = ['active_lifeguards' => 0, 'active_posts' => 0, 'completed_today' => 0];

try {
    // --- Fetch Active Shifts grouped by Post ---
    $stmt_active = $pdo->prepare("
        SELECT
    s.id as shift_id, s.start_time,
    s.start_photo_path,               -- << ПЕРЕВІРТЕ НАЯВНІСТЬ ЦЬОГО
    s.start_photo_approved_at,        -- << І ЦЬОГО
    u.id as user_id, u.full_name as lifeguard_name,
    p.id as post_id, p.name as post_name
FROM shifts s
JOIN users u ON s.user_id = u.id
JOIN posts p ON s.post_id = p.id
WHERE s.status = 'active'
ORDER BY p.name ASC, s.start_time ASC
    ");
    $stmt_active->execute();
    $active_shifts_raw = $stmt_active->fetchAll();

    // Group results by post_name in PHP
    foreach ($active_shifts_raw as $shift) {
    $post_name = $shift['post_name'];
    if (!isset($active_shifts_by_post[$post_name])) {
        $active_shifts_by_post[$post_name] = [
            'post_id' => $shift['post_id'],
            'lifeguards' => []
        ];
    }
    $active_shifts_by_post[$post_name]['lifeguards'][] = [
        'shift_id' => $shift['shift_id'], // Додано раніше, якщо є
        'name' => $shift['lifeguard_name'],
        'start_time' => $shift['start_time'],
        'start_photo_path' => $shift['start_photo_path'],           // << ПЕРЕВІРТЕ/ДОДАЙТЕ ЦЕЙ РЯДОК
        'start_photo_approved_at' => $shift['start_photo_approved_at'] // << І ЦЕЙ РЯДОК
    ];
}

     // --- Calculate Stats (Example) ---
     $stats['active_lifeguards'] = count($active_shifts_raw);
     $stats['active_posts'] = count($active_shifts_by_post);

    // Count shifts completed today
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $stmt_completed = $pdo->prepare("
        SELECT COUNT(id) as count
        FROM shifts
        WHERE status = 'completed' AND end_time BETWEEN :start AND :end
    ");
    $stmt_completed->bindParam(':start', $today_start);
    $stmt_completed->bindParam(':end', $today_end);
    $stmt_completed->execute();
    $completed_result = $stmt_completed->fetch();
    $stats['completed_today'] = $completed_result['count'] ?? 0;


} catch (PDOException $e) {
     // error_log("Duty Officer Panel DB Error: " . $e->getMessage());
    set_flash_message('помилка', 'Не вдалося завантажити дані чергувань.');
}
?>

<section id="duty-section">
    <h2 class="text-2xl font-semibold mb-6 text-center">Панель Чергового</h2>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
         <!-- Statistics -->
         <div class="lg:col-span-1 bg-white/20 backdrop-blur-sm p-6 rounded-lg shadow-lg">
             <h3 class="text-xl font-semibold mb-4"><i class="fas fa-chart-bar mr-2"></i> Статистика за день</h3>
             <div class="space-y-3">
                 <p><span class="font-semibold">Активних рятувальників:</span> <span class="text-xl font-bold text-green-300"><?php echo $stats['active_lifeguards']; ?></span></p>
                 <p><span class="font-semibold">Активних постів:</span> <span class="text-xl font-bold text-yellow-300"><?php echo $stats['active_posts']; ?></span></p>
                 <p><span class="font-semibold">Завершено змін сьогодні:</span> <span class="text-xl font-bold text-blue-300"><?php echo $stats['completed_today']; ?></span></p>
             </div>
        </div>

         <!-- Active Shifts Card -->
 <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md">
     <h4 class="text-lg font-semibold mb-4 text-gray-800"><i class="fas fa-users mr-2"></i> Активні Зміни на Постах</h4>
     <div class="space-y-6">
         <?php if (empty($active_shifts_by_post)): ?>
             <p class="text-gray-500 text-center py-4">Наразі немає активних змін.</p>
         <?php else: ?>
             <?php foreach ($active_shifts_by_post as $post_name => $post_data): ?>
                 <div class="border-b border-gray-200 pb-4 mb-4 last:border-b-0 last:pb-0 last:mb-0">
                     <h5 class="text-md font-semibold mb-3 text-gray-700">
                         <i class="fas fa-map-marker-alt mr-2 text-red-500"></i> <?php echo escape($post_name); ?>
                     </h5>
                     <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                         <?php foreach ($post_data['lifeguards'] as $lifeguard): ?>
                         <!-- Картка рятувальника -->
                         <div class="bg-gray-50 p-3 rounded-md shadow-sm space-y-2"> <!-- Додано space-y -->
                             <p class="font-semibold text-gray-800"><?php echo escape($lifeguard['name']); ?></p>
                             <p class="text-sm text-gray-600"><i class="fas fa-clock mr-1"></i> Початок: <?php echo format_datetime($lifeguard['start_time']); ?></p>
                             <p class="text-sm text-gray-600 mb-2"><i class="fas fa-hourglass-half mr-1"></i> Тривалість: <?php echo format_duration($lifeguard['start_time']); ?></p>

                             <!-- === ЛОГІКА ФОТО === -->
                             <?php if (!empty($lifeguard['start_photo_path'])): ?>
                                 <div>
                                     <!-- Використовуємо APP_URL або прямий відносний шлях, якщо uploads доступно -->
                                     <a href="<?php echo escape($lifeguard['start_photo_path']); ?>" target="_blank" title="Відкрити фото в новій вкладці">
                                         <img src="<?php echo escape($lifeguard['start_photo_path']); ?>" alt="Фото лайфгарда" class="w-full h-auto max-h-40 object-cover rounded-md mb-2 cursor-pointer hover:opacity-90">
                                     </a>
                                     <?php if ($lifeguard['start_photo_approved_at'] === null): ?>
                                          <!-- Форма для кнопок підтвердження/відхилення -->
                                         <form action="approve_photo.php" method="POST" class="flex items-center justify-center space-x-2">
                                             <?php csrf_input(); ?>
                                             <input type="hidden" name="shift_id" value="<?php echo escape($lifeguard['shift_id']); ?>">
                                             <input type="hidden" name="action" value="approve">
                                             <button type="submit" class="btn-green text-xs px-2 py-1 !font-normal"> <!-- Міні-кнопка -->
                                                 <i class="fas fa-check mr-1"></i> Підтвердити
                                             </button>
                                             <!-- Кнопку відхилення можна додати, коли буде відповідна логіка -->
                                             <!--
                                             <button type="submit" formaction="approve_photo.php" name="action" value="reject" class="btn-red text-xs px-2 py-1 !font-normal">
                                                 <i class="fas fa-times mr-1"></i> Відхилити
                                             </button>
                                              -->
                                         </form>
                                     <?php else: ?>
                                         <p class="text-xs text-green-600 font-semibold text-center"><i class="fas fa-check-circle mr-1"></i> Фото підтверджено</p>
                                     <?php endif; ?>
                                 </div>
                             <?php else: ?>
                                  <p class="text-xs text-yellow-600 font-semibold text-center"><i class="fas fa-camera mr-1"></i> Очікується фото...</p>
                             <?php endif; ?>
                              <!-- === КІНЕЦЬ ЛОГІКИ ФОТО === -->

                         </div>
                         <?php endforeach; ?>
                     </div>
                 </div>
             <?php endforeach; ?>
         <?php endif; ?>
     </div>
 </div>
    </div>
</section>