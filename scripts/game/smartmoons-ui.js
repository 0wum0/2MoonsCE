/**
 * SmartMoons UI - Interactive JavaScript for Sci-Fi Theme
 * Handles burger menu, real-time updates, animations, and UI interactions
 */

(function() {
    'use strict';

    // =====================================================
    // 1. SIDEBAR & BURGER MENU HANDLER
    // =====================================================
    const SidebarMenu = {
        init() {
            this.bindEvents();
            this.handleResize();
            this.setInitialState();
        },

        bindEvents() {
            // Get elements
            const burgerToggle = document.getElementById('sidebar-toggle');
            const overlay = document.getElementById('sidebar-overlay');
            const sidebar = document.getElementById('leftmenu');
            
            if (burgerToggle && sidebar) {
                // Burger menu click
                burgerToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleSidebar();
                });

                // Overlay click (mobile only)
                if (overlay) {
                    overlay.addEventListener('click', () => {
                        this.closeSidebar();
                    });
                }

                // Close sidebar when clicking outside on desktop
                document.addEventListener('click', (e) => {
                    if (window.innerWidth > 992) {
                        const isClickInside = sidebar.contains(e.target) || 
                                            (burgerToggle && burgerToggle.contains(e.target));
                        if (!isClickInside && sidebar.classList.contains('active')) {
                            this.closeSidebar();
                        }
                    }
                });

                // Handle escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                        this.closeSidebar();
                    }
                });
            }
        },

        toggleSidebar() {
            const burgerToggle = document.getElementById('sidebar-toggle');
            const overlay = document.getElementById('sidebar-overlay');
            const sidebar = document.getElementById('leftmenu');
            const page = document.getElementById('page');
            
            if (sidebar) {
                const isActive = sidebar.classList.contains('active');
                
                if (isActive) {
                    this.closeSidebar();
                } else {
                    sidebar.classList.add('active');
                    sidebar.classList.remove('collapsed');
                    if (burgerToggle) burgerToggle.classList.add('active');
                    if (overlay) overlay.classList.add('active');
                    
                    // On mobile, prevent body scroll
                    if (window.innerWidth <= 992) {
                        document.body.style.overflow = 'hidden';
                    }
                    
                    // Adjust page margin on desktop
                    if (page && window.innerWidth > 992) {
                        page.style.marginLeft = '280px';
                    }
                }
                
                // Save state to localStorage
                localStorage.setItem('sidebar-state', sidebar.classList.contains('active') ? 'open' : 'closed');
            }
        },

        closeSidebar() {
            const burgerToggle = document.getElementById('sidebar-toggle');
            const overlay = document.getElementById('sidebar-overlay');
            const sidebar = document.getElementById('leftmenu');
            const page = document.getElementById('page');
            
            if (sidebar) {
                sidebar.classList.remove('active');
                sidebar.classList.add('collapsed');
                if (burgerToggle) burgerToggle.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
                
                if (page) {
                    page.style.marginLeft = '0';
                }
                
                localStorage.setItem('sidebar-state', 'closed');
            }
        },

        setInitialState() {
            const savedState = localStorage.getItem('sidebar-state');
            const sidebar = document.getElementById('leftmenu');
            const page = document.getElementById('page');
            
            if (sidebar) {
                // On desktop, restore saved state
                if (window.innerWidth > 992) {
                    if (savedState === 'open') {
                        sidebar.classList.add('active');
                        sidebar.classList.remove('collapsed');
                        if (page) page.style.marginLeft = '280px';
                    } else {
                        sidebar.classList.add('collapsed');
                        sidebar.classList.remove('active');
                        if (page) page.style.marginLeft = '0';
                    }
                } else {
                    // On mobile, always start closed
                    sidebar.classList.add('collapsed');
                    sidebar.classList.remove('active');
                    if (page) page.style.marginLeft = '0';
                }
            }
        },

        handleResize() {
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    this.setInitialState();
                }, 250);
            });
        }
    };

    // =====================================================
    // 2. RESOURCE TICKER ENHANCEMENT
    // =====================================================
    const ResourceTicker = {
        init() {
            this.enhanceResourceBars();
            this.addResourceAnimations();
        },

        enhanceResourceBars() {
            const bars = document.querySelectorAll('.bar');
            bars.forEach(bar => {
                const percentage = bar.style.width;
                if (percentage) {
                    this.animateBar(bar, percentage);
                }
            });
        },

        animateBar(bar, targetWidth) {
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                bar.style.width = targetWidth;
            }, 100);
        },

        addResourceAnimations() {
            const resourceElements = document.querySelectorAll('.res_current');
            resourceElements.forEach(element => {
                element.addEventListener('mouseenter', () => {
                    element.style.transform = 'scale(1.05)';
                });
                element.addEventListener('mouseleave', () => {
                    element.style.transform = 'scale(1)';
                });
            });
        }
    };

    // =====================================================
    // 3. PROGRESS BAR MANAGER
    // =====================================================
    const ProgressBarManager = {
        activeBars: new Map(),

        init() {
            this.findAndInitProgressBars();
            this.startUpdateLoop();
        },

        findAndInitProgressBars() {
            document.querySelectorAll('[data-progress]').forEach(element => {
                const endTime = parseInt(element.getAttribute('data-end-time'));
                const startTime = parseInt(element.getAttribute('data-start-time'));
                const type = element.getAttribute('data-progress-type') || 'default';
                
                if (endTime && startTime) {
                    this.activeBars.set(element, {
                        endTime,
                        startTime,
                        type
                    });
                    this.createProgressBar(element, type);
                }
            });
        },

        createProgressBar(container, type) {
            if (!container.querySelector('.progress-bar-wrapper')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'progress-bar-wrapper';
                wrapper.innerHTML = `
                    <div class="progress-bar ${type}">
                        <span class="progress-text">0%</span>
                    </div>
                `;
                container.appendChild(wrapper);
            }
        },

        updateProgressBar(element, data) {
            const now = Date.now() / 1000;
            const progress = Math.min(100, ((now - data.startTime) / (data.endTime - data.startTime)) * 100);
            
            const bar = element.querySelector('.progress-bar');
            const text = element.querySelector('.progress-text');
            
            if (bar && text) {
                bar.style.width = `${progress}%`;
                text.textContent = `${Math.floor(progress)}%`;
                
                if (progress >= 100) {
                    bar.classList.add('complete');
                    text.textContent = 'Complete!';
                    this.activeBars.delete(element);
                }
            }
        },

        startUpdateLoop() {
            setInterval(() => {
                this.activeBars.forEach((data, element) => {
                    this.updateProgressBar(element, data);
                });
            }, 1000);
        }
    };

    // =====================================================
    // 4. NOTIFICATION SYSTEM
    // =====================================================
    const NotificationSystem = {
        container: null,

        init() {
            this.createContainer();
        },

        createContainer() {
            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                z-index: 1000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
            `;
            document.body.appendChild(this.container);
        },

        show(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} slide-in`;
            notification.innerHTML = `
                <div class="alert-icon">
                    <i class="fas fa-${this.getIcon(type)}"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-message">${message}</div>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            this.container.appendChild(notification);
            
            if (duration > 0) {
                setTimeout(() => {
                    notification.style.animation = 'slide-out 0.3s ease-out';
                    setTimeout(() => notification.remove(), 300);
                }, duration);
            }
        },

        getIcon(type) {
            const icons = {
                info: 'info-circle',
                success: 'check-circle',
                warning: 'exclamation-triangle',
                error: 'times-circle'
            };
            return icons[type] || 'info-circle';
        }
    };

    // =====================================================
    // 5. MODAL HANDLER
    // =====================================================
    const ModalHandler = {
        init() {
            this.bindTriggers();
            this.bindCloseEvents();
        },

        bindTriggers() {
            document.querySelectorAll('[data-modal]').forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modalId = trigger.getAttribute('data-modal');
                    this.open(modalId);
                });
            });
        },

        bindCloseEvents() {
            document.querySelectorAll('.modal-close, .modal-overlay').forEach(element => {
                element.addEventListener('click', (e) => {
                    if (e.target === element) {
                        this.close(element.closest('.modal-overlay'));
                    }
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const activeModal = document.querySelector('.modal-overlay.active');
                    if (activeModal) {
                        this.close(activeModal);
                    }
                }
            });
        },

        open(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },

        close(modal) {
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    };

    // =====================================================
    // 6. TOOLTIP ENHANCER
    // =====================================================
    const TooltipEnhancer = {
        init() {
            this.enhanceExistingTooltips();
        },

        enhanceExistingTooltips() {
            document.querySelectorAll('.tooltip').forEach(element => {
                if (!element.querySelector('.tooltip-content')) {
                    const content = element.getAttribute('data-tooltip-content');
                    if (content) {
                        const tooltipEl = document.createElement('div');
                        tooltipEl.className = 'tooltip-content';
                        tooltipEl.innerHTML = content;
                        element.appendChild(tooltipEl);
                    }
                }
            });
        }
    };

    // =====================================================
    // 7. ANIMATION OBSERVER
    // =====================================================
    const AnimationObserver = {
        init() {
            if ('IntersectionObserver' in window) {
                this.observeElements();
            }
        },

        observeElements() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.sci-fi-card, .building-card, .research-card, .ship-card').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                observer.observe(el);
            });
        }
    };

    // =====================================================
    // 8. THEME MANAGER
    // =====================================================
    const ThemeManager = {
        init() {
            this.loadTheme();
            this.createThemeToggle();
        },

        loadTheme() {
            const savedTheme = localStorage.getItem('smartmoons-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        },

        createThemeToggle() {
            // Theme toggle can be added if needed for light/dark mode switching
        },

        toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('smartmoons-theme', next);
        }
    };

    // =====================================================
    // 9. REAL-TIME CLOCK & PLANET SELECTOR
    // =====================================================
    const RealTimeClock = {
        init() {
            this.updateClocks();
            this.initPlanetSelector();
            setInterval(() => this.updateClocks(), 1000);
        },

        updateClocks() {
            document.querySelectorAll('.servertime').forEach(element => {
                if (typeof serverTime !== 'undefined') {
                    const hours = serverTime.getHours().toString().padStart(2, '0');
                    const minutes = serverTime.getMinutes().toString().padStart(2, '0');
                    const seconds = serverTime.getSeconds().toString().padStart(2, '0');
                    element.textContent = `${hours}:${minutes}:${seconds}`;
                }
            });
        },

        initPlanetSelector() {
            const selector = document.getElementById('planetSelector');
            if (selector) {
                selector.addEventListener('change', (e) => {
                    window.location.href = `?cp=${e.target.value}`;
                });
            }
        }
    };

    // =====================================================
    // 10. FORM VALIDATOR
    // =====================================================
    const FormValidator = {
        init() {
            this.enhanceForms();
        },

        enhanceForms() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (!this.validateForm(form)) {
                        e.preventDefault();
                    }
                });

                form.querySelectorAll('input, textarea, select').forEach(field => {
                    field.addEventListener('blur', () => {
                        this.validateField(field);
                    });
                });
            });
        },

        validateForm(form) {
            let isValid = true;
            form.querySelectorAll('[required]').forEach(field => {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            });
            return isValid;
        },

        validateField(field) {
            const value = field.value.trim();
            const isValid = value !== '';
            
            if (!isValid) {
                field.classList.add('error');
                this.showFieldError(field, 'This field is required');
            } else {
                field.classList.remove('error');
                this.hideFieldError(field);
            }
            
            return isValid;
        },

        showFieldError(field, message) {
            let error = field.parentElement.querySelector('.field-error');
            if (!error) {
                error = document.createElement('div');
                error.className = 'field-error';
                field.parentElement.appendChild(error);
            }
            error.textContent = message;
        },

        hideFieldError(field) {
            const error = field.parentElement.querySelector('.field-error');
            if (error) {
                error.remove();
            }
        }
    };

    // =====================================================
    // 11. SMOOTH SCROLL
    // =====================================================
    const SmoothScroll = {
        init() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', (e) => {
                    const targetId = anchor.getAttribute('href');
                    if (targetId && targetId !== '#') {
                        const target = document.querySelector(targetId);
                        if (target) {
                            e.preventDefault();
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }
                });
            });
        }
    };

    // =====================================================
    // 12. KEYBOARD SHORTCUTS
    // =====================================================
    const KeyboardShortcuts = {
        shortcuts: {
            'b': () => window.location.href = 'game.php?page=buildings',
            'r': () => window.location.href = 'game.php?page=research',
            'f': () => window.location.href = 'game.php?page=shipyard&mode=fleet',
            'd': () => window.location.href = 'game.php?page=shipyard&mode=defense',
            'g': () => window.location.href = 'game.php?page=galaxy',
            'a': () => window.location.href = 'game.php?page=alliance',
            'm': () => window.location.href = 'game.php?page=messages',
            'o': () => window.location.href = 'game.php?page=overview',
            '/': () => document.querySelector('.search-input')?.focus()
        },

        init() {
            document.addEventListener('keydown', (e) => {
                // Don't trigger shortcuts when typing in inputs
                if (e.target.matches('input, textarea, select')) return;
                
                const shortcut = this.shortcuts[e.key.toLowerCase()];
                if (shortcut && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    e.preventDefault();
                    shortcut();
                }
            });
        }
    };

    // =====================================================
    // INITIALIZATION
    // =====================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize all modules
        SidebarMenu.init();
        ResourceTicker.init();
        ProgressBarManager.init();
        NotificationSystem.init();
        ModalHandler.init();
        TooltipEnhancer.init();
        AnimationObserver.init();
        ThemeManager.init();
        RealTimeClock.init();
        FormValidator.init();
        SmoothScroll.init();
        KeyboardShortcuts.init();

        // Add loaded class to body for CSS animations
        document.body.classList.add('loaded');

        // Log initialization
        console.log('SmartMoons UI initialized successfully');
    });

    // =====================================================
    // EXPORT TO WINDOW (for external access if needed)
    // =====================================================
    window.SmartMoonsUI = {
        SidebarMenu,
        ResourceTicker,
        ProgressBarManager,
        NotificationSystem,
        ModalHandler,
        TooltipEnhancer,
        AnimationObserver,
        ThemeManager,
        RealTimeClock,
        FormValidator,
        SmoothScroll,
        KeyboardShortcuts
    };

})();