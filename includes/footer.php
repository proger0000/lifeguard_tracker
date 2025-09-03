<?php /* Файл: includes/footer.php */ ?>
<footer class="fixed-footer">
    <div class="container mx-auto text-xs text-center text-white bg-black bg-opacity-70 backdrop-blur-sm rounded-t-lg px-3 py-1">
        © <?php echo date('Y'); ?> Система обліку чергувань KLS. Всі права захищено.
    </div>
</footer>

<div id="toast-notification" class="toast fixed bottom-5 right-5 bg-gray-800 text-white py-2 px-4 rounded-md shadow-lg text-sm z-50 opacity-0 transition-opacity duration-300 pointer-events-none">
    URL скопійовано до буферу обміну!
</div>

<script>
    // JS-змінні та логіка...
</script>

<script src="<?php echo rtrim(APP_URL, '/'); ?>/js/admin_applications.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
