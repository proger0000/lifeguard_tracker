/**
 * Файл: assets/js/award_points.js
 * Описание: Управляет модальным окном начисления баллов.
 * ВЕРСИЯ 4.1 (ИСПРАВЛЕН ПУТЬ) - Работает с существующим HTML, обновляя его данными с сервера.
 */
'use strict';

document.addEventListener('DOMContentLoaded', function() {
    console.log('award_points.js v4.1 initialized');

    const modal = document.getElementById('awardPointsModal');
    const closeBtn = document.getElementById('closeAwardPointsModal');
    const form = document.getElementById('awardPointsForm');
    const modalShiftIdSpan = document.getElementById('modalShiftId');
    const modalShiftIdInput = document.getElementById('modalShiftIdInput');
    let currentShiftId = null;

    if (!modal || !closeBtn || !form) {
        console.error('Modal elements not found!');
        return;
    }

    /**
     * Открывает модальное окно и динамически обновляет его содержимое.
     * @param {number} shiftId - ID смены.
     */
    window.openAwardPointsModal = function(shiftId) {
        currentShiftId = shiftId;
        modalShiftIdSpan.textContent = '#' + currentShiftId;
        modalShiftIdInput.value = currentShiftId;

        form.reset();
        modal.classList.remove('hidden');

        // --- ИСПРАВЛЕНИЕ ЗДЕСЬ ---
        // Убираем 'admin/' из пути. Теперь путь будет строиться относительно
        // текущей страницы (manage_shifts.php), что правильно.
        fetch(`ajax_get_shift_points_data.php?shift_id=${currentShiftId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Помилка мережі: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    alert('Помилка завантаження даних: ' + data.error);
                    return;
                }

                // Обновляем отображаемые баллы
                if (data.calculated_points) {
                    for (const ruleId in data.calculated_points) {
                        const points = data.calculated_points[ruleId];
                        const pointsFormatted = (points >= 0 ? '+' : '') + points;

                        const inputElement = form.querySelector(`input[name="points[${ruleId}][awarded]"]`);
                        if (inputElement) {
                            const label = inputElement.closest('label');
                            if (label) {
                                const pointsSpan = label.querySelector('span > span');
                                if (pointsSpan) {
                                    pointsSpan.textContent = `(${pointsFormatted})`;
                                    pointsSpan.classList.toggle('text-red-500', points < 0);
                                    pointsSpan.classList.toggle('text-green-600', points >= 0);
                                }
                            }
                        }
                    }
                }

                // Отмечаем сохраненные чекбоксы и комментарии
                if (data.checked_rules) {
                    for (const ruleId in data.checked_rules) {
                        const checkbox = form.querySelector(`input[name="points[${ruleId}][awarded]"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }

                        const commentInput = form.querySelector(`input[name="points[${ruleId}][comment]"]`);
                        if (commentInput && data.checked_rules[ruleId].comment) {
                            commentInput.value = data.checked_rules[ruleId].comment;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching shift points data:', error);
                alert('Не вдалося завантажити дані про бали. Перевірте консоль розробника (F12) для деталей.');
            });
    };

    // Обработчики закрытия и отправки формы
    closeBtn.addEventListener('click', () => modal.classList.add('hidden'));

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch('ajax_award_points.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Помилка збереження: ' + (data.error || 'Невідома помилка'));
                }
            })
            .catch(err => alert('Помилка мережі при збереженні.'));
    });

    // Делегирование событий для кнопок "Начислить баллы"
    document.body.addEventListener('click', function(event) {
        const awardButton = event.target.closest('.award-points-btn');
        if (awardButton) {
            const shiftId = awardButton.dataset.shiftId;
            if (shiftId) {
                window.openAwardPointsModal(shiftId);
            }
        }
    });
});