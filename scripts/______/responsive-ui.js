/**
 * SmartMoons - Responsive UI Controller
 * Handles mobile navigation, notifications, and responsive interactions
 * Version: 4.0
 */

(function() {
    'use strict';
    
    // ============================================
    // MOBILE NAVIGATION CONTROLLER
    // ============================================
    const MobileNav = {
        init: function() {
            this.createMobileToggle();
            this.setupEventListeners();
            this.handleResize();
        },
        
        createMobileToggle: function() {
            // Check if already exists
            if (document.querySelector('.mobile-menu-toggle')) return;
            
            // Create toggle button
            const toggle = document.createElement('button');
            toggle.className = 'mobile-menu-toggle';
            toggle.innerHTML = '<i class="fas fa-bars"></i>';
            toggle.setAttribute('aria-label', 'Toggle Navigation Menu');
            
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'mobile-menu-overlay';
            
            document.body.appendChild(toggle);
            document.body.appendChild(overlay);
        },
        
        setupEventListeners: function() {
            const toggle = document.querySelector('.mobile-menu-toggle');
            const overlay = document.querySelector('.mobile-menu-overlay');
            const leftmenu = document.getElementById('leftmenu');
            
            if (!toggle || !overlay || !leftmenu) return;
            
            // Toggle menu on button click
            toggle.addEventListener('click', () => {
                this.toggleMenu();
            });
            
            // Close menu on overlay click
            overlay.addEventListener('click', () => {
                this.closeMenu();
            });
            
            // Close menu when navigating
            const menuLinks = leftmenu.querySelectorAll('a');
            menuLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 767) {
                        this.closeMenu();
                    }
                });
            });
            
            // Handle escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeMenu();
                }
            });
        },
        
        toggleMenu: function() {
            const leftmenu = document.getElementById('leftmenu');
            const overlay = document.querySelector('.mobile-menu-overlay');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (leftmenu.classList.contains('active')) {
                this.closeMenu();
            } else {
                this.openMenu();
            }
        },
        
        openMenu: function() {
            const leftmenu = document.getElementById('leftmenu');
            const overlay = document.querySelector('.mobile-menu-overlay');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            leftmenu.classList.add('active');
            overlay.classList.add('active');
            overlay.style.display = 'block';
            toggle.innerHTML = '<i class="fas fa-times"></i>';
            document.body.style.overflow = 'hidden';
        },
        
        closeMenu: function() {
            const leftmenu = document.getElementById('leftmenu');
            const overlay = document.querySelector('.mobile-menu-overlay');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            leftmenu.classList.remove('active');
            overlay.classList.remove('active');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 250);
            toggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.style.overflow = '';
        },
        
        handleResize: function() {
            window.addEventListener('resize', () => {
                if (window.innerWidth > 767) {
                    this.closeMenu();
                }
            });
        }
    };
    
    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================
    const NotificationSystem = {
        queue: [],
        container: null,
        
        init: function() {
            this.createContainer();
        },
        
        createContainer: function() {
            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.style.cssText = `
                position: fixed;
                top: 120px;
                right: 16px;
                z-index: 2000;
                max-width: 400px;
            `;
            document.body.appendChild(this.container);
        },
        
        show: function(message, type = 'info', duration = 5000) {
            const notification = this.create(message, type);
            this.container.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // Auto remove
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(notification);
                }, duration);
            }
            
            return notification;
        },
        
        create: function(message, type) {
            const notification = document.createElement('div');
            notification.className = `game-notification game-notification--${type}`;
            notification.style.cssText = `
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease-out;
                margin-bottom: 12px;
            `;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            notification.innerHTML = `
                <div class="game-notification__header">
                    <i class="fas ${icons[type] || icons.info}"></i>
                    <span>${this.getTitle(type)}</span>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="margin-left: auto; background: none; border: none; color: var(--color-text-secondary); cursor: pointer; font-size: 1.25rem;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="game-notification__body">
                    ${message}
                </div>
            `;
            
            return notification;
        },
        
        remove: function(notification) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                notification.remove();
            }, 300);
        },
        
        getTitle: function(type) {
            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Information'
            };
            return titles[type] || titles.info;
        },
        
        // Quick methods
        success: function(message, duration) {
            return this.show(message, 'success', duration);
        },
        
        error: function(message, duration) {
            return this.show(message, 'error', duration);
        },
        
        warning: function(message, duration) {
            return this.show(message, 'warning', duration);
        },
        
        info: function(message, duration) {
            return this.show(message, 'info', duration);
        }
    };
    
    // ============================================
    // RESPONSIVE TABLES
    // ============================================
    const ResponsiveTables = {
        init: function() {
            this.makeTablesResponsive();
        },
        
        makeTablesResponsive: function() {
            const tables = document.querySelectorAll('.sci-table');
            
            tables.forEach(table => {
                if (table.parentElement.classList.contains('table-responsive')) return;
                
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                wrapper.style.cssText = `
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                `;
                
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });
        }
    };
    
    // ============================================
    // TOUCH ENHANCEMENTS
    // ============================================
    const TouchEnhancements = {
        init: function() {
            if (!this.isTouchDevice()) return;
            
            this.addTouchFeedback();
            this.preventDoubleTapZoom();
        },
        
        isTouchDevice: function() {
            return ('ontouchstart' in window) || 
                   (navigator.maxTouchPoints > 0) || 
                   (navigator.msMaxTouchPoints > 0);
        },
        
        addTouchFeedback: function() {
            const buttons = document.querySelectorAll('.sci-btn, a[href]');
            
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.7';
                }, { passive: true });
                
                button.addEventListener('touchend', function() {
                    this.style.opacity = '';
                }, { passive: true });
            });
        },
        
        preventDoubleTapZoom: function() {
            let lastTouchEnd = 0;
            
            document.addEventListener('touchend', function(event) {
                const now = Date.now();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, { passive: false });
        }
    };
    
    // ============================================
    // RESOURCE TICKER ENHANCEMENT
    // ============================================
    const ResourceTicker = {
        init: function() {
            this.enhanceResourceBars();
            this.updateResourceColors();
        },
        
        enhanceResourceBars: function() {
            setInterval(() => {
                this.updateResourceColors();
            }, 1000);
        },
        
        updateResourceColors: function() {
            const bars = document.querySelectorAll('.bar');
            
            bars.forEach(bar => {
                const width = parseFloat(bar.style.width) || 0;
                
                if (width >= 90) {
                    bar.style.background = 'linear-gradient(90deg, #ef4444, #dc2626)';
                } else if (width >= 70) {
                    bar.style.background = 'linear-gradient(90deg, #fbbf24, #f59e0b)';
                } else {
                    bar.style.background = 'linear-gradient(90deg, var(--color-accent-blue), var(--color-accent-cyan))';
                }
            });
        }
    };
    
    // ============================================
    // BUILD QUEUE MANAGER
    // ============================================
    const BuildQueueManager = {
        init: function() {
            this.updateTimers();
            this.setupCancelHandlers();
        },
        
        updateTimers: function() {
            setInterval(() => {
                const timers = document.querySelectorAll('.timer[data-time]');
                
                timers.forEach(timer => {
                    let time = parseInt(timer.getAttribute('data-time'));
                    if (time > 0) {
                        time--;
                        timer.setAttribute('data-time', time);
                        timer.textContent = this.formatTime(time);
                        
                        // Update progress bar if exists
                        const progressBar = timer.parentElement.querySelector('.sci-progress__bar');
                        if (progressBar) {
                            const totalTime = parseInt(progressBar.getAttribute('data-total-time')) || 1;
                            const percentage = ((totalTime - time) / totalTime) * 100;
                            progressBar.style.width = percentage + '%';
                        }
                    } else {
                        timer.textContent = 'Completed';
                        timer.classList.add('completed');
                    }
                });
            }, 1000);
        },
        
        formatTime: function(seconds) {
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (days > 0) {
                return `${days}d ${hours}h ${minutes}m`;
            } else if (hours > 0) {
                return `${hours}h ${minutes}m ${secs}s`;
            } else if (minutes > 0) {
                return `${minutes}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        },
        
        setupCancelHandlers: function() {
            const cancelButtons = document.querySelectorAll('[data-action="cancel-build"]');
            
            cancelButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const confirmed = confirm('Are you sure you want to cancel this construction?');
                    if (confirmed) {
                        // Handle cancel logic here
                        NotificationSystem.info('Construction cancelled');
                    }
                });
            });
        }
    };
    
    // ============================================
    // FLEET MISSION TRACKER
    // ============================================
    const FleetMissionTracker = {
        init: function() {
            this.updateFleetTimers();
            this.highlightIncomingFleets();
        },
        
        updateFleetTimers: function() {
            setInterval(() => {
                const fleetTimers = document.querySelectorAll('[id^="fleettime_"]');
                
                fleetTimers.forEach(timer => {
                    let time = parseInt(timer.getAttribute('data-time'));
                    if (time > 0) {
                        time--;
                        timer.setAttribute('data-time', time);
                        timer.textContent = this.formatFleetTime(time);
                        
                        // Warning for incoming attacks
                        if (time < 300 && timer.getAttribute('data-mission-type') === 'attack') {
                            timer.classList.add('warning-flash');
                        }
                    }
                });
            }, 1000);
        },
        
        formatFleetTime: function(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },
        
        highlightIncomingFleets: function() {
            const incomingFleets = document.querySelectorAll('[data-fleet-type="incoming"]');
            
            incomingFleets.forEach(fleet => {
                fleet.style.borderLeft = '4px solid var(--color-accent-red)';
            });
        }
    };
    
    // ============================================
    // LAZY LOADING FOR IMAGES
    // ============================================
    const LazyLoader = {
        init: function() {
            if ('IntersectionObserver' in window) {
                this.observeImages();
            } else {
                this.loadAllImages();
            }
        },
        
        observeImages: function() {
            const images = document.querySelectorAll('img[data-src]');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.getAttribute('data-src');
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            });
            
            images.forEach(img => observer.observe(img));
        },
        
        loadAllImages: function() {
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                img.src = img.getAttribute('data-src');
                img.removeAttribute('data-src');
            });
        }
    };
    
    // ============================================
    // PERFORMANCE MONITOR
    // ============================================
    const PerformanceMonitor = {
        init: function() {
            if (window.performance && window.performance.timing) {
                window.addEventListener('load', () => {
                    setTimeout(() => {
                        this.logPerformance();
                    }, 0);
                });
            }
        },
        
        logPerformance: function() {
            const perfData = window.performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            
            console.log('🚀 Page Performance:');
            console.log(`Total Load Time: ${pageLoadTime}ms`);
            console.log(`DOM Content Loaded: ${perfData.domContentLoadedEventEnd - perfData.navigationStart}ms`);
        }
    };
    
    // ============================================
    // ACCESSIBILITY ENHANCEMENTS
    // ============================================
    const AccessibilityEnhancer = {
        init: function() {
            this.addAriaLabels();
            this.enhanceKeyboardNavigation();
        },
        
        addAriaLabels: function() {
            // Add ARIA labels to buttons without text
            const iconButtons = document.querySelectorAll('button:not([aria-label]) i.fas');
            
            iconButtons.forEach(icon => {
                const button = icon.closest('button');
                if (button && !button.getAttribute('aria-label')) {
                    const title = button.getAttribute('title');
                    if (title) {
                        button.setAttribute('aria-label', title);
                    }
                }
            });
        },
        
        enhanceKeyboardNavigation: function() {
            // Make card elements keyboard accessible
            const cards = document.querySelectorAll('.sci-card[onclick]');
            
            cards.forEach(card => {
                if (!card.hasAttribute('tabindex')) {
                    card.setAttribute('tabindex', '0');
                    card.setAttribute('role', 'button');
                    
                    card.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            this.click();
                        }
                    });
                }
            });
        }
    };
    
    // ============================================
    // GLOBAL GAME UI OBJECT
    // ============================================
    window.GameUI = {
        MobileNav,
        NotificationSystem,
        ResponsiveTables,
        TouchEnhancements,
        ResourceTicker,
        BuildQueueManager,
        FleetMissionTracker,
        LazyLoader,
        PerformanceMonitor,
        AccessibilityEnhancer,
        
        // Initialize all modules
        init: function() {
            console.log('🎮 Initializing SmartMoons Responsive UI...');
            
            MobileNav.init();
            NotificationSystem.init();
            ResponsiveTables.init();
            TouchEnhancements.init();
            ResourceTicker.init();
            BuildQueueManager.init();
            FleetMissionTracker.init();
            LazyLoader.init();
            PerformanceMonitor.init();
            AccessibilityEnhancer.init();
            
            console.log('✅ SmartMoons UI Initialized');
        }
    };
    
    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.GameUI.init();
        });
    } else {
        window.GameUI.init();
    }
    
})();
