/**
 * Onboarding wizard v3
 * Flow: (0 first-user?) → 1 welcome+install info → 2 site info →
 *       3 starter templates → complete
 */

function obT(key, fallback, params) {
    let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
        ? Birkly.i18n.t('admin.' + key, fallback)
        : (fallback != null ? fallback : key);
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            text = text.split('{' + name + '}').join(String(params[name]));
        });
    }
    return text;
}

function escapeOnboardingHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
}

const onboarding = {
    currentStep: 0,
    maxSteps: 3,
    needsFirstUser: false,
    starterTemplate: null,
    demoWebsiteUrl: null,
    installPath: null,
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
                case 'select-starter':
                    if (param) this.selectStarterTemplate(param);
                    break;
                case 'skip-starter':
                    this.skipStarterTemplate();
                    break;
                case 'apply-starter':
                    this.applyStarterTemplate();
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
                note.textContent = obT('onboarding_js_marsk_network_note', 'Marsk needs an internet connection from this computer so Birkly can download the demo pack. You can continue with Blank.');
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
    },

    async createFirstUser() {
        const form = document.getElementById('first-user-form');
        const errorDiv = document.getElementById('first-user-error');
        const submitBtn = form.querySelector('button[type="submit"]');
        const name = document.getElementById('first-user-name').value.trim();
        const email = document.getElementById('first-user-email').value.trim();
        const password = document.getElementById('first-user-password').value;
        const passwordConfirm = document.getElementById('first-user-password-confirm').value;
        const language = document.getElementById('first-user-language')?.value || 'en';

        errorDiv.hidden = true;
        if (password !== passwordConfirm) {
            errorDiv.textContent = obT('onboarding_js_passwords_mismatch', 'Passwords do not match');
            errorDiv.hidden = false;
            return;
        }

        submitBtn.disabled = true;
        try {
            const response = await fetch(Birkly.url('/api/onboarding.php?action=create_first_user'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, password, language })
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
            errorDiv.textContent = obT('onboarding_js_generic_error', 'An error occurred. Please try again.');
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
        if (step === 3) this.renderReturnReminder();
        if (step === 3 && this.starterTemplate) this.highlightStarter(this.starterTemplate);

        this.updateProgress();
    },

    getStepName(step) {
        const steps = {
            0: 'first-user',
            1: 'welcome',
            2: 'site-info',
            3: 'starter-templates',
        };
        return steps[step] || 'welcome';
    },

    localRestartCommand() {
        const platform = String((navigator.userAgent || '') + ' ' + (navigator.platform || ''));
        if (/Win/i.test(platform)) {
            return 'powershell -ExecutionPolicy Bypass -File .\\start-server.ps1';
        }
        return './start-server.sh';
    },

    renderInstallContext() {
        const body = document.getElementById('install-context-body');
        if (!body) return;
        const adminUrl = Birkly.url('/admin/index.php');
        const safeUrl = escapeOnboardingHtml(adminUrl);
        const login = '<p><strong>' + escapeOnboardingHtml(obT('onboarding_js_admin_login', 'Admin login:')) + '</strong> <a href="' + safeUrl + '">' + safeUrl + '</a></p>';
        const next = '<p class="form-help">' + escapeOnboardingHtml(obT('onboarding_js_next_step', 'On the next step, name your project and optionally pick a starter template.')) + '</p>';
        if (this.installPath === 'local') {
            const command = this.localRestartCommand();
            body.innerHTML = '<p><strong>' + escapeOnboardingHtml(obT('onboarding_js_local_title', 'You are running Birkly locally.')) + '</strong></p>'
                + login
                + '<p>' + escapeOnboardingHtml(obT('onboarding_js_local_restart', 'To start again later, open a terminal in your Birkly folder and run {command}. Leave that window open, then open the admin login above.', { command: command })).replace(escapeOnboardingHtml(command), '<code>' + escapeOnboardingHtml(command) + '</code>') + '</p>'
                + next;
        } else if (this.installPath === 'cloud') {
            body.innerHTML = '<p><strong>' + escapeOnboardingHtml(obT('onboarding_js_cloud_title', 'Birkly is on your web server.')) + '</strong></p>'
                + login
                + '<p>' + escapeOnboardingHtml(obT('onboarding_js_cloud_writable', 'Ensure content/, media/, users/, and settings/ stay writable.')) + '</p>'
                + next;
        } else {
            body.innerHTML = '<p><strong>' + escapeOnboardingHtml(obT('onboarding_js_ready_title', 'Birkly is ready.')) + '</strong></p>'
                + login
                + '<p>' + escapeOnboardingHtml(obT('onboarding_js_ready_help', 'Re-open 0_START-HERE.html from your Birkly folder if you need local vs cloud setup notes.')) + '</p>'
                + next;
        }
        this.renderReturnReminder();
    },

    renderReturnReminder() {
        const slot = document.getElementById('onboarding-return-reminder');
        if (!slot) return;
        const adminUrl = Birkly.url('/admin/index.php');
        const command = this.installPath === 'cloud' ? '' : this.localRestartCommand();
        let html = '<p><strong>' + escapeOnboardingHtml(obT('onboarding_js_return_title', 'How to open Birkly again')) + '</strong></p>'
            + '<p>' + escapeOnboardingHtml(obT('onboarding_js_admin_login', 'Admin login:')) + ' <a href="' + escapeOnboardingHtml(adminUrl) + '">' + escapeOnboardingHtml(adminUrl) + '</a></p>';
        if (this.installPath !== 'cloud') {
            html += '<p>' + escapeOnboardingHtml(obT('onboarding_js_local_restart', 'To start again later, open a terminal in your Birkly folder and run {command}. Leave that window open, then open the admin login above.', { command: command })).replace(escapeOnboardingHtml(command), '<code>' + escapeOnboardingHtml(command) + '</code>') + '</p>';
        }
        slot.innerHTML = html;
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
            btn.textContent = obT('onboarding_js_installing', 'Installing…');
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
                await this.complete();
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
            BirklyFeedback.toast(obT('onboarding_js_install_template_failed', 'Failed to install starter template.'), 'error');
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = obT('onboarding_js_continue', 'Continue');
        }
    },

    async saveSiteInfo() {
        const form = document.getElementById('site-info-form');
        if (!form) return;
        const submitBtn = form.querySelector('button[type="submit"]');
        const projectName = document.getElementById('project-name').value.trim();
        if (!projectName || projectName.length < 2) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = obT('onboarding_js_saving', 'Saving...');
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
                BirklyFeedback.toast(obT('onboarding_js_save_failed', 'Failed to save: {message}', { message: data.message || obT('error_unknown', 'Unknown error') }), 'error');
            }
        } catch (error) {
            BirklyFeedback.toast(obT('onboarding_js_save_project_failed', 'Failed to save project information.'), 'error');
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = obT('onboarding_js_continue', 'Continue');
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
            params.set('page', 'dashboard');
            if (this.demoWebsiteUrl) params.set('demo_website_url', this.demoWebsiteUrl);
            if (this.starterTemplate) params.set('starter_template', this.starterTemplate);
            window.location.href = Birkly.url('/admin/index.php?' + params.toString());
        } else {
            BirklyFeedback.toast(obT('onboarding_js_complete_failed', 'Failed to complete onboarding: {message}', { message: data.message || obT('error_unknown', 'Unknown error') }), 'error');
        }
    },

    getDemoWebsiteUrl() {
        const currentUrl = window.location.origin + window.location.pathname;
        const baseUrl = currentUrl.replace(/\/admin\/.*$/, '');
        return baseUrl + '/project/';
    },

    async finishWithoutAiStep() {
        return this.complete();
    },

    async skip() {
        if (!(await BirklyFeedback.confirm(obT('onboarding_js_confirm_skip_setup', 'Skip setup? You can finish later from the dashboard.')))) return;
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
