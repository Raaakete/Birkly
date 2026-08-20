/**
 * Onboarding wizard v3
 * Flow: (0 first-user?) → 1 welcome+install info → 2 site info →
 *       3 starter templates → 4 connect AI (optional) → complete
 */

const onboarding = {
    currentStep: 0,
    maxSteps: 4,
    showAiStep: true,
    needsFirstUser: false,
    starterTemplate: null,
    demoWebsiteUrl: null,
    installPath: null,
    aiTestPassed: false,
    aiProviders: null,
    resumeHandled: false,
    aiMode: 'chat',
    hostingWizard: null,

    init() {
        if (!document.querySelector('.onboarding-page')) return;

        const pageEl = document.querySelector('.onboarding-page');
        const dataInstall = pageEl?.dataset?.installPath || '';
        this.showAiStep = pageEl?.dataset?.aiStep !== '0';
        this.maxSteps = this.showAiStep ? 4 : 3;
        const urlInstall = new URLSearchParams(window.location.search).get('install');
        this.installPath = ['local', 'cloud'].includes(urlInstall) ? urlInstall
            : (['local', 'cloud'].includes(dataInstall) ? dataInstall : null);

        this.setupForms();
        this.bindStepActions();
        this.bindAiModeTabs();
        this.checkFirstUser();
    },

    bindAiModeTabs() {
        document.querySelectorAll('.ai-mode-tab').forEach(tab => {
            tab.addEventListener('click', async () => {
                this.aiMode = tab.dataset.aiMode || 'chat';
                document.querySelectorAll('.ai-mode-tab').forEach(t => {
                    const active = t === tab;
                    t.classList.toggle('active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                const chatPanel = document.getElementById('ai-panel-chat');
                const extPanel = document.getElementById('ai-panel-external');
                const saveBtn = document.getElementById('onboarding-save-ai-btn');
                if (chatPanel) chatPanel.hidden = this.aiMode !== 'chat';
                if (extPanel) extPanel.hidden = this.aiMode !== 'external';
                if (saveBtn) saveBtn.hidden = this.aiMode !== 'chat';
            });
        });
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
                case 'select-starter':
                    if (param) this.selectStarterTemplate(param);
                    break;
                case 'skip-starter':
                    this.skipStarterTemplate();
                    break;
                case 'apply-starter':
                    this.applyStarterTemplate();
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
    },

    applyStarterAvailability(availability) {
        const note = document.getElementById('starter-availability-note');
        const cards = document.querySelectorAll('.starter-template-grid [data-starter]');
        if (!cards.length) return;

        let anyRemoteUnavailable = false;
        let catalogMissing = false;

        cards.forEach((card) => {
            const id = card.dataset.starter;
            if (!id || id === 'blank') return;

            const meta = availability && availability[id] ? availability[id] : null;
            const statusEl = card.querySelector('[data-starter-status]');
            const selectable = !!(meta && meta.selectable);

            if (!meta) {
                catalogMissing = true;
            }

            card.dataset.starterSelectable = selectable ? '1' : '0';
            card.dataset.starterPending = '0';
            card.classList.toggle('use-case-card--disabled', !selectable);

            if (selectable) {
                card.removeAttribute('disabled');
                card.removeAttribute('aria-disabled');
                card.setAttribute('data-onboarding-action', 'select-starter');
                card.setAttribute('data-onboarding-param', id);
                if (statusEl) statusEl.remove();
            } else {
                anyRemoteUnavailable = true;
                card.setAttribute('disabled', 'disabled');
                card.setAttribute('aria-disabled', 'true');
                card.removeAttribute('data-onboarding-action');
                card.removeAttribute('data-onboarding-param');
                if (this.starterTemplate === id) {
                    this.starterTemplate = null;
                    const cont = document.getElementById('starter-continue');
                    if (cont) cont.disabled = true;
                }
                let label = 'Internet connection needed';
                if (meta && meta.catalog_reachable === false) {
                    label = 'Internet connection needed';
                } else if (meta && meta.enabled && !meta.ready) {
                    label = 'Not available yet';
                } else if (meta && !meta.enabled) {
                    label = 'Coming soon';
                }
                if (statusEl) {
                    statusEl.textContent = label;
                } else {
                    const em = document.createElement('em');
                    em.className = 'starter-coming-soon';
                    em.setAttribute('data-starter-status', '');
                    em.textContent = label;
                    card.appendChild(em);
                }
            }
        });

        if (note) {
            if (catalogMissing || anyRemoteUnavailable) {
                note.hidden = false;
                note.textContent = 'Marsk needs an internet connection from this computer so Birkly can download the demo pack. You can continue with Blank.';
            } else {
                note.hidden = true;
                note.textContent = '';
            }
        }
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
                if (typeof data.data.is_local_dev === 'boolean') {
                    this.showAiStep = !data.data.is_local_dev;
                    this.maxSteps = this.showAiStep ? 4 : 3;
                }
                if (data.data.starter_availability) {
                    this.applyStarterAvailability(data.data.starter_availability);
                }
                if (data.data.needs_first_user) {
                    this.needsFirstUser = true;
                    this.showStep(0);
                    return;
                }
                const resume = parseInt(data.data.last_step, 10);
                if (!this.resumeHandled && resume > 1 && resume <= this.maxSteps) {
                    this.resumeHandled = true;
                    this.starterTemplate = data.data.starter_template || data.data.use_case || null;
                    this.showStep(resume);
                    return;
                }
                if (!this.resumeHandled && resume === 4 && !this.showAiStep) {
                    this.resumeHandled = true;
                    await this.updateProgressAPI('ai_skipped_local', true);
                    await this.complete();
                    return;
                }
            }
            this.currentStep = 1;
            this.showStep(1);
        } catch (error) {
            console.error('Error checking first user:', error);
            this.applyStarterAvailability({});
            this.showStep(1);
        }
    },

    setupForms() {
        document.getElementById('first-user-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.createFirstUser();
        });
        document.getElementById('site-info-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSiteInfo();
        });
        document.getElementById('onboarding-ai-provider')?.addEventListener('change', () => {
            this.updateAiProviderFields();
            this.aiTestPassed = false;
        });
        document.getElementById('onboarding-test-ai-btn')?.addEventListener('click', async () => this.testAIConnection());
    },

    async createFirstUser() {
        const form = document.getElementById('first-user-form');
        const errorDiv = document.getElementById('first-user-error');
        const submitBtn = form.querySelector('button[type="submit"]');
        const name = document.getElementById('first-user-name').value.trim();
        const email = document.getElementById('first-user-email').value.trim();
        const password = document.getElementById('first-user-password').value;
        const passwordConfirm = document.getElementById('first-user-password-confirm').value;

        errorDiv.hidden = true;
        if (password !== passwordConfirm) {
            errorDiv.textContent = 'Passwords do not match';
            errorDiv.hidden = false;
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
                errorDiv.hidden = false;
                submitBtn.disabled = false;
            }
        } catch (error) {
            errorDiv.textContent = 'An error occurred. Please try again.';
            errorDiv.hidden = false;
            submitBtn.disabled = false;
        }
    },

    showStep(step) {
        this.currentStep = step;
        const stepName = this.getStepName(step);
        document.querySelectorAll('.onboarding-step').forEach(el => {
            el.hidden = el.id !== `step-${stepName}`;
        });

        const header = document.querySelector('.onboarding-header');
        if (header) {
            header.hidden = step === 0;
        }

        if (step === 1) this.renderInstallContext();
        if (step === 2) this.initDeliverySetup();
        if (step === 3 && this.starterTemplate) this.highlightStarter(this.starterTemplate);
        if (step === 4) this.initAiWizard();

        this.updateProgress();
    },

    getStepName(step) {
        const steps = {
            0: 'first-user',
            1: 'welcome',
            2: 'site-info',
            3: 'starter-templates',
            4: 'connect-ai',
        };
        return steps[step] || 'welcome';
    },

    renderInstallContext() {
        const body = document.getElementById('install-context-body');
        if (!body) return;
        const adminUrl = Birkly.url('/admin/index.php');
        if (this.installPath === 'local') {
            body.innerHTML = '<p><strong>You are running Birkly locally.</strong></p>'
                + '<p>Admin panel: <a href="' + adminUrl + '">' + adminUrl + '</a></p>'
                + '<p>To start again later, run <code>./start-server.sh</code> from your Birkly folder, then open the admin URL above.</p>'
                + '<p class="form-help">On the next step you can confirm CMS and public site URLs before continuing.</p>';
        } else if (this.installPath === 'cloud') {
            body.innerHTML = '<p><strong>Birkly is on your web server.</strong></p>'
                + '<p>Admin panel: <a href="' + adminUrl + '">' + adminUrl + '</a></p>'
                + '<p>Ensure <code>content/</code>, <code>media/</code>, <code>users/</code>, and <code>settings/</code> stay writable.</p>'
                + '<p class="form-help">On the next step, confirm CMS and public site URLs for your domains.</p>';
        } else {
            body.innerHTML = '<p><strong>Birkly is ready.</strong></p>'
                + '<p>Admin panel: <a href="' + adminUrl + '">' + adminUrl + '</a></p>'
                + '<p>Re-open <code>0_START-HERE.html</code> from your Birkly folder if you need local vs cloud setup notes.</p>'
                + '<p class="form-help">On the next step you can set up site delivery URLs.</p>';
        }
    },

    initDeliverySetup() {
        if (typeof window.initDeliveryStatus === 'function') {
            window.initDeliveryStatus('#delivery-status-onboarding');
        }
        const mount = document.getElementById('onboarding-hosting-wizard-mount');
        if (!mount || !window.BirklyHostingWizard) return;
        if (!this.hostingWizard) {
            this.hostingWizard = window.BirklyHostingWizard.create(mount, {
                id: 'onboarding-hosting-wizard',
                mode: 'onboarding'
            });
        }
        if (this.hostingWizard && typeof this.hostingWizard.load === 'function') {
            this.hostingWizard.load().catch(function (err) {
                console.error(err);
            });
        }
    },

    updateProgress() {
        const progressEl = document.getElementById('onboarding-progress');
        if (progressEl) {
            progressEl.hidden = this.currentStep === 0;
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
        if (this.currentStep === 3 && !this.showAiStep) {
            this.finishWithoutAiStep();
            return;
        }
        if (this.currentStep < this.maxSteps) {
            this.showStep(this.currentStep + 1);
            this.updateProgressAPI();
        }
    },

    async finishWithoutAiStep() {
        await this.updateProgressAPI('ai_skipped_local', true);
        await this.complete();
    },

    previousStep() {
        const min = this.needsFirstUser ? 0 : 1;
        if (this.currentStep > min) {
            this.showStep(this.currentStep - 1);
            this.updateProgressAPI();
        }
    },

    selectStarterTemplate(template) {
        const card = document.querySelector(`[data-starter="${template}"]`);
        if (card && card.dataset.starterSelectable !== '1') {
            return;
        }
        this.starterTemplate = template;
        document.querySelectorAll('[data-starter]').forEach(card => {
            card.classList.toggle('selected', card.dataset.starter === template);
        });
        const btn = document.getElementById('starter-continue');
        if (btn) btn.disabled = false;
        this.updateProgressAPI('starter_template', template);
    },

    highlightStarter(template) {
        document.querySelectorAll('[data-starter]').forEach(card => {
            card.classList.toggle('selected', card.dataset.starter === template);
        });
        const btn = document.getElementById('starter-continue');
        if (btn) btn.disabled = false;
    },

    async skipStarterTemplate() {
        this.starterTemplate = 'blank';
        await this.applyStarterTemplate(true);
    },

    async applyStarterTemplate(skipConfirm) {
        if (!this.starterTemplate) return;
        const btn = document.getElementById('starter-continue');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Installing…';
        }
        try {
            const response = await fetch(Birkly.url('/api/demo.php?action=install_starter'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ template: this.starterTemplate })
            });
            const data = await response.json();
            if (data.status === 'ok' && data.data?.template === this.starterTemplate) {
                if (this.starterTemplate !== 'blank' && data.data?.demo_website) {
                    this.demoWebsiteUrl = this.getDemoWebsiteUrl();
                } else {
                    this.demoWebsiteUrl = null;
                }
                if (skipConfirm) {
                    await this.updateProgressAPI('starter_skipped', true);
                }
                this.nextStep();
            } else {
                let message = data.message || 'Failed to install starter template';
                const collectionsError = data.diagnostics?.collections_dir?.error;
                const extractErrors = data.diagnostics?.extract?.errors;
                if (Array.isArray(extractErrors) && extractErrors.length > 0) {
                    const detail = extractErrors.slice(0, 2).join('; ');
                    if (!message.includes(detail)) {
                        message += ': ' + detail;
                    }
                } else if (collectionsError && !message.includes(collectionsError)) {
                    message += ': ' + collectionsError;
                }
                BirklyFeedback.toast(message, 'error');
            }
        } catch (error) {
            BirklyFeedback.toast('Failed to install starter template.', 'error')
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Continue';
        }
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

        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=save_site_info'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    project_name: projectName,
                    project_description: document.getElementById('project-description')?.value || ''
                })
            });
            const data = await response.json();
            if (data.status === 'ok') {
                this.nextStep();
            } else {
                BirklyFeedback.toast('Failed to save: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            BirklyFeedback.toast('Failed to save project information.', 'error')
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Continue';
        }
    },

    async updateProgressAPI(key, value) {
        try {
            const update = { step: this.currentStep };
            if (key && value !== undefined) update[key] = value;
            await fetch(Birkly.url('/api/onboarding.php?action=update_progress'), {
                method: 'POST',
                credentials: 'same-origin',
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
        if (keyGroup) keyGroup.hidden = isOllama;
        if (urlGroup) urlGroup.hidden = !(isOllama || isCustom);
        if (ollamaHint) ollamaHint.hidden = !isOllama;
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
        if (this.aiMode !== 'chat') {
            await this.complete();
            return;
        }
        const provider = document.getElementById('onboarding-ai-provider')?.value;
        const apiKey = document.getElementById('onboarding-ai-api-key')?.value || '';
        const apiUrl = document.getElementById('onboarding-ai-api-url')?.value || '';
        const role = document.getElementById('onboarding-ai-role')?.value || 'author';
        const requireApproval = document.getElementById('onboarding-ai-require-approval')?.checked ?? true;
        const csrf = await this.getCSRFToken();

        if (!provider || !csrf) {
            BirklyFeedback.toast('Select a provider first.', 'warning')
            return;
        }
        if (provider !== 'ollama' && !apiKey) {
            BirklyFeedback.toast('API key is required.', 'error')
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
                BirklyFeedback.toast(data.message || 'Failed to create AI connection', 'error')
                if (btn) btn.disabled = false;
            }
        } catch (error) {
            BirklyFeedback.toast('Failed to save AI connection.', 'error')
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
        const response = await fetch(Birkly.url('/api/onboarding.php?action=complete'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                demo_website_url: this.demoWebsiteUrl,
                starter_template: this.starterTemplate,
                demo_content_choice: this.starterTemplate && this.starterTemplate !== 'blank' ? 'keep' : 'delete'
            })
        });
        const data = await response.json();
        if (data.status === 'ok') {
            const params = new URLSearchParams();
            params.set('onboarding_complete', 'true');
            if (this.demoWebsiteUrl) params.set('demo_website_url', this.demoWebsiteUrl);
            if (this.starterTemplate) params.set('starter_template', this.starterTemplate);
            window.location.href = Birkly.url('/admin/index.php?page=dashboard') + '&' + params.toString();
        } else {
            BirklyFeedback.toast('Failed to complete onboarding: ' + (data.message || 'Unknown error'), 'error');
        }
    },

    getDemoWebsiteUrl() {
        const currentUrl = window.location.origin + window.location.pathname;
        const baseUrl = currentUrl.replace(/\/admin\/.*$/, '');
        return baseUrl + '/project/';
    },

    async skip() {
        if (!(await BirklyFeedback.confirm('Skip setup? You can finish later from the dashboard.'))) return;
        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=skip'), {
                method: 'POST',
                credentials: 'same-origin'
            });
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
