<!-- lifeguard_panel.php - Обновленная версия -->
<?php
require_role('lifeguard');
global $pdo;

$user_id = $_SESSION['user_id'];
$current_shift = null;
$completed_shifts_today_no_report_or_photo = [];
$lifeguard_error = '';
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$base_url = rtrim(APP_URL, '/');

try {
    // Получаем текущую активную смену
    $stmt_current = $pdo->prepare("
        SELECT s.id, s.start_time, s.post_id, p.name as post_name, s.start_photo_path
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.user_id = :user_id AND s.status = 'active'
        ORDER BY s.start_time DESC
        LIMIT 1
    ");
    $stmt_current->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_current->execute();
    $current_shift = $stmt_current->fetch();

    // Получаем завершенные сегодня смены без отчета или фото
    $stmt_completed_today = $pdo->prepare("
        SELECT s.id, s.start_time, s.end_time, p.name as post_name, 
               s.photo_close_path, s.lifeguard_assignment_type
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        LEFT JOIN shift_reports sr ON s.id = sr.shift_id
        WHERE s.user_id = :user_id
          AND s.status = 'completed'
          AND s.end_time BETWEEN :today_start AND :today_end
          AND (sr.id IS NULL OR s.photo_close_path IS NULL)
        ORDER BY s.end_time DESC
    ");
    $stmt_completed_today->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_completed_today->bindParam(':today_start', $today_start);
    $stmt_completed_today->bindParam(':today_end', $today_end);
    $stmt_completed_today->execute();
    $completed_shifts_today_no_report_or_photo = $stmt_completed_today->fetchAll();

} catch (PDOException $e) {
    error_log("Lifeguard Panel DB Error: " . $e->getMessage());
    $lifeguard_error = 'Не вдалося завантажити дані';
}

// Функции форматирования
if (!function_exists('format_duration')) {
    function format_duration($start_time, $end_time = null) {
        if (empty($start_time)) return '-';
        try {
            $start = new DateTime($start_time);
            $end = $end_time ? new DateTime($end_time) : new DateTime();
            $interval = $start->diff($end);
            $parts = [];
            if ($interval->h > 0) $parts[] = $interval->h . ' год';
            if ($interval->i > 0) $parts[] = $interval->i . ' хв';
            if (empty($parts) && $interval->s >= 0) return $interval->s . ' сек';
            return !empty($parts) ? implode(' ', $parts) : 'менше хвилини';
        } catch (Exception $e) {
            return 'Помилка';
        }
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($datetime_string) {
        if (empty($datetime_string)) return '-';
        try {
            $date = new DateTime($datetime_string);
            return $date->format('d.m.Y H:i');
        } catch (Exception $e) {
            return 'Невірно';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель лайфгарда</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <style>
        * {
            font-family: 'Comfortaa', cursive;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
        }
        
        .nfc-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: .7;
                transform: scale(1.05);
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            padding: 1.25rem;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .time-display {
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.05em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-card i {
                font-size: 1.25rem;
            }
            
            .stat-card h4 {
                font-size: 0.875rem;
            }
            
            .stat-card p {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <!-- Main Control Panel -->
        <div class="glass-card rounded-3xl p-6 md:p-8 mb-6 main-panel">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-life-ring text-purple-600 mr-3"></i>
                Керування Зміною
            </h2>

            <?php if (isset($_SESSION['action_pending'])): ?>
                <!-- NFC Waiting State -->
                <div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-2xl p-6 mb-6 text-white nfc-pulse">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-wifi text-4xl animate-pulse"></i>
                        <div>
                            <p class="text-xl font-bold">Очікується сканування NFC</p>
                            <p class="text-white/90">
                                Дія: <strong><?php echo $_SESSION['action_pending'] === 'start' ? 'Початок зміни' : 'Завершення зміни'; ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Action Buttons -->
                <div class="flex flex-col justify-center items-center md:items-start gap-4">
                    <?php if (!$current_shift && !isset($_SESSION['action_pending'])): ?>
                        <a href="<?php echo $base_url; ?>/set_action.php?action=start" class="btn-primary w-full md:w-auto justify-center">
                            <i class="fas fa-play"></i>
                            Почати Зміну
                        </a>
                        <p class="text-white/70 text-sm">Натисніть для початку нової зміни</p>
                    <?php elseif ($current_shift && !isset($_SESSION['action_pending'])): ?>
                        <a href="<?php echo $base_url; ?>/set_action.php?action=end" class="btn-danger w-full md:w-auto justify-center">
                            <i class="fas fa-stop"></i>
                            Завершити Зміну
                        </a>
                        <p class="text-white/70 text-sm">Завершіть поточну активну зміну</p>
                    <?php endif; ?>
                </div>

                <!-- Current Status -->
                <div class="glass-card rounded-2xl p-5">
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                        Поточний статус
                    </h4>
                    <?php if ($current_shift): ?>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                <span class="font-semibold text-green-600">Активна зміна</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-map-marker-alt text-gray-500"></i>
                                <span class="text-gray-700">Пост: <strong><?php echo escape($current_shift['post_name']); ?></strong></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-clock text-gray-500"></i>
                                <span class="text-gray-700 time-display"><?php echo format_datetime($current_shift['start_time']); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-hourglass-half text-gray-500"></i>
                                <span class="text-gray-700 font-mono duration-display"><?php echo format_duration($current_shift['start_time']); ?></span>
                            </div>
                            <?php if (empty($current_shift['start_photo_path'])): ?>
                                <a href="<?php echo $base_url; ?>/upload_photo.php?shift_id=<?php echo $current_shift['id']; ?>" 
                                   class="inline-flex items-center gap-2 text-orange-600 hover:text-orange-700 font-medium mt-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Завантажити стартове фото
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-gray-500 flex items-center gap-3">
                            <i class="fas fa-moon text-gray-400"></i>
                            <span>Немає активної зміни</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Actions -->
        <?php if (!empty($completed_shifts_today_no_report_or_photo)): ?>
        <div class="glass-card rounded-3xl p-6 md:p-8 mb-6 pending-panel">
            <h3 class="text-xl md:text-2xl font-bold text-gray-800 mb-5 flex items-center">
                <i class="fas fa-tasks text-orange-500 mr-3"></i>
                Незавершені дії
            </h3>
            <div class="space-y-4">
                <?php foreach ($completed_shifts_today_no_report_or_photo as $shift): ?>
                    <div class="glass-card rounded-xl p-4 hover:bg-white/30">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <h4 class="font-semibold text-gray-800"><?php echo escape($shift['post_name']); ?></h4>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-clock text-gray-500 mr-1"></i>
                                    <?php echo format_datetime($shift['start_time']); ?> - <?php echo format_datetime($shift['end_time']); ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                                <?php
                                    $needs_end_photo = empty($shift['photo_close_path']);
                                    $assignment_type = $shift['lifeguard_assignment_type'];
                                    $needs_report = ($assignment_type === 0 || $assignment_type === 2);
                                    $report_submitted = false;
                                    
                                    if ($needs_report) {
                                        $stmt_check_report = $pdo->prepare("SELECT id FROM shift_reports WHERE shift_id = :shift_id");
                                        $stmt_check_report->bindParam(':shift_id', $shift['id'], PDO::PARAM_INT);
                                        $stmt_check_report->execute();
                                        $report_submitted = (bool)$stmt_check_report->fetch();
                                    }
                                ?>

                                <?php if ($needs_end_photo): ?>
                                    <a href="<?php echo $base_url; ?>/upload_end_photo.php?shift_id=<?php echo $shift['id']; ?>" 
                                       class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all inline-flex items-center gap-2">
                                        <i class="fas fa-camera"></i>
                                        Фото завершення
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($needs_report && !$report_submitted && !$needs_end_photo): ?>
                                    <a href="<?php echo $base_url; ?>/submit_report.php?shift_id=<?php echo $shift['id']; ?>" 
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all inline-flex items-center gap-2">
                                        <i class="fas fa-file-alt"></i>
                                        Подати звіт
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Navigation -->
        <div class="glass-card rounded-xl p-6 stats-panel">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-line text-blue-600 mr-3"></i>
                Статистика та історія
            </h3>
            <div class="stats-grid">
                <a href="<?php echo $base_url; ?>/lifeguard_monthly_stats.php" 
                   class="stat-card text-center group">
                    <i class="fas fa-calendar-alt text-2xl text-blue-600 mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-medium text-gray-800 mb-1">Місячна статистика</h4>
                    <p class="text-sm text-gray-600">Години та зарплата</p>
                </a>
                
                <a href="<?php echo $base_url; ?>/lifeguard_yearly_stats.php" 
                   class="stat-card text-center group">
                    <i class="fas fa-chart-bar text-2xl text-green-600 mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-medium text-gray-800 mb-1">Річна статистика</h4>
                    <p class="text-sm text-gray-600">Загальний огляд</p>
                </a>
                
                <a href="<?php echo $base_url; ?>/lifeguard_history.php" 
                   class="stat-card text-center group">
                    <i class="fas fa-history text-2xl text-purple-600 mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-medium text-gray-800 mb-1">Історія змін</h4>
                    <p class="text-sm text-gray-600">Всі чергування</p>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Initial animations
        anime({
            targets: '.main-panel',
            opacity: [0, 1],
            translateY: [-30, 0],
            duration: 800,
            easing: 'easeOutExpo'
        });

        anime({
            targets: '.pending-panel',
            opacity: [0, 1],
            translateY: [-30, 0],
            duration: 800,
            delay: 200,
            easing: 'easeOutExpo'
        });

        anime({
            targets: '.stats-panel',
            opacity: [0, 1],
            translateY: [-30, 0],
            duration: 800,
            delay: 400,
            easing: 'easeOutExpo'
        });

        // Animate stat cards
        anime({
            targets: '.stat-card',
            opacity: [0, 1],
            scale: [0.9, 1],
            delay: anime.stagger(100, {start: 600}),
            duration: 600,
            easing: 'easeOutExpo'
        });

        // Duration counter update
        <?php if ($current_shift): ?>
        const startTime = new Date('<?php echo $current_shift['start_time']; ?>');
        
        function updateDuration() {
            const now = new Date();
            const diff = now - startTime;
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            let duration = '';
            if (hours > 0) duration += hours + ' год ';
            if (minutes > 0) duration += minutes + ' хв ';
            if (hours === 0 && minutes === 0) duration = seconds + ' сек';
            
            const durationEl = document.querySelector('.duration-display');
            if (durationEl) {
                durationEl.textContent = duration;
            }
        }
        
        updateDuration();
        setInterval(updateDuration, 1000);
        <?php endif; ?>

        // Add ripple effect on buttons
        document.querySelectorAll('a[class*="btn-"], .stat-card').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.className = 'absolute bg-white/30 rounded-full animate-ping pointer-events-none';
                ripple.style.width = ripple.style.height = '40px';
                
                const rect = this.getBoundingClientRect();
                ripple.style.left = e.clientX - rect.left - 20 + 'px';
                ripple.style.top = e.clientY - rect.top - 20 + 'px';
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // NFC waiting animation
        const nfcIcon = document.querySelector('.nfc-pulse i');
        if (nfcIcon) {
            anime({
                targets: nfcIcon,
                rotate: 360,
                duration: 2000,
                loop: true,
                easing: 'linear'
            });
        }
    </script>
</body>
</html>