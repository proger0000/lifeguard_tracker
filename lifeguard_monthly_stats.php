<!-- lifeguard_monthly_stats.php - Обновленная версия -->
<?php
require_once 'config.php';
require_once 'includes/helpers.php';
require_role('lifeguard');
global $pdo;

$user_id = $_SESSION['user_id'];
$page_title = "Статистика за Місяць";

// Определяем текущий месяц и год
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Валидация
if ($current_month < 1 || $current_month > 12) {
    $current_month = (int)date('m');
}
if ($current_year < 2000 || $current_year > 2100) {
    $current_year = (int)date('Y');
}

// Определяем даты
$start_date = date('Y-m-d 00:00:00', mktime(0, 0, 0, $current_month, 1, $current_year));
$end_date = date('Y-m-d 23:59:59', mktime(0, 0, 0, $current_month + 1, 0, $current_year));

// Получаем базовую ставку
$stmt_rate = $pdo->prepare("SELECT base_hourly_rate FROM users WHERE id = :user_id");
$stmt_rate->execute([':user_id' => $user_id]);
$base_rate = $stmt_rate->fetch(PDO::FETCH_COLUMN) ?? 120.00;

// Получаем статистику
$monthly_stats = [
    'total_shifts' => 0,
    'total_hours' => 0,
    'total_points' => 0,
    'total_reports' => 0
];
$shifts_in_month = [];
$stats_error = '';

try {
    $stmt = $pdo->prepare("
        SELECT
            s.id, s.start_time, s.end_time, s.post_id,
            s.rounded_work_hours,
            s.lifeguard_assignment_type,
            p.name as post_name,
            (SELECT COUNT(sr.id) FROM shift_reports sr WHERE sr.shift_id = s.id) as reports_count,
            (SELECT SUM(lsp.points_awarded) FROM lifeguard_shift_points lsp WHERE lsp.shift_id = s.id) as shift_points
        FROM shifts s
        JOIN posts p ON s.post_id = p.id
        WHERE s.user_id = :user_id
          AND s.status = 'completed'
          AND s.end_time BETWEEN :start_date AND :end_date
        ORDER BY s.start_time DESC
    ");
    
    $stmt->execute([
        ':user_id' => $user_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    $shifts_in_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($shifts_in_month as $shift) {
        $monthly_stats['total_shifts']++;
        $monthly_stats['total_hours'] += $shift['rounded_work_hours'] ?? 0;
        $monthly_stats['total_points'] += $shift['shift_points'] ?? 0;
        if ($shift['reports_count'] > 0) {
            $monthly_stats['total_reports']++;
        }
    }

} catch (PDOException $e) {
    error_log("Monthly Stats Error: " . $e->getMessage());
    $stats_error = 'Помилка завантаження статистики';
}

// Рассчитываем зарплату
$salary = calculate_salary($monthly_stats['total_hours'], $base_rate);

// Месяцы
$months_ukrainian = [
    1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень',
    5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень',
    9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'
];

// Доступные годы
$available_years = [];
try {
    $stmt_years = $pdo->prepare("
        SELECT DISTINCT YEAR(start_time) as year 
        FROM shifts 
        WHERE user_id = :user_id 
        ORDER BY year DESC
    ");
    $stmt_years->execute([':user_id' => $user_id]);
    $available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    if (empty($available_years)) {
        $available_years = [$current_year];
    }
} catch (PDOException $e) {
    $available_years = [$current_year];
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
        
        .stat-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            padding: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 20px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 600;
            line-height: 1.2;
            color: #1a1a1a;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .custom-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        .animated-number {
            display: inline-block;
            transition: all 0.3s ease;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .stat-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 640px) {
            .stat-value {
                font-size: 2rem;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-container table {
            min-width: 640px;
        }

        .table-header {
            background: rgba(0, 0, 0, 0.02);
            font-weight: 600;
        }

        .table-row {
            transition: background-color 0.2s ease;
        }

        .table-row:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .mobile-shift-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 flex items-center animate-fade-in">
                <i class="fas fa-calendar-alt text-blue-600 mr-4"></i>
                <?php echo $page_title; ?>
            </h1>
        </div>

        <!-- Filter Form -->
        <form action="lifeguard_monthly_stats.php" method="GET" 
              class="glass-card rounded-2xl p-6 mb-8 animate-slide-up">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Місяць</label>
                    <select name="month" id="month" 
                            class="w-full px-4 py-3 rounded-xl border-0 bg-white/50 text-gray-700 custom-select focus:ring-2 focus:ring-purple-500 transition-all">
                        <?php foreach ($months_ukrainian as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $num === $current_month ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-2">Рік</label>
                    <select name="year" id="year" 
                            class="w-full px-4 py-3 rounded-xl border-0 bg-white/50 text-gray-700 custom-select focus:ring-2 focus:ring-purple-500 transition-all">
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == $current_year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" 
                            class="w-full px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-medium transition-all hover-scale flex items-center justify-center gap-2">
                        <i class="fas fa-filter"></i>
                        Показати
                    </button>
                </div>
            </div>
        </form>

        <!-- Stats Grid -->
        <div class="stat-grid mb-8">
            <!-- Hours Card -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-blue-50 text-blue-600">
                        <i class="fas fa-clock"></i>
                    </div>
                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded-full">Години</span>
                </div>
                <div class="text-3xl font-bold mb-1 animated-number" id="stat-hours">
                    0
                </div>
                <div class="text-sm text-gray-600">годин відпрацьовано</div>
            </div>

            <!-- Points Card -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-purple-50 text-purple-600">
                        <i class="fas fa-star"></i>
                    </div>
                    <span class="text-xs bg-purple-50 text-purple-600 px-2 py-1 rounded-full">Бали</span>
                </div>
                <div class="text-3xl font-bold mb-1 animated-number" id="stat-points">
                    0
                </div>
                <div class="text-sm text-gray-600">балів нараховано</div>
            </div>

            <!-- Gross Salary Card -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-green-50 text-green-600">
                        <i class="fas fa-coins"></i>
                    </div>
                    <span class="text-xs bg-green-50 text-green-600 px-2 py-1 rounded-full">Брутто</span>
                </div>
                <div class="text-3xl font-bold mb-1">
                    ₴<span class="animated-number" id="stat-gross">0</span>
                </div>
                <div class="text-sm text-gray-600">зарплата брутто</div>
            </div>

            <!-- Net Salary Card -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon bg-indigo-50 text-indigo-600">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded-full">Нетто</span>
                </div>
                <div class="text-3xl font-bold mb-1">
                    ₴<span class="animated-number" id="stat-net">0</span>
                </div>
                <div class="text-sm text-gray-600">зарплата нетто</div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="glass-card rounded-xl p-6 mb-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800 mb-1">₴<?php echo format_money($base_rate); ?></div>
                    <div class="text-sm text-gray-600">Ставка/год</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800 mb-1"><?php echo $monthly_stats['total_shifts']; ?></div>
                    <div class="text-sm text-gray-600">Змін</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800 mb-1"><?php echo $monthly_stats['total_reports']; ?></div>
                    <div class="text-sm text-gray-600">Звітів</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800 mb-1">23%</div>
                    <div class="text-sm text-gray-600">Податок</div>
                </div>
            </div>
        </div>

        <!-- Shifts Table -->
        <div class="glass-card rounded-xl overflow-hidden">
            <div class="bg-gray-50 p-6">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-list text-blue-600 mr-3"></i>
                    Деталізація за <?php echo $months_ukrainian[$current_month] . ' ' . $current_year; ?>
                </h3>
            </div>
            
            <?php if (empty($shifts_in_month) && !$stats_error): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Немає завершених змін за обраний період</p>
                </div>
            <?php elseif (!empty($shifts_in_month)): ?>
                <!-- Desktop view -->
                <div class="hidden md:block table-container">
                    <table class="w-full">
                        <thead class="table-header">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Дата</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Пост</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Години</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Бали</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Звіт</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($shifts_in_month as $shift): ?>
                            <tr class="table-row">
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-800">
                                    <?php echo escape($shift['post_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-mono text-gray-700">
                                    <?php echo $shift['rounded_work_hours']; ?>
                                </td>
                                <td class="px-6 py-4 text-center text-sm font-mono text-gray-700">
                                    <?php echo $shift['shift_points'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php
                                        $assignment_type = $shift['lifeguard_assignment_type'] ?? null;
                                        if ($assignment_type == 1) {
                                            echo '<span class="text-gray-400 italic">Звіт не потребується</span>';
                                        } elseif (($assignment_type === 0 || $assignment_type === '0' || $assignment_type === 2 || $assignment_type === '2') && $shift['reports_count'] > 0) {
                                            echo '<i class="fas fa-check-circle text-green-500 text-lg"></i>';
                                        } elseif (($assignment_type === 0 || $assignment_type === '0' || $assignment_type === 2 || $assignment_type === '2') && $shift['reports_count'] == 0) {
                                            echo '<a href="' . $base_url . '/submit_report.php?shift_id=' . $shift['id'] . '" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs bg-blue-500 hover:bg-blue-600 text-white font-medium transition-all"><i class="fas fa-file-alt"></i>Подати звіт</a>';
                                        } else {
                                            echo '<span class="text-gray-300">-</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile view -->
                <div class="md:hidden p-4 space-y-3">
                    <?php foreach ($shifts_in_month as $shift): ?>
                    <div class="mobile-shift-card">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-medium text-gray-800"><?php echo escape($shift['post_name']); ?></div>
                                <div class="text-sm text-gray-600"><?php echo format_datetime($shift['start_time'], 'd.m.Y'); ?></div>
                            </div>
                            <?php
                                $assignment_type = $shift['lifeguard_assignment_type'] ?? null;
                                if ($assignment_type == 1) {
                                    echo '<span class="text-gray-400 italic">Звіт не потребується</span>';
                                } elseif (($assignment_type === 0 || $assignment_type === '0' || $assignment_type === 2 || $assignment_type === '2') && $shift['reports_count'] > 0) {
                                    echo '<i class="fas fa-check-circle text-green-500"></i>';
                                } elseif (($assignment_type === 0 || $assignment_type === '0' || $assignment_type === 2 || $assignment_type === '2') && $shift['reports_count'] == 0) {
                                    echo '<a href="' . $base_url . '/submit_report.php?shift_id=' . $shift['id'] . '" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs bg-blue-500 hover:bg-blue-600 text-white font-medium transition-all"><i class="fas fa-file-alt"></i>Подати звіт</a>';
                                } else {
                                    echo '<span class="text-gray-300">-</span>';
                                }
                            ?>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Години: <strong><?php echo $shift['rounded_work_hours']; ?></strong></span>
                            <span class="text-gray-600">Бали: <strong><?php echo $shift['shift_points'] ?? 0; ?></strong></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back button -->
        <div class="mt-8 text-center">
            <a href="<?php echo $base_url; ?>/index.php" 
               class="inline-flex items-center gap-2 glass-card px-6 py-3 rounded-2xl text-gray-700 hover:bg-white/40 transition-all">
                <i class="fas fa-arrow-left"></i>
                На головну
            </a>
        </div>
    </div>

    <script>
        // Animate page elements
        anime({
            targets: '.animate-fade-in',
            opacity: [0, 1],
            translateY: [-20, 0],
            duration: 800,
            easing: 'easeOutExpo'
        });

        anime({
            targets: '.animate-slide-up',
            opacity: [0, 1],
            translateY: [30, 0],
            duration: 800,
            delay: 200,
            easing: 'easeOutExpo'
        });

        anime({
            targets: '.stat-card',
            opacity: [0, 1],
            translateY: [30, 0],
            delay: anime.stagger(100, {start: 300}),
            duration: 800,
            easing: 'easeOutExpo'
        });

        // Animate numbers
        const stats = {
            hours: <?php echo $monthly_stats['total_hours']; ?>,
            points: <?php echo $monthly_stats['total_points']; ?>,
            gross: <?php echo $salary['gross'] ?? 0; ?>,
            net: <?php echo $salary['net'] ?? 0; ?>
        };

        // Animate hours
        anime({
            targets: '#stat-hours',
            innerHTML: [0, stats.hours],
            round: 1,
            duration: 1500,
            delay: 600,
            easing: 'easeInOutExpo'
        });

        // Animate points
        anime({
            targets: '#stat-points',
            innerHTML: [0, stats.points],
            round: 1,
            duration: 1500,
            delay: 700,
            easing: 'easeInOutExpo'
        });

        // Animate gross salary
        anime({
            targets: '#stat-gross',
            innerHTML: [0, stats.gross],
            round: 1,
            duration: 1500,
            delay: 800,
            easing: 'easeInOutExpo'
        });

        // Animate net salary
        anime({
            targets: '#stat-net',
            innerHTML: [0, stats.net],
            round: 1,
            duration: 1500,
            delay: 900,
            easing: 'easeInOutExpo'
        });

        // Table rows animation
        anime({
            targets: 'tbody tr',
            opacity: [0, 1],
            translateX: [-20, 0],
            delay: anime.stagger(50, {start: 500}),
            duration: 600,
            easing: 'easeOutExpo'
        });
    </script>
</body>
</html>