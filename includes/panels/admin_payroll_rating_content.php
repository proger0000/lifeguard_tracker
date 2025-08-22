<?php
// includes/panels/admin_payroll_rating_content.php

require_once dirname(__DIR__) . '/../config.php';
require_once dirname(__DIR__) . '/helpers.php';

// Перевірка ролі (вже виконана в admin_panel.php)
global $pdo, $APP_URL;
$APP_URL = defined('APP_URL') ? APP_URL : '';

$lifeguards = [];
$error_message = '';
$current_month_rating = date('m');
$current_year_rating = date('Y');

try {
    // --- Логіка для вкладки "Рейтинг" ---
    $start_of_month_rating = date('Y-m-01 00:00:00', strtotime("$current_year_rating-$current_month_rating-01"));
    $start_of_next_month_rating = date('Y-m-01 00:00:00', strtotime("$current_year_rating-$current_month_rating-01 +1 month"));

    $stmt_rating = $pdo->prepare("
        SELECT 
            u.id, u.full_name, u.base_hourly_rate,
            COALESCE(SUM(lsp.points_awarded), 0) as total_awarded_points
        FROM users u
        LEFT JOIN lifeguard_shift_points lsp ON u.id = lsp.user_id AND lsp.award_datetime >= :start_of_month AND lsp.award_datetime < :start_of_next_month
        WHERE u.role = 'lifeguard'
        GROUP BY u.id, u.full_name, u.base_hourly_rate
        ORDER BY total_awarded_points DESC, u.full_name ASC
    ");
    $stmt_rating->execute([':start_of_month' => $start_of_month_rating, ':start_of_next_month' => $start_of_next_month_rating]);
    $lifeguards = $stmt_rating->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("DB error fetching payroll rating: " . $e->getMessage());
    $error_message = 'Помилка бази даних: Не вдалося завантажити дані рейтингу.';
}

// Дані для селекторів місяця та року
$months_ukrainian = [1 => 'Січень', 2 => 'Лютий', 3 => 'Березень', 4 => 'Квітень', 5 => 'Травень', 6 => 'Червень', 7 => 'Липень', 8 => 'Серпень', 9 => 'Вересень', 10 => 'Жовтень', 11 => 'Листопад', 12 => 'Грудень'];
$year_options = range(date('Y'), 2023);
?>

<div class="payroll-rating-container glass-effect p-4 sm:p-6 rounded-xl shadow-lg border border-white/20 space-y-6">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pb-4 border-b border-gray-200/50">
        <h3 class="text-xl font-semibold text-gray-800 flex items-center font-comfortaa">
            <i class="fas fa-star mr-3 text-indigo-500"></i>Рейтинг та Деталізація
        </h3>
        <div class="flex-grow flex items-center gap-x-1 sm:gap-x-2 border-b-2 border-transparent">
             <button id="tab-rating-btn" class="payroll-tab-btn active" onclick="showPayrollTab('rating')">Рейтинг</button>
             <button id="tab-detail-btn" class="payroll-tab-btn" onclick="showPayrollTab('detail')">Деталізація балів</button>
        </div>
    </div>

    <div id="tab-content-rating" class="payroll-tab-content">
        <?php if ($error_message): ?>
            <p class="text-red-500 text-center"><?php echo escape($error_message); ?></p>
        <?php elseif (empty($lifeguards)): ?>
            <div class="text-center py-10 px-4"><p class="text-gray-500 italic">Лайфгарди не знайдені або відсутні дані для рейтингу.</p></div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ПІБ Лайфгарда</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Бали за поточний місяць</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Базова Ставка (грн/год)</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white/70 divide-y divide-gray-200/50">
                        <?php $rank = 1; foreach ($lifeguards as $lifeguard): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors duration-150">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo escape($lifeguard['full_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold"><?php echo escape($lifeguard['total_awarded_points'] ?? 0); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex items-center gap-2">
                                    <span id="rate-display-<?php echo $lifeguard['id']; ?>"><?php echo escape(format_money($lifeguard['base_hourly_rate'])); ?></span>
                                    <input type="number" step="0.01" id="rate-input-<?php echo $lifeguard['id']; ?>" value="<?php echo escape($lifeguard['base_hourly_rate']); ?>" class="hidden w-24 border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <button type="button" onclick="openRateHistoryModal(<?php echo $lifeguard['id']; ?>, '<?php echo escape(addslashes($lifeguard['full_name'])); ?>')" class="text-blue-500 hover:text-blue-700 transition-colors" title="Історія зміни ставки"><i class="fas fa-history"></i></button>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <button type="button" id="edit-btn-<?php echo $lifeguard['id']; ?>" onclick="toggleRateEdit(<?php echo $lifeguard['id']; ?>)" class="text-indigo-600 hover:text-indigo-900">Редагувати</button>
                                <button type="button" id="save-btn-<?php echo $lifeguard['id']; ?>" onclick="saveRate(<?php echo $lifeguard['id']; ?>)" class="hidden ml-2 text-green-600 hover:text-green-900">Зберегти</button>
                                <button type="button" id="cancel-btn-<?php echo $lifeguard['id']; ?>" onclick="cancelRateEdit(<?php echo $lifeguard['id']; ?>, '<?php echo escape($lifeguard['base_hourly_rate']); ?>')" class="hidden ml-2 text-red-600 hover:text-red-900">Скасувати</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="tab-content-detail" class="payroll-tab-content hidden">
        <p class="text-sm text-gray-600 mb-4">Оберіть період для формування детального звіту по балах та натисніть "Експорт". Буде згенеровано Excel-файл з усіма лайфгардами та балами, нарахованими за кожним правилом.</p>
        <form action="<?php echo $APP_URL; ?>/admin/export_points_detail_excel.php" method="GET" target="_blank" class="bg-gray-50/50 p-4 rounded-lg border border-gray-200/50 flex flex-wrap items-end gap-4">
            <div>
                <label for="detail_month" class="block text-sm font-medium text-gray-700">Місяць</label>
                <select name="detail_month" id="detail_month" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                    <option value="0">Весь рік</option>
                    <?php foreach ($months_ukrainian as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo (date('n') == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="detail_year" class="block text-sm font-medium text-gray-700">Рік</label>
                <select name="detail_year" id="detail_year" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                    <?php foreach ($year_options as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo (date('Y') == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-green inline-flex items-center self-end">
                <i class="fas fa-file-excel mr-2"></i> Експорт в Excel
            </button>
        </form>
    </div>

</div>

<div id="rate-history-modal" class="hidden fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex justify-center items-center">
    <div id="rate-history-modal-content" class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg relative">
        <h3 id="rate-history-modal-title" class="text-lg font-bold mb-4">Історія зміни ставки</h3>
        <div id="rate-history-body" class="space-y-2 max-h-80 overflow-y-auto">
            </div>
        <button onclick="closeRateHistoryModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">&times;</button>
    </div>
</div>

<style>
.payroll-tab-btn {
    padding: 8px 16px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease-in-out;
}
.payroll-tab-btn.active {
    border-color: #4f46e5; /* indigo-600 */
    color: #4f46e5;
    font-weight: 600;
}
.payroll-tab-content.hidden {
    display: none;
}
</style>


<script>

function showPayrollTab(tabName) {
    document.querySelectorAll('.payroll-tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.payroll-tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(`tab-content-${tabName}`).classList.remove('hidden');
    document.getElementById(`tab-${tabName}-btn`).classList.add('active');
}
    // Перевіряємо, чи функції вже існують, щоб уникнути конфліктів
    if (typeof toggleRateEdit !== 'function') {
        function toggleRateEdit(userId) {
            document.getElementById(`rate-display-${userId}`).classList.toggle('hidden');
            document.getElementById(`rate-input-${userId}`).classList.toggle('hidden');
            document.getElementById(`edit-btn-${userId}`).classList.toggle('hidden');
            document.getElementById(`save-btn-${userId}`).classList.toggle('hidden');
            document.getElementById(`cancel-btn-${userId}`).classList.toggle('hidden');
        }

        function cancelRateEdit(userId, originalRate) {
            document.getElementById(`rate-input-${userId}`).value = originalRate;
            toggleRateEdit(userId);
        }

        function saveRate(userId) {
            const newRate = document.getElementById(`rate-input-${userId}`).value;
            const csrfToken = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';

            if (isNaN(parseFloat(newRate)) || !isFinite(newRate) || parseFloat(newRate) < 0) {
                alert('Будь ласка, введіть дійсне позитивне число для ставки.');
                return;
            }

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('new_rate', newRate);
            formData.append('csrf_token', csrfToken);

            fetch('admin/ajax_update_hourly_rate.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Ставка успішно оновлена!');
                    const formattedRate = parseFloat(newRate).toLocaleString('uk-UA', { style: 'currency', currency: 'UAH', minimumFractionDigits: 2 });
                    document.getElementById(`rate-display-${userId}`).textContent = formattedRate.replace('₴', '') + ' грн';
                    toggleRateEdit(userId);
                } else {
                    alert('Помилка оновлення ставки: ' + (data.error || 'Невідома помилка.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Сталася помилка при відправці запиту.');
            });
        }
    }

    // Функції для модального вікна історії
    const historyModal = document.getElementById('rate-history-modal');
    const historyModalContent = document.getElementById('rate-history-modal-content');
    const historyModalTitle = document.getElementById('rate-history-modal-title');
    const historyModalBody = document.getElementById('rate-history-body');

    function openRateHistoryModal(userId, userName) {
 if (!historyModal || !historyModalContent || !historyModalTitle || !historyModalBody) return;


 historyModalTitle.textContent = `Історія ставки: ${userName}`;
 historyModalBody.innerHTML = '<p class="text-center text-gray-500 dark:text-gray-400 italic py-4">Завантаження...</p>';
 historyModal.classList.remove('hidden');


 anime({
 targets: historyModal,
 opacity: [0, 1],
 duration: 300,
 easing: 'easeOutQuad'
 });
 anime({
 targets: historyModalContent,
 translateY: ['-20px', '0'],
 opacity: [0, 1],
 duration: 400,
 easing: 'easeOutCubic'
 });
        
        // Завантажуємо дані
        fetch(`admin/ajax_get_rate_history.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.history.length > 0) {
                    historyModalBody.innerHTML = '';
                    data.history.forEach(record => {
                        const date = new Date(record.changed_at).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                        const changedBy = record.changed_by_name ? ` (змінив: ${escapeHtml(record.changed_by_name)})` : '';
                        
                        const recordHtml = `
                            <div class="flex items-center justify-between p-3 bg-gray-50/50 dark:bg-gray-900/50 rounded-lg border border-gray-200/50 dark:border-gray-700/50">
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-mono text-gray-500 dark:text-gray-400">${date}</span>
                                    <div>
                                        <span class="text-sm text-red-500 line-through">${parseFloat(record.old_rate).toFixed(2)} грн</span>
                                        <i class="fas fa-long-arrow-alt-right text-gray-400 mx-2"></i>
                                        <span class="text-sm text-green-600 font-semibold">${parseFloat(record.new_rate).toFixed(2)} грн</span>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-gray-500">${changedBy}</span>
                            </div>
                        `;
                        historyModalBody.insertAdjacentHTML('beforeend', recordHtml);
                    });
                } else {
                    historyModalBody.innerHTML = '<p class="text-center text-gray-500 italic py-8">Історія змін відсутня.</p>';
                }
            })
            .catch(err => {
                console.error('History fetch error:', err);
                historyModalBody.innerHTML = '<p class="text-center text-red-500 italic py-8">Не вдалося завантажити історію.</p>';
            });
    }

    function closeRateHistoryModal() {
        if (!historyModal) return;
        
        anime({
            targets: historyModal,
            opacity: 0,
            duration: 300,
            easing: 'easeOutQuad',
            complete: () => {
                historyModal.classList.add('hidden');
            }
        });
        anime({
            targets: historyModalContent,
            translateY: '20px',
            opacity: 0,
            duration: 300,
            easing: 'easeInCubic',
            complete: () => {
                // Скидаємо стилі для наступного відкриття
                historyModalContent.style.transform = '';
                historyModalContent.style.opacity = '';
            }
        });
    }

    // Закриття модалки по кліку на оверлей
    historyModal?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeRateHistoryModal();
        }
    });

    // Універсальна функція для екранування HTML, якщо раптом знадобиться в інших місцях
    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

</script>