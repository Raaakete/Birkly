/**
 * Onboarding JavaScript
 * Handles the onboarding wizard flow
 */

const onboarding = {
    currentStep: 0,
    maxSteps: 4,
    needsFirstUser: false,
    demoContentChoice: null, // 'keep' or 'delete'
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
                this.maxSteps = 5; // Add one step for first user (0 + 4 normal steps)
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
            3: 'demo-content', // Keep or delete demo content
            4: 'complete'
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
            // No special handling needed - demo content step handles its own logic
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
    
    async keepDemoContent() {
        // Remove selected class from all cards
        document.querySelectorAll('.demo-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to keep card
        event.target.closest('.demo-card').classList.add('selected');
        
        // Keeping demo content requires no API call - just update progress and continue
        // Update progress (no API call needed for "keep")
        await this.updateProgressAPI('demo_content_kept', true);
        
        // Show continue button and proceed
        const actions = document.getElementById('demo-content-actions');
        if (actions) {
            actions.style.display = 'flex';
        }
        
        // Auto-continue after a moment
        setTimeout(() => {
            this.nextStep();
            this.updateProgressAPI();
        }, 300);
    },
    
    async deleteDemoContent() {
        // Remove selected class from all cards
        document.querySelectorAll('.demo-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to delete card
        event.target.closest('.demo-card').classList.add('selected');
        
        // Confirm deletion
        if (!confirm('Are you sure you want to delete all demo content? This cannot be undone.')) {
            return;
        }
        
        // Show loading state
        const deleteBtn = event.target.closest('.delete-card')?.querySelector('button');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting...';
        }
        
        try {
            const response = await fetch('/api/demo.php?action=delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    delete_content: true,
                    delete_website: true
                })
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                // Update progress
                await this.updateProgressAPI('demo_content_deleted', true);
                
                // Show continue button and proceed
                const actions = document.getElementById('demo-content-actions');
                if (actions) {
                    actions.style.display = 'flex';
                }
                
                // Auto-continue after a moment
                setTimeout(() => {
                    this.nextStep();
                    this.updateProgressAPI();
                }, 500);
            } else {
                console.error('Failed to delete demo content:', data.message);
                alert('Failed to delete demo content: ' + (data.message || 'Unknown error'));
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Delete';
                }
            }
        } catch (error) {
            console.error('Error deleting demo content:', error);
            alert('Error deleting demo content. Please try again.');
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Delete';
            }
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

