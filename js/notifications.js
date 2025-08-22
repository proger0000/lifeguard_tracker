// ===============================================
// Система уведомлений с адаптацией под iOS
// ===============================================

class NotificationSystem {
    constructor() {
        this.container = null;
        this.notifications = new Map();
        this.defaultDuration = 5000;
        this.maxNotifications = 5;
        
        this.init();
    }
    
    init() {
        this.createContainer();
        this.setupStyles();
    }
    
    createContainer() {
        // Удаляем существующий контейнер, если есть
        const existing = document.getElementById('notification-container');
        if (existing) {
            existing.remove();
        }
        
        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = 'fixed pointer-events-none';
        
        // Позиционирование с учетом safe area и навигационной панели
        this.container.style.cssText = `
            top: calc(4rem + var(--safe-area-inset-top, 0px));
            right: calc(1rem + var(--safe-area-inset-right, 0px));
            left: calc(1rem + var(--safe-area-inset-left, 0px));
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            z-index: 9999;
        `;
        
        document.body.appendChild(this.container);
    }
    
    setupStyles() {
        // Добавляем стили для уведомлений, если их еще нет
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideInFromTop {
                    from {
                        opacity: 0;
                        transform: translateY(-100%) scale(0.95);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }
                
                @keyframes slideOutToTop {
                    from {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                    to {
                        opacity: 0;
                        transform: translateY(-100%) scale(0.95);
                    }
                }
                
                .notification-enter {
                    animation: slideInFromTop 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .notification-exit {
                    animation: slideOutToTop 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                
                .notification-progress {
                    animation: progressBar var(--duration, 5000ms) linear forwards;
                }
                
                @keyframes progressBar {
                    from { width: 100%; }
                    to { width: 0%; }
                }
                
                /* Специальные стили для iOS */
                @supports (-webkit-touch-callout: none) {
                    .notification {
                        backdrop-filter: blur(20px);
                        -webkit-backdrop-filter: blur(20px);
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    show(message, type = 'info', options = {}) {
        const config = {
            duration: options.duration || this.defaultDuration,
            closable: options.closable !== false,
            actions: options.actions || [],
            persistent: options.persistent || false,
            ...options
        };
        
        // Ограничиваем количество уведомлений
        if (this.notifications.size >= this.maxNotifications) {
            const firstKey = this.notifications.keys().next().value;
            this.hide(firstKey);
        }
        
        const notification = this.createNotification(message, type, config);
        const id = this.generateId();
        
        this.notifications.set(id, notification);
        this.container.appendChild(notification.element);
        
        // Запускаем анимацию входа
        requestAnimationFrame(() => {
            notification.element.classList.add('notification-enter');
        });
        
        // Автоматическое скрытие
        if (!config.persistent && config.duration > 0) {
            notification.timer = setTimeout(() => {
                this.hide(id);
            }, config.duration);
        }
        
        return id;
    }
    
    createNotification(message, type, config) {
        const element = document.createElement('div');
        element.className = `notification pointer-events-auto mb-3 rounded-lg shadow-lg overflow-hidden transition-all duration-300 ${this.getTypeClasses(type)}`;
        
        const iconHtml = this.getTypeIcon(type);
        const actionsHtml = this.createActionsHtml(config.actions);
        const closeButtonHtml = config.closable ? `
            <button type="button" class="notification-close ml-2 text-current opacity-70 hover:opacity-100 transition-opacity">
                <i class="fas fa-times"></i>
            </button>
        ` : '';
        
        const progressBarHtml = !config.persistent && config.duration > 0 ? `
            <div class="notification-progress-container absolute bottom-0 left-0 right-0 h-1 bg-black bg-opacity-20">
                <div class="notification-progress h-full bg-white bg-opacity-60" style="--duration: ${config.duration}ms;"></div>
            </div>
        ` : '';
        
        element.innerHTML = `
            <div class="relative">
                <div class="flex items-start p-4">
                    <div class="flex-shrink-0">
                        ${iconHtml}
                    </div>
                    <div class="ml-3 flex-1">
                        <div class="font-comfortaa text-sm font-medium">
                            ${this.escapeHtml(message)}
                        </div>
                        ${actionsHtml}
                    </div>
                    ${closeButtonHtml}
                </div>
                ${progressBarHtml}
            </div>
        `;
        
        // Добавляем обработчики событий
        if (config.closable) {
            const closeBtn = element.querySelector('.notification-close');
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = this.findNotificationId(element);
                if (id) this.hide(id);
            });
        }
        
        // Обработчики для действий
        config.actions.forEach((action, index) => {
            const actionBtn = element.querySelector(`[data-action-index="${index}"]`);
            if (actionBtn && action.handler) {
                actionBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    action.handler();
                    if (action.closeOnClick !== false) {
                        const id = this.findNotificationId(element);
                        if (id) this.hide(id);
                    }
                });
            }
        });
        
        return {
            element,
            timer: null,
            config
        };
    }
    
    hide(id) {
        const notification = this.notifications.get(id);
        if (!notification) return;
        
        // Очищаем таймер
        if (notification.timer) {
            clearTimeout(notification.timer);
        }
        
        // Анимация выхода
        notification.element.classList.remove('notification-enter');
        notification.element.classList.add('notification-exit');
        
        setTimeout(() => {
            if (notification.element.parentNode) {
                notification.element.remove();
            }
            this.notifications.delete(id);
        }, 300);
    }
    
    hideAll() {
        this.notifications.forEach((_, id) => {
            this.hide(id);
        });
    }
    
    getTypeClasses(type) {
        const classes = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-yellow-500 text-white',
            info: 'bg-blue-500 text-white',
            default: 'bg-gray-800 text-white'
        };
        
        return classes[type] || classes.default;
    }
    
    getTypeIcon(type) {
        const icons = {
            success: '<i class="fas fa-check-circle text-lg"></i>',
            error: '<i class="fas fa-exclamation-triangle text-lg"></i>',
            warning: '<i class="fas fa-exclamation-circle text-lg"></i>',
            info: '<i class="fas fa-info-circle text-lg"></i>',
            default: '<i class="fas fa-bell text-lg"></i>'
        };
        
        return icons[type] || icons.default;
    }
    
    createActionsHtml(actions) {
        if (!actions || actions.length === 0) return '';
        
        const actionButtons = actions.map((action, index) => `
            <button type="button" 
                    class="btn btn-sm ${action.className || 'btn-outline'} mr-2 mt-2" 
                    data-action-index="${index}">
                ${action.icon ? `<i class="${action.icon} mr-1"></i>` : ''}
                ${this.escapeHtml(action.text)}
            </button>
        `).join('');
        
        return `<div class="mt-2">${actionButtons}</div>`;
    }
    
    generateId() {
        return 'notification_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    findNotificationId(element) {
        for (const [id, notification] of this.notifications.entries()) {
            if (notification.element === element) {
                return id;
            }
        }
        return null;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Публичные методы для удобства
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }
    
    error(message, options = {}) {
        return this.show(message, 'error', options);
    }
    
    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }
    
    info(message, options = {}) {
        return this.show(message, 'info', options);
    }
    
    // Специальный метод для отображения flash-сообщений из PHP
    showFlashMessage(type, message) {
        // Преобразуем типы из PHP в типы нашей системы
        const typeMap = {
            'успіх': 'success',
            'помилка': 'error',
            'інфо': 'info',
            'увага': 'warning'
        };
        
        const notificationType = typeMap[type] || 'info';
        return this.show(message, notificationType, { duration: 6000 });
    }
}

// Инициализация глобальной системы уведомлений
document.addEventListener('DOMContentLoaded', () => {
    window.notifications = new NotificationSystem();
    
    // Ищем и обрабатываем существующие flash-сообщения
    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        const type = flashMessage.classList.contains('bg-green-50') ? 'успіх' :
                    flashMessage.classList.contains('bg-red-50') ? 'помилка' :
                    flashMessage.classList.contains('bg-yellow-50') ? 'увага' : 'інфо';
        
        const messageText = flashMessage.querySelector('span').textContent.trim();
        
        // Скрываем стандартное сообщение и показываем через нашу систему
        flashMessage.style.display = 'none';
        setTimeout(() => {
            window.notifications.showFlashMessage(type, messageText);
        }, 500);
    }
});

// Экспорт для использования в других модулях
window.NotificationSystem = NotificationSystem;
