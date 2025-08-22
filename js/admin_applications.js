// js/admin_applications.js
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    let selectedApplicationId = null;
    let selectedApplicationRow = null;

    // Елементи модального вікна редагування
    const editModal = document.getElementById('applicationEditModal');
    const editModalContent = document.getElementById('applicationEditModalContent');
    const editModalTitle = document.getElementById('editModalTitle');
    const editForm = document.getElementById('editApplicationForm');
    const editAppIdInput = document.getElementById('editAppId');
    const editStatusSelect = document.getElementById('editStatus');
    const editManagerNameInput = document.getElementById('editManagerName'); // Тепер readonly
    const editManagerNoteTextarea = document.getElementById('editManagerNote');
    const managerNameError = document.getElementById('managerNameError'); // Поле для помилки імені

    // Елементи модального вікна історії
    const historyModal = document.getElementById('applicationHistoryModal');
    const historyModalContent = document.getElementById('applicationHistoryModalContent');
    const historyModalTitle = document.getElementById('historyModalTitle');
    const historyModalBody = document.getElementById('historyModalBody');

    // Кнопка "Редагувати Виділене"
    const editSelectedBtn = document.getElementById('editSelectedApplicationBtn');

    // Таблиця та її тіло
    const applicationTbody = document.querySelector('.application-tbody');

    // Функція екранування HTML (якщо потрібна, краще мати глобальну)
    function escapeHtmlLocal(str) {
        if (str === null || typeof str === 'undefined') return '';
        const stringValue = String(str);
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        const regex = /[&<>"']/g;
        return stringValue.replace(regex, (match) => map[match]);
    }


    // --- Функції Модального Вікна Редагування ---

    window.openApplicationEditModal = function(triggerElement) {
        if (!editModal || !editForm) {
            console.error("Edit modal elements not found.");
            return;
        }

        let row = null;
        if (triggerElement && typeof triggerElement.closest === 'function') {
            // Якщо викликано з кнопки всередині рядка
            row = triggerElement.closest('.application-row');
        } else if (selectedApplicationRow) {
            // Якщо викликано кнопкою "Редагувати Виділене"
            row = selectedApplicationRow;
        }

        if (!row) {
            alert('Будь ласка, спочатку виділіть заявку у таблиці, клікнувши на неї.');
            return;
        }

        highlightRow(row); // Виділяємо рядок (або повторно виділяємо)

        // Отримуємо дані з data-атрибутів рядка
        const appId = row.dataset.id;
        const currentStatus = row.dataset.status || 'Новий';
        const currentManagerNote = row.dataset.managerNote || ''; // Останній коментар
        const applicantName = row.querySelector('td[data-label="ПІБ:"]')?.textContent.trim() || `ID: ${appId}`;

        // Заповнюємо форму
        editAppIdInput.value = appId;
        editModalTitle.textContent = `Заявка: ${applicantName}`;
        editStatusSelect.value = currentStatus;
        // Ім'я менеджера вже заповнене з PHP сесії і є readonly
        editManagerNoteTextarea.value = ''; // Очищуємо поле нового коментаря
        managerNameError?.classList.add('hidden'); // Ховаємо помилку

        // Відкриваємо модалку з анімацією
        editModal.classList.remove('hidden');
        requestAnimationFrame(() => { // Даємо браузеру час на рендер display:flex
            editModalContent.classList.remove('scale-95', 'opacity-0');
            editModalContent.classList.add('scale-100', 'opacity-100');
        });
        editStatusSelect.focus();
    }

    window.closeApplicationEditModal = function() {
        if (!editModal) return;
        editModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            editModal.classList.add('hidden');
        }, 150);
    }

    // Функція для відправки форми оновлення статусу
    window.submitStatusUpdate = function() {
        if (!editForm) return;
        // Валідація імені менеджера (хоча воно readonly, про всяк випадок)
        if (!editManagerNameInput?.value?.trim()) {
            managerNameError?.classList.remove('hidden');
            editManagerNameInput?.focus();
            alert("Помилка: Ім'я менеджера не визначено.");
            return;
        }
        managerNameError?.classList.add('hidden');
        editForm.action = 'admin/application_update_status.php'; // URL для оновлення статусу
        editForm.submit();
    }

    // Функція для відправки форми додавання коментаря
    window.submitCommentAdd = function() {
        if (!editForm) return;
         if (!editManagerNameInput?.value?.trim()) {
            managerNameError?.classList.remove('hidden');
            editManagerNameInput?.focus();
            alert("Помилка: Ім'я менеджера не визначено.");
            return;
        }
         if (!editManagerNoteTextarea.value.trim()) {
            editManagerNoteTextarea.focus();
            alert("Текст нового коментаря не може бути порожнім!");
            return;
        }
        managerNameError?.classList.add('hidden');
        editForm.action = 'admin/application_add_comment.php'; // URL для додавання коментаря
        editForm.submit();
    }

    // --- Функції Модального Вікна Історії ---

    window.openApplicationHistoryModal = function(type = 'status', button) {
        if (!historyModal || !historyModalBody) { console.error("History modal elements not found."); return; }
        const row = button?.closest('.application-row');
        if (!row) { console.error("Could not find parent row for history button."); return; }

        const historyData = (type === 'status') ? row.dataset.statusHistory : row.dataset.commentsHistory;
        const applicantName = row.querySelector('td[data-label="ПІБ:"]')?.textContent.trim() || `ID: ${row.dataset.id}`;
        const title = (type === 'status') ? `Історія Статусів: ${applicantName}` : `Історія Коментарів: ${applicantName}`;

        historyModalTitle.textContent = title;
        historyModalBody.innerHTML = ''; // Очищуємо

        if (historyData && historyData.trim() !== '') {
            const entries = historyData.split('\n');
            entries.forEach(entry => {
                if (entry.trim() !== '') {
                    const entryDiv = document.createElement('div');
                    entryDiv.className = 'p-2 rounded bg-gray-50 border-l-4 border-gray-400'; // Сірий за замовчуванням

                    // Розпізнаємо дату/час та решту
                    const dateTimeMatch = entry.match(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}):\s*(.*)/s);
                    let displayDateTime = '';
                    let displayMessage = entry; // За замовчуванням весь запис

                    if (dateTimeMatch) {
                        displayDateTime = escapeHtmlLocal(dateTimeMatch[1]);
                        displayMessage = escapeHtmlLocal(dateTimeMatch[2]);

                        // Додаємо кольорове кодування для історії статусів
                        if (type === 'status') {
                            const msgLower = displayMessage.toLowerCase();
                            if (msgLower.includes('новий')) entryDiv.className = 'p-2 rounded bg-blue-50 border-l-4 border-blue-400';
                            else if (msgLower.includes('не актуально')) entryDiv.className = 'p-2 rounded bg-red-50 border-l-4 border-red-400';
                            else if (msgLower.includes('передзвонити')) entryDiv.className = 'p-2 rounded bg-yellow-50 border-l-4 border-yellow-400';
                            else if (msgLower.includes('запрошений у басейн')) entryDiv.className = 'p-2 rounded bg-purple-50 border-l-4 border-purple-400';
                            else if (msgLower.includes('склав нормативи')) entryDiv.className = 'p-2 rounded bg-indigo-50 border-l-4 border-indigo-400';
                            else if (msgLower.includes('доданий до групи')) entryDiv.className = 'p-2 rounded bg-green-50 border-l-4 border-green-400';
                            else if (msgLower.includes('пройшов академію')) entryDiv.className = 'p-2 rounded bg-teal-50 border-l-4 border-teal-400';
                        } else {
                            // Стиль для історії коментарів
                            entryDiv.className = 'p-2 rounded bg-blue-50 border-l-4 border-blue-400';
                        }
                    }

                    entryDiv.innerHTML = `
                        <div class="text-xs text-gray-500 mb-1">${displayDateTime}</div>
                        <div class="text-sm">${displayMessage.replace(/\(ID: \d+\)/, '') /* Прибираємо ID менеджера */}</div>
                    `; // Використовуємо displayMessage
                    historyModalBody.appendChild(entryDiv);
                }
            });
        } else {
            historyModalBody.innerHTML = '<p class="text-gray-500 italic p-2">Історія відсутня.</p>';
        }

        // Відкриваємо модалку
        historyModal.classList.remove('hidden');
        requestAnimationFrame(() => {
            historyModalContent.classList.remove('scale-95', 'opacity-0');
            historyModalContent.classList.add('scale-100', 'opacity-100');
        });
    }

    window.closeApplicationHistoryModal = function() {
        if (!historyModal) return;
        historyModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            historyModal.classList.add('hidden');
        }, 150);
    }

    // --- Виділення Рядка ---
    function highlightRow(rowElement) {
        if (!rowElement) return;
        // Знімаємо виділення з інших рядків
        applicationTbody?.querySelectorAll('.application-row').forEach(r => {
            r.classList.remove('bg-red-50', 'ring-1', 'ring-red-200', 'shadow-inner');
        });
        // Додаємо виділення поточному
        rowElement.classList.add('bg-red-50', 'ring-1', 'ring-red-200', 'shadow-inner');
        selectedApplicationRow = rowElement;
        selectedApplicationId = rowElement.dataset.id;
        editSelectedBtn?.classList.remove('hidden'); // Показуємо кнопку редагування
    }

    // --- Обробники Подій ---
    if (applicationTbody) {
        applicationTbody.addEventListener('click', (event) => {
            const clickedRow = event.target.closest('.application-row');
            if (clickedRow) {
                 // Виділяємо рядок тільки якщо клік був не по кнопці всередині
                 if (!event.target.closest('button, a')) {
                     highlightRow(clickedRow);
                 }
            }
        });
    }

    // Закриття модалок по Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (editModal && !editModal.classList.contains('hidden')) {
                closeApplicationEditModal();
            }
            if (historyModal && !historyModal.classList.contains('hidden')) {
                closeApplicationHistoryModal();
            }
        }
    });

    // Закриття модалок по кліку на оверлей
    editModal?.addEventListener('click', (e) => { if (e.target === editModal) closeApplicationEditModal(); });
    historyModal?.addEventListener('click', (e) => { if (e.target === historyModal) closeApplicationHistoryModal(); });

    // Анімація появи рядків таблиці заявок
    const appRows = document.querySelectorAll('.applications-list .application-row');
    if (appRows.length > 0 && typeof anime === 'function') {
        anime({
            targets: appRows,
            opacity: [0, 1],
            translateY: [10, 0],
            delay: anime.stagger(30, { start: 50 }),
            duration: 400,
            easing: 'easeOutQuad'
        });
    }

    console.log("Admin Applications JS Initialized.");
}); // --- Кінець DOMContentLoaded ---