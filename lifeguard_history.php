<!-- lifeguard_history.php - Обновленная версия -->
<?php
require_once 'config.php';
require_role('lifeguard');
global $pdo;

$user_id = $_SESSION['user_id'];
$page_title = "Історія Змін";

// Ініціалізація
$all_shifts = [];
$history_error = '';

// Отримання історії змін
try {
    $stmt_history = $pdo->prepare("
        SELECT
            s.id, 
            s.start_time, 
            s.end_time, 
            s.status, 
            s.post_id,
            p.name as post_name,
            s.photo_close_path,
            s.lifeguard_assignment_type,
            TIMESTAMPDIFF(SECOND, s.start_time, s.end_time) as duration_seconds,
            (SELECT COUNT(sr.id) FROM shift_reports sr WHERE sr.shift_id = s.id) as reports_count
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.user_id = :user_id AND s.status IN ('completed', 'cancelled')
        ORDER BY s.start_time DESC
    ");
    $stmt_history->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_history->execute();
    $all_shifts = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $history_error = 'Не вдалося завантажити історію змін.';
    error_log("Lifeguard History Error: " . $e->getMessage());
}

// Функції форматування
if (!function_exists('format_datetime')) {
    function format_datetime($datetime_string, $format = 'd.m.Y H:i') {
        if (empty($datetime_string)) return '-';
        try { 
            $date = new DateTime($datetime_string); 
            return $date->format($format); 
        } catch (Exception $e) { 
            return 'Нев.'; 
        }
    }
}

if (!function_exists('format_duration_from_seconds')) {
    function format_duration_from_seconds($total_seconds) {
        if ($total_seconds === null || $total_seconds < 0) return '-';
        if ($total_seconds == 0) return '0 хв';
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        $parts = [];
        if ($hours > 0) $parts[] = $hours . ' год';
        if ($minutes > 0) $parts[] = $minutes . ' хв';
        if (empty($parts) && $total_seconds > 0) return round($total_seconds) . ' сек';
        return !empty($parts) ? implode(' ', $parts) : '0 хв';
    }
}

require_once 'includes/header.php';
$base_url = rtrim(APP_URL, '/');
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.08);
        }
        .status-badge {
            animation: none;
        }
        .mobile-card {
            display: none;
        }
        @media (max-width: 768px) {
            .mobile-card { display: block; }
            .desktop-table { display: none; }
        }
        @media (min-width: 769px) {
            .mobile-card { display: none; }
            .desktop-table { display: table; }
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8 animate-fade-in">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-history text-blue-600 mr-4"></i>
                    <?php echo escape($page_title); ?>
                </h1>
                <a href="<?php echo $base_url; ?>/index.php" 
                   class="glass-card px-6 py-3 rounded-2xl text-gray-700 hover:bg-white/40 hover-lift inline-flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i>
                    На головну
                </a>
            </div>
        </div>

        <?php if ($history_error): ?>
            <div class="glass-card p-6 rounded-2xl mb-6 border-l-4 border-red-500">
                <p class="text-white flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-red-300"></i>
                    <?php echo escape($history_error); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Desktop Table View -->
        <div class="desktop-table glass-card rounded-3xl overflow-hidden">
            <?php if (empty($all_shifts) && !$history_error): ?>
                <div class="p-16 text-center">
                    <i class="fas fa-inbox text-6xl text-white/30 mb-4"></i>
                    <p class="text-white/70 text-lg">Історія змін порожня</p>
                </div>
            <?php elseif (!empty($all_shifts)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="glass-dark">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Дата</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Пост</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Час</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Тривалість</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Статус</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Дії</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($all_shifts as $shift): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    <?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo escape($shift['post_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-clock text-gray-400"></i>
                                        <?php echo format_datetime($shift['start_time'], 'H:i'); ?> - 
                                        <?php echo format_datetime($shift['end_time'], 'H:i'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-mono">
                                    <?php echo format_duration_from_seconds($shift['duration_seconds'] ?? null); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php
                                        $status_text = '';
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($shift['status'] ?? 'unknown') {
                                            case 'completed':
                                                $status_text = 'Завершено';
                                                $status_class = 'bg-green-100 text-green-700 border-green-200';
                                                $status_icon = 'fa-check-circle';
                                                break;
                                            case 'cancelled':
                                                $status_text = 'Скасовано';
                                                $status_class = 'bg-yellow-100 text-yellow-700 border-yellow-200';
                                                $status_icon = 'fa-times-circle';
                                                break;
                                            default:
                                                $status_text = 'Невідомо';
                                                $status_class = 'bg-gray-100 text-gray-500 border-gray-200';
                                                $status_icon = 'fa-question-circle';
                                        }
                                    ?>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border <?php echo $status_class; ?> status-badge">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <?php if ($shift['status'] === 'completed'): ?>
                                            <?php
                                                $end_photo_loaded = !empty($shift['photo_close_path']);
                                                $assignment_type = $shift['lifeguard_assignment_type'] ?? null;
                                                $report_needed = ($assignment_type === 0 || $assignment_type === 2);
                                                $report_submitted = ($shift['reports_count'] > 0);
                                            ?>

                                            <?php if ($end_photo_loaded): ?>
                                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs bg-green-100 text-green-700">
                                                    <i class="fas fa-camera"></i>
                                                    <i class="fas fa-check"></i>
                                                </span>
                                            <?php else: ?>
                                                <a href="<?php echo $base_url; ?>/upload_end_photo.php?shift_id=<?php echo $shift['id']; ?>"
                                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition-colors">
                                                    <i class="fas fa-camera"></i>
                                                    <i class="fas fa-upload"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($report_needed): ?>
                                                <?php if ($report_submitted): ?>
                                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                                                        <i class="fas fa-file-alt"></i>
                                                        <i class="fas fa-check"></i>
                                                    </span>
                                                <?php elseif ($end_photo_loaded): ?>
                                                    <a href="<?php echo $base_url; ?>/submit_report.php?shift_id=<?php echo $shift['id']; ?>"
                                                       class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs bg-orange-100 text-orange-700 hover:bg-orange-200 transition-colors">
                                                        <i class="fas fa-file-signature"></i>
                                                        <i class="fas fa-arrow-right"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-300">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Card View -->
        <div class="mobile-card space-y-4">
            <?php if (empty($all_shifts) && !$history_error): ?>
                <div class="glass-card rounded-2xl p-16 text-center">
                    <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Історія змін порожня</p>
                </div>
            <?php elseif (!empty($all_shifts)): ?>
                <?php foreach ($all_shifts as $index => $shift): ?>
                    <div class="glass-card rounded-2xl p-5 hover-lift shift-card" data-index="<?php echo $index; ?>">
                        <!-- Card Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <?php echo escape($shift['post_name']); ?>
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?>
                                </p>
                            </div>
                            <?php
                                $status_text = '';
                                $status_class = '';
                                $status_icon = '';
                                switch ($shift['status'] ?? 'unknown') {
                                    case 'completed':
                                        $status_text = 'Завершено';
                                        $status_class = 'bg-green-100 text-green-700 border-green-200';
                                        $status_icon = 'fa-check-circle';
                                        break;
                                    case 'cancelled':
                                        $status_text = 'Скасовано';
                                        $status_class = 'bg-yellow-100 text-yellow-700 border-yellow-200';
                                        $status_icon = 'fa-times-circle';
                                        break;
                                    default:
                                        $status_text = 'Невідомо';
                                        $status_class = 'bg-gray-100 text-gray-500 border-gray-200';
                                        $status_icon = 'fa-question-circle';
                                }
                            ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                <i class="fas <?php echo $status_icon; ?>"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <!-- Card Details -->
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center justify-between text-gray-700">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-clock text-gray-400"></i>
                                    Час
                                </span>
                                <span class="font-mono">
                                    <?php echo format_datetime($shift['start_time'], 'H:i'); ?> - 
                                    <?php echo format_datetime($shift['end_time'], 'H:i'); ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-gray-700">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-hourglass-half text-gray-400"></i>
                                    Тривалість
                                </span>
                                <span class="font-mono">
                                    <?php echo format_duration_from_seconds($shift['duration_seconds'] ?? null); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <?php if ($shift['status'] === 'completed'): ?>
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <div class="flex flex-wrap gap-2">
                                    <?php
                                        $end_photo_loaded = !empty($shift['photo_close_path']);
                                        $assignment_type = $shift['lifeguard_assignment_type'] ?? null;
                                        $report_needed = ($assignment_type === 0 || $assignment_type === 2);
                                        $report_submitted = ($shift['reports_count'] > 0);
                                    ?>

                                    <?php if ($end_photo_loaded): ?>
                                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs bg-green-100 text-green-700 font-medium">
                                            <i class="fas fa-camera"></i>
                                            Фото завантажено
                                        </span>
                                    <?php else: ?>
                                        <a href="<?php echo $base_url; ?>/upload_end_photo.php?shift_id=<?php echo $shift['id']; ?>"
                                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs bg-yellow-100 text-yellow-700 hover:bg-yellow-200 transition-colors font-medium">
                                            <i class="fas fa-camera"></i>
                                            Завантажити фото
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($report_needed): ?>
                                        <?php if ($report_submitted): ?>
                                            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs bg-blue-100 text-blue-700 font-medium">
                                                <i class="fas fa-file-alt"></i>
                                                Звіт подано
                                            </span>
                                        <?php elseif ($end_photo_loaded): ?>
                                            <a href="<?php echo $base_url; ?>/submit_report.php?shift_id=<?php echo $shift['id']; ?>"
                                               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs bg-orange-100 text-orange-700 hover:bg-orange-200 transition-colors font-medium">
                                                <i class="fas fa-file-signature"></i>
                                                Подати звіт
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Animate page load
        anime({
            targets: '.animate-fade-in',
            opacity: [0, 1],
            translateY: [-20, 0],
            duration: 800,
            easing: 'easeOutExpo'
        });

        // Animate table rows
        anime({
            targets: '.desktop-table tbody tr',
            opacity: [0, 1],
            translateX: [-20, 0],
            delay: anime.stagger(50),
            duration: 600,
            easing: 'easeOutExpo'
        });

        // Animate mobile cards
        anime({
            targets: '.shift-card',
            opacity: [0, 1],
            translateY: [30, 0],
            delay: anime.stagger(100),
            duration: 800,
            easing: 'easeOutExpo'
        });

        // Add ripple effect on click
        document.querySelectorAll('.hover-lift').forEach(element => {
            element.addEventListener('click', function(e) {
                const ripple = document.createElement('div');
                ripple.className = 'absolute bg-white/30 rounded-full animate-ping';
                ripple.style.width = ripple.style.height = '20px';
                ripple.style.left = e.clientX - this.offsetLeft - 10 + 'px';
                ripple.style.top = e.clientY - this.offsetTop - 10 + 'px';
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });
    </script>
</body>
</html>