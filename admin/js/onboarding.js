/**
 * Onboarding JavaScript
 * Handles the onboarding wizard flow
 */

const onboarding = {
    currentStep: 0,
    maxSteps: 5,
    needsFirstUser: false,
    selectedDemoContent: null,
    selectedDemoTemplate: null,
    siteInfo: {},
    
    init() {
        // Check if we're on the onboarding page
        if (!document.querySelector('.onboarding-page')) {
            return;
        }
        
        // Check if we need to create first user
        this.checkFirstUser();
        
        // Setup form handlers
        this.setupForms();
    },
    
    async checkFirstUser() {
        try {
            const response = await fetch('/api/onboarding.php?action=status');
            const data = await response.json();
            
            if (data.status === 'ok' && data.data.needs_first_user) {
                this.needsFirstUser = true;
                this.maxSteps = 6; // Add one step for first user
                this.currentStep = 0;
                this.showStep(0); // Show first user step
            } else {
                this.currentStep = 1;
                this.showStep(1); // Show welcome step
            }
        } catch (error) {
            console.error('Error checking first user:', error);
            // Default to step 1 if check fails
            this.currentStep = 1;
            this.showStep(1);
        }
    },
    
    setupForms() {
        // First user form
        const firstUserForm = document.getElementById('first-user-form');
        if (firstUserForm) {
            firstUserForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createFirstUser();
            });
        }
        
        // Site info form
        const siteInfoForm = document.getElementById('site-info-form');
        if (siteInfoForm) {
            siteInfoForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSiteInfo();
            });
        }
    },
    
    async createFirstUser() {
        const form = document.getElementById('first-user-form');
        const errorDiv = document.getElementById('first-user-error');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        const email = document.getElementById('first-user-email').value.trim();
        const password = document.getElementById('first-user-password').value;
        const passwordConfirm = document.getElementById('first-user-password-confirm').value;
        
        // Clear previous errors
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
        
        // Validate passwords match
        if (password !== passwordConfirm) {
            errorDiv.textContent = 'Passwords do not match';
            errorDiv.style.display = 'block';
            return;
        }
        
        // Validate password length
        if (password.length < 8) {
            errorDiv.textContent = 'Password must be at least 8 characters';
            errorDiv.style.display = 'block';
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating Account...';
        
        try {
            const response = await fetch('/api/onboarding.php?action=create_first_user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.status === 'ok') {
                // User created and logged in - reload to continue onboarding
                window.location.reload();
            } else {
                errorDiv.textContent = data.message || 'Failed to create account';
                errorDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Admin Account';
            }
        } catch (error) {
            console.error('Error creating first user:', error);
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Admin Account';
        }
    },
    
    showStep(step) {
        this.currentStep = step;
        
        // Hide all steps
        document.querySelectorAll('.onboarding-step').forEach(el => {
            el.style.display = 'none';
        });
        
        // Show current step
        const currentStepEl = document.getElementById(`step-${this.getStepName(step)}`);
        if (currentStepEl) {
            currentStepEl.style.display = 'block';
        }
        
        // Update progress
        this.updateProgress();
        
        // Update progress steps
        document.querySelectorAll('.progress-step').forEach((el, index) => {
            if (index + 1 <= step) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
    },
    
    getStepName(step) {
        const steps = {
            0: 'first-user',
            1: 'welcome',
            2: 'site-info',
            3: 'demo-content',
            4: 'demo-template',
            5: 'complete'
        };
        return steps[step] || 'welcome';
    },
    
    updateProgress() {
        const progress = (this.currentStep / this.maxSteps) * 100;
        const progressFill = document.getElementById('progress-fill');
        if (progressFill) {
            progressFill.style.width = `${progress}%`;
        }
    },
    
    nextStep() {
        if (this.currentStep < this.maxSteps) {
            // Special handling for demo content step
            if (this.currentStep === 3) {
                if (this.selectedDemoContent === null && document.getElementById('demo-content-actions')) {
                    document.getElementById('demo-content-actions').style.display = 'flex';
                    return;
                }
                if (this.selectedDemoContent !== null) {
                    this.installDemoContent(this.selectedDemoContent);
                }
            }
            
            // Special handling for demo template step
            if (this.currentStep === 4) {
                if (this.selectedDemoTemplate !== null) {
                    this.installDemoTemplate(this.selectedDemoTemplate);
                }
            }
            
            this.showStep(this.currentStep + 1);
            this.updateProgressAPI();
        }
    },
    
    previousStep() {
        if (this.currentStep > 1) {
            this.showStep(this.currentStep - 1);
            this.updateProgressAPI();
        }
    },
    
    async getCSRFToken() {
        try {
            const response = await fetch('/api/csrf.php');
            const data = await response.json();
            if (data.status === 'ok' && data.data && data.data.csrf_token) {
                return data.data.csrf_token;
            }
        } catch (error) {
            console.error('Error fetching CSRF token:', error);
        }
        return null;
    },
    
    async saveSiteInfo() {
        const form = document.getElementById('site-info-form');
        if (!form) return;
        
        const formData = new FormData(form);
        this.siteInfo = {
            site_title: formData.get('site_title'),
            site_description: formData.get('site_description')
        };
        
        // Get CSRF token
        const csrfToken = await this.getCSRFToken();
        if (!csrfToken) {
            alert('Failed to get security token. Please refresh the page and try again.');
            return;
        }
        
        // Save site info via API
        fetch('/api/settings.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update',
                category: 'site',
                settings: {
                    name: this.siteInfo.site_title,
                    description: this.siteInfo.site_description
                },
                _csrf: csrfToken
            })
        }).then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                this.nextStep();
            } else {
                alert('Failed to save site information: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error saving site info:', error);
            alert('Failed to save site information. Please try again.');
        });
    },
    
    selectDemoContent(packId) {
        // Remove selected class from all cards
        document.querySelectorAll('.demo-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        event.target.closest('.demo-card').classList.add('selected');
        
        this.selectedDemoContent = packId;
        
        // Show continue button
        const actions = document.getElementById('demo-content-actions');
        if (actions) {
            actions.style.display = 'flex';
        }
    },
    
    selectDemoTemplate(templateId) {
        // Remove selected class from all cards
        document.querySelectorAll('.template-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to clicked card (if not skip card)
        if (templateId !== null) {
            event.target.closest('.demo-card').classList.add('selected');
        }
        
        this.selectedDemoTemplate = templateId;
    },
    
    async installDemoContent(packId) {
        if (!packId) return;
        
        try {
            const response = await fetch('/api/demo.php?action=install_content', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    pack_id: packId
                })
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                // Update progress
                this.updateProgressAPI('demo_content_installed', true);
            } else {
                console.error('Failed to install demo content:', data.message);
            }
        } catch (error) {
            console.error('Error installing demo content:', error);
        }
    },
    
    async installDemoTemplate(templateId) {
        if (!templateId) return;
        
        try {
            const response = await fetch('/api/demo.php?action=install_template', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    template_id: templateId
                })
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                // Update progress
                this.updateProgressAPI('demo_template_installed', true);
            } else {
                console.error('Failed to install demo template:', data.message);
            }
        } catch (error) {
            console.error('Error installing demo template:', error);
        }
    },
    
    async updateProgressAPI(key, value) {
        try {
            const update = {};
            if (key && value !== undefined) {
                update[key] = value;
            }
            update.step = this.currentStep;
            
            await fetch('/api/onboarding.php?action=update_progress', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(update)
            });
        } catch (error) {
            console.error('Error updating progress:', error);
        }
    },
    
    async complete() {
        try {
            const response = await fetch('/api/onboarding.php?action=complete', {
                method: 'POST'
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                // Redirect to dashboard
                window.location.href = '/admin/index.php?page=dashboard';
            } else {
                alert('Failed to complete onboarding: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error completing onboarding:', error);
            // Redirect anyway
            window.location.href = '/admin/index.php?page=dashboard';
        }
    },
    
    async skip() {
        if (!confirm('Are you sure you want to skip the setup? You can always complete it later from settings.')) {
            return;
        }
        
        try {
            const response = await fetch('/api/onboarding.php?action=skip', {
                method: 'POST'
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                // Redirect to dashboard
                window.location.href = '/admin/index.php?page=dashboard';
            } else {
                alert('Failed to skip onboarding: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error skipping onboarding:', error);
            // Redirect anyway
            window.location.href = '/admin/index.php?page=dashboard';
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => onboarding.init());
} else {
    onboarding.init();
}

