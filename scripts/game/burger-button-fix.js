/**
 * Burger Button Fix Script
 * Stellt sicher, dass der Burger-Button immer drei sichtbare Linien hat
 * Version: 1.0
 * Date: 2025-10-05
 */

document.addEventListener('DOMContentLoaded', function() {
    // Finde alle Burger Buttons
    const burgerButtons = document.querySelectorAll('.burger-menu, #burgerMenu');
    
    burgerButtons.forEach(function(button) {
        // Prüfe ob der Button bereits 3 span Elemente hat
        const spans = button.querySelectorAll('span');
        
        if (spans.length < 3) {
            // Entferne alle existierenden spans
            spans.forEach(span => span.remove());
            
            // Füge genau 3 neue spans hinzu
            for (let i = 0; i < 3; i++) {
                const span = document.createElement('span');
                button.appendChild(span);
            }
        }
        
        // Stelle sicher, dass der Button sichtbar ist
        button.style.visibility = 'visible';
        button.style.opacity = '1';
        
        // Füge Klick-Handler hinzu falls noch nicht vorhanden
        if (!button.hasAttribute('data-initialized')) {
            button.setAttribute('data-initialized', 'true');
            
            // Verbesserter Toggle mit Animation
            button.addEventListener('click', function() {
                this.classList.toggle('active');
                
                // Trigger mobile navigation (falls vorhanden)
                const mobileNav = document.getElementById('mobileNav');
                const navOverlay = document.getElementById('navOverlay');
                
                if (mobileNav) {
                    mobileNav.classList.toggle('active');
                }
                if (navOverlay) {
                    navOverlay.classList.toggle('active');
                }
                
                // Body scroll lock
                document.body.classList.toggle('nav-open');
            });
        }
    });
    
    // Debug: Log Burger Button Status
    console.log('Burger Button Fix: Found ' + burgerButtons.length + ' burger button(s)');
    burgerButtons.forEach((btn, index) => {
        const spanCount = btn.querySelectorAll('span').length;
        console.log(`Button ${index + 1}: ${spanCount} spans, visible: ${btn.style.visibility !== 'hidden'}`);
    });
});

// Zusätzlicher Check nach dynamischen Updates
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'childList') {
            const burgerButtons = document.querySelectorAll('.burger-menu, #burgerMenu');
            burgerButtons.forEach(function(button) {
                const spans = button.querySelectorAll('span');
                if (spans.length !== 3) {
                    // Repariere den Button
                    spans.forEach(span => span.remove());
                    for (let i = 0; i < 3; i++) {
                        const span = document.createElement('span');
                        button.appendChild(span);
                    }
                }
            });
        }
    });
});

// Beobachte Änderungen am Body
observer.observe(document.body, {
    childList: true,
    subtree: true
});