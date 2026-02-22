/**
 * SmartMoons - Enhanced Mobile Menu Handler
 * Ensures burger menu displays navigation items correctly
 */

(function() {
    'use strict';
    
    // Initialize when DOM is ready
    function initMobileMenu() {
        console.log('[MobileMenu Enhanced] Initializing mobile menu');
        
        // Find burger button (multiple possible selectors)
        const burgerButton = document.getElementById('sidebar-toggle') || 
                           document.querySelector('.burger-menu') ||
                           document.getElementById('burgerMenu');
        
        // Find mobile navigation
        const mobileNav = document.getElementById('mobile-nav') || 
                         document.querySelector('.mobile-nav');
        
        // Find or create overlay
        let overlay = document.getElementById('sidebar-overlay') || 
                     document.querySelector('.mobile-nav-overlay');
        
        if (!overlay && (mobileNav || burgerButton)) {
            overlay = createOverlay();
        }
        
        // Debug logging
        console.log('[MobileMenu Enhanced] Elements found:', {
            burgerButton: !!burgerButton,
            mobileNav: !!mobileNav,
            overlay: !!overlay
        });
        
        // If we have mobile nav elements, ensure they're visible
        if (mobileNav) {
            // Check if nav has content
            const navItems = mobileNav.querySelectorAll('li');
            console.log('[MobileMenu Enhanced] Navigation items found:', navItems.length);
            
            // Ensure nav is properly initialized
            mobileNav.style.display = 'none';
            mobileNav.classList.remove('open');
        }
        
        // Setup burger button click handler
        if (burgerButton && mobileNav) {
            // Remove any existing handlers
            const newButton = burgerButton.cloneNode(true);
            burgerButton.parentNode.replaceChild(newButton, burgerButton);
            
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[MobileMenu Enhanced] Burger button clicked');
                toggleMobileMenu(mobileNav, newButton, overlay);
            });
            
            // Set initial ARIA attributes
            newButton.setAttribute('aria-expanded', 'false');
            newButton.setAttribute('aria-controls', 'mobile-nav');
            
            if (mobileNav) {
                mobileNav.setAttribute('aria-hidden', 'true');
                mobileNav.setAttribute('role', 'navigation');
            }
        }
        
        // Setup overlay click handler
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('[MobileMenu Enhanced] Overlay clicked');
                closeMobileMenu(mobileNav, burgerButton || newButton, overlay);
            });
        }
        
        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && mobileNav && mobileNav.classList.contains('open')) {
                console.log('[MobileMenu Enhanced] Escape key pressed');
                closeMobileMenu(mobileNav, burgerButton || newButton, overlay);
            }
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize(mobileNav, burgerButton || newButton, overlay);
            }, 250);
        });
        
        // Initial setup
        handleResize(mobileNav, burgerButton || newButton, overlay);
        
        // Ensure menu links work
        if (mobileNav) {
            const links = mobileNav.querySelectorAll('a');
            links.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    // Allow default action but close menu
                    console.log('[MobileMenu Enhanced] Menu link clicked:', link.href);
                    closeMobileMenu(mobileNav, burgerButton || newButton, overlay);
                });
            });
        }
    }
    
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'sidebar-overlay';
        overlay.className = 'mobile-nav-overlay';
        overlay.style.display = 'none';
        document.body.appendChild(overlay);
        console.log('[MobileMenu Enhanced] Created overlay element');
        return overlay;
    }
    
    function toggleMobileMenu(mobileNav, burgerButton, overlay) {
        if (!mobileNav) {
            console.warn('[MobileMenu Enhanced] No mobile nav element found');
            return;
        }
        
        if (mobileNav.classList.contains('open')) {
            closeMobileMenu(mobileNav, burgerButton, overlay);
        } else {
            openMobileMenu(mobileNav, burgerButton, overlay);
        }
    }
    
    function openMobileMenu(mobileNav, burgerButton, overlay) {
        console.log('[MobileMenu Enhanced] Opening mobile menu');
        
        // Show and animate menu
        mobileNav.style.display = 'block';
        // Force reflow
        mobileNav.offsetHeight;
        mobileNav.classList.add('open');
        
        // Update burger button
        if (burgerButton) {
            burgerButton.classList.add('active');
            burgerButton.setAttribute('aria-expanded', 'true');
        }
        
        // Show overlay
        if (overlay) {
            overlay.style.display = 'block';
            // Force reflow
            overlay.offsetHeight;
            overlay.classList.add('active');
        }
        
        // Update ARIA
        mobileNav.setAttribute('aria-hidden', 'false');
        
        // Prevent body scroll
        document.body.classList.add('menu-open');
        
        // Log menu state
        const visibleItems = mobileNav.querySelectorAll('li:not([style*="display: none"])');
        console.log('[MobileMenu Enhanced] Menu opened with', visibleItems.length, 'visible items');
    }
    
    function closeMobileMenu(mobileNav, burgerButton, overlay) {
        if (!mobileNav) return;
        
        console.log('[MobileMenu Enhanced] Closing mobile menu');
        
        // Hide menu with animation
        mobileNav.classList.remove('open');
        
        // Update burger button
        if (burgerButton) {
            burgerButton.classList.remove('active');
            burgerButton.setAttribute('aria-expanded', 'false');
        }
        
        // Hide overlay
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(function() {
                if (!overlay.classList.contains('active')) {
                    overlay.style.display = 'none';
                }
            }, 300);
        }
        
        // Update ARIA
        mobileNav.setAttribute('aria-hidden', 'true');
        
        // Restore body scroll
        document.body.classList.remove('menu-open');
        
        // Hide menu after animation
        setTimeout(function() {
            if (!mobileNav.classList.contains('open')) {
                mobileNav.style.display = 'none';
            }
        }, 300);
    }
    
    function handleResize(mobileNav, burgerButton, overlay) {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Mobile view
            if (burgerButton) {
                burgerButton.style.display = 'flex';
            }
            
            // Ensure menu is hidden initially
            if (mobileNav && !mobileNav.classList.contains('open')) {
                mobileNav.style.display = 'none';
            }
        } else {
            // Desktop view
            if (burgerButton) {
                burgerButton.style.display = 'none';
            }
            
            // Hide mobile menu on desktop
            if (mobileNav) {
                mobileNav.style.display = 'none';
                mobileNav.classList.remove('open');
            }
            
            // Hide overlay
            if (overlay) {
                overlay.style.display = 'none';
                overlay.classList.remove('active');
            }
            
            // Restore body scroll
            document.body.classList.remove('menu-open');
        }
    }
    
    // Initialize based on DOM state
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        // DOM is already loaded
        initMobileMenu();
    }
    
    // Also try jQuery ready if available
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(initMobileMenu);
    }
    
    // Export for debugging
    window.MobileMenuEnhanced = {
        init: initMobileMenu,
        open: function() {
            const mobileNav = document.querySelector('.mobile-nav, #mobile-nav');
            const burgerButton = document.querySelector('.burger-menu, #sidebar-toggle');
            const overlay = document.querySelector('#sidebar-overlay, .mobile-nav-overlay');
            if (mobileNav) openMobileMenu(mobileNav, burgerButton, overlay);
        },
        close: function() {
            const mobileNav = document.querySelector('.mobile-nav, #mobile-nav');
            const burgerButton = document.querySelector('.burger-menu, #sidebar-toggle');
            const overlay = document.querySelector('#sidebar-overlay, .mobile-nav-overlay');
            if (mobileNav) closeMobileMenu(mobileNav, burgerButton, overlay);
        },
        toggle: function() {
            const mobileNav = document.querySelector('.mobile-nav, #mobile-nav');
            const burgerButton = document.querySelector('.burger-menu, #sidebar-toggle');
            const overlay = document.querySelector('#sidebar-overlay, .mobile-nav-overlay');
            if (mobileNav) toggleMobileMenu(mobileNav, burgerButton, overlay);
        },
        debug: function() {
            const mobileNav = document.querySelector('.mobile-nav, #mobile-nav');
            const burgerButton = document.querySelector('.burger-menu, #sidebar-toggle');
            const overlay = document.querySelector('#sidebar-overlay, .mobile-nav-overlay');
            
            console.group('[MobileMenu Enhanced] Debug Info');
            console.log('Mobile Nav:', mobileNav);
            if (mobileNav) {
                console.log('- Display:', window.getComputedStyle(mobileNav).display);
                console.log('- Classes:', mobileNav.classList.toString());
                console.log('- Items:', mobileNav.querySelectorAll('li').length);
                console.log('- Links:', mobileNav.querySelectorAll('a').length);
            }
            console.log('Burger Button:', burgerButton);
            if (burgerButton) {
                console.log('- Display:', window.getComputedStyle(burgerButton).display);
                console.log('- Classes:', burgerButton.classList.toString());
            }
            console.log('Overlay:', overlay);
            console.groupEnd();
        }
    };
})();