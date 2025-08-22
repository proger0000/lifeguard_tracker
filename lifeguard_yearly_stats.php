<!-- lifeguard_yearly_stats.php - Обновленная версия -->
<?php
require_once 'config.php';
require_role('lifeguard');
global $pdo;

$user_id = $_SESSION['user_id'];
$page_title = "Статистика за Рік";

// Визначення року
$current_year = date('Y');
$selected_year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 2020, 'max_range' => $current_year + 1]
]);
if (!$selected_year) {
    $selected_year = (int)$current_year;
}

// Ініціалізація статистики
$yearly_stats = [
    'total_shifts' => 0,
    'total_hours' => 0.0,
    'total_reports_submitted' => 0
];
$stats_error = '';

// Отримання даних
try {
    // Запит для змін
    $stmt_shifts = $pdo->prepare("
        SELECT
            COUNT(s.id) as shift_count,
            SUM(TIMESTAMPDIFF(SECOND, s.start_time, s.end_time)) as total_seconds_worked
        FROM shifts s
        WHERE s.user_id = :user_id
          AND s.status = 'completed'
          AND YEAR(s.end_time) = :year
          AND s.start_time IS NOT NULL
          AND s.end_time IS NOT NULL
    ");
    $stmt_shifts->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_shifts->bindParam(':year', $selected_year, PDO::PARAM_INT);
    $stmt_shifts->execute();
    $shift_summary = $stmt_shifts->fetch();

    if ($shift_summary) {
        $yearly_stats['total_shifts'] = (int)$shift_summary['shift_count'];
        $total_seconds = (int)($shift_summary['total_seconds_worked'] ?? 0);
        if ($total_seconds > 0) {
            $yearly_stats['total_hours'] = round($total_seconds / 3600, 1);
        }
    }

    // Запит для звітів
    $stmt_reports = $pdo->prepare("
        SELECT COUNT(sr.id) as reports_submitted_count
        FROM shift_reports sr
        JOIN shifts s ON sr.shift_id = s.id
        WHERE s.user_id = :user_id
          AND YEAR(s.end_time) = :year
          AND s.status = 'completed'
    ");
    $stmt_reports->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_reports->bindParam(':year', $selected_year, PDO::PARAM_INT);
    $stmt_reports->execute();
    $report_summary = $stmt_reports->fetch();

    if ($report_summary) {
        $yearly_stats['total_reports_submitted'] = (int)$report_summary['reports_submitted_count'];
    }

} catch (PDOException $e) {
    $stats_error = "Помилка завантаження статистики";
    error_log("Yearly Stats Error: " . $e->getMessage());
}

// Доступні роки
$available_years = [];
try {
    $stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(end_time) as year FROM shifts WHERE user_id = :user_id AND status = 'completed' ORDER BY year DESC");
    $stmt_years->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_years->execute();
    $available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    if (empty($available_years)) {
        $available_years = [$current_year];
    }
} catch(PDOException $e) {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
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
        
        @media (max-width: 768px) {
            .stat-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl font-semibold text-gray-800 flex items-center">
                <i class="fas fa-chart-line text-blue-600 mr-3"></i>
                <?php echo $page_title; ?>
            </h1>
        </div>

        <!-- Year Selector -->
        <form action="lifeguard_yearly_stats.php" method="GET" 
              class="glass-card rounded-xl p-6 mb-8">
            <div class="flex items-center gap-4">
                <label for="year" class="text-sm font-medium text-gray-600 whitespace-nowrap">Оберіть рік:</label>
                <select name="year" id="year" 
                        class="flex-1 px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo ($year == $selected_year) ? 'selected' : ''; ?>>
                            <?php echo $year; ?> рік
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" 
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-all flex items-center gap-2 whitespace-nowrap">
                    <i class="fas fa-sync"></i>
                    Оновити
                </button>
            </div>
        </form>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <!-- Total Shifts -->
            <div class="stat-card p-6">
                <div class="flex items-start justify-between">
                    <div class="stat-icon bg-blue-50 text-blue-600">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-value" id="stat-shifts">0</div>
                        <div class="stat-label">змін за рік</div>
                    </div>
                </div>
            </div>

            <!-- Total Hours -->
            <div class="stat-card p-6">
                <div class="flex items-start justify-between">
                    <div class="stat-icon bg-green-50 text-green-600">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-value" id="stat-hours">0</div>
                        <div class="stat-label">годин працював</div>
                    </div>
                </div>
            </div>

            <!-- Reports Submitted -->
            <div class="stat-card p-6">
                <div class="flex items-start justify-between">
                    <div class="stat-icon bg-purple-50 text-purple-600">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="text-right">
                        <div class="stat-value" id="stat-reports">0</div>
                        <div class="stat-label">звітів подано</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($yearly_stats['total_shifts'] == 0 && !$stats_error): ?>
            <div class="glass-card rounded-xl p-12 text-center">
                <i class="fas fa-info-circle text-5xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">За <?php echo $selected_year; ?> рік немає даних про завершені зміни</p>
            </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?php echo $base_url; ?>/lifeguard_monthly_stats.php" 
               class="glass-card px-6 py-3 rounded-lg text-center text-gray-700 hover:bg-white/80 transition-all inline-flex items-center justify-center gap-2">
                <i class="fas fa-calendar-alt"></i>
                Місячна статистика
            </a>
            <a href="<?php echo $base_url; ?>/index.php" 
               class="glass-card px-6 py-3 rounded-lg text-center text-gray-700 hover:bg-white/80 transition-all inline-flex items-center justify-center gap-2">
                <i class="fas fa-home"></i>
                На головну
            </a>
        </div>
    </div>

    <script>
        // Stats data
        const statsData = {
            shifts: <?php echo $yearly_stats['total_shifts']; ?>,
            hours: <?php echo $yearly_stats['total_hours']; ?>,
            reports: <?php echo $yearly_stats['total_reports_submitted']; ?>
        };

        // Animate numbers
        Object.keys(statsData).forEach((key, index) => {
            anime({
                targets: `#stat-${key}`,
                innerHTML: [0, statsData[key]],
                round: key === 'hours' ? 10 : 1,
                duration: 1500,
                delay: 300 + (index * 100),
                easing: 'easeInOutExpo',
                update: function(anim) {
                    if (key === 'hours') {
                        const element = document.querySelector(`#stat-${key}`);
                        const value = parseFloat(element.textContent);
                        element.textContent = value.toFixed(1);
                    }
                }
            });
        });
    </script>
</body>
</html>