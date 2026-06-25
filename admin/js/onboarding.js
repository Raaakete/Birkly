/**
 * Onboarding JavaScript
 * Handles the onboarding wizard flow
 */

const onboarding = {
    currentStep: 0,
    maxSteps: 3, // Reduced from 4 since completion is now a modal
    needsFirstUser: false,
    demoContentChoice: null, // 'keep' or 'delete'
    siteInfo: {},
    demoWebsiteUrl: null, // Will be set when demo content is kept
    
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
                this.maxSteps = 4; // Add one step for first user (0 + 3 normal steps)
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
            
            // Real-time validation for first user form
            this.setupValidation('first-user-name', 'form-group-name', (value) => value.length >= 2);
            this.setupValidation('first-user-email', 'form-group-email', (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value));
            this.setupValidation('first-user-password', 'form-group-password', (value) => value.length >= 8);
            
            // Password confirmation validation
            const passwordInput = document.getElementById('first-user-password');
            const passwordConfirmInput = document.getElementById('first-user-password-confirm');
            if (passwordInput && passwordConfirmInput) {
                const validatePasswordMatch = () => {
                    const password = passwordInput.value;
                    const passwordConfirm = passwordConfirmInput.value;
                    const formGroup = document.getElementById('form-group-password-confirm');
                    if (passwordConfirm.length > 0) {
                        if (password === passwordConfirm && password.length >= 8) {
                            formGroup.classList.remove('error');
                            formGroup.classList.add('success');
                        } else {
                            formGroup.classList.remove('success');
                            formGroup.classList.add('error');
                        }
                    } else {
                        formGroup.classList.remove('error', 'success');
                    }
                };
                
                passwordInput.addEventListener('input', validatePasswordMatch);
                passwordConfirmInput.addEventListener('input', validatePasswordMatch);
            }
        }
        
        // Site info form
        const siteInfoForm = document.getElementById('site-info-form');
        if (siteInfoForm) {
            siteInfoForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSiteInfo();
            });
            
            // Real-time validation for site info form
            this.setupValidation('project-name', 'form-group-project-name', (value) => value.length >= 2);
        }
        
        // Logo preview
        const logoInput = document.getElementById('project-logo');
        if (logoInput) {
            logoInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const preview = document.getElementById('logo-preview');
                        const previewImg = document.getElementById('logo-preview-img');
                        if (preview && previewImg) {
                            previewImg.src = event.target.result;
                            preview.style.display = 'block';
                            preview.style.animation = 'fadeIn 0.3s ease-out';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    },
    
    setupValidation(inputId, formGroupId, validator) {
        const input = document.getElementById(inputId);
        const formGroup = document.getElementById(formGroupId);
        
        if (!input || !formGroup) return;
        
        input.addEventListener('blur', () => {
            const value = input.value.trim();
            if (value.length > 0) {
                if (validator(value)) {
                    formGroup.classList.remove('error');
                    formGroup.classList.add('success');
                } else {
                    formGroup.classList.remove('success');
                    formGroup.classList.add('error');
                }
            } else {
                formGroup.classList.remove('error', 'success');
            }
        });
        
        input.addEventListener('input', () => {
            const value = input.value.trim();
            if (value.length > 0) {
                if (validator(value)) {
                    formGroup.classList.remove('error');
                    formGroup.classList.add('success');
                } else {
                    formGroup.classList.remove('success');
                    formGroup.classList.add('error');
                }
            } else {
                formGroup.classList.remove('error', 'success');
            }
        });
    },
    
    async createFirstUser() {
        const form = document.getElementById('first-user-form');
        const errorDiv = document.getElementById('first-user-error');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        const name = document.getElementById('first-user-name').value.trim();
        const email = document.getElementById('first-user-email').value.trim();
        const password = document.getElementById('first-user-password').value;
        const passwordConfirm = document.getElementById('first-user-password-confirm').value;
        
        // Clear previous errors
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
        
        // Validate name
        if (!name || name.length < 2) {
            errorDiv.textContent = 'Name must be at least 2 characters';
            errorDiv.style.display = 'block';
            return;
        }
        
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
        
        // Disable submit button and show loading state
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.textContent = 'Creating Account...';
        
        try {
            const response = await fetch('/api/onboarding.php?action=create_first_user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: name,
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
                submitBtn.classList.remove('loading');
                submitBtn.textContent = 'Create Admin Account';
            }
        } catch (error) {
            console.error('Error creating first user:', error);
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            submitBtn.textContent = 'Create Admin Account';
        }
    },
    
    showStep(step) {
        this.currentStep = step;
        
        // Get the step name for the current step
        const stepName = this.getStepName(step);
        const currentStepId = `step-${stepName}`;
        
        // First, immediately hide all steps that are not the current one
        // This prevents any race conditions with animations
        document.querySelectorAll('.onboarding-step').forEach(el => {
            const stepId = el.id;
            if (stepId !== currentStepId) {
                // Immediately hide (no animation delay)
                el.style.display = 'none';
                el.style.animation = '';
            }
        });
        
        // Show current step with animation
        const currentStepEl = document.getElementById(currentStepId);
        if (currentStepEl) {
            // Always show the step, even if it was already visible
            currentStepEl.style.display = 'block';
            // Force reflow to ensure animation works
            currentStepEl.offsetHeight;
            // Reset animation and apply fadeIn
            currentStepEl.style.animation = '';
            // Use requestAnimationFrame to ensure the display change is applied before animation
            requestAnimationFrame(() => {
                currentStepEl.style.animation = 'fadeInUp 0.4s ease-out';
            });
        }
        
        // Update progress
        this.updateProgress();
        
        // Update progress steps
        document.querySelectorAll('.progress-step').forEach((el, index) => {
            const stepNumber = this.needsFirstUser ? index : index + 1;
            if (stepNumber < step) {
                el.classList.add('completed');
                el.classList.remove('active');
            } else if (stepNumber === step) {
                el.classList.add('active');
                el.classList.remove('completed');
            } else {
                el.classList.remove('active', 'completed');
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

    resetSiteInfoSubmitButton(submitBtn, label = 'Continue') {
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading');
        submitBtn.textContent = label;
    },
    
    async saveSiteInfo() {
        const form = document.getElementById('site-info-form');
        if (!form) return;
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const continueLabel = submitBtn ? (submitBtn.dataset.defaultLabel || submitBtn.textContent.trim() || 'Continue') : 'Continue';
        if (submitBtn && !submitBtn.dataset.defaultLabel) {
            submitBtn.dataset.defaultLabel = continueLabel;
        }
        const projectName = document.getElementById('project-name').value.trim();
        
        // Validate project name
        if (!projectName || projectName.length < 2) {
            const formGroup = document.getElementById('form-group-project-name');
            if (formGroup) {
                formGroup.classList.add('error');
                formGroup.classList.remove('success');
            }
            return;
        }
        
        const formData = new FormData(form);
        this.siteInfo = {
            project_name: formData.get('project_name'),
            project_description: formData.get('project_description')
        };
        
        // Show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Saving...';
        }
        
        // Get CSRF token
        const csrfToken = await this.getCSRFToken();
        if (!csrfToken) {
            alert('Failed to get security token. Please refresh the page and try again.');
            this.resetSiteInfoSubmitButton(submitBtn, continueLabel);
            return;
        }
        
        // Handle logo upload if provided
        const logoFile = formData.get('project_logo');
        let logoPath = '';
        
        if (logoFile && logoFile.size > 0) {
            // Upload logo first
            const logoFormData = new FormData();
            logoFormData.append('file', logoFile);
            logoFormData.append('_csrf', csrfToken);
            
            try {
                const uploadResponse = await fetch('/api/settings.php?action=upload_logo', {
                    method: 'POST',
                    body: logoFormData
                });
                
                const uploadResult = await uploadResponse.json();
                if (uploadResult.status === 'ok') {
                    logoPath = uploadResult.data.logoPath;
                } else {
                    alert('Failed to upload logo: ' + (uploadResult.message || 'Unknown error'));
                    this.resetSiteInfoSubmitButton(submitBtn, continueLabel);
                    return;
                }
            } catch (error) {
                console.error('Error uploading logo:', error);
                alert('Failed to upload logo. Please try again.');
                this.resetSiteInfoSubmitButton(submitBtn, continueLabel);
                return;
            }
        }
        
        // Save project info via API
        const settingsData = {
            action: 'update',
            category: 'admin',
            settings: {
                project_name: this.siteInfo.project_name,
                project_description: this.siteInfo.project_description || ''
            },
            _csrf: csrfToken
        };
        
        // If logo was uploaded, save it to branding settings
        if (logoPath) {
            const brandingFormData = new FormData();
            brandingFormData.append('action', 'update');
            brandingFormData.append('category', 'branding');
            brandingFormData.append('logoPath', logoPath);
            brandingFormData.append('_csrf', csrfToken);
            
            // Save branding settings
            await fetch('/api/settings.php?action=update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update',
                    category: 'branding',
                    settings: {
                        logoPath: logoPath
                    },
                    _csrf: csrfToken
                })
            });
        }
        
        fetch('/api/settings.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(settingsData)
        }).then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                this.nextStep();
            } else {
                alert('Failed to save project information: ' + (data.message || 'Unknown error'));
                this.resetSiteInfoSubmitButton(submitBtn, continueLabel);
            }
        })
        .catch(error => {
            console.error('Error saving project info:', error);
            alert('Failed to save project information. Please try again.');
            this.resetSiteInfoSubmitButton(submitBtn, continueLabel);
        });
    },
    
    async keepDemoContent() {
        // Remove selected class from all cards
        document.querySelectorAll('.demo-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to keep card
        event.target.closest('.demo-card').classList.add('selected');
        
        // Store choice
        this.demoContentChoice = 'keep';
        
        // Keeping demo content requires no API call - just update progress and continue
        // Update progress (no API call needed for "keep")
        await this.updateProgressAPI('demo_content_kept', true);
        
        // Show continue button and proceed
        const actions = document.getElementById('demo-content-actions');
        if (actions) {
            actions.style.display = 'flex';
        }
        
        // Store demo website URL for later use
        this.demoWebsiteUrl = this.getDemoWebsiteUrl();
        
        // Auto-continue after a moment
        setTimeout(() => {
            this.complete(); // Complete onboarding instead of nextStep
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
                // Store choice
                this.demoContentChoice = 'delete';
                
                // Update progress
                await this.updateProgressAPI('demo_content_deleted', true);
                
                // Show continue button and proceed
                const actions = document.getElementById('demo-content-actions');
                if (actions) {
                    actions.style.display = 'flex';
                }
                
                // Auto-continue after a moment
                setTimeout(() => {
                    this.complete(); // Complete onboarding instead of nextStep
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
            // Store demo website URL if demo content was kept
            const demoWebsiteUrl = this.demoContentChoice === 'keep' ? this.getDemoWebsiteUrl() : null;
            
            const response = await fetch('/api/onboarding.php?action=complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    demo_website_url: demoWebsiteUrl,
                    demo_content_choice: this.demoContentChoice
                })
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                // Redirect to dashboard - the modal will be shown there
                const params = new URLSearchParams();
                if (demoWebsiteUrl) {
                    params.set('demo_website_url', demoWebsiteUrl);
                }
                if (this.demoContentChoice) {
                    params.set('demo_choice', this.demoContentChoice);
                }
                const queryString = params.toString();
                window.location.href = '/admin/index.php?page=dashboard' + (queryString ? '&' + queryString : '');
            } else {
                alert('Failed to complete onboarding: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error completing onboarding:', error);
            // Redirect anyway
            window.location.href = '/admin/index.php?page=dashboard';
        }
    },
    
    /**
     * Generate demo website URL based on current location
     */
    getDemoWebsiteUrl() {
        const currentUrl = window.location.origin + window.location.pathname;
        // Remove /admin/index.php or /admin/ from the path
        const baseUrl = currentUrl.replace(/\/admin\/.*$/, '');
        return baseUrl + '/demo-website/';
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

