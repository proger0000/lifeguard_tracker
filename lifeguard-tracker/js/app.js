 // ===============================================
 // Файл: js/app.js
 // ===============================================

 'use strict'; // Включаємо строгий режим для кращого контролю помилок

 /**
  * -------------------------------------------
  *            ДОПОМІЖНІ ФУНКЦІЇ
  * -------------------------------------------
  */

 /**
  * Екранує спеціальні символи HTML у рядку для безпечного виведення.
  * @param {*} str - Вхідне значення для екранування.
  * @returns {string} Безпечний для HTML рядок.
  */
 function escapeHtml(str) {
     if (str === null || typeof str === 'undefined') return '';
     const stringValue = String(str);
     const map = {
         '&': '&',
         '<': '<',
         '>': '>',
         '"': '"',
         "'": '''
     };
     const regex = /[&<>"']/g;
     return stringValue.replace(regex, (match) => map[match]);
 }

 /**
  * Форматує час (HH:MM).
  * @param {string|null} timeString - Вхідний рядок часу.
  * @returns {string} Форматований час HH:MM або '-'.
  */
 function formatModalTime(timeString) {
     if (!timeString || typeof timeString !== 'string') return '-';
     try {
         const parts = timeString.split(':');
         if (parts.length >= 2) {
             const hours = parts[0].padStart(2, '0');
             const minutes = parts[1].padStart(2, '0');
             return `${hours}:${minutes}`;
         }
     } catch (e) { console.error("Error formatting time:", timeString, e); }
     return escapeHtml(timeString);
 }

 /**
  * Форматує дату-час (dd.mm.yyyy hh:ii) за київським часом.
  * @param {string|null} dateTimeString - Вхідний рядок дати-часу.
  * @returns {string} Відформатована дата-час або '-'.
  */
 function formatModalDateTime(dateTimeString) {
      if (!dateTimeString) return '-';
     try {
         const dateStr = dateTimeString.includes('Z') ? dateTimeString : dateTimeString.replace(' ', 'T') + 'Z';
         const date = new Date(dateStr);
         if (isNaN(date.getTime())) { return escapeHtml(dateTimeString); }
         const options = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Europe/Kiev' };
         return date.toLocaleString('uk-UA', options);
     } catch (e) { console.error("Error formatting date-time:", dateTimeString, e); }
     return escapeHtml(dateTimeString);
 }

 /**
  * Форматує тривалість (Х год Y хв).
  * @param {string|null} startString - Дата-час початку.
  * @param {string|null} endString - Дата-час кінця.
  * @returns {string} Форматована тривалість або '-'.
  */
 function formatDuration(startString, endString) {
      if (!startString || !endString) return '-';
      try {
         const start = new Date(startString.includes('Z') ? startString : startString.replace(' ', 'T') + 'Z');
         const end = new Date(endString.includes('Z') ? endString : endString.replace(' ', 'T') + 'Z');
         if (isNaN(start.getTime()) || isNaN(end.getTime())) return '-';
         const diffMs = end - start;
         if (diffMs < 0) return '-';
         const diffMinsTotal = Math.round(diffMs / 60000);
         const hours = Math.floor(diffMinsTotal / 60);
         const minutes = diffMinsTotal % 60;
         let result = '';
         if (hours > 0) result += `${hours} год `;
         result += `${minutes} хв`;
         return result.trim();
       } catch(e) { console.error("Error calculating duration:", startString, endString, e); }
      return '-';
  }

 /**
  * Отримує переклад для ключа.
  * @param {string} key - Ключ для перекладу.
  * @param {string|null} [fallback=null] - Значення за замовчуванням.
  * @returns {string} Переклад або ключ/fallback.
  */
 function getTranslation(key, fallback = null) {
     const translations = {
         ...(typeof incidentFieldTranslations === 'object' && incidentFieldTranslations !== null ? incidentFieldTranslations : {}),
         ...(typeof statsTranslations === 'object' && statsTranslations !== null ? statsTranslations : {})
     };
     if (typeof key === 'object' && key !== null) { return fallback !== null ? fallback : JSON.stringify(key); }
     const keyString = String(key);
     return translations[keyString] || (fallback !== null ? fallback : keyString);
 }

 /**
  * -------------------------------------------
  *          ПЕРЕМИКАННЯ ВКЛАДОК
  * -------------------------------------------
  */
 function switchTabGeneral(allContentIds, allButtonIds, activeContentId, activeButtonId) {
      let activeContentFound = false;
      let activeButtonFound = false;
      allContentIds.forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
      allButtonIds.forEach(id => { const btn = document.getElementById(id); if (btn) { btn.classList.remove('border-red-500', 'text-red-600'); btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300'); } });
      const contentEl = document.getElementById(activeContentId); if (contentEl) { contentEl.style.display = 'block'; activeContentFound = true; } else { console.warn(`Content element #${activeContentId} not found.`); }
      const buttonEl = document.getElementById(activeButtonId); if (buttonEl) { buttonEl.classList.add('border-red-500', 'text-red-600'); buttonEl.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300'); activeButtonFound = true; } else { console.warn(`Button element #${activeButtonId} not found.`); }
 }

 function showAdminTab(tabName) {
      const map = { 'posts': {c:'admin-posts-content', b:'admin-posts-tab'}, 'users': {c:'admin-users-content', b:'admin-users-tab'}, 'duty': {c:'admin-duty-content', b:'admin-duty-tab'} };
      const active = map[tabName];
      if (active) switchTabGeneral(Object.values(map).map(v=>v.content), Object.values(map).map(v=>v.button), active.content, active.button);
      else console.error(`showAdminTab: Unknown tab name: ${tabName}`);
 }

 function showLifeguardTab(tabName) {
     const map = { 'current': {c:'lifeguard-current-content', b:'lifeguard-current-tab'}, 'history': {c:'lifeguard-history-content', b:'lifeguard-history-tab'} };
     const active = map[tabName];
     if (active) switchTabGeneral(Object.values(map).map(v=>v.content), Object.values(map).map(v=>v.button), active.content, active.button);
     else console.error(`showLifeguardTab: Unknown tab name: ${tabName}`);
 }


 /**
  * -------------------------------------------
  *          КОПІЮВАННЯ URL NFC
  * -------------------------------------------
  */
 function copyToClipboardEnhanced(elementId, buttonElement) {
     const urlElement = document.getElementById(elementId);
     if (!urlElement || !navigator.clipboard) {
         console.error(`copyToClipboardEnhanced: Element '#${elementId}' not found or Clipboard API not supported.`);
         alert('Помилка копіювання: елемент не знайдено або функція не підтримується.');
         return;
     }
     const textToCopy = urlElement.innerText;
     const elementBaseId = elementId.startsWith('nfc-url-') ? elementId.substring(8) : elementId;
     const copyIcon = document.getElementById(`copy-icon-${elementBaseId}`);
     const copiedIcon = document.getElementById(`copied-icon-${elementBaseId}`);
     const copiedMessage = document.getElementById(`copied-message-${elementBaseId}`);
     const allElementsFound = copyIcon && copiedIcon && copiedMessage;

     navigator.clipboard.writeText(textToCopy).then(() => {
         if (allElementsFound) {
             copyIcon.classList.add('hidden');
             copiedIcon.classList.remove('hidden');
             copiedMessage.classList.remove('hidden');
             if (buttonElement) buttonElement.disabled = true;
             setTimeout(() => {
                 copyIcon.classList.remove('hidden');
                 copiedIcon.classList.add('hidden');
                 copiedMessage.classList.add('hidden');
                 if (buttonElement) buttonElement.disabled = false;
             }, 2000);
         } else {
              const toast = document.getElementById('toast-notification');
              if(toast){
                   toast.textContent = 'URL скопійовано!';
                  toast.classList.add('show');
                  setTimeout(() => {
                       toast.classList.remove('show');
                       toast.textContent = 'URL скопійовано до буферу обміну!';
                  }, 3000);
              } else { alert('URL скопійовано!'); }
           }
     }).catch(err => {
         console.error('copyToClipboardEnhanced: Error: ', err);
         alert('Не вдалося скопіювати URL.');
     });
 }

 /**
  * -------------------------------------------
  *          МОДАЛЬНЕ ВІКНО ЗВІТІВ
  * -------------------------------------------
  */
 let modal = null, modalOverlay = null, modalCloseButton = null, modalReportIdSpan = null,
     modalGeneralInfoDiv = null, modalStatsDiv = null, modalGeneralNotesP = null, modalIncidentsDiv = null;

 function openReportModal(reportId) {
      if (typeof reportsData==='undefined'||typeof incidentsData==='undefined'|| typeof incidentFieldTranslations === 'undefined'||typeof statsTranslations === 'undefined') { console.error("Необхідні дані (reportsData...) не визначені."); alert("Помилка: Немає даних."); return; }
      if (!modal) { console.error("Елемент Модалки не знайдено."); alert("Помилка UI."); return; }
      const report = reportsData.find(r => r.report_id == reportId);
      const incidents = incidentsData[reportId] || [];
      if (!report) { console.error(`Звіт ${reportId} не знайдено.`); alert(`Звіт №${reportId} не знайдено.`); return; }

      if(modalReportIdSpan) modalReportIdSpan.textContent = report.report_id;
      if(modalGeneralInfoDiv) {
           modalGeneralInfoDiv.innerHTML = `
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm">
                 <div><strong class="font-semibold text-gray-700">Пост:</strong> ${escapeHtml(report.post_name || '-')}</div>
                 <div><strong class="font-semibold text-gray-700">Лайфгард:</strong> ${escapeHtml(report.reporter_name || '-')}</div>
                 <div><strong class="font-semibold text-gray-700">Дата звіту:</strong> ${formatModalDateTime(report.report_submitted_at) || '-'}</div>
                 <div><strong class="font-semibold text-gray-700">Тривалість зміни:</strong> ${formatDuration(report.start_time, report.end_time) || '-'}</div>
              </div>`;
       }

      if(modalStatsDiv) {
           modalStatsDiv.innerHTML = ''; let statsHtml = '';
          if(typeof statsTranslations === 'object' && statsTranslations !== null) {
              for (const key in statsTranslations) {
                 if (report.hasOwnProperty(key)) {
                      let valueDisplay;
                     if (report[key] !== null && typeof report[key] !== 'object') { valueDisplay = escapeHtml(String(report[key])); }
                     else if (report[key] === null) { valueDisplay = '<i class="text-gray-400">0</i>'; }
                      else { valueDisplay = '[Object]'; } // Якщо все ж потрапив об'єкт
                     const label = escapeHtml(getTranslation(key, key)); // Використання getTranslation
                     statsHtml += `<div class="bg-gray-50 p-2 rounded border border-gray-200 text-xs"><strong class="font-semibold text-gray-700 block sm:inline">${label}:</strong> ${valueDisplay}</div>`;
                 }
             }
         }
         modalStatsDiv.innerHTML = statsHtml || '<p class="text-xs text-gray-500 col-span-full">Статистика відсутня.</p>';
      }

       const notesContainer = modalGeneralNotesP ? modalGeneralNotesP.parentElement : null;
      if (notesContainer) {
          if (report.general_notes && report.general_notes.trim() !== '') {
               modalGeneralNotesP.textContent = escapeHtml(report.general_notes);
               notesContainer.style.display = 'block';
           } else { notesContainer.style.display = 'none'; }
       }

      if (modalIncidentsDiv) {
          modalIncidentsDiv.innerHTML = '<h4 class="text-md font-semibold text-gray-700 mb-2">Зафіксовані інциденти:</h4>';
         if (incidents.length === 0) {
             modalIncidentsDiv.innerHTML += '<p class="text-gray-500 italic text-sm">Інцидентів не зафіксовано.</p>';
         } else {
              incidents.forEach((incident, index) => {
                 const incidentDiv = document.createElement('div');
                 incidentDiv.className = 'incident-detail-item border-t border-gray-200 pt-3 mt-3';
                  const incidentTypeTranslated = getTranslation(incident.incident_type, incident.incident_type);
                  incidentDiv.innerHTML = `<h5 class="font-semibold text-gray-800 mb-2">${escapeHtml(incidentTypeTranslated)} #${index + 1}</h5>`;

                 const detailsList = document.createElement('dl');
                 detailsList.className = 'space-y-1 text-xs pl-2';

                  for (const key in incident) {
                     if (key === 'id' || key === 'shift_report_id' || key === 'incident_type' || incident[key] === null || incident[key] === '') continue;

                      let value = incident[key];
                     let valueHtml = '';
                     let labelHtml = getTranslation(key, key); // Переклад НАЗВИ поля

                      // Форматування/переклад ЗНАЧЕНЬ
                      if ((key === 'cause_details' || key === 'actions_taken') && typeof value === 'string') { // JSON Поля
                         try {
                             const items = JSON.parse(value);
                              if (Array.isArray(items) && items.length > 0) {
                                  valueHtml = '<ul class="list-disc list-inside ml-4 space-y-0.5">';
                                  items.forEach(itemValue => {
                                     const translationKey = key + '_' + itemValue; // Формуємо ключ для значення
                                      const itemTranslated = getTranslation(translationKey, String(itemValue)); // Перекладаємо
                                      valueHtml += `<li>${escapeHtml(itemTranslated)}</li>`;
                                  });
                                  valueHtml += '</ul>';
                              } else { continue; }
                          } catch (e) { valueHtml = escapeHtml(String(value)); } // Якщо не JSON
                      } else if (key === 'subject_gender' || key === 'outcome_details' || key === 'result') { // Одиничні значення
                           valueHtml = escapeHtml(getTranslation(String(value), String(value))); // Переклад значення
                      } else if (key === 'incident_time') { valueHtml = formatModalTime(String(value)); }
                       else if (key === 'involved_lifeguard_name' && value) { labelHtml = getTranslation('involved_lifeguard_id'); valueHtml = escapeHtml(String(value)); }
                       else if (key === 'involved_lifeguard_id') {
                           const hasNameSibling = incident.hasOwnProperty('involved_lifeguard_name') && incident.involved_lifeguard_name;
                          if (!hasNameSibling) {
                              labelHtml = getTranslation('involved_lifeguard_id');
                              const lgName = (typeof lifeguards_list_js === 'object' && lifeguards_list_js) ? lifeguards_list_js[value] : null;
                              valueHtml = escapeHtml(lgName || `(ID: ${value})`);
                           } else { continue; }
                       }
                      else { valueHtml = escapeHtml(String(value)); } // Решта

                       detailsList.innerHTML += `<div class="flex"><dt class="w-1/2 sm:w-2/5 font-medium text-gray-600 shrink-0 pr-1">${labelHtml}:</dt><dd class="w-1/2 sm:w-3/5 text-gray-800 break-words">${valueHtml}</dd></div>`;
                  }
                  incidentDiv.appendChild(detailsList);
                  modalIncidentsDiv.appendChild(incidentDiv);
              });
           }
       }

       if (modal) modal.classList.remove('hidden');
       else console.error("Modal element not found when trying to show.");
   }

  function closeReportModal() {
      if (modal) modal.classList.add('hidden');
       // Очищення полів
      if (modalReportIdSpan) modalReportIdSpan.textContent = '';
      if (modalGeneralInfoDiv) modalGeneralInfoDiv.innerHTML = '';
      if (modalStatsDiv) modalStatsDiv.innerHTML = '';
       if (modalGeneralNotesP && modalGeneralNotesP.parentElement) { modalGeneralNotesP.textContent = ''; modalGeneralNotesP.parentElement.style.display = 'none'; }
       if (modalIncidentsDiv) modalIncidentsDiv.innerHTML = '';
  }

 /**
  * -------------------------------------------
  *  ЛОГІКА ДИНАМІЧНИХ ІНЦИДЕНТІВ ФОРМИ ЗВІТУ
  * -------------------------------------------
  */
 const incidentCounters = { medical_aid: 0, lost_child: 0, critical_swimmer: 0, police_call: 0, ambulance_call: 0, other: 0 };

 function addIncident(type) {
      const template = document.getElementById(`template-${type}`);
      const listContainer = document.getElementById(`incidents-${type}-list`);
      const initialText = listContainer ? listContainer.querySelector('.initial-text') : null;
      if (!template || !listContainer) { console.error(`AddIncident Error: Missing template or container for type "${type}"`); return; }
      if (initialText) initialText.remove();
      const index = incidentCounters[type]++;
      const index_display = index + 1;
      const clone = template.content.cloneNode(true); // Важливо клонувати content
      if (!clone.firstElementChild) { console.error("Template content is empty!"); return; }

       // Оновлення атрибутів та вмісту плейсхолдерів
      clone.querySelectorAll('*').forEach(el => {
           ['name', 'id', 'for'].forEach(attr => {
               if(el.hasAttribute(attr) && el.getAttribute(attr).includes('{index}')) { el.setAttribute(attr, el.getAttribute(attr).replace(/\{index\}/g, index)); }
           });
           if (el.matches('input[name*="[{index}]"]') || el.matches('select[name*="[{index}]"]') || el.matches('textarea[name*="[{index}]"]')) {
               el.name = el.name.replace('{index}', index);
            }
           if (el.textContent.includes('{index_display}')) { el.textContent = el.textContent.replace('{index_display}', index_display); }
           const h5 = el.querySelector('h5'); // Заміна {index_display} у заголовку H5
           if (h5 && h5.textContent.includes('{index_display}')) { h5.textContent = h5.textContent.replace('{index_display}', index_display); }
      });
       // Додаємо кнопку видалення програмно, щоб гарантувати атрибут data-index
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.onclick = function() { removeIncident(this); }; // Прив'язуємо функцію
      removeButton.className = 'absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl font-bold focus:outline-none';
       removeButton.title = 'Видалити цей випадок';
      removeButton.innerHTML = '×'; // × символ
      clone.firstElementChild.appendChild(removeButton); // Додаємо кнопку до .incident-item
       clone.firstElementChild.dataset.index = index; // Зберігаємо індекс для довідки (не використовується)

       // Застосування стилів до полів (викликаємо після всіх змін)
       applyInputStylesToBlock(clone.firstElementChild);

      listContainer.appendChild(clone); // Додаємо шаблон у DOM
 }

 function removeIncident(button) {
      const incidentItem = button.closest('.incident-item');
      if (incidentItem) {
         const listContainer = incidentItem.parentNode;
          incidentItem.remove();
          if (listContainer && listContainer.children.length === 0) {
              const initialTextParagraph = document.createElement('p');
              initialTextParagraph.className = 'text-sm text-gray-500 italic initial-text';
               initialTextParagraph.textContent = 'Не було зафіксовано випадків цього типу.';
               listContainer.appendChild(initialTextParagraph);
          }
      }
 }

 function applyInputStylesToBlock(blockElement) {
      if (!blockElement) return;
      blockElement.querySelectorAll('input[type=text], input[type=number], input[type=tel], input[type=time], select, textarea').forEach(input => {
            input.classList.add('shadow-sm', 'appearance-none', 'block', 'w-full', 'px-3', 'border', 'border-gray-300', 'rounded-md', 'placeholder-gray-400', 'focus:outline-none', 'focus:ring-1', 'focus:ring-red-500', 'focus:border-red-500', 'sm:text-sm');
           if (input.tagName.toLowerCase() === 'textarea') { input.classList.add('py-1'); }
            else { input.classList.add('py-2'); }
            // Видаляємо маркерний клас, якщо він був
            input.classList.remove('incident-field');
       });
      blockElement.querySelectorAll('input[type=checkbox], input[type=radio]').forEach(input => {
           input.classList.add('h-4', 'w-4', 'text-red-600', 'border-gray-300', 'rounded', 'focus:ring-red-500', 'focus:ring-offset-0');
           input.classList.remove('form-checkbox', 'form-radio'); // Видалення старих класів
      });
 }


 /**
  * -------------------------------------------
  *   ІНІЦІАЛІЗАЦІЯ та ГЛОБАЛЬНІ ОБРОБНИКИ ПОДІЙ
  * -------------------------------------------
  */
 document.addEventListener('DOMContentLoaded', () => {
      // --- Ініціалізація Елементів Модального Вікна Звітів ---
     modal = document.getElementById('report-modal');
     modalOverlay = document.getElementById('modal-overlay');
     modalCloseButton = document.getElementById('modal-close-button');
     modalReportIdSpan = document.getElementById('modal-report-id');
     modalGeneralInfoDiv = document.getElementById('modal-general-info');
     modalStatsDiv = document.getElementById('modal-stats');
     modalGeneralNotesP = document.querySelector('#modal-general-notes p');
     modalIncidentsDiv = document.getElementById('modal-incidents');

      // --- Ініціалізація Вкладок ---
      const adminTabContainer = document.getElementById('adminTabContent');
      const lifeguardTabContainer = document.getElementById('lifeguardTabContent');

     // Важливо: Викликати функцію ТІЛЬКИ ЯКЩО відповідний контейнер існує
     if (adminTabContainer) {
           // console.log("Initializing Admin Tabs with 'duty'");
           try { showAdminTab('duty'); } catch(e){ console.error('Error initializing admin tabs:', e); }
       }
       if (lifeguardTabContainer) {
           // console.log("Initializing Lifeguard Tabs with 'current'");
           try { showLifeguardTab('current'); } catch(e){ console.error('Error initializing lifeguard tabs:', e); }
       }

      // --- Обробники Подій для Модалки ---
      if (modalOverlay) modalOverlay.addEventListener('click', closeReportModal);
      if (modalCloseButton) modalCloseButton.addEventListener('click', closeReportModal);
      document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) { closeReportModal(); }
      });

      // Можливо, додати тут інші ініціалізації чи глобальні слухачі

      // console.log("Application JS Initialized.");

 }); // --- Кінець DOMContentLoaded ---