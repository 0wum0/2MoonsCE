/**
 * Modern Layout JavaScript
 * Handles sidebar toggle, responsive behavior, and UI interactions
 */

(function($) {
    'use strict';
    
    // Cache DOM elements
    var $body = $('body');
    var $sidebar = $('#gameSidebar');
    var $burgerMenu = $('#burgerMenu');
    var $sidebarClose = $('#sidebarClose');
    var $sidebarOverlay = $('#sidebarOverlay');
    var $userDropdown = $('.user-dropdown');
    var $navCards = $('.nav-card');
    var $dropAdmin = $('#drop-admin');
    
    // State variables
    var isMobile = window.innerWidth <= 768;
    var sidebarOpen = false;
    
    /**
     * Initialize the modern layout
     */
    function init() {
        bindEvents();
        checkMobileState();
        initTooltips();
        initResourceTickers();
        initAnimations();
    }
    
    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // Burger menu click
        $burgerMenu.on('click', toggleSidebar);
        
        // Sidebar close button
        $sidebarClose.on('click', closeSidebar);
        
        // Overlay click
        $sidebarOverlay.on('click', closeSidebar);
        
        // Window resize
        $(window).on('resize', debounce(onWindowResize, 250));
        
        // Navigation card clicks (mobile)
        if (isMobile) {
            $navCards.on('click', function(e) {
                // Close sidebar after navigation on mobile
                if (sidebarOpen) {
                    setTimeout(closeSidebar, 300);
                }
            });
        }
        
        // Drop admin access
        if ($dropAdmin.length) {
            $dropAdmin.on('click', function(e) {
                e.preventDefault();
                dropAdminAccess();
            });
        }
        
        // ESC key to close sidebar
        $(document).on('keyup', function(e) {
            if (e.keyCode === 27 && sidebarOpen) {
                closeSidebar();
            }
        });
        
        // Prevent body scroll when sidebar is open on mobile
        $sidebar.on('touchmove', function(e) {
            if (isMobile && sidebarOpen) {
                e.stopPropagation();
            }
        });
    }
    
    /**
     * Toggle sidebar visibility
     */
    function toggleSidebar() {
        if (sidebarOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    /**
     * Open sidebar
     */
    function openSidebar() {
        sidebarOpen = true;
        $sidebar.addClass('active');
        $sidebarOverlay.addClass('active');
        $burgerMenu.addClass('active');
        
        // Prevent body scroll on mobile
        if (isMobile) {
            $body.css('overflow', 'hidden');
        }
        
        // Animate sidebar items
        animateSidebarItems();
    }
    
    /**
     * Close sidebar
     */
    function closeSidebar() {
        sidebarOpen = false;
        $sidebar.removeClass('active');
        $sidebarOverlay.removeClass('active');
        $burgerMenu.removeClass('active');
        
        // Restore body scroll
        if (isMobile) {
            $body.css('overflow', '');
        }
    }
    
    /**
     * Check and update mobile state
     */
    function checkMobileState() {
        var wasMobile = isMobile;
        isMobile = window.innerWidth <= 768;
        
        // State changed from desktop to mobile or vice versa
        if (wasMobile !== isMobile) {
            if (!isMobile && sidebarOpen) {
                // Switched to desktop, close mobile sidebar
                closeSidebar();
            }
        }
    }
    
    /**
     * Handle window resize
     */
    function onWindowResize() {
        checkMobileState();
        
        // Update resource displays
        updateResourceDisplays();
    }
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        // Custom tooltip implementation for better mobile support
        $('[data-tooltip-content]').each(function() {
            var $elem = $(this);
            var content = $elem.attr('data-tooltip-content');
            
            $elem.on('mouseenter', function(e) {
                if (!isMobile) {
                    showTooltip(e, content);
                }
            }).on('mouseleave', function() {
                hideTooltip();
            }).on('click', function(e) {
                if (isMobile) {
                    e.preventDefault();
                    showMobileTooltip(content);
                }
            });
        });
    }
    
    /**
     * Show tooltip
     */
    function showTooltip(e, content) {
        var $tooltip = $('#tooltip');
        if (!$tooltip.length) {
            $tooltip = $('<div id="tooltip" class="tip"></div>').appendTo('body');
        }
        
        $tooltip.html(content).css({
            left: e.pageX + 10,
            top: e.pageY + 10
        }).fadeIn(200);
    }
    
    /**
     * Hide tooltip
     */
    function hideTooltip() {
        $('#tooltip').fadeOut(200);
    }
    
    /**
     * Show mobile tooltip (as a temporary notification)
     */
    function showMobileTooltip(content) {
        var $notification = $('<div class="mobile-notification"></div>')
            .html(content)
            .appendTo('body');
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 10);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 2000);
    }
    
    /**
     * Initialize resource tickers
     */
    function initResourceTickers() {
        // Resource update animations
        $('.resource-value').each(function() {
            var $value = $(this);
            var realValue = parseFloat($value.attr('data-real'));
            
            // Add change detection
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        animateResourceChange($value);
                    }
                });
            });
            
            observer.observe($value[0], {
                childList: true,
                characterData: true,
                subtree: true
            });
        });
    }
    
    /**
     * Animate resource value change
     */
    function animateResourceChange($element) {
        $element.addClass('resource-changed');
        setTimeout(function() {
            $element.removeClass('resource-changed');
        }, 500);
    }
    
    /**
     * Update resource displays based on screen size
     */
    function updateResourceDisplays() {
        // Sync mobile and desktop resource values
        $('.resource-value').each(function() {
            var $desktop = $(this);
            var resourceName = $desktop.attr('id').replace('current_', '');
            var $mobile = $('#mobile_current_' + resourceName);
            
            if ($mobile.length) {
                $mobile.text($desktop.text());
            }
        });
    }
    
    /**
     * Animate sidebar items on open
     */
    function animateSidebarItems() {
        var delay = 0;
        $('.nav-card').each(function() {
            var $card = $(this);
            setTimeout(function() {
                $card.addClass('animated');
            }, delay);
            delay += 30;
        });
        
        // Remove animation class after completion
        setTimeout(function() {
            $('.nav-card').removeClass('animated');
        }, 1000);
    }
    
    /**
     * Initialize entrance animations
     */
    function initAnimations() {
        // Animate page content on load
        $('.game-card').each(function(index) {
            var $card = $(this);
            setTimeout(function() {
                $card.addClass('fade-in');
            }, index * 100);
        });
    }
    
    /**
     * Drop admin access
     */
    function dropAdminAccess() {
        $.ajax({
            url: 'game.php?page=logout',
            data: { mode: 'admin' },
            type: 'POST',
            success: function() {
                location.reload();
            }
        });
    }
    
    /**
     * Debounce function for performance
     */
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Add CSS for animations and mobile notifications
     */
    function addDynamicStyles() {
        var styles = `
            <style>
                .resource-changed {
                    animation: glow 0.5s ease;
                }
                
                @keyframes glow {
                    0%, 100% { filter: brightness(1); }
                    50% { filter: brightness(1.5); }
                }
                
                .nav-card.animated {
                    animation: slideIn 0.3s ease forwards;
                    opacity: 0;
                }
                
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateX(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }
                
                .game-card.fade-in {
                    animation: fadeInUp 0.5s ease forwards;
                }
                
                .mobile-notification {
                    position: fixed;
                    top: 70px;
                    left: 50%;
                    transform: translateX(-50%) translateY(-20px);
                    background: rgba(10, 10, 20, 0.95);
                    border: 1px solid rgba(0, 255, 255, 0.3);
                    border-radius: 8px;
                    padding: 10px 20px;
                    color: #b8b8b8;
                    font-size: 13px;
                    z-index: 10000;
                    opacity: 0;
                    transition: all 0.3s ease;
                    backdrop-filter: blur(10px);
                    box-shadow: 0 4px 20px rgba(0, 255, 255, 0.2);
                    max-width: 90%;
                    text-align: center;
                }
                
                .mobile-notification.show {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
                
                /* Smooth transitions for sidebar on mobile */
                @media (max-width: 768px) {
                    .game-sidebar {
                        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    }
                    
                    .sidebar-overlay {
                        transition: opacity 0.3s ease, visibility 0.3s ease;
                    }
                }
                
                /* Pulse animation for badges */
                @keyframes pulse {
                    0%, 100% {
                        transform: scale(1);
                        opacity: 1;
                    }
                    50% {
                        transform: scale(1.1);
                        opacity: 0.8;
                    }
                }
                
                .pulse {
                    animation: pulse 2s infinite;
                }
            </style>
        `;
        
        $('head').append(styles);
    }
    
    /**
     * Handle touch events for better mobile experience
     */
    function initTouchHandlers() {
        if (!isMobile) return;
        
        var touchStartX = 0;
        var touchEndX = 0;
        
        // Swipe to open sidebar from left edge
        $(document).on('touchstart', function(e) {
            touchStartX = e.originalEvent.changedTouches[0].screenX;
        });
        
        $(document).on('touchend', function(e) {
            touchEndX = e.originalEvent.changedTouches[0].screenX;
            handleSwipe();
        });
        
        function handleSwipe() {
            var swipeDistance = touchEndX - touchStartX;
            
            // Swipe right from left edge to open sidebar
            if (touchStartX < 30 && swipeDistance > 100 && !sidebarOpen) {
                openSidebar();
            }
            // Swipe left to close sidebar
            else if (swipeDistance < -100 && sidebarOpen) {
                closeSidebar();
            }
        }
    }
    
    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Alt + M to toggle sidebar
            if (e.altKey && e.keyCode === 77) {
                e.preventDefault();
                toggleSidebar();
            }
            
            // Alt + H for home/overview
            if (e.altKey && e.keyCode === 72) {
                e.preventDefault();
                window.location.href = 'game.php?page=overview';
            }
        });
    }
    
    /**
     * Performance optimization: Lazy load images
     */
    function initLazyLoad() {
        var lazyImages = $('img[data-lazy]');
        
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var image = entry.target;
                        image.src = image.dataset.lazy;
                        image.removeAttribute('data-lazy');
                        imageObserver.unobserve(image);
                    }
                });
            });
            
            lazyImages.each(function() {
                imageObserver.observe(this);
            });
        } else {
            // Fallback for older browsers
            lazyImages.each(function() {
                $(this).attr('src', $(this).attr('data-lazy'));
            });
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        addDynamicStyles();
        init();
        initTouchHandlers();
        initKeyboardShortcuts();
        initLazyLoad();
    });
    
    // Public API
    window.ModernLayout = {
        toggleSidebar: toggleSidebar,
        openSidebar: openSidebar,
        closeSidebar: closeSidebar
    };
    
})(jQuery);