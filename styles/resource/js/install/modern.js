/**
 * SmartMoons Modern Installer JavaScript
 * Dark Sci-Fi Theme with GSAP Animations & Anime.js
 */

class SmartMoonsInstaller {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 8;
        this.init();
    }

    init() {
        this.createStarfield();
        this.setupEventListeners();
        this.animateEntrance();
        this.updateProgress();
    }

    createStarfield() {
        const starfield = document.createElement('div');
        starfield.className = 'starfield';
        document.body.appendChild(starfield);

        // Add more stars dynamically
        for (let i = 0; i < 100; i++) {
            const star = document.createElement('div');
            star.style.position = 'absolute';
            star.style.width = Math.random() * 3 + 'px';
            star.style.height = star.style.width;
            star.style.backgroundColor = '#fff';
            star.style.borderRadius = '50%';
            star.style.left = Math.random() * 100 + '%';
            star.style.top = Math.random() * 100 + '%';
            star.style.opacity = Math.random();
            star.style.animation = `twinkle ${Math.random() * 3 + 2}s ease-in-out infinite alternate`;
            starfield.appendChild(star);
        }
    }

    setupEventListeners() {
        // Language selector
        const langSelect = document.getElementById('lang');
        if (langSelect) {
            langSelect.addEventListener('change', (e) => {
                this.showWarpLoader();
                setTimeout(() => {
                    window.location.href = `?lang=${e.target.value}`;
                }, 1000);
            });
        }

        // Form submissions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                this.showWarpLoader();
            });
        });

        // Button clicks
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('click', (e) => {
                this.animateButtonClick(e.target);
            });
        });

        // Step navigation
        const stepItems = document.querySelectorAll('.step-item');
        stepItems.forEach((item, index) => {
            item.addEventListener('click', () => {
                this.goToStep(index + 1);
            });
        });
    }

    animateEntrance() {
        // GSAP entrance animation
        if (typeof gsap !== 'undefined') {
            gsap.from('.installer-container', {
                duration: 1,
                y: 50,
                opacity: 0,
                ease: 'power2.out'
            });

            gsap.from('.installer-logo', {
                duration: 1.2,
                scale: 0.8,
                opacity: 0,
                ease: 'back.out(1.7)',
                delay: 0.3
            });

            gsap.from('.step-content', {
                duration: 0.8,
                y: 30,
                opacity: 0,
                stagger: 0.1,
                delay: 0.6
            });
        }
    }

    showWarpLoader() {
        const loader = document.createElement('div');
        loader.className = 'warp-loader';
        loader.innerHTML = `
            <div class="warp-ring"></div>
            <div class="warp-ring"></div>
            <div class="warp-ring"></div>
        `;
        document.body.appendChild(loader);

        // Hide loader after animation
        setTimeout(() => {
            loader.remove();
        }, 2000);
    }

    animateButtonClick(button) {
        if (typeof gsap !== 'undefined') {
            gsap.to(button, {
                duration: 0.1,
                scale: 0.95,
                yoyo: true,
                repeat: 1
            });
        }
    }

    updateProgress() {
        const progressFill = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        
        if (progressFill && progressText) {
            const percentage = (this.currentStep / this.totalSteps) * 100;
            
            if (typeof gsap !== 'undefined') {
                gsap.to(progressFill, {
                    duration: 0.5,
                    width: percentage + '%',
                    ease: 'power2.out'
                });
            } else {
                progressFill.style.width = percentage + '%';
            }
            
            progressText.textContent = `Step ${this.currentStep} of ${this.totalSteps}`;
        }

        // Update step navigation
        const stepItems = document.querySelectorAll('.step-item');
        stepItems.forEach((item, index) => {
            item.classList.remove('active', 'completed');
            if (index + 1 < this.currentStep) {
                item.classList.add('completed');
            } else if (index + 1 === this.currentStep) {
                item.classList.add('active');
            }
        });
    }

    goToStep(step) {
        if (step >= 1 && step <= this.totalSteps) {
            this.currentStep = step;
            this.updateProgress();
            this.animateStepTransition();
        }
    }

    animateStepTransition() {
        const stepContent = document.querySelector('.step-content');
        if (stepContent && typeof gsap !== 'undefined') {
            gsap.to(stepContent, {
                duration: 0.3,
                opacity: 0,
                y: -20,
                onComplete: () => {
                    gsap.fromTo(stepContent, {
                        opacity: 0,
                        y: 20
                    }, {
                        duration: 0.3,
                        opacity: 1,
                        y: 0,
                        ease: 'power2.out'
                    });
                }
            });
        }
    }

    showError(message) {
        this.showMessage(message, 'error');
    }

    showSuccess(message) {
        this.showMessage(message, 'success');
    }

    showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `${type}-message fade-in-up`;
        messageDiv.textContent = message;
        
        const container = document.querySelector('.step-content');
        if (container) {
            container.appendChild(messageDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (typeof gsap !== 'undefined') {
                    gsap.to(messageDiv, {
                        duration: 0.3,
                        opacity: 0,
                        y: -20,
                        onComplete: () => messageDiv.remove()
                    });
                } else {
                    messageDiv.remove();
                }
            }, 5000);
        }
    }

    // System requirements checker
    checkSystemRequirements() {
        const requirements = [
            {
                name: 'PHP Version',
                check: () => {
                    const phpVersion = document.querySelector('.php-version');
                    return phpVersion && phpVersion.textContent.includes('8.0');
                }
            },
            {
                name: 'PDO Extension',
                check: () => {
                    const pdoCheck = document.querySelector('.pdo-check');
                    return pdoCheck && pdoCheck.textContent.includes('Yes');
                }
            },
            {
                name: 'JSON Extension',
                check: () => {
                    const jsonCheck = document.querySelector('.json-check');
                    return jsonCheck && jsonCheck.textContent.includes('Yes');
                }
            },
            {
                name: 'Config File Writable',
                check: () => {
                    const configCheck = document.querySelector('.config-check');
                    return configCheck && configCheck.textContent.includes('writable');
                }
            }
        ];

        let allPassed = true;
        requirements.forEach(req => {
            const passed = req.check();
            if (!passed) allPassed = false;
        });

        return allPassed;
    }

    // Database connection tester
    async testDatabaseConnection(formData) {
        this.showWarpLoader();
        
        try {
            const response = await fetch('index.php?mode=install&step=4', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.text();
            
            if (result.includes('successful')) {
                this.showSuccess('Database connection successful!');
                setTimeout(() => {
                    this.goToStep(5);
                }, 1500);
            } else {
                this.showError('Database connection failed. Please check your credentials.');
            }
        } catch (error) {
            this.showError('Connection test failed: ' + error.message);
        }
    }

    // Installation progress tracker
    trackInstallationProgress() {
        const steps = [
            'Checking system requirements...',
            'Testing database connection...',
            'Creating database tables...',
            'Setting up configuration...',
            'Creating admin account...',
            'Finalizing installation...',
            'Installation complete!'
        ];

        let currentStepIndex = 0;
        
        const updateProgress = () => {
            const progressText = document.querySelector('.progress-text');
            if (progressText && currentStepIndex < steps.length) {
                progressText.textContent = steps[currentStepIndex];
                currentStepIndex++;
                
                if (currentStepIndex < steps.length) {
                    setTimeout(updateProgress, 2000);
                }
            }
        };

        updateProgress();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.smartMoonsInstaller = new SmartMoonsInstaller();
});

// Add some interactive effects
document.addEventListener('mousemove', (e) => {
    const cursor = document.querySelector('.cursor-glow');
    if (cursor) {
        cursor.style.left = e.clientX + 'px';
        cursor.style.top = e.clientY + 'px';
    }
});

// Add keyboard navigation
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList.contains('form-control')) {
        const form = e.target.closest('form');
        if (form) {
            form.submit();
        }
    }
});

// Add touch support for mobile
if ('ontouchstart' in window) {
    document.body.classList.add('touch-device');
    
    // Add touch feedback
    document.addEventListener('touchstart', (e) => {
        if (e.target.classList.contains('btn')) {
            e.target.style.transform = 'scale(0.95)';
        }
    });
    
    document.addEventListener('touchend', (e) => {
        if (e.target.classList.contains('btn')) {
            e.target.style.transform = 'scale(1)';
        }
    });
}
