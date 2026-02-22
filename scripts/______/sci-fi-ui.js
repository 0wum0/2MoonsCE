/**
 * SmartMoons - Sci-Fi UI Interactions
 * Futuristic animations and smooth transitions
 */

(function($) {
    'use strict';

    // ============================================
    // MOBILE BURGER MENU
    // ============================================
    function initBurgerMenu() {
        // Create burger menu if not exists
        if (!$('.burger-menu').length) {
            const burgerMenu = $('<div class="burger-menu"><span></span><span></span><span></span></div>');
            const overlay = $('<div class="mobile-nav-overlay"></div>');
            $('body').append(burgerMenu).append(overlay);
        }

        // Toggle menu on burger click
        $(document).on('click', '.burger-menu', function() {
            $(this).toggleClass('active');
            $('#leftmenu').toggleClass('active');
            $('.mobile-nav-overlay').toggleClass('active').toggle();
        });

        // Close menu on overlay click
        $(document).on('click', '.mobile-nav-overlay', function() {
            $('.burger-menu').removeClass('active');
            $('#leftmenu').removeClass('active');
            $(this).removeClass('active').hide();
        });

        // Close menu when clicking a menu link (on mobile)
        $(document).on('click', '#leftmenu a', function() {
            if ($(window).width() <= 768) {
                $('.burger-menu').removeClass('active');
                $('#leftmenu').removeClass('active');
                $('.mobile-nav-overlay').removeClass('active').hide();
            }
        });
    }

    // ============================================
    // RESOURCE COUNT-UP ANIMATION
    // ============================================
    function animateResourceCounter(element, start, end, duration) {
        const $element = $(element);
        const range = end - start;
        const increment = range / (duration / 16); // 60fps
        let current = start;

        const timer = setInterval(function() {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            
            // Format number with separators
            const formatted = Math.floor(current).toLocaleString('de-DE');
            $element.text(formatted);
        }, 16);
    }

    // ============================================
    // PROGRESS BAR ANIMATION
    // ============================================
    function updateProgressBars() {
        $('.sci-progress__bar[data-progress]').each(function() {
            const $bar = $(this);
            const progress = parseFloat($bar.data('progress')) || 0;
            const maxProgress = parseFloat($bar.data('max-progress')) || 100;
            const percentage = (progress / maxProgress) * 100;
            
            $bar.css('width', percentage + '%');
        });
    }

    // ============================================
    // TIMER COUNTDOWN
    // ============================================
    function updateTimers() {
        $('.timer[data-time]').each(function() {
            const $timer = $(this);
            let timeLeft = parseInt($timer.data('time'));
            
            if (timeLeft > 0) {
                timeLeft--;
                $timer.data('time', timeLeft);
                $timer.text(formatTime(timeLeft));
            } else {
                $timer.text('00:00:00');
            }
        });
    }

    function formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        return [hours, minutes, secs]
            .map(v => v < 10 ? '0' + v : v)
            .join(':');
    }

    // ============================================
    // LOADING SPINNER
    // ============================================
    function showLoadingSpinner(message) {
        const spinner = $('<div class="sci-loading-overlay">' +
            '<div class="sci-loading-container">' +
                '<div class="sci-spinner sci-spinner--large"></div>' +
                (message ? '<div class="sci-loading-text">' + message + '</div>' : '') +
            '</div>' +
        '</div>');
        
        $('body').append(spinner);
        setTimeout(() => spinner.addClass('active'), 10);
    }

    function hideLoadingSpinner() {
        $('.sci-loading-overlay').removeClass('active');
        setTimeout(() => $('.sci-loading-overlay').remove(), 300);
    }

    // ============================================
    // CARD HOVER EFFECTS
    // ============================================
    function initCardEffects() {
        $('.sci-card').on('mouseenter', function(e) {
            const $card = $(this);
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            // Create glow effect at cursor position
            const glow = $('<div class="sci-card-glow"></div>');
            glow.css({
                left: x + 'px',
                top: y + 'px'
            });
            
            $card.append(glow);
            setTimeout(() => glow.remove(), 1000);
        });
    }

    // ============================================
    // TOOLTIP ENHANCEMENT
    // ============================================
    function enhanceTooltips() {
        $('[data-tooltip]').each(function() {
            const $element = $(this);
            const tooltipText = $element.data('tooltip');
            
            if (!$element.hasClass('sci-tooltip')) {
                $element.addClass('sci-tooltip');
                $element.attr('data-tooltip-content', tooltipText);
            }
        });
    }

    // ============================================
    // SMOOTH SCROLLING
    // ============================================
    function initSmoothScrolling() {
        $('a[href^="#"]').on('click', function(e) {
            const target = $(this.hash);
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 600, 'swing');
            }
        });
    }

    // ============================================
    // PLANET IMAGE ANIMATION
    // ============================================
    function initPlanetAnimation() {
        $('#planetImage').on('mouseenter', function() {
            $(this).addClass('sci-animate-float');
        }).on('mouseleave', function() {
            $(this).removeClass('sci-animate-float');
        });
    }

    // ============================================
    // RESOURCE BAR PERCENTAGE UPDATE
    // ============================================
    function updateResourceBars() {
        $('.bar-container').each(function() {
            const $container = $(this);
            const $bar = $container.find('.bar');
            const $text = $container.find('.bar-text');
            
            // Get current and max values from text
            const textContent = $text.text();
            const match = textContent.match(/(\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)/);
            
            if (match) {
                const current = parseFloat(match[1].replace(/[.,]/g, ''));
                const max = parseFloat(match[2].replace(/[.,]/g, ''));
                const percentage = (current / max) * 100;
                
                $bar.css('width', Math.min(percentage, 100) + '%');
                
                // Add color coding based on percentage
                if (percentage >= 90) {
                    $bar.css('background', 'linear-gradient(90deg, var(--color-accent-red), var(--color-accent-orange))');
                } else if (percentage >= 70) {
                    $bar.css('background', 'linear-gradient(90deg, var(--color-accent-yellow), var(--color-accent-green))');
                } else {
                    $bar.css('background', 'linear-gradient(90deg, var(--color-accent-blue), var(--color-accent-cyan))');
                }
            }
        });
    }

    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================
    window.showNotification = function(message, type = 'info', duration = 3000) {
        const iconMap = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        
        const colorMap = {
            'success': 'var(--color-accent-green)',
            'error': 'var(--color-accent-red)',
            'warning': 'var(--color-accent-yellow)',
            'info': 'var(--color-accent-cyan)'
        };
        
        const notification = $('<div class="sci-notification">' +
            '<i class="fas ' + iconMap[type] + '"></i>' +
            '<span>' + message + '</span>' +
        '</div>');
        
        notification.css('border-color', colorMap[type]);
        
        $('body').append(notification);
        setTimeout(() => notification.addClass('active'), 10);
        
        setTimeout(() => {
            notification.removeClass('active');
            setTimeout(() => notification.remove(), 300);
        }, duration);
    };

    // ============================================
    // PARALLAX SCROLLING EFFECT
    // ============================================
    function initParallaxEffect() {
        let scrollPos = 0;
        
        $(window).on('scroll', function() {
            const newScrollPos = $(window).scrollTop();
            const scrollDiff = newScrollPos - scrollPos;
            scrollPos = newScrollPos;
            
            // Subtle parallax on background
            $('body::before').css('transform', 'translateY(' + (scrollPos * 0.3) + 'px)');
        });
    }

    // ============================================
    // FORM VALIDATION ENHANCEMENT
    // ============================================
    function enhanceFormValidation() {
        $('input[required], textarea[required], select[required]').on('blur', function() {
            const $input = $(this);
            if (!this.validity.valid) {
                $input.addClass('invalid');
            } else {
                $input.removeClass('invalid');
            }
        });
        
        $('form').on('submit', function(e) {
            const $form = $(this);
            let isValid = true;
            
            $form.find('[required]').each(function() {
                if (!this.validity.valid) {
                    $(this).addClass('invalid');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Bitte fülle alle erforderlichen Felder aus', 'error');
            }
        });
    }

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // ESC to close overlays
            if (e.key === 'Escape') {
                $('.burger-menu').removeClass('active');
                $('#leftmenu').removeClass('active');
                $('.mobile-nav-overlay').removeClass('active').hide();
            }
            
            // M to toggle menu on mobile
            if (e.key === 'm' || e.key === 'M') {
                if ($(window).width() <= 768) {
                    $('.burger-menu').trigger('click');
                }
            }
        });
    }

    // ============================================
    // DYNAMIC TABLE STYLING
    // ============================================
    function enhanceTables() {
        $('table').each(function() {
            const $table = $(this);
            
            // Add sci-table class if not present
            if (!$table.hasClass('sci-table') && !$table.closest('.sci-card').length) {
                $table.addClass('sci-table');
            }
            
            // Add hover effects to rows
            $table.find('tbody tr').on('mouseenter', function() {
                $(this).css('background', 'rgba(0, 243, 255, 0.05)');
            }).on('mouseleave', function() {
                $(this).css('background', '');
            });
        });
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    $(document).ready(function() {
        console.log('SmartMoons Sci-Fi UI initialized');
        
        // Initialize all components
        initBurgerMenu();
        initCardEffects();
        enhanceTooltips();
        initSmoothScrolling();
        initPlanetAnimation();
        enhanceFormValidation();
        initKeyboardShortcuts();
        enhanceTables();
        updateResourceBars();
        updateProgressBars();
        
        // Set up intervals for dynamic updates
        setInterval(updateTimers, 1000);
        setInterval(updateResourceBars, 2000);
        setInterval(updateProgressBars, 1000);
        
        // Add fade-in animation to content
        $('.content_page, #content').addClass('sci-fade-in');
    });

    // Export functions for global use
    window.SciFiUI = {
        showLoading: showLoadingSpinner,
        hideLoading: hideLoadingSpinner,
        showNotification: showNotification,
        updateResourceBars: updateResourceBars,
        updateProgressBars: updateProgressBars
    };

})(jQuery);
