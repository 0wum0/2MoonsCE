/**
 * Burger Menu Fix - Enhanced Mobile Navigation
 * This script ensures the burger menu works correctly across all pages
 */

(function() {
    'use strict';
    
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBurgerMenu);
    } else {
        // DOM is already loaded
        initBurgerMenu();
    }
    
    function initBurgerMenu() {
        console.log('[BurgerMenuFix] Initializing burger menu functionality');
        
        // Try to find elements with multiple possible selectors
        const burgerButton = document.getElementById('burgerMenu') || 
                           document.getElementById('burger-toggle') || 
                           document.getElementById('sidebar-toggle') || 
                           document.querySelector('.burger-menu');
                           
        // Look for mobile navigation first, then sidebar
        const mobileNav = document.getElementById('mobile-nav') || 
                         document.querySelector('.mobile-nav');
                         
        const sidebar = document.getElementById('gameSidebar') || 
                       document.getElementById('leftmenu') || 
                       document.querySelector('.game-sidebar') || 
                       document.querySelector('.sidebar-nav');
                       
        const overlay = document.getElementById('sidebarOverlay') || 
                       document.getElementById('sidebar-overlay') || 
                       document.querySelector('.sidebar-overlay');
                       
        const closeButton = document.getElementById('sidebarClose') || 
                           document.getElementById('closeLeftSidebar') || 
                           document.querySelector('.sidebar-close');
        
        // Debug logging
        console.log('[BurgerMenuFix] Elements found:', {
            burgerButton: !!burgerButton,
            mobileNav: !!mobileNav,
            sidebar: !!sidebar,
            overlay: !!overlay,
            closeButton: !!closeButton
        });
        
        // Create overlay if it doesn't exist
        let overlayElement = overlay;
        if (!overlayElement && (sidebar || mobileNav)) {
            overlayElement = createOverlay();
            console.log('[BurgerMenuFix] Created overlay element');
        }
        
        // Setup event handlers for mobile navigation (priority)
        if (burgerButton && mobileNav) {
            // Remove any existing event listeners to avoid duplicates
            const newBurgerButton = burgerButton.cloneNode(true);
            burgerButton.parentNode.replaceChild(newBurgerButton, burgerButton);
            
            // Add click handler to burger button for mobile nav
            newBurgerButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[BurgerMenuFix] Burger button clicked for mobile nav');
                toggleMobileNav(mobileNav, newBurgerButton, overlayElement);
            });
            
            // Add ARIA attributes
            newBurgerButton.setAttribute('aria-expanded', 'false');
            newBurgerButton.setAttribute('aria-controls', 'mobile-nav');
            mobileNav.setAttribute('aria-hidden', 'true');
        }
        // Setup event handlers for sidebar (fallback)
        else if (burgerButton && sidebar) {
            // Remove any existing event listeners to avoid duplicates
            const newBurgerButton = burgerButton.cloneNode(true);
            burgerButton.parentNode.replaceChild(newBurgerButton, burgerButton);
            
            // Add click handler to burger button
            newBurgerButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[BurgerMenuFix] Burger button clicked');
                toggleSidebar(sidebar, newBurgerButton, overlayElement);
            });
            
            // Add ARIA attributes
            newBurgerButton.setAttribute('aria-expanded', 'false');
            newBurgerButton.setAttribute('aria-controls', sidebar.id || 'sidebar');
            sidebar.setAttribute('aria-hidden', 'true');
        }
        
        // Setup overlay click handler
        if (overlayElement) {
            overlayElement.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('[BurgerMenuFix] Overlay clicked');
                
                // Close mobile nav if it's open
                if (mobileNav && (mobileNav.classList.contains('open') || mobileNav.style.display === 'block')) {
                    closeMobileNav(mobileNav, burgerButton || newBurgerButton, overlayElement);
                }
                // Otherwise close sidebar if it's open
                else if (sidebar && sidebar.classList.contains('active')) {
                    closeSidebar(sidebar, burgerButton || newBurgerButton, overlayElement);
                }
            });
        }
        
        // Setup close button handler
        if (closeButton && sidebar) {
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('[BurgerMenuFix] Close button clicked');
                closeSidebar(sidebar, burgerButton || newBurgerButton, overlayElement);
            });
        }
        
        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                console.log('[BurgerMenuFix] Escape key pressed');
                
                // Close mobile nav if it's open
        if (mobileNav && (mobileNav.classList.contains('open') || mobileNav.style.display === 'block')) {
                    closeMobileNav(mobileNav, burgerButton || newBurgerButton, overlayElement);
                }
                // Otherwise close sidebar if it's open
                else if (sidebar && sidebar.classList.contains('active')) {
                    closeSidebar(sidebar, burgerButton || newBurgerButton, overlayElement);
                }
            }
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize(sidebar, burgerButton || newBurgerButton, overlayElement);
            }, 250);
        });
        
        // Initial state based on screen size
        handleResize(sidebar, burgerButton || newBurgerButton, overlayElement);
    }
    
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'sidebarOverlay';
        overlay.className = 'sidebar-overlay';
        overlay.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9998;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        document.body.appendChild(overlay);
        return overlay;
    }
    
    function toggleMobileNav(mobileNav, burgerButton, overlay) {
        if (mobileNav.style.display === 'block') {
            closeMobileNav(mobileNav, burgerButton, overlay);
        } else {
            openMobileNav(mobileNav, burgerButton, overlay);
        }
    }
    
    function openMobileNav(mobileNav, burgerButton, overlay) {
        console.log('[BurgerMenuFix] Opening mobile nav');
        
        // Show mobile nav
        mobileNav.style.display = 'block';
        
        if (burgerButton) {
            burgerButton.classList.add('active');
            burgerButton.setAttribute('aria-expanded', 'true');
        }
        
        if (overlay) {
            overlay.style.display = 'block';
            // Force reflow to ensure transition works
            overlay.offsetHeight;
            overlay.classList.add('active');
            overlay.style.opacity = '1';
        }
        
        // Update ARIA
        mobileNav.setAttribute('aria-hidden', 'false');
        
        // Prevent body scroll on mobile
        document.body.style.overflow = 'hidden';
    }
    
    function closeMobileNav(mobileNav, burgerButton, overlay) {
        console.log('[BurgerMenuFix] Closing mobile nav');
        
        // Hide mobile nav
        mobileNav.style.display = 'none';
        
        if (burgerButton) {
            burgerButton.classList.remove('active');
            burgerButton.setAttribute('aria-expanded', 'false');
        }
        
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.opacity = '0';
            setTimeout(function() {
                if (!overlay.classList.contains('active')) {
                    overlay.style.display = 'none';
                }
            }, 300);
        }
        
        // Update ARIA
        mobileNav.setAttribute('aria-hidden', 'true');
        
        // Restore body scroll
        document.body.style.overflow = '';
    }
    
    function toggleSidebar(sidebar, burgerButton, overlay) {
        if (sidebar.classList.contains('active')) {
            closeSidebar(sidebar, burgerButton, overlay);
        } else {
            openSidebar(sidebar, burgerButton, overlay);
        }
    }
    
    function openSidebar(sidebar, burgerButton, overlay) {
        console.log('[BurgerMenuFix] Opening sidebar');
        
        // Add active classes
        sidebar.classList.add('active');
        sidebar.classList.remove('collapsed');
        
        if (burgerButton) {
            burgerButton.classList.add('active');
            burgerButton.setAttribute('aria-expanded', 'true');
        }
        
        if (overlay) {
            overlay.style.display = 'block';
            // Force reflow to ensure transition works
            overlay.offsetHeight;
            overlay.classList.add('active');
            overlay.style.opacity = '1';
        }
        
        // Update ARIA
        sidebar.setAttribute('aria-hidden', 'false');
        
        // Prevent body scroll on mobile
        if (window.innerWidth <= 992) {
            document.body.style.overflow = 'hidden';
        }
        
        // Add animation
        sidebar.style.transform = 'translateX(0)';
        sidebar.style.visibility = 'visible';
    }
    
    function closeSidebar(sidebar, burgerButton, overlay) {
        console.log('[BurgerMenuFix] Closing sidebar');
        
        // Remove active classes
        sidebar.classList.remove('active');
        sidebar.classList.add('collapsed');
        
        if (burgerButton) {
            burgerButton.classList.remove('active');
            burgerButton.setAttribute('aria-expanded', 'false');
        }
        
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.opacity = '0';
            setTimeout(function() {
                if (!overlay.classList.contains('active')) {
                    overlay.style.display = 'none';
                }
            }, 300);
        }
        
        // Update ARIA
        sidebar.setAttribute('aria-hidden', 'true');
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Add animation
        if (window.innerWidth <= 992) {
            sidebar.style.transform = 'translateX(-100%)';
        }
    }
    
    function handleResize(sidebar, burgerButton, overlay) {
        if (!sidebar) return;
        
        if (window.innerWidth > 992) {
            // Desktop view
            sidebar.classList.remove('active', 'collapsed');
            sidebar.style.transform = '';
            sidebar.style.visibility = '';
            
            if (burgerButton) {
                burgerButton.style.display = 'none';
            }
            
            if (overlay) {
                overlay.style.display = 'none';
                overlay.classList.remove('active');
            }
            
            document.body.style.overflow = '';
        } else {
            // Mobile view
            if (burgerButton) {
                burgerButton.style.display = 'flex';
            }
            
            // Ensure sidebar starts hidden on mobile
            if (!sidebar.classList.contains('active')) {
                sidebar.style.transform = 'translateX(-100%)';
                sidebar.classList.add('collapsed');
            }
        }
    }
    
    // Export functions for external use
    window.BurgerMenuFix = {
        init: initBurgerMenu,
        open: function() {
            const sidebar = document.querySelector('.game-sidebar, #gameSidebar, #leftmenu');
            const burgerButton = document.querySelector('.burger-menu, #burgerMenu, #sidebar-toggle');
            const overlay = document.querySelector('.sidebar-overlay, #sidebarOverlay');
            if (sidebar) openSidebar(sidebar, burgerButton, overlay);
        },
        close: function() {
            const sidebar = document.querySelector('.game-sidebar, #gameSidebar, #leftmenu');
            const burgerButton = document.querySelector('.burger-menu, #burgerMenu, #sidebar-toggle');
            const overlay = document.querySelector('.sidebar-overlay, #sidebarOverlay');
            if (sidebar) closeSidebar(sidebar, burgerButton, overlay);
        },
        toggle: function() {
            const sidebar = document.querySelector('.game-sidebar, #gameSidebar, #leftmenu');
            const burgerButton = document.querySelector('.burger-menu, #burgerMenu, #sidebar-toggle');
            const overlay = document.querySelector('.sidebar-overlay, #sidebarOverlay');
            if (sidebar) toggleSidebar(sidebar, burgerButton, overlay);
        }
    };
})();