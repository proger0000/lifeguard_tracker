<?php
// Файл: includes/director_panel.php
// КОМЕНТАР: Виправлена версія з коректною структурою вкладок.

require_role('director');
?>
<section id="director-section" class="space-y-4 md:space-y-6">
    <div class="px-4 py-3 sm:px-6 panel-header-gradient text-white rounded-xl shadow-lg flex items-center justify-between">
        <h2 class="text-lg sm:text-xl leading-6 font-semibold flex items-center font-comfortaa">
            <i class="fas fa-user-tie mr-2 text-xl"></i> Панель Директора
        </h2>
    </div>

    <div class="border-b border-gray-200/80">
        <nav aria-label="Панель директора">
            <ul class="-mb-px flex flex-wrap space-x-1 sm:space-x-2" id="directorTab" role="tablist">
                <li role="presentation">
                    <button class="director-tab-button px-4 py-2 text-sm font-medium rounded-t-lg focus:outline-none" id="director-posts-tab" type="button" onclick="showDirectorTab('posts')" aria-selected="true">Сітка Постів</button>
                </li>
                <li role="presentation">
                    <button class="director-tab-button px-4 py-2 text-sm font-medium rounded-t-lg focus:outline-none" id="director-analytics-tab" type="button" onclick="showDirectorTab('analytics')" aria-selected="false">Комплексна аналітика</button>
                </li>
                <li role="presentation">
                    <button class="director-tab-button px-4 py-2 text-sm font-medium rounded-t-lg focus:outline-none" id="director-rating-tab" type="button" onclick="showDirectorTab('rating')" aria-selected="false">Рейтинг</button>
                </li>
            </ul>
        </nav>
    </div>

    <div id="directorTabContent">
    <div class="director-tab-content block" id="director-posts-content" role="tabpanel">
        <?php require __DIR__ . '/panels/director_post_grid.php'; ?>
    </div>
    <div class="director-tab-content hidden" id="director-analytics-content" role="tabpanel">
        <?php // ✅ ОСЬ ТУТ ПРАВИЛЬНЕ ПІДКЛЮЧЕННЯ
            require __DIR__ . '/panels/admin_posts_analytics_content.php'; 
        ?>
    </div>
    <div class="director-tab-content hidden" id="director-rating-content" role="tabpanel">
        <?php require __DIR__ . '/panels/admin_payroll_rating_content.php'; ?>
    </div>
</div>
</section>
<style>
/* Стили для вкладок директора */
.director-tab-button {
    border-bottom: 2px solid transparent;
    color: #6b7280;
    transition: all 0.2s ease;
}

.director-tab-button[aria-selected="true"] {
    border-bottom: 2px solid #2563eb;
    color: #2563eb;
    font-weight: 600;
}

.director-tab-button:hover:not([aria-selected="true"]) {
    color: #374151;
    border-bottom-color: #d1d5db;
}

.director-tab-content {
    display: none;
}

.director-tab-content.block {
    display: block;
}

.panel-header-gradient {
    background: linear-gradient(90deg, #dc2626 0%, #f97316 100%);
}
</style>
