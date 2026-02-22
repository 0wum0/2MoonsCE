/**
 * SmartMoons - Sidebar Navigation Controller
 * Handles mobile sidebar toggle and interactions
 * Version: 1.0
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initSidebarNavigation();
    });
    
    /**
     * Initialize sidebar navigation functionality
     */
    function initSidebarNavigation() {
        // Get elements
        const sidebar = document.getElementById('leftmenu') || document.querySelector('.sidebar-nav');
        const burgerButton = document.getElementById('sidebar-toggle') || document.querySelector('.burger-menu');
        const overlay = document.getElementById('sidebar-overlay') || document.querySelector('.sidebar-overlay');
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        
        // Check if elements exist
        if (!sidebar) {
            console.warn('Sidebar element not found');
            return;
        }
        
        // Create overlay if it doesn't exist
        if (!overlay) {
            createOverlay();
        }
        
        // Setup burger button click handler
        if (burgerButton) {
            burgerButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // Setup overlay click handler
        const overlayElement = document.getElementById('sidebar-overlay') || document.querySelector('.sidebar-overlay');
        if (overlayElement) {
            overlayElement.addEventListener('click', function(e) {
                e.preventDefault();
                closeSidebar();
            });
        }
        
        // Close sidebar when clicking on links (mobile only)
        if (window.innerWidth <= 768) {
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Don't prevent default - let the link navigate
                    // Just close the sidebar after a small delay
                    setTimeout(() => {
                        closeSidebar();
                    }, 100);
                });
            });
        }
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize();
            }, 250);
        });
        
        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                if (isSidebarOpen()) {
                    closeSidebar();
                }
            }
        });
        
        // Initialize state based on screen size
        handleResize();
    }
    
    /**
     * Create overlay element if it doesn't exist
     */
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'sidebar-overlay';
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    /**
     * Toggle sidebar visibility
     */
    function toggleSidebar() {
        const sidebar = document.getElementById('leftmenu') || document.querySelector('.sidebar-nav');
        const burgerButton = document.getElementById('sidebar-toggle') || document.querySelector('.burger-menu');
        const overlay = document.getElementById('sidebar-overlay') || document.querySelector('.sidebar-overlay');
        
        if (!sidebar) return;
        
        if (sidebar.classList.contains('active')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    /**
     * Open sidebar
     */
    function openSidebar() {
        const sidebar = document.getElementById('leftmenu') || document.querySelector('.sidebar-nav');
        const burgerButton = document.getElementById('sidebar-toggle') || document.querySelector('.burger-menu');
        const overlay = document.getElementById('sidebar-overlay') || document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        if (burgerButton) {
            burgerButton.classList.add('active');
        }
        
        if (overlay) {
            overlay.classList.add('active');
        }
        
        // Add ARIA attributes for accessibility
        if (sidebar) {
            sidebar.setAttribute('aria-hidden', 'false');
        }
        if (burgerButton) {
            burgerButton.setAttribute('aria-expanded', 'true');
        }
    }
    
    /**
     * Close sidebar
     */
    function closeSidebar() {
        const sidebar = document.getElementById('leftmenu') || document.querySelector('.sidebar-nav');
        const burgerButton = document.getElementById('sidebar-toggle') || document.querySelector('.burger-menu');
        const overlay = document.getElementById('sidebar-overlay') || document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            sidebar.classList.remove('active');
            document.body.style.overflow = ''; // Restore scrolling
        }
        
        if (burgerButton) {
            burgerButton.classList.remove('active');
        }
        
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        // Update ARIA attributes
        if (sidebar) {
            sidebar.setAttribute('aria-hidden', 'true');
        }
        if (burgerButton) {
            burgerButton.setAttribute('aria-expanded', 'false');
        }
    }
    
    /**
     * Check if sidebar is open
     */
    function isSidebarOpen() {
        const sidebar = document.getElementById('leftmenu') || document.querySelector('.sidebar-nav');
        return sidebar && sidebar.classList.contains('active');
    }
    
    /**
     * Handle window resize events
     */
    function handleResize() {
        const sidebar = document.getElementById('leftmenu') || document.querySelector('.sidebar-nav');
        const overlay = document.getElementById('sidebar-overlay') || document.querySelector('.sidebar-overlay');
        
        if (window.innerWidth > 768) {
            // Desktop view - ensure sidebar is visible and overlay is hidden
            if (sidebar) {
                sidebar.classList.remove('active');
                sidebar.style.transform = '';
            }
            if (overlay) {
                overlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        } else {
            // Mobile view - ensure sidebar starts hidden
            if (sidebar && !sidebar.classList.contains('active')) {
                sidebar.style.transform = 'translateX(-100%)';
            }
        }
    }
    
    /**
     * Public API
     */
    window.SidebarNavigation = {
        open: openSidebar,
        close: closeSidebar,
        toggle: toggleSidebar,
        isOpen: isSidebarOpen
    };
    
})();