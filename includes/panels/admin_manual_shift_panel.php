<?php
/**
 * Панель ручного керування змінами для адміністраторів та чергових.
 * Дозволяє вручну відкривати, закривати та (в майбутньому) редагувати зміни.
 */

// Перевірка ролі вже має бути виконана у файлі, що підключає цю панель (duty_officer_content.php)
// global $pdo, $APP_URL; // Ці змінні мають бути доступні з батьківського файлу

// --- Завантаження даних, необхідних для форм ---
$manual_panel_posts = [];
$manual_panel_lifeguards = [];
$manual_panel_active_shifts = [];
$manual_panel_error = '';

try {
    // 1. Отримуємо всі пости для випадаючого списку
    if (isset($pdo)) { // Перевіряємо, чи доступний $pdo
        $stmt_posts_manual = $pdo->query("SELECT id, name FROM posts ORDER BY name ASC");
        $manual_panel_posts = $stmt_posts_manual->fetchAll(PDO::FETCH_ASSOC);

        // 2. Отримуємо всіх користувачів з роллю 'lifeguard'
        $stmt_lifeguards_manual = $pdo->query("SELECT id, full_name FROM users WHERE role = 'lifeguard' ORDER BY full_name ASC");
        $manual_panel_lifeguards = $stmt_lifeguards_manual->fetchAll(PDO::FETCH_ASSOC);

        // 3. Отримуємо поточні активні зміни для можливості їх закриття
        $stmt_active_shifts_manual = $pdo->prepare("
            SELECT s.id, s.start_time, p.name as post_name, u.full_name as lifeguard_name
            FROM shifts s
            JOIN posts p ON s.post_id = p.id
            JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            ORDER BY s.start_time DESC
        ");
        $stmt_active_shifts_manual->execute();
        $manual_panel_active_shifts = $stmt_active_shifts_manual->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $manual_panel_error = "Помилка: Немає підключення до бази даних для панелі ручного керування.";
    }

} catch (PDOException $e) {
    $manual_panel_error = "Помилка завантаження даних для панелі ручного керування: " . escape($e->getMessage());
    error_log("Manual Shift Panel Data Error: " . $e->getMessage());
    // Не будемо встановлювати flash_message тут, щоб не перезаписувати можливі інші повідомлення
}

// Типи призначення рятувальника (L0, L1, L2)
$lifeguard_assignment_types_manual = [
    0 => 'L0 (Один, стандарт)',
    1 => 'L1 (Пара, помічник)',
    2 => 'L2 (Пара, старший)'
    // Можна додати більше описів, якщо потрібно
];

// Поточна дата та час для полів datetime-local
$current_datetime_for_input_manual = date('Y-m-d\TH:i');

// Перевіряємо, чи визначено APP_URL, якщо ні - встановлюємо заглушку для посилань у формі
$form_action_base_url = isset($APP_URL) ? rtrim($APP_URL, '/') : '.';

?>

<!-- Сітка постів (актуальні зміни) -->
<div class="manual-shift-operations-panel glass-effect p-4 sm:p-5 rounded-xl shadow-lg border border-white/20 space-y-4 mb-6">
    <button 
        type="button" 
        onclick="togglePostGridPanel(this)" 
        aria-expanded="false"
        aria-controls="postGridPanelContent"
        class="w-full flex justify-between items-center text-left p-3 bg-gray-100/70 hover:bg-gray-200/80 rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50 mb-2">
        <span class="font-semibold text-gray-700 text-md">
            <i class="fas fa-th-large mr-2 text-indigo-600"></i> Сітка Постів (актуальні зміни)
        </span>
        <i id="postGridToggleIcon" class="fas fa-chevron-down transition-transform duration-300 text-indigo-600"></i>
    </button>
    <div id="postGridPanelContent" class="hidden">
        <div id="post-grid-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
        <div id="post-grid-loading" class="text-center py-16 text-black hidden">
            <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
            <div>Завантаження...</div>
        </div>
        <div id="post-grid-nodata" class="text-center py-16 text-black hidden">
            <i class="fas fa-info-circle text-2xl mb-3"></i>
            <div>Немає даних для відображення.</div>
        </div>
    </div>
</div>

<script>
function togglePostGridPanel(btn) {
    const content = document.getElementById('postGridPanelContent');
    const icon = document.getElementById('postGridToggleIcon');
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    if (expanded) {
        content.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        content.classList.remove('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        btn.setAttribute('aria-expanded', 'true');
        // Підвантаження даних одразу при першому відкритті
        if (!window.postGridLoaded) {
            window.postGridLoaded = true;
            document.getElementById('post-grid-loading').classList.remove('hidden');
            fetch('admin/ajax_get_post_grid.php')
                .then(response => response.json())
                .then(function(postGridData) {
                    const container = document.getElementById('post-grid-container');
                    const loading = document.getElementById('post-grid-loading');
                    const nodata = document.getElementById('post-grid-nodata');
                    loading.classList.add('hidden');
                    if (!postGridData || Object.keys(postGridData).length === 0) {
                        nodata.classList.remove('hidden');
                        return;
                    }
                    container.innerHTML = '';
                    const sortedPostIds = Object.keys(postGridData).sort((a, b) => (postGridData[a]?.name?.toLowerCase() || '').localeCompare(postGridData[b]?.name?.toLowerCase() || '', 'uk'));
                    let postsGenerated = 0;
                    sortedPostIds.forEach(postId => {
                        const post = postGridData[postId];
                        if (post && typeof post === 'object' && post.name) {
                            postsGenerated++;
                            const postCard = document.createElement('div');
                            postCard.className = 'post-card bg-white rounded-lg shadow border border-gray-200/80 p-3 space-y-2 flex flex-col text-xs';
                            let cardContent = `<h4 class=\"font-bold text-gray-800 text-sm border-b border-gray-200 pb-1 mb-2 flex-shrink-0\">${escapeHtml(post.name)}</h4>`;
                            if (post.active_shifts && Array.isArray(post.active_shifts) && post.active_shifts.length > 0) {
                                cardContent += '<div class="active-shifts space-y-1.5 flex-grow">';
                                cardContent += '<h5 class="font-semibold text-green-700 uppercase tracking-wide text-[0.7rem] mb-1">Активні:</h5>';
                                post.active_shifts.forEach(shift => {
                                    cardContent += ` <div class=\"shift-item bg-green-50/70 p-1.5 rounded border border-green-200/50\"> <p class=\"font-semibold text-gray-900 truncate flex items-center\" title=\"${escapeHtml(shift.lifeguard_name)}\"> <i class=\"fas fa-user w-3 text-center mr-1.5 text-green-700\"></i> ${escapeHtml(shift.lifeguard_name)} </p> <p class=\"flex items-center mt-0.5\"> <i class=\"fas fa-clock w-3 text-center mr-1.5 text-gray-500\"></i> Початок: ${formatModalTime(shift.start_time)} </p> </div>`;
                                });
                                cardContent += '</div>';
                            }
                            if (post.completed_shifts && Array.isArray(post.completed_shifts) && post.completed_shifts.length > 0) {
                                cardContent += `<div class=\"completed-shifts space-y-1.5 ${post.active_shifts?.length > 0 ? 'mt-2 pt-2 border-t border-gray-100' : ''} flex-grow\">`;
                                cardContent += '<h5 class="font-semibold text-blue-700 uppercase tracking-wide text-[0.7rem] mb-1">Завершені:</h5>';
                                post.completed_shifts.forEach(shift => {
                                    cardContent += ` <div class=\"shift-item bg-blue-50/70 p-1.5 rounded border border-blue-200/50\"> <p class=\"font-semibold text-gray-900 truncate flex items-center\" title=\"${escapeHtml(shift.lifeguard_name)}\"> <i class=\"fas fa-user w-3 text-center mr-1.5 text-blue-700\"></i> ${escapeHtml(shift.lifeguard_name)} </p> <p class=\"flex items-center mt-0.5\"> <i class=\"fas fa-history w-3 text-center mr-1.5 text-gray-500\"></i> ${formatModalTime(shift.start_time)} - ${formatModalTime(shift.end_time)} </p> </div>`;
                                });
                                cardContent += '</div>';
                            }
                            const noActive = !post.active_shifts?.length; const noCompleted = !post.completed_shifts?.length;
                            if (noActive && noCompleted) { cardContent += '<div class="flex-grow flex items-center justify-center"><p class="text-center text-xs text-gray-400 italic pt-4">Змін немає</p></div>'; }
                            postCard.innerHTML = cardContent; container.appendChild(postCard);
                        }
                    });
                    if (postsGenerated === 0 && Object.keys(postGridData).length > 0) { nodata.classList.remove('hidden'); }
                })
                .catch(function() {
                    document.getElementById('post-grid-loading').classList.add('hidden');
                    document.getElementById('post-grid-nodata').classList.remove('hidden');
                });
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    // Автоматично не розгортати сітку постів
    // fetch запускається тільки при першому розгортанні
    let postGridLoaded = false;
    document.querySelector('[onclick^="togglePostGridPanel"]').addEventListener('click', function() {
        if (!postGridLoaded && this.getAttribute('aria-expanded') === 'false') {
            postGridLoaded = true;
            document.getElementById('post-grid-loading').classList.remove('hidden');
            fetch('admin/ajax_get_post_grid.php')
                .then(response => response.json())
                .then(function(postGridData) {
                    const container = document.getElementById('post-grid-container');
                    const loading = document.getElementById('post-grid-loading');
                    const nodata = document.getElementById('post-grid-nodata');
                    loading.classList.add('hidden');
                    if (!postGridData || Object.keys(postGridData).length === 0) {
                        nodata.classList.remove('hidden');
                        return;
                    }
                    container.innerHTML = '';
                    const sortedPostIds = Object.keys(postGridData).sort((a, b) => (postGridData[a]?.name?.toLowerCase() || '').localeCompare(postGridData[b]?.name?.toLowerCase() || '', 'uk'));
                    let postsGenerated = 0;
                    sortedPostIds.forEach(postId => {
                        const post = postGridData[postId];
                        if (post && typeof post === 'object' && post.name) {
                            postsGenerated++;
                            const postCard = document.createElement('div');
                            postCard.className = 'post-card bg-white rounded-lg shadow border border-gray-200/80 p-3 space-y-2 flex flex-col text-xs';
                            let cardContent = `<h4 class=\"font-bold text-gray-800 text-sm border-b border-gray-200 pb-1 mb-2 flex-shrink-0\">${escapeHtml(post.name)}</h4>`;
                            if (post.active_shifts && Array.isArray(post.active_shifts) && post.active_shifts.length > 0) {
                                cardContent += '<div class="active-shifts space-y-1.5 flex-grow">';
                                cardContent += '<h5 class="font-semibold text-green-700 uppercase tracking-wide text-[0.7rem] mb-1">Активні:</h5>';
                                post.active_shifts.forEach(shift => {
                                    cardContent += ` <div class=\"shift-item bg-green-50/70 p-1.5 rounded border border-green-200/50\"> <p class=\"font-semibold text-gray-900 truncate flex items-center\" title=\"${escapeHtml(shift.lifeguard_name)}\"> <i class=\"fas fa-user w-3 text-center mr-1.5 text-green-700\"></i> ${escapeHtml(shift.lifeguard_name)} </p> <p class=\"flex items-center mt-0.5\"> <i class=\"fas fa-clock w-3 text-center mr-1.5 text-gray-500\"></i> Початок: ${formatModalTime(shift.start_time)} </p> </div>`;
                                });
                                cardContent += '</div>';
                            }
                            if (post.completed_shifts && Array.isArray(post.completed_shifts) && post.completed_shifts.length > 0) {
                                cardContent += `<div class=\"completed-shifts space-y-1.5 ${post.active_shifts?.length > 0 ? 'mt-2 pt-2 border-t border-gray-100' : ''} flex-grow\">`;
                                cardContent += '<h5 class="font-semibold text-blue-700 uppercase tracking-wide text-[0.7rem] mb-1">Завершені:</h5>';
                                post.completed_shifts.forEach(shift => {
                                    cardContent += ` <div class=\"shift-item bg-blue-50/70 p-1.5 rounded border border-blue-200/50\"> <p class=\"font-semibold text-gray-900 truncate flex items-center\" title=\"${escapeHtml(shift.lifeguard_name)}\"> <i class=\"fas fa-user w-3 text-center mr-1.5 text-blue-700\"></i> ${escapeHtml(shift.lifeguard_name)} </p> <p class=\"flex items-center mt-0.5\"> <i class=\"fas fa-history w-3 text-center mr-1.5 text-gray-500\"></i> ${formatModalTime(shift.start_time)} - ${formatModalTime(shift.end_time)} </p> </div>`;
                                });
                                cardContent += '</div>';
                            }
                            const noActive = !post.active_shifts?.length; const noCompleted = !post.completed_shifts?.length;
                            if (noActive && noCompleted) { cardContent += '<div class="flex-grow flex items-center justify-center"><p class="text-center text-xs text-gray-400 italic pt-4">Змін немає</p></div>'; }
                            postCard.innerHTML = cardContent; container.appendChild(postCard);
                        }
                    });
                    if (postsGenerated === 0 && Object.keys(postGridData).length > 0) { nodata.classList.remove('hidden'); }
                })
                .catch(function() {
                    document.getElementById('post-grid-loading').classList.add('hidden');
                    document.getElementById('post-grid-nodata').classList.remove('hidden');
                });
        }
    });
});
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"]/g, function (s) {
        switch (s) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
        }
    });
}
function formatModalTime(timeString) {
    if (!timeString) return '-';
    try {
        const d = new Date(timeString);
        return d.toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' });
    } catch (e) { return timeString; }
}
</script>

<div class="manual-shift-operations-panel glass-effect p-4 sm:p-5 rounded-xl shadow-lg border border-white/20 space-y-4 mt-6">
    <button 
        type="button" 
        onclick="toggleManualShiftPanel(this)" 
        aria-expanded="false"
        aria-controls="manualShiftPanelContent"
        class="w-full flex justify-between items-center text-left p-3 bg-gray-100/70 hover:bg-gray-200/80 rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
        <span class="font-semibold text-gray-800 text-md">
            <i class="fas fa-cogs mr-2 text-indigo-600"></i> Ручне Керування Змінами
        </span>
        <i id="manualShiftToggleIcon" class="fas fa-chevron-down transition-transform duration-300 text-indigo-600"></i>
    </button>

    <div id="manualShiftPanelContent" class="hidden space-y-6 pt-4 border-t border-gray-200/60">
        
        <?php if ($manual_panel_error): ?>
            <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded-md text-sm">
                <?php echo $manual_panel_error; ?>
            </div>
        <?php endif; ?>

        <div class="manual-open-shift-section p-3 bg-white/50 rounded-lg border border-gray-200/50">
            <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b border-gray-200/70 pb-2">
                <i class="fas fa-play-circle mr-2 text-green-500"></i>Відкрити Зміну Вручну
            </h4>
            <form action="<?php echo $form_action_base_url; ?>/admin_manual_open_shift.php" method="POST" class="space-y-3 text-sm" onsubmit="return confirm('Ви впевнені, що хочете відкрити цю зміну вручну?');">
                <?php if(function_exists('csrf_input')) echo csrf_input(); ?>
                
                <div>
                    <label for="manual_open_post_id" class="block text-xs font-medium text-gray-700 mb-0.5">Пост *</label>
                    <select name="post_id" id="manual_open_post_id" required class="std-select w-full !text-sm">
                        <option value="">-- Оберіть пост --</option>
                        <?php foreach ($manual_panel_posts as $post_item): ?>
                            <option value="<?php echo $post_item['id']; ?>"><?php echo escape($post_item['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="manual_open_user_id" class="block text-xs font-medium text-gray-700 mb-0.5">Лайфгард *</label>
                    <select name="user_id" id="manual_open_user_id" required class="std-select w-full !text-sm">
                        <option value="">-- Оберіть лайфгарда --</option>
                        <?php foreach ($manual_panel_lifeguards as $lifeguard_item): ?>
                            <option value="<?php echo $lifeguard_item['id']; ?>"><?php echo escape($lifeguard_item['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="manual_open_assignment_type" class="block text-xs font-medium text-gray-700 mb-0.5">Тип зміни (L0/L1/L2) *</label>
                    <select name="assignment_type" id="manual_open_assignment_type" required class="std-select w-full !text-sm">
                        <option value="">-- Оберіть тип --</option>
                        <?php foreach ($lifeguard_assignment_types_manual as $key_type => $label_type): ?>
                            <option value="<?php echo $key_type; ?>"><?php echo escape($label_type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="manual_open_activity_type" class="block text-xs font-medium text-gray-700 mb-0.5">Тип активності *</label>
                    <select name="activity_type" id="manual_open_activity_type" required class="std-select w-full !text-sm">
                        <option value="shift">Зміна</option>
                        <option value="training">Тренування</option>
                    </select>
                </div>
                
                <div>
                    <label for="manual_open_start_time" class="block text-xs font-medium text-gray-700 mb-0.5">Час початку *</label>
                    <input type="datetime-local" name="start_time" id="manual_open_start_time" value="<?php echo $current_datetime_for_input_manual; ?>" required class="std-input w-full !text-sm">
                </div>
                
                <button type="submit" class="btn-green !text-sm !py-1.5 !px-3 w-full sm:w-auto">
                    <i class="fas fa-plus-circle mr-1"></i>Відкрити Зміну
                </button>
            </form>
        </div>

        <hr class="my-5 border-gray-300/50">

        <div class="manual-close-shift-section p-3 bg-white/50 rounded-lg border border-gray-200/50">
            <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b border-gray-200/70 pb-2">
                <i class="fas fa-stop-circle mr-2 text-red-500"></i>Закрити Активну Зміну Вручну
            </h4>
            <?php if (empty($manual_panel_active_shifts)): ?>
                <p class="text-xs text-gray-600 italic">Наразі немає активних змін для ручного закриття.</p>
            <?php else: ?>
                <form action="<?php echo $form_action_base_url; ?>/admin_manual_close_shift.php" method="POST" class="space-y-3 text-sm" onsubmit="return confirm('Ви впевнені, що хочете примусово закрити обрану зміну?');">
                    <?php if(function_exists('csrf_input')) echo csrf_input(); ?>
                    <div>
                        <label for="manual_close_shift_id" class="block text-xs font-medium text-gray-700 mb-0.5">Активна зміна *</label>
                        <select name="shift_id" id="manual_close_shift_id" required class="std-select w-full !text-sm">
                            <option value="">-- Оберіть активну зміну --</option>
                            <?php foreach ($manual_panel_active_shifts as $active_shift_item): ?>
                                <option value="<?php echo $active_shift_item['id']; ?>">
                                    ID: <?php echo $active_shift_item['id']; ?> 
                                    (<?php echo escape($active_shift_item['post_name']); ?> - <?php echo escape($active_shift_item['lifeguard_name']); ?>) 
                                    Початок: <?php echo function_exists('format_datetime') ? format_datetime($active_shift_item['start_time'], 'H:i d.m.y') : $active_shift_item['start_time']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="manual_close_end_time" class="block text-xs font-medium text-gray-700 mb-0.5">Час завершення *</label>
                        <input type="datetime-local" name="end_time" id="manual_close_end_time" value="<?php echo $current_datetime_for_input_manual; ?>" required class="std-input w-full !text-sm">
                    </div>
                    
                    <div>
                        <label for="manual_close_comment_input" class="block text-xs font-medium text-gray-700 mb-0.5">Коментар (причина закриття) *</label>
                        <textarea name="comment" id="manual_close_comment_input" rows="2" class="std-input w-full !text-sm" placeholder="Напр.: Лайфгард забув закрити зміну, технічна проблема." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn-red !text-sm !py-1.5 !px-3 w-full sm:w-auto">
                        <i class="fas fa-lock mr-1"></i>Закрити Зміну
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <hr class="my-5 border-gray-300/50">

        <div class="manage-shifts-section p-3 bg-white/50 rounded-lg border border-gray-200/50">
            <h4 class="text-sm font-semibold text-gray-800 mb-3 border-b border-gray-200/70 pb-2">
                <i class="fas fa-search-location mr-2 text-sky-500"></i>Знайти та Керувати Змінами
            </h4>
            <p class="text-xs text-gray-600 mb-3">
                Для розширеного пошуку, детального редагування або видалення конкретних змін (минулих або активних), будь ласка, перейдіть до спеціалізованого розділу керування змінами.
            </p>
            <a href="<?php echo $form_action_base_url; ?>/admin/manage_shifts.php" class="btn-secondary !text-sm !py-1.5 !px-3 w-full sm:w-auto">
                <i class="fas fa-tasks mr-1"></i> Перейти до Керування
            </a>
            <p class="text-xs text-gray-600 mt-1.5">(Рекомендується для виправлення помилок або перегляду архівних даних)</p>
        </div>

    </div>
</div>

<script>
if (typeof toggleManualShiftPanel !== 'function') {
    function toggleManualShiftPanel(buttonElement) {
        const content = document.getElementById('manualShiftPanelContent');
        const icon = buttonElement.querySelector('i.fa-chevron-down, i.fa-chevron-up'); // Шукаємо іконку всередині кнопки
        
        if (content && icon) {
            const isHidden = content.classList.contains('hidden');
            
            if (typeof anime === 'function') {
                if (isHidden) { // Панель прихована, розгортаємо
                    content.classList.remove('hidden');
                    anime({
                        targets: content,
                        height: [0, content.scrollHeight], // Анімація висоти
                        opacity: [0, 1],
                        translateY: [-10, 0], // Невеликий зсув для ефекту
                        duration: 350,
                        easing: 'easeOutCubic',
                        begin: function() {
                            buttonElement.setAttribute('aria-expanded', 'true');
                        }
                    });
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else { // Панель видима, згортаємо
                    anime({
                        targets: content,
                        height: 0, // Анімація висоти до 0
                        opacity: 0,
                        translateY: [0, -10],
                        duration: 300,
                        easing: 'easeInCubic',
                        complete: function() {
                            content.classList.add('hidden');
                            content.style.height = ''; // Скидаємо висоту для майбутніх розгортань
                            buttonElement.setAttribute('aria-expanded', 'false');
                        }
                    });
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            } else { // Fallback без Anime.js
                content.classList.toggle('hidden');
                if (content.classList.contains('hidden')) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                    buttonElement.setAttribute('aria-expanded', 'false');
                } else {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                    buttonElement.setAttribute('aria-expanded', 'true');
                }
            }
        }
    }
}

// Переконуємося, що панель згорнута за замовчуванням, якщо JS працює
document.addEventListener('DOMContentLoaded', () => {
    const contentPanel = document.getElementById('manualShiftPanelContent');
    const toggleButton = document.querySelector('button[onclick^="toggleManualShiftPanel"]');
    if (contentPanel && !contentPanel.classList.contains('hidden')) {
        contentPanel.classList.add('hidden'); // Примусово ховаємо, якщо JS увімкнено
        if(toggleButton) toggleButton.setAttribute('aria-expanded', 'false');
        const iconEl = document.getElementById('manualShiftToggleIcon');
        if (iconEl) {
            iconEl.classList.remove('fa-chevron-up');
            iconEl.classList.add('fa-chevron-down');
        }
    }
});
</script>

<?php
// Важливо! Стилі .std-select та .std-input мають бути доступні на сторінці,
// де підключається ця панель (наприклад, у includes/panels/duty_officer_content.php або в global.css)
// Приклад:
/*
<style>
    .std-select, .std-input {
        @apply shadow-sm block w-full border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm px-3 py-2;
    }
    .std-input {
        @apply py-1.5; 
    }
    .std-select.error, .std-input.error {
        @apply border-red-500 ring-1 ring-red-500;
    }
</style>
*/
?>