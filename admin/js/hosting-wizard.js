/**
 * Hosting wizard — shared between Settings hosting tab and onboarding (P44-M5).
 * Wires to api/hosting.php (same patterns as hosting-settings.js).
 */
(function (global) {
    'use strict';

    var instances = {};

    function apiUrl(path) {
        return (global.Birkly && typeof global.Birkly.url === 'function') ? global.Birkly.url(path) : path;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function t(key, fallback) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback || key;
    }

    function renderWizardHtml(id) {
        return '' +
            '<div class="hosting-wizard" data-wizard-id="' + escapeHtml(id) + '">' +
            '<div class="hosting-wizard-steps" role="tablist" aria-label="' + escapeHtml(t('hosting_wizard_steps_label', 'Hosting setup steps')) + '">' +
            '<button type="button" class="hosting-wizard-step-btn active" data-step="1" role="tab" aria-selected="true">' + escapeHtml(t('hosting_wizard_step_cms', '1. CMS URL')) + '</button>' +
            '<button type="button" class="hosting-wizard-step-btn" data-step="2" role="tab" aria-selected="false">' + escapeHtml(t('hosting_wizard_step_public', '2. Public site')) + '</button>' +
            '<button type="button" class="hosting-wizard-step-btn" data-step="3" role="tab" aria-selected="false">' + escapeHtml(t('hosting_wizard_step_preview', '3. Preview')) + '</button>' +
            '</div>' +
            '<div class="hosting-wizard-panels">' +
            '<section class="hosting-wizard-panel active" data-step-panel="1">' +
            '<h3 class="hosting-wizard-panel-title">' + escapeHtml(t('hosting_wizard_cms_title', 'Where do you log in?')) + '</h3>' +
            '<p class="hosting-wizard-panel-desc">' + escapeHtml(t('hosting_wizard_cms_desc', 'Admin panel, API, and birkly-client.js base URL.')) + '</p>' +
            '<div class="form-group">' +
            '<label for="' + id + '-cms-url">' + escapeHtml(t('hosting_wizard_cms_url', 'CMS URL')) + '</label>' +
            '<div class="input-with-action">' +
            '<input type="url" id="' + id + '-cms-url" class="input hosting-wizard-cms-url" placeholder="https://cms.example.com">' +
            '<button type="button" class="btn btn-secondary btn-sm hosting-wizard-use-host">' + escapeHtml(t('hosting_wizard_use_current_host', 'Use current host')) + '</button>' +
            '</div>' +
            '</div>' +
            '<label class="checkbox-label hosting-wizard-docker-toggle">' +
            '<input type="checkbox" class="hosting-wizard-self-hosted">' +
            '<span>' + escapeHtml(t('hosting_wizard_self_hosted', 'Self-hosted / Docker (Dokploy)')) + '</span>' +
            '</label>' +
            '<div class="hosting-wizard-dokploy" hidden>' +
            '<h4 class="hosting-wizard-dokploy-title">' + escapeHtml(t('hosting_wizard_dokploy_title', 'Dokploy checklist')) + '</h4>' +
            '<ul class="hosting-wizard-checklist">' +
            '<li>' + escapeHtml(t('hosting_wizard_dokploy_1', 'Add each domain (CMS + public) pointing to the same app')) + '</li>' +
            '<li>' + escapeHtml(t('hosting_wizard_dokploy_2', 'Path / · Internal Path / · port 80')) + '</li>' +
            '<li>' + escapeHtml(t('hosting_wizard_dokploy_3', 'Mount persistent volume at /app/data (see docker-compose.yml)')) + '</li>' +
            '<li>' + escapeHtml(t('hosting_wizard_dokploy_4', 'Test: CMS root → admin · public root → site (no /project/ in URL)')) + '</li>' +
            '</ul>' +
            '</div>' +
            '</section>' +
            '<section class="hosting-wizard-panel" data-step-panel="2" hidden>' +
            '<h3 class="hosting-wizard-panel-title">' + escapeHtml(t('hosting_wizard_public_title', 'Where is your public website?')) + '</h3>' +
            '<fieldset class="form-group">' +
            '<legend class="sr-only">' + escapeHtml(t('hosting_wizard_public_mode_legend', 'Public website mode')) + '</legend>' +
            '<label class="radio-label"><input type="radio" name="' + id + '-public-mode" value="none" checked> ' + escapeHtml(t('hosting_wizard_public_none', 'None — CMS only (no public site on this install)')) + '</label>' +
            '<label class="radio-label"><input type="radio" name="' + id + '-public-mode" value="external"> ' + escapeHtml(t('hosting_wizard_public_external', 'Hosted elsewhere (Netlify, separate server, etc.)')) + '</label>' +
            '<label class="radio-label"><input type="radio" name="' + id + '-public-mode" value="project"> ' + escapeHtml(t('hosting_wizard_public_project', 'In Birkly (project/ folder)')) + '</label>' +
            '</fieldset>' +
            '<div class="form-group hosting-wizard-public-field" hidden>' +
            '<label for="' + id + '-public-url">' + escapeHtml(t('hosting_wizard_public_url', 'Public site URL')) + '</label>' +
            '<input type="url" id="' + id + '-public-url" class="input hosting-wizard-public-url" placeholder="https://www.example.com">' +
            '<small class="form-help">' + escapeHtml(t('hosting_wizard_public_url_help', 'Visitors see this URL in the browser.')) + '</small>' +
            '</div>' +
            '<fieldset class="form-group hosting-wizard-same-host-field" hidden>' +
            '<legend>' + escapeHtml(t('hosting_wizard_same_host_legend', 'When CMS and public use the same domain')) + '</legend>' +
            '<label class="radio-label"><input type="radio" name="' + id + '-same-host" value="admin" checked> ' + escapeHtml(t('hosting_wizard_same_host_admin', 'Root / opens admin')) + '</label>' +
            '<label class="radio-label"><input type="radio" name="' + id + '-same-host" value="public"> ' + escapeHtml(t('hosting_wizard_same_host_public', 'Root / opens public site')) + '</label>' +
            '</fieldset>' +
            '<label class="checkbox-label">' +
            '<input type="checkbox" class="hosting-wizard-strict-admin" checked>' +
            ' ' + escapeHtml(t('hosting_wizard_strict_admin', 'Redirect /admin on public host to CMS URL')) +
            '</label>' +
            '</section>' +
            '<section class="hosting-wizard-panel" data-step-panel="3" hidden>' +
            '<h3 class="hosting-wizard-panel-title">' + escapeHtml(t('hosting_wizard_preview_title', 'Preview')) + '</h3>' +
            '<div class="hosting-wizard-preview card">' +
            '<p class="hosting-wizard-preview-line">' + escapeHtml(t('hosting_wizard_preview_manage', 'You manage content at')) + ' <strong class="hosting-wizard-preview-cms">—</strong></p>' +
            '<p class="hosting-wizard-preview-line hosting-wizard-preview-public-wrap" hidden>' + escapeHtml(t('hosting_wizard_preview_visitors', 'Visitors see')) + ' <strong class="hosting-wizard-preview-public">—</strong></p>' +
            '<p class="hosting-wizard-preview-line hosting-wizard-preview-none">' + escapeHtml(t('hosting_wizard_preview_none', 'No public site configured on this install.')) + '</p>' +
            '</div>' +
            '<div class="hosting-wizard-snippet-block">' +
            '<label>' + escapeHtml(t('hosting_wizard_snippet_label', 'Integration snippet')) + '</label>' +
            '<pre class="domains-code-pre"><code class="hosting-wizard-snippet"></code></pre>' +
            '<button type="button" class="btn btn-secondary btn-sm hosting-wizard-copy-snippet">' + escapeHtml(t('hosting_wizard_copy_snippet', 'Copy snippet')) + '</button>' +
            '</div>' +
            '</section>' +
            '</div>' +
            '<div class="hosting-wizard-actions">' +
            '<button type="button" class="btn btn-secondary hosting-wizard-prev" hidden>' + escapeHtml(t('hosting_wizard_back', 'Back')) + '</button>' +
            '<button type="button" class="btn btn-primary hosting-wizard-next">' + escapeHtml(t('hosting_wizard_continue', 'Continue')) + '</button>' +
            '<button type="button" class="btn btn-primary hosting-wizard-save" hidden>' + escapeHtml(t('hosting_wizard_save', 'Save hosting settings')) + '</button>' +
            '</div>' +
            '</div>';
    }

    function HostingWizard(container, options) {
        this.container = container;
        this.options = options || {};
        this.id = options.id || ('hosting-wizard-' + Math.random().toString(36).slice(2, 9));
        this.step = 1;
        this.maxStep = 3;
        this.hosting = null;
        this.snippet = '';
        this.sameHost = false;
        this.mode = options.mode || 'settings';
    }

    HostingWizard.prototype.$ = function (sel) {
        return this.root ? this.root.querySelector(sel) : null;
    };

    HostingWizard.prototype.getPublicMode = function () {
        var checked = this.root.querySelector('input[name="' + this.id + '-public-mode"]:checked');
        return checked ? checked.value : 'none';
    };

    HostingWizard.prototype.getHostingPayload = function () {
        var landingInput = this.root.querySelector('input[name="' + this.id + '-same-host"]:checked');
        return {
            cms_base_url: (this.$('.hosting-wizard-cms-url') || {}).value || '',
            public_site_mode: this.getPublicMode(),
            public_site_url: (this.$('.hosting-wizard-public-url') || {}).value || '',
            same_host_landing: landingInput ? landingInput.value : 'admin',
            strict_admin_host: this.$('.hosting-wizard-strict-admin') ? this.$('.hosting-wizard-strict-admin').checked : true
        };
    };

    HostingWizard.prototype.togglePublicFields = function () {
        var mode = this.getPublicMode();
        var show = mode !== 'none';
        var publicField = this.$('.hosting-wizard-public-field');
        var sameHostField = this.$('.hosting-wizard-same-host-field');
        if (publicField) publicField.hidden = !show;
        if (sameHostField) sameHostField.hidden = !(show && this.sameHost);
    };

    HostingWizard.prototype.updatePreview = function () {
        var payload = this.getHostingPayload();
        var cmsEl = this.$('.hosting-wizard-preview-cms');
        var publicEl = this.$('.hosting-wizard-preview-public');
        var publicWrap = this.$('.hosting-wizard-preview-public-wrap');
        var noneEl = this.$('.hosting-wizard-preview-none');
        var cmsUrl = payload.cms_base_url.trim() || window.location.origin;

        if (cmsEl) cmsEl.textContent = cmsUrl;
        if (payload.public_site_mode === 'none') {
            if (publicWrap) publicWrap.hidden = true;
            if (noneEl) noneEl.hidden = false;
        } else {
            if (noneEl) noneEl.hidden = true;
            if (publicWrap) publicWrap.hidden = false;
            var publicUrl = payload.public_site_url.trim();
            if (payload.public_site_mode === 'project' && !publicUrl) {
                publicUrl = cmsUrl.replace(/\/$/, '') + '/project/';
            }
            if (publicEl) publicEl.textContent = publicUrl || '(configure public URL)';
        }

        var snippetEl = this.$('.hosting-wizard-snippet');
        if (snippetEl) {
            snippetEl.textContent = this.snippet || '<script src="…/birkly-client.js" data-cms-url="…"></script>';
        }
    };

    HostingWizard.prototype.goToStep = function (step) {
        this.step = Math.max(1, Math.min(this.maxStep, step));
        this.root.querySelectorAll('.hosting-wizard-step-btn').forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-step'), 10);
            var active = n === this.step;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }.bind(this));
        this.root.querySelectorAll('.hosting-wizard-panel').forEach(function (panel) {
            var n = parseInt(panel.getAttribute('data-step-panel'), 10);
            var active = n === this.step;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        }.bind(this));

        var prevBtn = this.$('.hosting-wizard-prev');
        var nextBtn = this.$('.hosting-wizard-next');
        var saveBtn = this.$('.hosting-wizard-save');
        if (prevBtn) prevBtn.hidden = this.step <= 1;
        if (nextBtn) nextBtn.hidden = this.step >= this.maxStep;
        if (saveBtn) {
            saveBtn.hidden = this.mode === 'onboarding' || this.step < this.maxStep;
        }
        if (this.step === this.maxStep) {
            this.updatePreview();
        }
    };

    HostingWizard.prototype.populateFromApi = function (data) {
        this.hosting = data.hosting || {};
        this.snippet = data.client_snippet || '';
        this.sameHost = !!data.same_host;

        var cmsInput = this.$('.hosting-wizard-cms-url');
        if (cmsInput) cmsInput.value = this.hosting.cms_base_url || data.suggested_cms_url || '';

        var mode = this.hosting.public_site_mode || 'none';
        var modeInput = this.root.querySelector('input[name="' + this.id + '-public-mode"][value="' + mode + '"]');
        if (modeInput) modeInput.checked = true;

        var publicInput = this.$('.hosting-wizard-public-url');
        if (publicInput) publicInput.value = this.hosting.public_site_url || '';

        var landing = this.hosting.same_host_landing || 'admin';
        var landingInput = this.root.querySelector('input[name="' + this.id + '-same-host"][value="' + landing + '"]');
        if (landingInput) landingInput.checked = true;

        var strict = this.$('.hosting-wizard-strict-admin');
        if (strict) strict.checked = this.hosting.strict_admin_host !== false;

        this.togglePublicFields();
        this.updatePreview();
    };

    HostingWizard.prototype.load = function () {
        var self = this;
        return fetch(apiUrl('/api/hosting.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Failed to load hosting');
                self.populateFromApi(payload.data || {});
                return payload;
            });
    };

    HostingWizard.prototype.save = function () {
        var self = this;
        var body = {
            action: 'save',
            hosting: this.getHostingPayload(),
            _csrf: getCsrfToken()
        };
        return fetch(apiUrl('/api/hosting.php?action=save'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status === 'ok' && payload.data) {
                    self.snippet = payload.data.client_snippet || self.snippet;
                    self.updatePreview();
                }
                return payload;
            });
    };

    HostingWizard.prototype.bindEvents = function () {
        var self = this;

        this.root.querySelectorAll('.hosting-wizard-step-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                self.goToStep(parseInt(btn.getAttribute('data-step'), 10));
            });
        });

        var useHost = this.$('.hosting-wizard-use-host');
        if (useHost) {
            useHost.addEventListener('click', function () {
                var cms = self.$('.hosting-wizard-cms-url');
                if (cms) cms.value = window.location.origin;
            });
        }

        var dockerToggle = this.$('.hosting-wizard-self-hosted');
        var dokploy = this.$('.hosting-wizard-dokploy');
        if (dockerToggle && dokploy) {
            dockerToggle.addEventListener('change', function () {
                dokploy.hidden = !dockerToggle.checked;
            });
        }

        this.root.querySelectorAll('input[name="' + this.id + '-public-mode"]').forEach(function (input) {
            input.addEventListener('change', function () {
                self.togglePublicFields();
                self.updatePreview();
            });
        });

        ['.hosting-wizard-cms-url', '.hosting-wizard-public-url'].forEach(function (sel) {
            var el = self.$(sel);
            if (el) el.addEventListener('input', function () { self.updatePreview(); });
        });

        var prevBtn = this.$('.hosting-wizard-prev');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                self.goToStep(self.step - 1);
            });
        }

        var nextBtn = this.$('.hosting-wizard-next');
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (self.step < self.maxStep) {
                    self.goToStep(self.step + 1);
                }
            });
        }

        var saveBtn = this.$('.hosting-wizard-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                saveBtn.disabled = true;
                self.save().then(function (payload) {
                    if (payload.status === 'ok') {
                        if (typeof self.options.onSave === 'function') {
                            self.options.onSave(payload);
                        } else if (global.Birkly && global.Birkly.Utils && typeof global.Birkly.Utils.showToast === 'function') {
                            global.Birkly.Utils.showToast(t('hosting_wizard_saved', 'Hosting settings saved'), 'success');
                        }
                    } else {
                        BirklyFeedback.toast(payload.message || t('hosting_wizard_save_failed', 'Failed to save hosting settings'), 'error');
                    }
                }).catch(function () {
                    BirklyFeedback.toast(t('hosting_wizard_save_failed', 'Failed to save hosting settings'), 'error');
                }).finally(function () {
                    saveBtn.disabled = false;
                });
            });
        }

        var copyBtn = this.$('.hosting-wizard-copy-snippet');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var text = (self.$('.hosting-wizard-snippet') || {}).textContent || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).catch(function () {});
                }
            });
        }
    };

    HostingWizard.prototype.mount = function () {
        this.container.innerHTML = renderWizardHtml(this.id);
        this.root = this.container.querySelector('.hosting-wizard');
        this.bindEvents();
        this.goToStep(1);
    };

    global.BirklyHostingWizard = {
        create: function (container, options) {
            if (!container) return null;
            var wizard = new HostingWizard(container, options || {});
            wizard.mount();
            instances[wizard.id] = wizard;
            return wizard;
        },
        get: function (id) {
            return instances[id];
        }
    };
})(window);
