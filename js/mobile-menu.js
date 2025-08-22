// ===============================================
// Мобильное меню с адаптацией для iOS
// ===============================================

class MobileMenu {
    constructor() {
        this.menuButton = document.getElementById('menu-btn');
        this.closeButton = document.getElementById('close-btn');
        this.sideMenu = document.getElementById('side-menu');
        this.overlay = null;
        this.isOpen = false;
        
        this.init();
    }
    
    init() {
        if (!this.menuButton || !this.sideMenu || !this.closeButton) {
            console.warn('Mobile menu elements not found');
            return;
        }
        
        // Создаем оверлей
        this.createOverlay();
        
        // Добавляем обработчики событий
        this.menuButton.addEventListener('click', () => this.toggle());
        this.closeButton.addEventListener('click', () => this.close());
        this.overlay.addEventListener('click', () => this.close());
        
        // Обработка клавиши Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
        
        // Закрытие меню при изменении ориентации на мобильных
        window.addEventListener('orientationchange', () => {
            if (this.isOpen) {
                setTimeout(() => this.close(), 100);
            }
        });
        
        // Поддержка свайпа для закрытия меню
        this.addSwipeSupport();
        
        // Предотвращение скролла body когда меню открыто
        this.preventBodyScroll();
    }
    
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-40 opacity-0 pointer-events-none transition-opacity duration-300';
        this.overlay.id = 'menu-overlay';
        document.body.appendChild(this.overlay);
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        if (this.isOpen) return;
        
        this.isOpen = true;
        
        // Показываем оверлей
        this.overlay.classList.remove('pointer-events-none');
        this.overlay.classList.remove('opacity-0');
        this.overlay.classList.add('opacity-100');
        
        // Анимируем меню
        this.sideMenu.classList.remove('-translate-x-full');
        this.sideMenu.classList.add('translate-x-0');
        
        // Блокируем скролл
        document.body.style.overflow = 'hidden';
        
        // Добавляем класс для body
        document.body.classList.add('menu-open');
        
        // Фокус на первый элемент меню для доступности
        const firstMenuItem = this.sideMenu.querySelector('a, button');
        if (firstMenuItem) {
            setTimeout(() => firstMenuItem.focus(), 300);
        }
        
        // Добавляем анимацию появления элементов меню
        this.animateMenuItems();
    }
    
    close() {
        if (!this.isOpen) return;
        
        this.isOpen = false;
        
        // Скрываем оверлей
        this.overlay.classList.add('opacity-0');
        this.overlay.classList.remove('opacity-100');
        setTimeout(() => {
            this.overlay.classList.add('pointer-events-none');
        }, 300);
        
        // Анимируем меню
        this.sideMenu.classList.add('-translate-x-full');
        this.sideMenu.classList.remove('translate-x-0');
        
        // Разблокируем скролл
        document.body.style.overflow = '';
        
        // Убираем класс с body
        document.body.classList.remove('menu-open');
        
        // Возвращаем фокус на кнопку меню
        setTimeout(() => {
            if (this.menuButton) {
                this.menuButton.focus();
            }
        }, 300);
    }
    
    animateMenuItems() {
        const menuItems = this.sideMenu.querySelectorAll('nav a, nav button');
        
        menuItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-10px)';
            
            setTimeout(() => {
                item.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 150 + (index * 30));
        });
    }
    
    addSwipeSupport() {
        let startX = 0;
        let startY = 0;
        
        document.addEventListener('touchstart', (e) => {
            if (this.isOpen) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            }
        });
        
        document.addEventListener('touchend', (e) => {
            if (this.isOpen) {
                const endX = e.changedTouches[0].clientX;
                const endY = e.changedTouches[0].clientY;
                const deltaX = startX - endX;
                const deltaY = Math.abs(startY - endY);
                
                // Если свайп влево больше 50px и вертикальное движение меньше 100px
                if (deltaX > 50 && deltaY < 100) {
                    this.close();
                }
            }
        });
    }
    
    preventBodyScroll() {
        // Предотвращаем скролл на iOS при открытом меню
        let startY = 0;
        
        document.addEventListener('touchstart', (e) => {
            if (this.isOpen && this.sideMenu.contains(e.target)) {
                startY = e.touches[0].clientY;
            }
        });
        
        document.addEventListener('touchmove', (e) => {
            if (this.isOpen) {
                if (!this.sideMenu.contains(e.target)) {
                    e.preventDefault();
                } else {
                    // Разрешаем скролл только внутри меню
                    const currentY = e.touches[0].clientY;
                    const menuScrollTop = this.sideMenu.scrollTop;
                    const menuScrollHeight = this.sideMenu.scrollHeight;
                    const menuClientHeight = this.sideMenu.clientHeight;
                    
                    if ((menuScrollTop === 0 && currentY > startY) ||
                        (menuScrollTop + menuClientHeight >= menuScrollHeight && currentY < startY)) {
                        e.preventDefault();
                    }
                }
            }
        }, { passive: false });
    }
}

// Инициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', () => {
    window.mobileMenu = new MobileMenu();
});

// Утилиты для работы с мобильным меню
window.MobileMenuUtils = {
    addMenuItem: function(text, href, icon, position = 'append') {
        const menu = document.querySelector('#side-menu nav');
        if (!menu) return;
        
        const menuItem = document.createElement('a');
        menuItem.href = href;
        menuItem.className = 'flex items-center text-white hover:text-gray-300 py-2 px-1 rounded transition-colors';
        menuItem.innerHTML = `<i class="fas ${icon} mr-3"></i> ${text}`;
        
        if (position === 'prepend') {
            menu.insertBefore(menuItem, menu.firstChild);
        } else {
            menu.appendChild(menuItem);
        }
        
        return menuItem;
    },
    
    removeMenuItem: function(href) {
        const menuItem = document.querySelector(`#side-menu nav a[href="${href}"]`);
        if (menuItem) {
            menuItem.remove();
        }
    },
    
    updateUserInfo: function(name, role) {
        const nameElement = document.querySelector('#side-menu .font-semibold');
        const roleElement = document.querySelector('#side-menu .text-gray-400');
        
        if (nameElement) nameElement.textContent = name;
        if (roleElement) roleElement.textContent = role;
    }
};
