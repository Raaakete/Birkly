/**
 * Onboarding wizard v2 (P19)
 * Flow: (0 first-user?) → 1 welcome → 2 install context → 3 site info →
 *       4 use-case + demo → 5 marketplace → 6 connect AI (optional BYOAI) → complete
 */

const onboarding = {
    currentStep: 0,
    maxSteps: 6,
    needsFirstUser: false,
    demoContentChoice: null,
    siteInfo: {},
    demoWebsiteUrl: null,
    installPath: null,
    useCase: null,
    aiTestPassed: false,
    aiProviders: null,
    resumeHandled: false,

    init() {
        if (!document.querySelector('.onboarding-page')) return;

        const pageEl = document.querySelector('.onboarding-page');
        const dataInstall = pageEl?.dataset?.installPath || '';
        const urlInstall = new URLSearchParams(window.location.search).get('install');
        this.installPath = ['local', 'cloud'].includes(urlInstall) ? urlInstall
            : (['local', 'cloud'].includes(dataInstall) ? dataInstall : null);

        this.setupForms();
        this.bindStepActions();
        this.checkFirstUser();
    },

    bindStepActions() {
        const page = document.querySelector('.onboarding-page');
        if (!page) return;

        page.addEventListener('click', (e) => {
            const el = e.target.closest('[data-onboarding-action]');
            if (!el || el.disabled) return;

            const action = el.dataset.onboardingAction;
            const param = el.dataset.onboardingParam;

            switch (action) {
                case 'next':
                    this.nextStep();
                    break;
                case 'prev':
                    this.previousStep();
                    break;
                case 'skip':
                    this.skip();
                    break;
                case 'skip-use-case':
                    this.skipUseCase();
                    break;
                case 'select-use-case':
                    if (param) this.selectUseCase(param);
                    break;
                case 'keep-demo':
                    this.keepDemoContent();
                    break;
                case 'delete-demo':
                    this.deleteDemoContent();
                    break;
                case 'skip-ai':
                    this.skipAI();
                    break;
                case 'save-ai':
                    this.saveAIConnection();
                    break;
                default:
                    break;
            }
        });

        page.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const el = e.target.closest('[data-onboarding-action="keep-demo"], [data-onboarding-action="delete-demo"]');
            if (!el) return;
            e.preventDefault();
            el.click();
        });
    },

    async checkFirstUser() {
        try {
            let statusUrl = Birkly.url('/api/onboarding.php?action=status');
            if (this.installPath) {
                statusUrl += '&install=' + encodeURIComponent(this.installPath);
            }
            const response = await fetch(statusUrl);
            const data = await response.json();

            if (data.status === 'ok' && data.data) {
                if (data.data.install_path) {
                    this.installPath = data.data.install_path;
                }
                if (data.data.needs_first_user) {
                    this.needsFirstUser = true;
                    this.showStep(0);
                    return;
                }
                const resume = parseInt(data.data.last_step, 10);
                if (!this.resumeHandled && resume > 1 && resume <= this.maxSteps) {
                    this.resumeHandled = true;
                    this.useCase = data.data.use_case || null;
                    this.showStep(resume);
                    return;
                }
            }
            this.currentStep = 1;
            this.showStep(1);
        } catch (error) {
            console.error('Error checking first user:', error);
            this.showStep(1);
        }
    },

    setupForms() {
        const firstUserForm = document.getElementById('first-user-form');
        if (firstUserForm) {
            firstUserForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createFirstUser();
            });
        }

        const siteInfoForm = document.getElementById('site-info-form');
        if (siteInfoForm) {
            siteInfoForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveSiteInfo();
            });
        }

        document.getElementById('onboarding-ai-provider')?.addEventListener('change', () => {
            this.updateAiProviderFields();
            this.aiTestPassed = false;
        });
        document.getElementById('onboarding-test-ai-btn')?.addEventListener('click', () => this.testAIConnection());
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
        if (password !== passwordConfirm) {
            errorDiv.textContent = 'Passwords do not match';
            errorDiv.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
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
            }
        } catch (error) {
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.style.display = 'block';
            submitBtn.disabled = false;
        }
    },

    showStep(step) {
        this.currentStep = step;
        const stepName = this.getStepName(step);
        document.querySelectorAll('.onboarding-step').forEach(el => {
            el.style.display = el.id === `step-${stepName}` ? 'block' : 'none';
        });

        const header = document.querySelector('.onboarding-header');
        if (header) {
            header.style.display = step === 0 ? 'none' : '';
        }

        if (step === 2) this.renderInstallContext();
        if (step === 4 && this.useCase) this.highlightUseCase(this.useCase);
        if (step === 6) this.initAiWizard();

        this.updateProgress();
    },

    getStepName(step) {
        const steps = {
            0: 'first-user',
            1: 'welcome',
            2: 'install-context',
            3: 'site-info',
            4: 'use-case',
            5: 'marketplace',
            6: 'connect-ai',
        };
        return steps[step] || 'welcome';
    },

    renderInstallContext() {
        const body = document.getElementById('install-context-body');
        if (!body) return;
        if (this.installPath === 'local') {
            body.innerHTML = '<strong>Local development</strong><p>You are setting up on <code>localhost</code>. Use <code>./start-server.sh</code> and visit <code>http://localhost:8080/admin</code>.</p>';
        } else if (this.installPath === 'cloud') {
            body.innerHTML = '<strong>Cloud / FTP hosting</strong><p>You uploaded Birkly to your web server. Ensure <code>content/</code>, <code>media/</code>, <code>users/</code>, and <code>settings/</code> are writable.</p>';
        } else {
            body.innerHTML = '<strong>Not sure?</strong><p>Re-open <code>START_HERE.html</code> from your Birkly folder and pick local or cloud.</p>';
        }
    },

    updateProgress() {
        const progressEl = document.getElementById('onboarding-progress');
        if (progressEl) {
            progressEl.style.display = this.currentStep === 0 ? 'none' : '';
        }
        const pct = this.maxSteps > 1
            ? Math.min(100, ((this.currentStep - 1) / (this.maxSteps - 1)) * 100)
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
        const min = this.needsFirstUser ? 0 : 1;
        if (this.currentStep > min) {
            this.showStep(this.currentStep - 1);
            this.updateProgressAPI();
        }
    },

    selectUseCase(useCase) {
        this.useCase = useCase;
        document.querySelectorAll('.use-case-card').forEach(card => {
            card.classList.toggle('selected', card.dataset.useCase === useCase);
        });
        const btn = document.getElementById('use-case-continue');
        if (btn) btn.disabled = false;
        this.updateProgressAPI('use_case', useCase);
    },

    async skipUseCase() {
        await this.updateProgressAPI('use_case_skipped', true);
        this.nextStep();
    },

    highlightUseCase(useCase) {
        document.querySelectorAll('.use-case-card').forEach(card => {
            card.classList.toggle('selected', card.dataset.useCase === useCase);
        });
        const btn = document.getElementById('use-case-continue');
        if (btn) btn.disabled = false;
    },

    async getCSRFToken() {
        try {
            const response = await fetch(Birkly.url('/api/csrf.php'));
            const data = await response.json();
            if (data.status === 'ok' && data.data?.csrf_token) {
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
        const submitBtn = form.querySelector('button[type="submit"]');
        const projectName = document.getElementById('project-name').value.trim();
        if (!projectName || projectName.length < 2) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        const csrfToken = await this.getCSRFToken();
        if (!csrfToken) {
            alert('Failed to get security token.');
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        const formData = new FormData(form);
        this.siteInfo = {
            project_name: formData.get('project_name'),
            project_description: formData.get('project_description')
        };

        try {
            const response = await fetch(Birkly.url('/api/settings.php?action=update'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update',
                    category: 'admin',
                    settings: this.siteInfo,
                    _csrf: csrfToken
                })
            });
            const data = await response.json();
            if (data.status === 'ok') {
                this.nextStep();
            } else {
                alert('Failed to save: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Failed to save project information.');
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Continue';
        }
    },

    async keepDemoContent() {
        this.demoContentChoice = 'keep';
        await this.updateProgressAPI('demo_content_kept', true);
        this.demoWebsiteUrl = this.getDemoWebsiteUrl();
        document.getElementById('use-case-continue')?.removeAttribute('disabled');
    },

    async deleteDemoContent() {
        if (!confirm('Delete all demo content? This cannot be undone.')) return;
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
                document.getElementById('use-case-continue')?.removeAttribute('disabled');
            }
        } catch (error) {
            alert('Error deleting demo content.');
        }
    },

    async updateProgressAPI(key, value) {
        try {
            const update = { step: this.currentStep };
            if (key && value !== undefined) update[key] = value;
            await fetch(Birkly.url('/api/onboarding.php?action=update_progress'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(update)
            });
        } catch (error) {
            console.error('Error updating progress:', error);
        }
    },

    async initAiWizard() {
        if (this.aiProviders) return;
        try {
            const [provRes, rolesRes] = await Promise.all([
                fetch(Birkly.url('/api/ai_connections.php?action=providers')),
                fetch(Birkly.url('/api/roles.php?action=list_roles'))
            ]);
            const provData = await provRes.json();
            const rolesData = await rolesRes.json();

            this.aiProviders = provData.data?.providers || {};
            const select = document.getElementById('onboarding-ai-provider');
            if (select) {
                select.innerHTML = '<option value="">Select provider…</option>';
                Object.entries(this.aiProviders).forEach(([id, p]) => {
                    if (id === 'mcp') return;
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = p.name || id;
                    select.appendChild(opt);
                });
            }

            const roleSelect = document.getElementById('onboarding-ai-role');
            const roles = (rolesData.status === 'ok' && Array.isArray(rolesData.roles))
                ? rolesData.roles
                : [{ id: 'author', name: 'Author' }, { id: 'editor', name: 'Editor' }, { id: 'admin', name: 'Admin' }];
            if (roleSelect) {
                roleSelect.innerHTML = '';
                roles.forEach(role => {
                    const opt = document.createElement('option');
                    opt.value = role.id || role.name;
                    opt.textContent = role.name || role.id;
                    roleSelect.appendChild(opt);
                });
            }
        } catch (error) {
            console.error('AI wizard init failed:', error);
        }
    },

    updateAiProviderFields() {
        const provider = document.getElementById('onboarding-ai-provider')?.value;
        const keyGroup = document.getElementById('onboarding-api-key-group');
        const urlGroup = document.getElementById('onboarding-api-url-group');
        const ollamaHint = document.getElementById('onboarding-ollama-hint');
        const isOllama = provider === 'ollama';
        const isCustom = provider === 'custom';
        if (keyGroup) keyGroup.style.display = isOllama ? 'none' : '';
        if (urlGroup) urlGroup.style.display = (isOllama || isCustom) ? '' : 'none';
        if (ollamaHint) ollamaHint.style.display = isOllama ? '' : 'none';
    },

    async testAIConnection() {
        const provider = document.getElementById('onboarding-ai-provider')?.value;
        const apiKey = document.getElementById('onboarding-ai-api-key')?.value || '';
        const apiUrl = document.getElementById('onboarding-ai-api-url')?.value || '';
        const statusEl = document.getElementById('onboarding-test-ai-status');
        const csrf = await this.getCSRFToken();
        if (!provider || !csrf) return;

        if (statusEl) statusEl.textContent = 'Testing…';
        try {
            const response = await fetch(Birkly.url('/api/ai_connections.php?action=test'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider, api_key: apiKey, api_url: apiUrl, _csrf: csrf })
            });
            const data = await response.json();
            this.aiTestPassed = data.status === 'ok';
            if (statusEl) {
                statusEl.textContent = this.aiTestPassed ? '✓ Connection OK' : ('✗ ' + (data.message || 'Failed'));
                statusEl.className = this.aiTestPassed ? 'test-ok' : 'test-error';
            }
        } catch (error) {
            this.aiTestPassed = false;
            if (statusEl) statusEl.textContent = '✗ Test failed';
        }
    },

    async saveAIConnection() {
        const provider = document.getElementById('onboarding-ai-provider')?.value;
        const apiKey = document.getElementById('onboarding-ai-api-key')?.value || '';
        const apiUrl = document.getElementById('onboarding-ai-api-url')?.value || '';
        const role = document.getElementById('onboarding-ai-role')?.value || 'author';
        const requireApproval = document.getElementById('onboarding-ai-require-approval')?.checked ?? true;
        const csrf = await this.getCSRFToken();

        if (!provider || !csrf) {
            alert('Select a provider first.');
            return;
        }
        if (provider !== 'ollama' && !apiKey) {
            alert('API key is required.');
            return;
        }

        const btn = document.getElementById('onboarding-save-ai-btn');
        if (btn) btn.disabled = true;

        try {
            const response = await fetch(Birkly.url('/api/ai_connections.php?action=create'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    provider,
                    api_key: apiKey,
                    api_url: apiUrl,
                    role,
                    require_approval: requireApproval,
                    shared_type: 'none',
                    _csrf: csrf
                })
            });
            const data = await response.json();
            if (data.status === 'ok') {
                await this.updateProgressAPI('ai_connected', true);
                await this.complete();
            } else {
                alert(data.message || 'Failed to create AI connection');
                if (btn) btn.disabled = false;
            }
        } catch (error) {
            alert('Failed to save AI connection.');
            if (btn) btn.disabled = false;
        }
    },

    async skipAI() {
        await this.updateProgressAPI('ai_skipped', true);
        await this.complete();
    },

    async complete() {
        try {
            await this._doComplete();
        } catch (error) {
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
        if (!confirm('Skip setup? You can finish later from the dashboard.')) return;
        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=skip'), { method: 'POST' });
            const data = await response.json();
            if (data.status === 'ok') {
                window.location.href = Birkly.url('/admin/index.php?page=dashboard');
            }
        } catch (error) {
            window.location.href = Birkly.url('/admin/index.php?page=dashboard');
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => onboarding.init());
} else {
    onboarding.init();
}
