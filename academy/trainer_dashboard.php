<?php
// /academy/trainer_dashboard.php
require_once '../config.php'; // Шлях до config.php (на рівень вище)
require_role('trainer');    // Доступ тільки для тренерів
global $pdo;

$trainer_id = $_SESSION['user_id'];
$page_title = "Панель Тренера";
$groups = [];
$fetch_error = '';

try {
    // Знаходимо групу(и), які веде цей тренер
    $stmt = $pdo->prepare("SELECT id, name FROM academy_groups WHERE trainer_user_id = :trainer_id ORDER BY name");
    $stmt->bindParam(':trainer_id', $trainer_id, PDO::PARAM_INT);
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fetch_error = "Помилка завантаження даних групи.";
    error_log("Trainer Dashboard Fetch Error: TrainerID {$trainer_id} | Error: " . $e->getMessage());
    set_flash_message('помилка', $fetch_error);
}

// Підключаємо хедер
require_once '../includes/header.php'; // Шлях до хедера
?>

<section id="trainer-dashboard" class="space-y-6">

    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
         <i class="fas fa-chalkboard-teacher text-indigo-500 mr-3"></i>
         <?php echo $page_title; ?>
    </h2>

    <?php display_flash_message(); ?>

    <?php if ($fetch_error): ?>
         <p class="text-red-500"><?php echo $fetch_error; ?></p>
    <?php elseif (empty($groups)): ?>
        <div class="glass-effect p-6 rounded-xl shadow-lg border border-white/20 text-center">
            <p class="text-orange-600 font-semibold"><i class="fas fa-info-circle mr-2"></i> Вас ще не призначено тренером жодної групи.</p>
            <p class="mt-2 text-sm text-gray-600">Зверніться до адміністратора системи.</p>
        </div>
    <?php else: ?>
        <p class="text-gray-700 mb-4">Вітаємо, <?php echo escape($_SESSION['full_name']); ?>! Ви є тренером наступних груп:</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($groups as $group): ?>
                <div class="group-card glass-effect p-5 rounded-xl shadow-lg border border-white/20">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                        <?php echo escape($group['name']); ?>
                    </h3>
                    <div class="space-y-3">
                        <a href="mark_attendance.php?group_id=<?php echo $group['id']; ?>" class="block text-center sm:text-left btn-secondary w-full transform hover:scale-[1.02] transition">
                             <i class="fas fa-user-check w-5 mr-2"></i> Відмітити Відвідуваність
                        </a>
                        <a href="mark_tests.php?group_id=<?php echo $group['id']; ?>" class="block text-center sm:text-left btn-secondary w-full ">
                            <i class="fas fa-clipboard-list w-5 mr-2"></i> Внести Бали за Тести
                        </a>
                        <a href="mark_standards.php?group_id=<?php echo $group['id']; ?>" class="block text-center sm:text-left btn-secondary w-full transform hover:scale-[1.02] transition" title="Внести результати нормативів для цієї групи">
                             <i class="fas fa-award w-5 mr-2 text-rose-500"></i> Зафіксувати Нормативи
                        </a>
                         <a href="view_group.php?group_id=<?php echo $group['id']; ?>" class="block text-center sm:text-left btn-secondary w-full">
                             <i class="fas fa-chart-line w-5 mr-2"></i> Переглянути Прогрес Групи
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</section>

<?php
// Підключаємо футер
require_once '../includes/footer.php'; // Шлях до футера
?>