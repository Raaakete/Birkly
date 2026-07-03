/**
 * Onboarding JavaScript
 * Handles the onboarding wizard flow
 *
 * Flow: (0: first-user?) → 1: welcome → 2: site-info → 3: demo-content → 4: connect-ai (optional) → complete
 */

const onboarding = {
    currentStep: 0,
    maxSteps: 4, // steps 1-4 (AI step is visible but skippable)
    needsFirstUser: false,
    demoContentChoice: null, // 'keep' or 'delete'
    siteInfo: {},
    demoWebsiteUrl: null,
    
    init() {
        if (!document.querySelector('.onboarding-page')) {
            return;
        }
        
        this.checkFirstUser();
        this.setupForms();
    },
    
    async checkFirstUser() {
        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=status'));
            const data = await response.json();
            
            if (data.status === 'ok' && data.data.needs_first_user) {
                this.needsFirstUser = true;
                this.currentStep = 0;
                this.showStep(0);
            } else {
                this.currentStep = 1;
                this.showStep(1);
            }
        } catch (error) {
            console.error('Error checking first user:', error);
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
            
            this.setupValidation('first-user-name', 'form-group-name', (value) => value.length >= 2);
            this.setupValidation('first-user-email', 'form-group-email', (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value));
            this.setupValidation('first-user-password', 'form-group-password', (value) => value.length >= 8);
            
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
            
            this.setupValidation('project-name', 'form-group-project-name', (value) => value.length >= 2);
        }
        
        // Logo upload — click-through on the whole area
        const logoArea = document.getElementById('logo-upload-area');
        const logoInput = document.getElementById('project-logo');
        if (logoArea && logoInput) {
            logoArea.addEventListener('click', (e) => {
                if (e.target !== logoInput) {
                    logoInput.click();
                }
            });
            
            logoInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const placeholder = document.getElementById('logo-upload-placeholder');
                        const preview = document.getElementById('logo-preview');
                        const previewImg = document.getElementById('logo-preview-img');
                        if (placeholder) placeholder.style.display = 'none';
                        if (preview && previewImg) {
                            previewImg.src = event.target.result;
                            preview.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    },
    
    removeLogo() {
        const logoInput = document.getElementById('project-logo');
        const placeholder = document.getElementById('logo-upload-placeholder');
        const preview = document.getElementById('logo-preview');
        if (logoInput) logoInput.value = '';
        if (placeholder) placeholder.style.display = '';
        if (preview) preview.style.display = 'none';
    },
    
    setupValidation(inputId, formGroupId, validator) {
        const input = document.getElementById(inputId);
        const formGroup = document.getElementById(formGroupId);
        
        if (!input || !formGroup) return;
        
        const validate = () => {
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
        };
        
        input.addEventListener('blur', validate);
        input.addEventListener('input', validate);
    },
    
    async createFirstUser() {
        const form = document.getElementById('first-user-form');
        const errorDiv = document.getElementById('first-user-error');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        const name = document.getElementById('first-user-name').value.trim();
        const email = document.getElementById('first-user-email').value.trim();
        const password = document.getElementById('first-user-password').value;
        const passwordConfirm = document.getElementById('first-user-password-confirm').value;
        
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
        
        if (!name || name.length < 2) {
            errorDiv.textContent = 'Name must be at least 2 characters';
            errorDiv.style.display = 'block';
            return;
        }
        
        if (password !== passwordConfirm) {
            errorDiv.textContent = 'Passwords do not match';
            errorDiv.style.display = 'block';
            return;
        }
        
        if (password.length < 8) {
            errorDiv.textContent = 'Password must be at least 8 characters';
            errorDiv.style.display = 'block';
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.textContent = 'Creating Account...';
        
        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=create_first_user'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, password })
            });
            
            const data = await response.json();
            
            if (data.status === 'ok') {
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
        
        const stepName = this.getStepName(step);
        const currentStepId = `step-${stepName}`;
        
        document.querySelectorAll('.onboarding-step').forEach(el => {
            if (el.id !== currentStepId) {
                el.style.display = 'none';
                el.style.animation = '';
            }
        });
        
        const currentStepEl = document.getElementById(currentStepId);
        if (currentStepEl) {
            currentStepEl.style.display = 'block';
            currentStepEl.offsetHeight; // force reflow
            currentStepEl.style.animation = '';
            requestAnimationFrame(() => {
                currentStepEl.style.animation = 'fadeInUp 0.35s cubic-bezier(0.4, 0, 0.2, 1)';
            });
        }
        
        this.updateProgress();
    },
    
    getStepName(step) {
        const steps = {
            0: 'first-user',
            1: 'welcome',
            2: 'site-info',
            3: 'demo-content',
            4: 'connect-ai',
        };
        return steps[step] || 'welcome';
    },
    
    updateProgress() {
        // Progress bar only tracks steps 1-4
        const visibleSteps = this.maxSteps; // 4
        const currentVisible = Math.max(0, this.currentStep - (this.needsFirstUser ? 0 : 0));
        // For step 0 (first-user), hide the progress bar entirely
        const progressEl = document.getElementById('onboarding-progress');
        if (progressEl) {
            progressEl.style.display = (this.currentStep === 0) ? 'none' : '';
        }

        const pct = visibleSteps > 0
            ? Math.min(100, ((this.currentStep - 1) / (visibleSteps - 1)) * 100)
            : 0;
        const fill = document.getElementById('progress-fill');
        if (fill) fill.style.width = `${Math.max(0, pct)}%`;
        
        document.querySelectorAll('.progress-step').forEach(el => {
            const s = parseInt(el.dataset.step, 10);
            el.classList.toggle('completed', s < this.currentStep);
            el.classList.toggle('active', s === this.currentStep);
        });
    },
    
    nextStep() {
        if (this.currentStep < this.maxSteps) {
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
            const response = await fetch(Birkly.url('/api/csrf.php'));
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
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Saving...';
        }
        
        const csrfToken = await this.getCSRFToken();
        if (!csrfToken) {
            alert('Failed to get security token. Please refresh the page and try again.');
            this.resetSiteInfoSubmitButton(submitBtn, continueLabel);
            return;
        }
        
        const logoFile = formData.get('project_logo');
        let logoPath = '';
        
        if (logoFile && logoFile.size > 0) {
            const logoFormData = new FormData();
            logoFormData.append('file', logoFile);
            logoFormData.append('_csrf', csrfToken);
            
            try {
                const uploadResponse = await fetch(Birkly.url('/api/settings.php?action=upload_logo'), {
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
        
        if (logoPath) {
            await fetch(Birkly.url('/api/settings.php?action=update'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update',
                    category: 'branding',
                    settings: { logoPath },
                    _csrf: csrfToken
                })
            });
        }
        
        fetch(Birkly.url('/api/settings.php?action=update'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                category: 'admin',
                settings: {
                    project_name: this.siteInfo.project_name,
                    project_description: this.siteInfo.project_description || ''
                },
                _csrf: csrfToken
            })
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
        document.querySelectorAll('.demo-card').forEach(card => card.classList.remove('selected'));
        event.target.closest('.demo-card').classList.add('selected');
        
        this.demoContentChoice = 'keep';
        await this.updateProgressAPI('demo_content_kept', true);
        
        const actions = document.getElementById('demo-content-actions');
        if (actions) actions.style.display = 'flex';
        
        this.demoWebsiteUrl = this.getDemoWebsiteUrl();
        
        setTimeout(() => this.nextStep(), 300);
    },
    
    async deleteDemoContent() {
        document.querySelectorAll('.demo-card').forEach(card => card.classList.remove('selected'));
        event.target.closest('.demo-card').classList.add('selected');
        
        if (!confirm('Are you sure you want to delete all demo content? This cannot be undone.')) {
            return;
        }
        
        const deleteBtn = event.target.closest('.delete-card')?.querySelector('button');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting...';
        }
        
        try {
            const response = await fetch(Birkly.url('/api/demo.php?action=delete'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delete_content: true, delete_website: true })
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                this.demoContentChoice = 'delete';
                await this.updateProgressAPI('demo_content_deleted', true);
                
                const actions = document.getElementById('demo-content-actions');
                if (actions) actions.style.display = 'flex';
                
                setTimeout(() => this.nextStep(), 500);
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
            if (key && value !== undefined) update[key] = value;
            update.step = this.currentStep;
            
            await fetch(Birkly.url('/api/onboarding.php?action=update_progress'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(update)
            });
        } catch (error) {
            console.error('Error updating progress:', error);
        }
    },
    
    /**
     * Skip the optional AI step and complete onboarding.
     */
    skipAI() {
        this.complete();
    },

    /**
     * User clicked "Connect AI" — complete onboarding then navigate to Settings → AI.
     */
    async goToAISettings(e) {
        e.preventDefault();
        const href = e.currentTarget.getAttribute('href');
        try {
            await this._doComplete();
        } catch (_) {}
        window.location.href = href || Birkly.url('/admin/index.php?page=settings#ai');
    },
    
    async complete() {
        try {
            await this._doComplete();
        } catch (error) {
            console.error('Error completing onboarding:', error);
            window.location.href = Birkly.url('/admin/index.php?page=dashboard');
        }
    },

    async _doComplete() {
        const demoWebsiteUrl = this.demoContentChoice === 'keep' ? this.getDemoWebsiteUrl() : null;
        
        const response = await fetch(Birkly.url('/api/onboarding.php?action=complete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                demo_website_url: demoWebsiteUrl,
                demo_content_choice: this.demoContentChoice
            })
        });
        
        const data = await response.json();
        if (data.status === 'ok') {
            const params = new URLSearchParams();
            if (demoWebsiteUrl) params.set('demo_website_url', demoWebsiteUrl);
            if (this.demoContentChoice) params.set('demo_choice', this.demoContentChoice);
            const qs = params.toString();
            window.location.href = Birkly.url('/admin/index.php?page=dashboard') + (qs ? '&' + qs : '');
        } else {
            alert('Failed to complete onboarding: ' + (data.message || 'Unknown error'));
        }
    },
    
    getDemoWebsiteUrl() {
        const currentUrl = window.location.origin + window.location.pathname;
        const baseUrl = currentUrl.replace(/\/admin\/.*$/, '');
        return baseUrl + '/demo-website/';
    },
    
    async skip() {
        if (!confirm('Are you sure you want to skip the setup? You can always complete it later from settings.')) {
            return;
        }
        
        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=skip'), {
                method: 'POST'
            });
            
            const data = await response.json();
            if (data.status === 'ok') {
                window.location.href = Birkly.url('/admin/index.php?page=dashboard');
            } else {
                alert('Failed to skip onboarding: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error skipping onboarding:', error);
            window.location.href = Birkly.url('/admin/index.php?page=dashboard');
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => onboarding.init());
} else {
    onboarding.init();
}
