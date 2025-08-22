<?php
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['director', 'admin'])) { die('Доступ заборонено.'); }
?>
<div class="posts-grid-page">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 border-b border-gray-200/50">
        <h3 class="text-xl font-semibold text-gray-800 mb-3 sm:mb-0 flex items-center font-comfortaa">
            <i class="fas fa-th-large mr-3 text-indigo-500"></i>Сітка Постів (актуальні зміни)
        </h3>
    </div>
    <div id="post-grid-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
    <div id="post-grid-loading" class="text-center py-16 text-gray-500 hidden">
        <i class="fas fa-spinner fa-spin text-2xl mb-3"></i>
        <div>Завантаження...</div>
    </div>
    <div id="post-grid-nodata" class="text-center py-16 text-gray-500 hidden">
        <i class="fas fa-info-circle text-2xl mb-3"></i>
        <div>Немає даних для відображення.</div>
    </div>
</div>
<script>
// --- Динамическая загрузка и отрисовка сетки постів для директора ---
document.addEventListener('DOMContentLoaded', function() {
    // Получаем данные через AJAX (или можно внедрить через PHP, если нужно)
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
                        cardContent += `<div class="completed-shifts space-y-1.5 ${post.active_shifts?.length > 0 ? 'mt-2 pt-2 border-t border-gray-100' : ''} flex-grow">`;
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
    document.getElementById('post-grid-loading').classList.remove('hidden');
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