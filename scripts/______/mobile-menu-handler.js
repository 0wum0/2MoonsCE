/**
 * SmartMoons - Enhanced Mobile Menu Handler
 * Ensures burger menu works correctly and shows all navigation items
 */

(function() {
    'use strict';
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initMobileMenu();
    });
    
    // Also initialize if DOM is already loaded
    if (document.readyState !== 'loading') {
        initMobileMenu();
    }
    
    function initMobileMenu() {
        console.log('[MobileMenu] Initializing mobile menu system');
        
        // Find burger menu button
        const burgerButton = document.getElementById('sidebar-toggle') || 
                           document.querySelector('.burger-menu');
        
        // Find sidebar/navigation menu
        const sidebar = document.getElementById('leftmenu') || 
                       document.querySelector('.sidebar-nav') ||
                       document.querySelector('#gameSidebar');
        
        // Find or create overlay
        let overlay = document.getElementById('sidebar-overlay') || 
                     document.querySelector('.sidebar-overlay');
        
        if (!overlay && sidebar) {
            overlay = createOverlay();
        }
        
        if (!burgerButton || !sidebar) {
            console.warn('[MobileMenu] Required elements not found', {
                burgerButton: !!burgerButton,
                sidebar: !!sidebar
            });
            return;
        }
        
        // Setup burger button click handler
        burgerButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu(sidebar, burgerButton, overlay);
        });
        
        // Setup overlay click handler (close menu)
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                e.preventDefault();
                closeMenu(sidebar, burgerButton, overlay);
            });
        }
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isMenuOpen(sidebar)) {
                closeMenu(sidebar, burgerButton, overlay);
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', debounce(function() {
            handleResize(sidebar, burgerButton, overlay);
        }, 250));
        
        // Setup close button if sidebar has one
        setupCloseButton(sidebar, burgerButton, overlay);
        
        // Ensure menu items are properly styled
        ensureMenuItemsVisible(sidebar);
        
        // Initial setup based on screen size
        handleResize(sidebar, burgerButton, overlay);
    }
    
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'sidebar-overlay';
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        return overlay;
    }
    
    function toggleMenu(sidebar, burgerButton, overlay) {
        if (isMenuOpen(sidebar)) {
            closeMenu(sidebar, burgerButton, overlay);
        } else {
            openMenu(sidebar, burgerButton, overlay);
        }
    }
    
    function openMenu(sidebar, burgerButton, overlay) {
        console.log('[MobileMenu] Opening menu');
        
        // Add active classes
        sidebar.classList.add('active');
        sidebar.classList.remove('collapsed');
        burgerButton.classList.add('active');
        
        // Show overlay
        if (overlay) {
            overlay.classList.add('active');
        }
        
        // Prevent body scroll
        document.body.classList.add('sidebar-open');
        
        // Update ARIA attributes
        burgerButton.setAttribute('aria-expanded', 'true');
        sidebar.setAttribute('aria-hidden', 'false');
        
        // Ensure menu items are visible
        ensureMenuItemsVisible(sidebar);
        
        // Force reflow to ensure CSS transitions work
        sidebar.offsetHeight;
    }
    
    function closeMenu(sidebar, burgerButton, overlay) {
        console.log('[MobileMenu] Closing menu');
        
        // Remove active classes
        sidebar.classList.remove('active');
        sidebar.classList.add('collapsed');
        burgerButton.classList.remove('active');
        
        // Hide overlay
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        // Restore body scroll
        document.body.classList.remove('sidebar-open');
        
        // Update ARIA attributes
        burgerButton.setAttribute('aria-expanded', 'false');
        sidebar.setAttribute('aria-hidden', 'true');
    }
    
    function isMenuOpen(sidebar) {
        return sidebar.classList.contains('active');
    }
    
    function setupCloseButton(sidebar, burgerButton, overlay) {
        // Check if sidebar already has a close button
        let closeButton = sidebar.querySelector('.sidebar-close, .close-btn, [data-close-sidebar]');
        
        // If no close button exists, create one
        if (!closeButton && window.innerWidth <= 768) {
            closeButton = document.createElement('button');
            closeButton.className = 'sidebar-close mobile-only';
            closeButton.innerHTML = '<span>&times;</span>';
            closeButton.setAttribute('aria-label', 'Close menu');
            
            // Insert at the beginning of sidebar
            sidebar.insertBefore(closeButton, sidebar.firstChild);
        }
        
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                closeMenu(sidebar, burgerButton, overlay);
            });
        }
    }
    
    function ensureMenuItemsVisible(sidebar) {
        // Make sure all navigation links are visible
        const menuItems = sidebar.querySelectorAll('a, button, .nav-link, .sidebar-link');
        
        menuItems.forEach(function(item) {
            // Ensure items are displayed and visible
            if (window.getComputedStyle(item).display === 'none') {
                item.style.display = 'flex';
            }
            
            // Ensure text is visible
            const textElements = item.querySelectorAll('span, p, div');
            textElements.forEach(function(text) {
                if (window.getComputedStyle(text).display === 'none') {
                    text.style.display = 'inline-block';
                }
            });
        });
        
        // Log menu items for debugging
        console.log('[MobileMenu] Menu items found:', menuItems.length);
    }
    
    function handleResize(sidebar, burgerButton, overlay) {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Mobile view - ensure burger button is visible
            burgerButton.style.display = 'flex';
            
            // Ensure sidebar starts closed on mobile
            if (!isMenuOpen(sidebar)) {
                sidebar.classList.add('collapsed');
                sidebar.style.transform = 'translateX(-100%)';
            }
        } else {
            // Desktop view - hide burger button, show sidebar
            burgerButton.style.display = 'none';
            sidebar.classList.remove('active', 'collapsed');
            sidebar.style.transform = '';
            
            // Hide overlay on desktop
            if (overlay) {
                overlay.classList.remove('active');
            }
            
            // Restore body scroll
            document.body.classList.remove('sidebar-open');
        }
    }
    
    // Utility function: debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Export for external use
    window.MobileMenuHandler = {
        init: initMobileMenu,
        open: function() {
            const sidebar = document.querySelector('#leftmenu, .sidebar-nav');
            const burgerButton = document.querySelector('#sidebar-toggle, .burger-menu');
            const overlay = document.querySelector('#sidebar-overlay, .sidebar-overlay');
            if (sidebar && burgerButton) {
                openMenu(sidebar, burgerButton, overlay);
            }
        },
        close: function() {
            const sidebar = document.querySelector('#leftmenu, .sidebar-nav');
            const burgerButton = document.querySelector('#sidebar-toggle, .burger-menu');
            const overlay = document.querySelector('#sidebar-overlay, .sidebar-overlay');
            if (sidebar && burgerButton) {
                closeMenu(sidebar, burgerButton, overlay);
            }
        },
        toggle: function() {
            const sidebar = document.querySelector('#leftmenu, .sidebar-nav');
            const burgerButton = document.querySelector('#sidebar-toggle, .burger-menu');
            const overlay = document.querySelector('#sidebar-overlay, .sidebar-overlay');
            if (sidebar && burgerButton) {
                toggleMenu(sidebar, burgerButton, overlay);
            }
        }
    };
})();