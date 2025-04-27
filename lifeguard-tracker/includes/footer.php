<?php /* Файл: includes/footer.php */ ?>
            </main> <!-- Кінець тегу <main> -->

            <!-- Футер сторінки -->
            <footer class="bg-gray-200 text-gray-700 text-center p-4 mt-auto">
                <div class="container mx-auto text-sm">
                    © <?php echo date('Y'); ?> Система обліку чергувань. Всі права захищено.
                </div>
            </footer>

            <!-- HTML елемент для Toast сповіщення -->
            <div id="toast-notification" class="toast fixed bottom-5 right-5 bg-gray-800 text-white py-2 px-4 rounded-md shadow-lg text-sm z-50 opacity-0 transition-opacity duration-300">
                URL скопійовано до буферу обміну!
            </div>

              <!-- === БЛОК ПЕРЕДАЧІ ДАНИХ PHP У JS (МАЄ БУТИ ОДИН!) === -->
            <script>
                 // Оголошуємо глобальні константи (саме const!)
                 const reportsData = <?php echo isset($reports_on_date) ? json_encode($reports_on_date, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]'; ?>;
                 const incidentsData = <?php echo isset($incidents_by_report) ? json_encode($incidents_by_report, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}'; ?>;
                 const lifeguards_list_js = <?php echo isset($lifeguards_list) ? json_encode($lifeguards_list, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}'; ?>;
            </script>

            <!-- === ПІДКЛЮЧЕННЯ ФАЙЛІВ JS (КОЖЕН ПО ОДНОМУ РАЗУ!) === -->
            <script src="/lifeguard-tracker/js/translations.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/lifeguard-tracker/js/translations.js'); ?>" defer></script>
            <script src="/lifeguard-tracker/js/app.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/lifeguard-tracker/js/app.js'); ?>" defer></script>

         </body>
     </html>