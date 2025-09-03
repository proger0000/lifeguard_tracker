// ===============================================
// Файл: js/app.js
// Основний JavaScript додатка Lifeguard Tracker
// ВЕРСІЯ 4.2 (Виправлення навігації адмін-панелі)
// ===============================================
'use strict';
/**
 * -------------------------------------------
 * ІНІЦІАЛІЗАЦІЯ ПРИ ЗАВАНТАЖЕННІ СТОРІНКИ
 * -------------------------------------------
 */
document.addEventListener('DOMContentLoaded', () => {
    // Ініціалізуємо всі компоненти
    // initializeTabNavigation(); // ВИДАЛЕНО: функція не існує і не потрібна
    initializeModalWindows(); 
    initializeDynamicContent();
    initializeEventListeners();
    console.log("Application JS v4.2 Initialized Successfully.");
});

/**
  * -------------------------------------------
  * ГЛОБАЛЬНІ ЗМІННІ (Ініціалізуються в DOMContentLoaded)
  * -------------------------------------------
  */
 // Для модалки деталей звітів (view_reports.php)
 let modal = null, modalOverlay = null, modalCloseButton = null, modalReportIdSpan = null,
     modalGeneralInfoDiv = null, modalStatsDiv = null, modalGeneralNotesP = null, modalIncidentsDiv = null;
 // Для модалки сітки постів (duty_officer_content.php)
 let postGridModal = null, postGridModalOverlay = null, postGridContainer = null,
     postGridLoading = null, postGridNoData = null, modalSelectedDateField = null;
 // Для модалки редагування нормативів кандидата (mark_standards.php)
 let candidateModal = null, candidateModalOverlay = null, candidateModalForm = null,
     candidateModalTitle = null, candidateModalInfo = null, candidateModalCandidateIdInput = null,
     candidateModalAttemptDateInput = null, candidateModalGroupIdInput = null,
     modalStandardsContainer = null;
 
 /**
  * -------------------------------------------
  * ДОПОМІЖНІ ФУНКЦІЇ
  * -------------------------------------------
  */
 function escapeHtml(str) {
     if (str === null || typeof str === 'undefined') return '';
     const stringValue = String(str);
     const map = { '&': '&amp;', '<': '<', '>': '>', '"': '&quot;', "'": '&#039;' };
     const regex = /[&<>"']/g;
     return stringValue.replace(regex, (match) => map[match]);
 }
 function formatModalTime(timeString) {
     if (!timeString || typeof timeString !== 'string') return '-';
     try {
         const timePart = timeString.split(' ')[1] || timeString;
         const parts = timePart.split(':');
         if (parts.length >= 2) {
             return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
         }
     } catch (e) { console.error("Error formatting time:", timeString, e); }
     return escapeHtml(timeString);
 }
 function formatModalDateTime(dateTimeString) {
      if (!dateTimeString) return '-';
     try {
         const date = new Date(dateTimeString);
         if (isNaN(date.getTime())) {
             const parts = dateTimeString.match(/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/);
             if (parts) {
                 if (parseInt(parts[2]) > 0 && parseInt(parts[2]) <= 12 && parseInt(parts[3]) > 0 && parseInt(parts[3]) <= 31) {
                     const dateAgain = new Date(dateTimeString.replace(' ', 'T') + 'Z');
                     if (!isNaN(dateAgain.getTime())) {
                          const options = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Europe/Kiev' };
                          return dateAgain.toLocaleString('uk-UA', options);
                     }
                 }
             }
             return escapeHtml(dateTimeString);
         }
         const options = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Europe/Kiev' };
         return date.toLocaleString('uk-UA', options);
     } catch (e) { console.error("Error formatting date-time:", dateTimeString, e); }
     return escapeHtml(dateTimeString);
 }
 function formatDuration(startString, endString = null) {
      if (!startString) return '-';
      try {
         const start = new Date(startString);
         const end = endString ? new Date(endString) : new Date();
         if (isNaN(start.getTime()) || isNaN(end.getTime())) return '-';
         let diffMs = end.getTime() - start.getTime(); if (diffMs < 0) diffMs = 0;
         const totalSeconds = Math.floor(diffMs / 1000);
         const hours = Math.floor(totalSeconds / 3600);
         const minutes = Math.floor((totalSeconds % 3600) / 60);
         const seconds = totalSeconds % 60;
         const parts = [];
         if (hours > 0) parts.push(`${hours} год`);
         if (minutes > 0) parts.push(`${minutes} хв`);
         if (parts.length === 0 && totalSeconds > 0) return `${seconds} сек`;
         if (parts.length === 0 && totalSeconds === 0) return '0 хв';
         return parts.join(' ');
       } catch(e) { console.error("Error calculating duration:", startString, endString, e); return 'Помилка'; }
  }
 function getTranslation(key, fallback = null) {
     const incidentTranslations = (typeof incidentFieldTranslations === 'object' && incidentFieldTranslations !== null) ? incidentFieldTranslations : {};
     const statsTrans = (typeof statsTranslations === 'object' && statsTranslations !== null) ? statsTranslations : {};
     const translations = { ...statsTrans, ...incidentTranslations };
     if (typeof key !== 'string' && key !== null && typeof key !== 'undefined') { console.warn("getTranslation called with non-string key:", key); return fallback !== null ? fallback : JSON.stringify(key); }
     const keyString = String(key);
     return translations[keyString] || (fallback !== null ? fallback : keyString);
 }

/**
 * Універсальна функція для перемикання вкладок.
 */
function switchTab(allContentIds, allButtonIds, activeContentId, activeButtonId, styles) {
    // Ховаємо весь контент і деактивуємо всі кнопки
    allContentIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('hidden');
            // Додаткова перевірка - примусово приховуємо
            el.style.display = 'none';
        }
    });
    allButtonIds.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.setAttribute('aria-selected', 'false');
            btn.classList.remove(...styles.active.button);
            btn.classList.add(...styles.inactive.button);
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.remove(...styles.active.icon);
                icon.classList.add(...styles.inactive.icon);
            }
        }
    });

    // Показуємо активний контент і активуємо кнопку
    const contentEl = document.getElementById(activeContentId);
    if (contentEl) {
        contentEl.classList.remove('hidden');
        contentEl.style.display = 'block'; // Примусово показуємо
        contentEl.style.animation = 'none';
        void contentEl.offsetWidth; // Trigger reflow
        contentEl.style.animation = 'fadeIn 0.4s ease-out';
    }
    const buttonEl = document.getElementById(activeButtonId);
    if (buttonEl) {
        buttonEl.setAttribute('aria-selected', 'true');
        buttonEl.classList.add(...styles.active.button);
        buttonEl.classList.remove(...styles.inactive.button);
        const icon = buttonEl.querySelector('i');
        if (icon) {
            icon.classList.add(...styles.active.icon);
            icon.classList.remove(...styles.inactive.icon);
        }
    }
}

// Функція для адмін-панелі (ОНОВЛЕНА)
function showAdminTab(tabName) {
    const map = {
        'duty':            { c: 'admin-duty-content',           b: 'admin-duty-tab' },
        'shift_history':   { c: 'admin-shift_history-content',  b: 'admin-shift_history-tab' },
        'posts-analytics': { c: 'admin-posts-analytics-content',b: 'admin-posts-analytics-tab' },
        'payroll_rating':  { c: 'admin-payroll_rating-content',   b: 'admin-payroll_rating-tab' },
        'salary_report':   { c: 'admin-salary_report-content',    b: 'admin-salary_report-tab' },
        'applications':    { c: 'admin-applications-content',   b: 'admin-applications-tab' },
        'posts':           { c: 'admin-posts-content',          b: 'admin-posts-tab' },
        'users':           { c: 'admin-users-content',          b: 'admin-users-tab' },
        'academy':         { c: 'admin-academy-content',        b: 'admin-academy-tab' }
    };

    const styles = {
        active:   { button: ['border-red-500', 'text-red-600', 'font-semibold'], icon: ['text-red-600'] },
        inactive: { button: ['border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300'], icon: ['text-gray-400', 'group-hover:text-gray-500'] }
    };
    
    const allContentIds = Object.values(map).map(v => v.c);
    const allButtonIds = Object.values(map).map(v => v.b);
    
    const active = map[tabName];
    if (active) {
        switchTab(allContentIds, allButtonIds, active.c, active.b, styles);
        try { 
            const url = new URL(window.location);
            url.searchParams.set('tab_admin', tabName);
            url.hash = 'admin-' + tabName;
            history.replaceState(null, '', url.toString());
        } catch (e) {
            console.warn("Could not update URL:", e);
        }
    } else {
        console.error("Admin tab not found in map:", tabName);
    }
}
function showDirectorTab(tabName) {
    const map = {
        'posts':      { c: 'director-posts-content',      b: 'director-posts-tab' },
        'analytics':  { c: 'director-analytics-content',  b: 'director-analytics-tab' },
        'rating':     { c: 'director-rating-content',     b: 'director-rating-tab' }
    };
     const styles = {
        active:   { button: ['border-blue-500', 'text-blue-700', 'font-semibold'], icon: [] },
        inactive: { button: ['border-transparent', 'text-gray-500'], icon: [] }
    };
    const active = map[tabName];
    if (active) {
        switchTab(Object.values(map).map(v => v.c), Object.values(map).map(v => v.b), active.c, active.b, styles);
        try { history.replaceState(null, '', '#director-' + tabName); } catch (e) {}
    }
}
function showDutyOfficerTab(tabName) {
    const map = {
        'operational': { c: 'duty-operational-content', b: 'duty-operational-tab' },
        'history':     { c: 'duty-history-content',     b: 'duty-history-tab' }
    };
     const styles = {
        active:   { button: ['border-sky-500', 'text-sky-600', 'font-semibold'], icon: ['text-sky-600'] },
        inactive: { button: ['border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300'], icon: ['text-gray-400', 'group-hover:text-gray-500'] }
    };
    const active = map[tabName];
    if (active) {
        switchTab(Object.values(map).map(v => v.c), Object.values(map).map(v => v.b), active.c, active.b, styles);
         try {
            const url = new URL(window.location);
            url.searchParams.set('tab_duty', tabName);
            history.replaceState(null, '', url.pathname + url.search + '#duty-' + tabName);
        } catch (e) {}
    }
}
 /**
  * -------------------------------------------
  * КОПІЮВАННЯ URL NFC (Оновлена версія)
  * -------------------------------------------
  */
 function copyToClipboardEnhanced(elementId, buttonElement) {
     const urlElement = document.getElementById(elementId);
     const textToCopy = urlElement?.innerText?.trim();
     if (!textToCopy || !navigator.clipboard) {
         alert('Помилка: Не вдалося скопіювати URL (буфер обміну недоступний або текст порожній).');
         return;
     }
     const postId = elementId.replace('nfc-url-', '');
     const copyIcon = document.getElementById(`copy-icon-${postId}`);
     const copiedIcon = document.getElementById(`copied-icon-${postId}`);
     const copiedMessage = document.getElementById(`copied-message-${postId}`);
     navigator.clipboard.writeText(textToCopy).then(() => {
         if (copyIcon && copiedIcon) {
             copyIcon.classList.add('hidden');
             copiedIcon.classList.remove('hidden');
             if (copiedMessage) copiedMessage.classList.remove('hidden');
             if (buttonElement) buttonElement.disabled = true;
             setTimeout(() => {
                 copyIcon.classList.remove('hidden');
                 copiedIcon.classList.add('hidden');
                 if (copiedMessage) copiedMessage.classList.add('hidden');
                 if (buttonElement) buttonElement.disabled = false;
             }, 2000);
         } else {
             const toast = document.getElementById('toast-notification');
             if(toast){
                 toast.textContent = 'URL скопійовано!';
                 toast.classList.add('show');
                 setTimeout(() => { toast.classList.remove('show'); }, 2000);
             } else { alert('URL скопійовано!'); }
         }
     }).catch(err => {
         console.error('copyToClipboardEnhanced Error: ', err);
         alert('Не вдалося автоматично скопіювати URL.');
     });
 }
 /**
  * -------------------------------------------
  * МОДАЛЬНЕ ВІКНО ДЕТАЛЕЙ ЗВІТУ (VIEW_REPORTS.PHP)
  * -------------------------------------------
  */
 function openReportModal(reportId) {
     if (typeof reportsData==='undefined' || !Array.isArray(reportsData)) { console.error("Modal Error: Global 'reportsData' error."); alert("Помилка: Дані звітів недоступні."); return; }
     if (typeof incidentsData==='undefined' || typeof incidentsData!=='object' || incidentsData===null) { console.warn("Modal Warning: Global 'incidentsData' error."); }
     if (typeof getTranslation!=='function') { console.error("Modal Error: getTranslation missing."); alert("Помилка: Переклади недоступні."); return; }
     if (!modal || !modalReportIdSpan || !modalGeneralInfoDiv || !modalStatsDiv || !modalIncidentsDiv) { console.error("Modal Error: Modal elements missing."); alert("Помилка інтерфейсу."); return; }
     const report = reportsData.find(r => String(r.report_id) === String(reportId));
     const incidents = (typeof incidentsData === 'object' && incidentsData !== null && incidentsData[reportId]) ? incidentsData[reportId] : [];
     if (!report) { alert(`Помилка: Звіт №${reportId} не знайдено.`); return; }
     modalReportIdSpan.textContent = report.report_id || 'N/A';
     modalGeneralInfoDiv.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm"> <div><strong class="font-semibold text-gray-700">Пост:</strong> ${escapeHtml(report.post_name || '-')}</div> <div><strong class="font-semibold text-gray-700">Лайфгард:</strong> ${escapeHtml(report.reporter_name || '-')}</div> <div><strong class="font-semibold text-gray-700">Дата звіту:</strong> ${formatModalDateTime(report.report_submitted_at) || '-'}</div> <div><strong class="font-semibold text-gray-700">Тривалість зміни:</strong> ${formatDuration(report.start_time, report.end_time) || '-'}</div> </div>`;
     let statsHtml = '';
     if(typeof statsTranslations === 'object' && statsTranslations !== null) {
         for (const key in statsTranslations) {
             if (report.hasOwnProperty(key)) {
                 let valueDisplay = (report[key] === null || String(report[key]).trim() === '') ? '<i class="text-gray-400">0</i>' : escapeHtml(String(report[key]));
                 const label = escapeHtml(getTranslation(key, key));
                 statsHtml += `<div class="bg-gray-50 p-2 rounded border border-gray-200 text-xs"><strong class="font-semibold text-gray-700 block sm:inline">${label}:</strong> ${valueDisplay}</div>`;
             }
         }
     }
     modalStatsDiv.innerHTML = statsHtml || '<p class="text-xs text-gray-500 col-span-full">Статистика відсутня.</p>';
     const notesContainer = modalGeneralNotesP?.parentElement; const notesHr = document.getElementById('modal-notes-hr');
     if (notesContainer && modalGeneralNotesP && notesHr) {
         const notesText = String(report.general_notes || '').trim();
         if (notesText !== '') { modalGeneralNotesP.textContent = notesText; notesContainer.style.display = 'block'; notesHr.style.display = 'block'; }
         else { modalGeneralNotesP.textContent = ''; notesContainer.style.display = 'none'; notesHr.style.display = 'none'; }
     }
     modalIncidentsDiv.innerHTML = '<h4 class="text-md font-semibold text-gray-700 mb-2">Зафіксовані інциденти:</h4>';
     if (incidents.length === 0) { modalIncidentsDiv.innerHTML += '<p class="text-gray-500 italic text-sm">Інцидентів не зафіксовано.</p>'; }
     else { incidents.forEach((incident, index) => { if (typeof incident !== 'object' || incident === null) return; const incidentDiv = document.createElement('div'); incidentDiv.className = 'incident-detail-item border-t border-gray-200 pt-3 mt-3'; const incidentTypeOriginal = incident.incident_type || 'unknown_type'; const incidentTypeTranslated = getTranslation(incidentTypeOriginal, incidentTypeOriginal); incidentDiv.innerHTML = `<h5 class="font-semibold text-gray-800 mb-2">${escapeHtml(incidentTypeTranslated)} # ${index + 1}</h5>`; const detailsList = document.createElement('dl'); detailsList.className = 'space-y-1 text-xs pl-2'; for (const key in incident) { if (!incident.hasOwnProperty(key)) continue; if (['id', 'shift_report_id', 'incident_type'].includes(key) || incident[key] === null || String(incident[key]).trim() === '') continue; let value = incident[key]; let valueHtml = ''; let labelHtml = getTranslation(key, key); try { if (['cause_details', 'actions_taken', 'outcome_details'].includes(key) && typeof value === 'string' && value.startsWith('[') && value.endsWith(']')) { const items = JSON.parse(value); if (Array.isArray(items) && items.length > 0) { valueHtml = '<ul class="list-disc list-inside ml-4 space-y-0.5">'; items.forEach(itemValue => { const translationKey = key + '_' + String(itemValue).trim(); const itemTranslated = getTranslation(translationKey, String(itemValue)); valueHtml += `<li>${escapeHtml(itemTranslated)}</li>`; }); valueHtml += '</ul>'; } else { continue; } } else if (['cause_details', 'actions_taken', 'outcome_details', 'subject_gender', 'result'].includes(key)) { const translationKey = key + '_' + String(value).trim(); let translatedValue = getTranslation(translationKey, null); if (translatedValue === null) { translatedValue = getTranslation(String(value), String(value)); } valueHtml = escapeHtml(translatedValue); } else if (key === 'incident_time') { valueHtml = formatModalTime(String(value)); } else if (key === 'involved_lifeguard_name' && value) { labelHtml = getTranslation('involved_lifeguard_id'); valueHtml = escapeHtml(String(value)); } else if (key === 'involved_lifeguard_id') { const hasName = incident.hasOwnProperty('involved_lifeguard_name') && incident.involved_lifeguard_name; if (!hasName) { labelHtml = getTranslation('involved_lifeguard_id'); const lgName = (typeof lifeguards_list_js === 'object' && lifeguards_list_js && lifeguards_list_js[value]) ? lifeguards_list_js[value] : null; valueHtml = escapeHtml(lgName || `(ID: ${value})`); } else { continue; } } else { valueHtml = escapeHtml(String(value)); } } catch (e) { console.warn(`Err processing field '${key}':`, e); valueHtml = `<i class="text-red-500">Помилка обробки</i>`; } if (valueHtml !== '') { detailsList.innerHTML += `<div class="flex"><dt class="w-1/2 sm:w-2/5 font-medium text-gray-600 shrink-0 pr-1">${labelHtml}:</dt><dd class="w-1/2 sm:w-3/5 text-gray-800 break-words">${valueHtml}</dd></div>`; } } incidentDiv.appendChild(detailsList); modalIncidentsDiv.appendChild(incidentDiv); }); }
     if (modal) { modal.classList.remove('hidden'); modalCloseButton?.focus(); }
 }
 function closeReportModal() {
     if (modal) { modal.classList.add('hidden'); if (modalReportIdSpan) modalReportIdSpan.textContent = ''; if (modalGeneralInfoDiv) modalGeneralInfoDiv.innerHTML = ''; if (modalStatsDiv) modalStatsDiv.innerHTML = ''; if (modalGeneralNotesP) modalGeneralNotesP.textContent = ''; if (modalGeneralNotesP?.parentElement) modalGeneralNotesP.parentElement.style.display = 'none'; const notesHr = document.getElementById('modal-notes-hr'); if (notesHr) notesHr.style.display = 'none'; if (modalIncidentsDiv) modalIncidentsDiv.innerHTML = ''; }
 }
 /**
  * -------------------------------------------
  * ЛОГІКА ДИНАМІЧНИХ ІНЦИДЕНТІВ ФОРМИ ЗВІТУ (SUBMIT_REPORT.PHP)
  * -------------------------------------------
  */
 const incidentCounters = { medical_aid: 0, lost_child: 0, critical_swimmer: 0, police_call: 0, ambulance_call: 0, other: 0 };
 function addIncident(type) {
     const template = document.getElementById(`template-${type}`); const listContainer = document.getElementById(`incidents-${type}-list`); const initialText = listContainer ? listContainer.querySelector('.initial-text') : null; if (!template || !listContainer) { console.error(`AddIncident Error: Missing elements for type "${type}"`); return; } if (initialText) initialText.remove(); const index = incidentCounters[type]++; const index_display = index + 1; let clone; try { clone = template.content.cloneNode(true); } catch (e) { console.error(`Error cloning template for type "${type}":`, e); return; } if (!clone.firstElementChild) { console.error(`Template content error for type "${type}".`); return; } const elementsToUpdate = clone.querySelectorAll('[name*="{index}"], [id*="{index}"], [for*="{index}"]'); elementsToUpdate.forEach(el => { ['name', 'id', 'for'].forEach(attr => { if (el.hasAttribute(attr)) { el.setAttribute(attr, el.getAttribute(attr).replace(/\{index\}/g, index)); } }); }); clone.querySelectorAll('*').forEach(el => { if (el.childNodes.length > 0) { el.childNodes.forEach(node => { if (node.nodeType === Node.TEXT_NODE && node.nodeValue.includes('{index_display}')) { node.nodeValue = node.nodeValue.replace(/\{index_display\}/g, index_display); } }); } if (el.tagName === 'H5' && el.textContent.includes('{index_display}')) { el.textContent = el.textContent.replace('{index_display}', index_display); } }); const firstDiv = clone.querySelector('.incident-item'); if (firstDiv) { firstDiv.dataset.index = index; firstDiv.style.position = 'relative'; const removeButton = document.createElement('button'); removeButton.type = 'button'; removeButton.onclick = function() { removeIncident(this); }; removeButton.className = 'absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold focus:outline-none z-10 p-1 leading-none'; removeButton.innerHTML = '&times;'; removeButton.title = 'Видалити'; removeButton.setAttribute('aria-label', 'Видалити'); firstDiv.appendChild(removeButton); applyInputStylesToBlock(firstDiv); } listContainer.appendChild(clone);
 }
 function removeIncident(button) {
     const incidentItem = button.closest('.incident-item'); if (incidentItem) { const listContainer = incidentItem.parentNode; incidentItem.remove(); if (listContainer && !listContainer.querySelector('.incident-item')) { const p = document.createElement('p'); p.className = 'text-sm text-gray-500 italic initial-text'; p.textContent = 'Не було зафіксовано випадків цього типу.'; listContainer.appendChild(p); } }
 }
 function applyInputStylesToBlock(blockElement) {
     if (!blockElement) return; const inputs = 'input[type=text], input[type=number], input[type=tel], input[type=time], select, textarea'; const radiosCheckboxes = 'input[type=checkbox], input[type=radio]'; const baseInputClasses = ['shadow-sm', 'appearance-none', 'block', 'w-full', 'px-3', 'border', 'border-gray-300', 'rounded-md', 'placeholder-gray-400', 'focus:outline-none', 'focus:ring-1', 'focus:ring-red-500', 'focus:border-red-500', 'sm:text-sm']; blockElement.querySelectorAll(inputs).forEach(inputElement => { const currentClasses = [...baseInputClasses]; if (inputElement.tagName.toLowerCase() === 'textarea') { currentClasses.push('py-1.5'); } else { currentClasses.push('py-2'); } inputElement.classList.add(...currentClasses); inputElement.classList.remove('incident-field'); }); blockElement.querySelectorAll(radiosCheckboxes).forEach(radioCheckboxElement => { radioCheckboxElement.classList.add('h-4', 'w-4', 'text-red-600', 'border-gray-300', 'rounded', 'focus:ring-red-500', 'focus:ring-offset-0'); radioCheckboxElement.classList.remove('form-checkbox', 'form-radio'); });
 }
 /**
  * -------------------------------------------
  * МОДАЛЬНЕ ВІКНО СІТКИ ПОСТІВ (DUTY_OFFICER_CONTENT.PHP)
  * -------------------------------------------
  */
 function formatPhotoStatus(photoPath, approvedAt) {
    const baseUrl = typeof APP_URL !== 'undefined' ? APP_URL.replace(/\/$/, '') : window.location.origin;
    if (!photoPath) { return `<span class="text-xs text-yellow-600 font-medium flex items-center" title="Фото ще не завантажено"><i class="fas fa-camera w-3 text-center mr-1.5"></i> Очікується</span>`; }
    const fullPhotoUrl = photoPath.startsWith('http') ? photoPath : `${baseUrl}/${photoPath.replace(/^\//, '')}`;
    if (approvedAt) { return `<a href="${escapeHtml(fullPhotoUrl)}" target="_blank" class="text-xs text-green-600 font-medium flex items-center hover:underline" title="Фото підтверджено (${formatModalDateTime(approvedAt)}). Натисніть для перегляду."><i class="fas fa-check-circle w-3 text-center mr-1.5"></i> OK</a>`; }
    else { return `<a href="${escapeHtml(fullPhotoUrl)}" target="_blank" class="text-xs text-orange-600 hover:underline font-medium flex items-center" title="Фото очікує підтвердження. Натисніть для перегляду."><i class="fas fa-image w-3 text-center mr-1.5"></i> Переглянути</a>`; }
}
 function formatReportStatus(reportId, shiftId) {
    const baseUrl = typeof APP_URL !== 'undefined' ? APP_URL.replace(/\/$/, '') : '';
    if (reportId) { return `<span class="text-xs text-green-600 font-medium flex items-center" title="Звіт для зміни ${shiftId} подано"><i class="fas fa-check mr-1"></i> Подано</span>`; }
    else { return `<span class="text-xs text-yellow-600 font-medium flex items-center" title="Звіт для зміни ${shiftId} ще не подано"><i class="fas fa-clock mr-1"></i> Очікується</span>`; }
 }
function openPostGridModal() {
    console.log("--- openPostGridModal called ---");
    if (!postGridModal || !postGridContainer || !postGridNoData) { console.error("Post Grid Modal Error: Required modal elements not found in the DOM."); return; }
    console.log("Checking postGridData type:", typeof postGridData, "Value:", postGridData);
    if (typeof postGridData !== 'object' || postGridData === null || Object.keys(postGridData).length === 0) { console.warn("Post Grid Modal: postGridData is empty, null, or not an object."); postGridContainer.innerHTML = ''; postGridNoData.classList.remove('hidden'); postGridLoading?.classList.add('hidden'); postGridModal.classList.remove('hidden'); return; }
    console.log("Data found. Preparing grid..."); postGridContainer.innerHTML = ''; postGridNoData.classList.add('hidden'); postGridLoading?.classList.add('hidden');
    const selectedDateInput = document.getElementById('duty_date_select');
    if (modalSelectedDateField && selectedDateInput?.value) { try { const dateValue = selectedDateInput.value; if (/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) { const d = new Date(dateValue + 'T00:00:00'); if (!isNaN(d.getTime())) { modalSelectedDateField.textContent = d.toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' }); } else { modalSelectedDateField.textContent = dateValue; } } else { modalSelectedDateField.textContent = dateValue; } } catch (e) { console.error("Error formatting date for modal title:", e); modalSelectedDateField.textContent = selectedDateInput.value; } }
    let postsGenerated = 0;
    const sortedPostIds = Object.keys(postGridData).sort((a, b) => (postGridData[a]?.name?.toLowerCase() || '').localeCompare(postGridData[b]?.name?.toLowerCase() || '', 'uk'));
    sortedPostIds.forEach(postId => {
        const post = postGridData[postId];
        if (post && typeof post === 'object' && post.name) {
            postsGenerated++;
            const postCard = document.createElement('div');
            postCard.className = 'post-card bg-white rounded-lg shadow border border-gray-200/80 p-3 space-y-2 flex flex-col text-xs';
            let cardContent = `<h4 class="font-bold text-gray-800 text-sm border-b border-gray-200 pb-1 mb-2 flex-shrink-0">${escapeHtml(post.name)}</h4>`;
            if (post.active_shifts && Array.isArray(post.active_shifts) && post.active_shifts.length > 0) {
                cardContent += '<div class="active-shifts space-y-1.5 flex-grow">'; cardContent += '<h5 class="font-semibold text-green-700 uppercase tracking-wide text-[0.7rem] mb-1">Активні:</h5>';
                post.active_shifts.forEach(shift => { cardContent += ` <div class="shift-item bg-green-50/70 p-1.5 rounded border border-green-200/50"> <p class="font-semibold text-gray-900 truncate flex items-center" title="${escapeHtml(shift.lifeguard_name)}"> <i class="fas fa-user w-3 text-center mr-1.5 text-green-700"></i> ${escapeHtml(shift.lifeguard_name)} </p> <p class="flex items-center mt-0.5"> <i class="fas fa-clock w-3 text-center mr-1.5 text-gray-500"></i> Початок: ${formatModalTime(shift.start_time)} </p> <p class="mt-0.5"> ${formatPhotoStatus(shift.start_photo_path, shift.start_photo_approved_at)} </p> </div>`; });
                cardContent += '</div>';
            }
             if (post.completed_shifts && Array.isArray(post.completed_shifts) && post.completed_shifts.length > 0) {
                cardContent += `<div class="completed-shifts space-y-1.5 ${post.active_shifts?.length > 0 ? 'mt-2 pt-2 border-t border-gray-100' : ''} flex-grow">`; cardContent += '<h5 class="font-semibold text-blue-700 uppercase tracking-wide text-[0.7rem] mb-1">Завершені:</h5>';
                post.completed_shifts.forEach(shift => { cardContent += ` <div class="shift-item bg-blue-50/70 p-1.5 rounded border border-blue-200/50"> <p class="font-semibold text-gray-900 truncate flex items-center" title="${escapeHtml(shift.lifeguard_name)}"> <i class="fas fa-user w-3 text-center mr-1.5 text-blue-700"></i> ${escapeHtml(shift.lifeguard_name)} </p> <p class="flex items-center mt-0.5"> <i class="fas fa-history w-3 text-center mr-1.5 text-gray-500"></i> ${formatModalTime(shift.start_time)} - ${formatModalTime(shift.end_time)} </p> <p class="mt-0.5"> ${formatReportStatus(shift.report_id, shift.shift_id)} </p> </div>`; });
                cardContent += '</div>';
             }
             const noActive = !post.active_shifts?.length; const noCompleted = !post.completed_shifts?.length;
             if (noActive && noCompleted) { cardContent += '<div class="flex-grow flex items-center justify-center"><p class="text-center text-xs text-gray-400 italic pt-4">Змін немає</p></div>'; }
             postCard.innerHTML = cardContent; postGridContainer.appendChild(postCard);
         } else { console.warn(`Post with ID ${postId} is missing data or 'name' property.`); }
    });
    console.log(`Generated ${postsGenerated} post cards.`);
    if (postsGenerated === 0 && Object.keys(postGridData).length > 0) { console.warn("Post data exists, but no post cards were generated. Showing 'No Data' message."); postGridNoData.classList.remove('hidden'); }
    postGridModal.classList.remove('hidden'); postGridContainer.focus();
    console.log("--- openPostGridModal finished ---");
}
 function closePostGridModal() {
     if (postGridModal) { postGridModal.classList.add('hidden'); }
 }
 /**
 * -------------------------------------------
 * МОДАЛЬНЕ ВІКНО НОРМАТИВІВ (MARK_STANDARDS.PHP)
 * -------------------------------------------
 */
 function openCandidateStandardsModal(candidateId, candidateName, attemptDate, existingResults) {
    if (!candidateModal || !candidateModalForm || !modalStandardsContainer) { console.error("Candidate modal elements not found!"); return; }
    currentCandidateResults = existingResults || {};
    candidateModalTitle.textContent = `Нормативи: ${candidateName}`;
     try { candidateModalInfo.textContent = `Дата спроби: ${new Date(attemptDate).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' })}`; } catch (e) { candidateModalInfo.textContent = `Дата спроби: ${attemptDate}`; console.warn("Could not format attempt date:", attemptDate, e); }
    candidateModalCandidateIdInput.value = candidateId; candidateModalAttemptDateInput.value = attemptDate; const urlParams = new URLSearchParams(window.location.search); candidateModalGroupIdInput.value = urlParams.get('group_id') || '';
    modalStandardsContainer.innerHTML = ''; if(typeof allStandardTypes === 'undefined' || !Array.isArray(allStandardTypes) || allStandardTypes.length === 0) { modalStandardsContainer.innerHTML = '<p class="text-red-500">Помилка: Типи нормативів не завантажено.</p>'; return; }
    allStandardTypes.forEach(stdType => {
        const stdId = stdType.id; const result = currentCandidateResults[stdId] || {}; const resValue = result.result_value || ''; const resPassed = result.passed; const resComments = result.comments || ''; const passCriteria = stdType.pass_criteria || ''; const entryDiv = document.createElement('div'); entryDiv.className = 'standard-entry border-b border-gray-200 pb-3 mb-3 last:border-b-0';
        entryDiv.innerHTML = ` <h4 class="text-md font-semibold text-indigo-700 mb-1"> ${escapeHtml(stdType.name)} ${passCriteria ? `<span class="text-xs font-normal text-gray-500 ml-2">(Критерій: ${escapeHtml(passCriteria)})</span>` : ''} </h4> <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-x-4 gap-y-2 items-center"> <div> <label for="modal_std_${stdId}_result" class="block text-xs font-medium text-gray-500 mb-0.5">Результат</label> <input type="text" name="modal_standards[${stdId}][result_value]" id="modal_std_${stdId}_result" value="${escapeHtml(resValue)}" placeholder="Час, бал, тощо" class="std-input !py-1 !text-sm w-full"> </div> <div class="self-end"> <label class="block text-xs font-medium text-gray-500 mb-0.5">Статус</label> <div class="flex items-center space-x-3"> <label class="flex items-center text-sm cursor-pointer"> <input type="radio" name="modal_standards[${stdId}][passed]" value="yes" class="form-radio h-4 w-4 text-green-600 modal_std_${stdId}_passed" ${resPassed === 1 || resPassed === true ? 'checked' : ''}> <span class="ml-1.5 text-green-700">Склав</span> </label> <label class="flex items-center text-sm cursor-pointer"> <input type="radio" name="modal_standards[${stdId}][passed]" value="no" class="form-radio h-4 w-4 text-red-600 modal_std_${stdId}_passed" ${resPassed === 0 || resPassed === false ? 'checked' : ''}> <span class="ml-1.5 text-red-700">Не склав</span> </label> <label class="flex items-center text-sm cursor-pointer"> <input type="radio" name="modal_standards[${stdId}][passed]" value="" class="form-radio h-4 w-4 text-gray-400 modal_std_${stdId}_passed" ${resPassed === null ? 'checked' : ''}> <span class="ml-1.5 text-gray-500">?</span> </label> </div> </div> <div class="sm:col-span-2"> <label for="modal_std_${stdId}_comments" class="block text-xs font-medium text-gray-500 mb-0.5">Коментар</label> <textarea name="modal_standards[${stdId}][comments]" id="modal_std_${stdId}_comments" rows="1" placeholder="Необов'язково" class="std-input !py-1 !text-sm w-full min-h-[30px]">${escapeHtml(resComments)}</textarea> </div> </div>`;
        modalStandardsContainer.appendChild(entryDiv);
    });
    candidateModal.classList.remove('hidden'); candidateModalOverlay?.addEventListener('click', closeCandidateStandardsModal); const firstResultInput = modalStandardsContainer.querySelector('input[type="text"]'); firstResultInput?.focus();
 }
 function closeCandidateStandardsModal() {
     if (candidateModal) { candidateModal.classList.add('hidden'); candidateModalOverlay?.removeEventListener('click', closeCandidateStandardsModal); if(modalStandardsContainer) modalStandardsContainer.innerHTML = ''; }
 }
 /**
  * -------------------------------------------
  * ІНІЦІАЛІЗАЦІЯ МОДАЛЬНИХ ВІКОН
  * -------------------------------------------
  */
function initializeModalWindows() {
    // --- Ініціалізація Елементів Модалки Звітів ---
    modal = document.getElementById('report-modal');
    modalOverlay = document.getElementById('modal-overlay');
    modalCloseButton = document.getElementById('modal-close-button');
    modalReportIdSpan = document.getElementById('modal-report-id');
    modalGeneralInfoDiv = document.getElementById('modal-general-info');
    modalStatsDiv = document.getElementById('modal-stats');
    modalGeneralNotesP = document.querySelector('#modal-general-notes p');
    modalIncidentsDiv = document.getElementById('modal-incidents');

    if (modal) {
        modalOverlay?.addEventListener('click', closeReportModal);
        modalCloseButton?.addEventListener('click', closeReportModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeReportModal();
        });
    }

    // --- Ініціалізація Елементів Модалки Сітки Постів ---
    postGridModal = document.getElementById('post-grid-modal');
    postGridModalOverlay = document.getElementById('post-grid-modal-overlay');
    postGridContainer = document.getElementById('post-grid-container');
    postGridLoading = document.getElementById('post-grid-loading');
    postGridNoData = document.getElementById('post-grid-nodata');
    modalSelectedDateField = document.getElementById('modal-selected-date');

    if (postGridModal) {
        const postGridCloseButton = postGridModal.querySelector('button[onclick="closePostGridModal()"]');
        postGridModalOverlay?.addEventListener('click', closePostGridModal);
        postGridCloseButton?.addEventListener('click', closePostGridModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !postGridModal.classList.contains('hidden')) closePostGridModal();
        });
    }

    // --- Ініціалізація Елементів Модалки Нормативів ---
    candidateModal = document.getElementById('candidate-standards-modal');
    candidateModalOverlay = document.getElementById('candidate-modal-overlay');
    candidateModalForm = document.getElementById('candidate-modal-form');
    candidateModalTitle = document.getElementById('candidate-modal-title');
    candidateModalInfo = document.getElementById('modal-candidate-info');
    candidateModalCandidateIdInput = document.getElementById('modal_candidate_id');
    candidateModalAttemptDateInput = document.getElementById('modal_attempt_date');
    candidateModalGroupIdInput = document.getElementById('modal_group_id');
    modalStandardsContainer = document.getElementById('modal-standards-container');

    if (candidateModal) {
        const candidateModalCloseButton = candidateModal.querySelector('button[onclick="closeCandidateStandardsModal()"]');
        candidateModalOverlay?.addEventListener('click', closeCandidateStandardsModal);
        candidateModalCloseButton?.addEventListener('click', closeCandidateStandardsModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !candidateModal.classList.contains('hidden')) closeCandidateStandardsModal();
        });
    }
}

/**
 * -------------------------------------------
 * ІНІЦІАЛІЗАЦІЯ ДИНАМІЧНОГО ВМІСТУ
 * -------------------------------------------
 */
function initializeDynamicContent() {
    // --- Ініціалізація для submit_report.php ---
    if (document.getElementById('report-form')) {
        document.querySelectorAll('.incident-item').forEach(applyInputStylesToBlock);
    }

    // --- Ініціалізація Вкладок Адмін-панелі (ВИПРАВЛЕНО) ---
    if (document.getElementById('adminTabContent') && typeof showAdminTab === 'function') {
        const hash = window.location.hash;
        const urlParams = new URLSearchParams(window.location.search);
        
        // *** ОСНОВНЕ ВИПРАВЛЕННЯ ТУТ: Додано всі вкладки до списку валідних ***
        const validAdminTabs = [
            'duty', 'shift_history', 'posts-analytics', 'payroll_rating', 
            'salary_report', 'applications', 'posts', 'users', 'academy'
        ];
        
        let initialAdminTab = 'duty'; // Вкладка за замовчуванням

        // 1. Пріоритет: хеш в URL (#admin-...)
        if (hash.startsWith('#admin-')) {
            const tabNameFromHash = hash.substring(7);
            if (validAdminTabs.includes(tabNameFromHash)) {
                initialAdminTab = tabNameFromHash;
            }
        } 
        // 2. Якщо хеша немає, перевіряємо GET-параметри (tab_admin або tab)
        else {
            const tabFromGet = urlParams.get('tab_admin') || urlParams.get('tab');
            if (tabFromGet && validAdminTabs.includes(tabFromGet)) {
                initialAdminTab = tabFromGet;
            }
        }
        
        // Додаткова перевірка - примусово приховуємо всі вкладки перед показом активної
        const allAdminTabs = [
            'admin-duty-content', 'admin-shift_history-content', 'admin-posts-analytics-content',
            'admin-payroll_rating-content', 'admin-salary_report-content', 'admin-posts-content',
            'admin-users-content', 'admin-applications-content', 'admin-academy-content'
        ];
        
        allAdminTabs.forEach(tabId => {
            const tab = document.getElementById(tabId);
            if (tab) {
                tab.classList.add('hidden');
                tab.style.display = 'none';
            }
        });
        
        showAdminTab(initialAdminTab);
    }

    // --- Ініціалізація Вкладок Панелі Директора ---
    if (document.getElementById('directorTabContent') && typeof showDirectorTab === 'function') {
        const hash = window.location.hash;
        const urlParams = new URLSearchParams(window.location.search);

        const validDirectorTabs = ['posts', 'analytics', 'rating'];
        let initialDirectorTab = 'posts';

        if (hash.startsWith('#director-')) {
            const tabFromHash = hash.substring(10);
            if (validDirectorTabs.includes(tabFromHash)) {
                initialDirectorTab = tabFromHash;
            }
        } else {
            const tabFromGet = urlParams.get('tab_director');
            if (tabFromGet && validDirectorTabs.includes(tabFromGet)) {
                initialDirectorTab = tabFromGet;
            }
        }

        const allDirectorTabs = ['director-posts-content', 'director-analytics-content', 'director-rating-content'];
        allDirectorTabs.forEach(tabId => {
            const tab = document.getElementById(tabId);
            if (tab) {
                tab.classList.add('hidden');
                tab.style.display = 'none';
            }
        });

        showDirectorTab(initialDirectorTab);
    }
}

/**
 * -------------------------------------------
 * ІНІЦІАЛІЗАЦІЯ ГЛОБАЛЬНИХ ОБРОБНИКІВ ПОДІЙ
 * -------------------------------------------
 */
function initializeEventListeners() {
    // --- Автоматичне зникнення Flash повідомлень ---
    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        let start = null;
        const duration = 500;
        const delay = 5000;
        const fadeOut = (timestamp) => {
            if (!start) start = timestamp;
            const elapsed = timestamp - start;
            const progress = Math.min(elapsed / duration, 1);
            flashMessage.style.opacity = 1 - progress;
            if (progress < 1) {
                requestAnimationFrame(fadeOut);
            } else {
                flashMessage.remove();
            }
        };
        setTimeout(() => {
            requestAnimationFrame(fadeOut);
        }, delay);
        flashMessage.addEventListener('click', () => {
            flashMessage.remove();
        }, {
            once: true
        });
    }
    // --- Додаткові глобальні обробники подій можуть бути тут ---
}

// --- Кінець app.js ---