/**
 * Hosting wizard — Settings → Site delivery (P44-M5, P66-M2a).
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

    function t(key, fallback, params) {
        var text = (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function')
            ? global.Birkly.i18n.t('admin.' + key, fallback)
            : (fallback || key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function renderWizardHtml(id) {
        return '' +
            '<div class="hosting-wizard" data-wizard-id="' + escapeHtml(id) + '">' +
            '<div class="hosting-wizard-proposal card card--padded" hidden>' +
            '<div class="hosting-wizard-proposal-head u-stack-1">' +
            '<h4 class="hosting-wizard-proposal-title">' + escapeHtml(t('hosting_wizard_proposal_title', 'Suggested delivery setup')) + '</h4>' +
            '<p class="hosting-wizard-proposal-desc form-help">' + escapeHtml(t('hosting_wizard_proposal_desc', 'Review detected settings before applying. Nothing is saved until you confirm.')) + '</p>' +
            '</div>' +
            '<ul class="hosting-wizard-proposal-changes"></ul>' +
            '<div class="u-cluster">' +
            '<button type="button" class="btn btn-primary btn-sm hosting-wizard-apply-proposal">' + escapeHtml(t('hosting_wizard_apply_proposal', 'Apply suggestion')) + '</button>' +
            '<button type="button" class="btn btn-secondary btn-sm hosting-wizard-dismiss-proposal">' + escapeHtml(t('hosting_wizard_dismiss_proposal', 'Dismiss')) + '</button>' +
            '</div>' +
            '</div>' +
            '<p class="hosting-wizard-env-banner message message-info" hidden>Delivery URLs are managed by environment variables (read-only).</p>' +
            '<div class="hosting-wizard-steps" role="tablist" aria-label="' + escapeHtml(t('hosting_wizard_steps_label', 'Hosting setup steps')) + '">' +
            '<button type="button" class="hosting-wizard-step-btn active" data-step="1" role="tab" aria-selected="true">' + escapeHtml(t('hosting_wizard_step_cms', '1. CMS URL')) + '</button>' +
            '<button type="button" class="hosting-wizard-step-btn" data-step="2" role="tab" aria-selected="false">' + escapeHtml(t('hosting_wizard_step_public', '2. Public site')) + '</button>' +
            '<button type="button" class="hosting-wizard-step-btn" data-step="3" role="tab" aria-selected="false">' + escapeHtml(t('hosting_wizard_step_preview', '3. Preview')) + '</button>' +
            '</div>' +
            '<div class="hosting-wizard-panels">' +
            '<section class="hosting-wizard-panel active" data-step-panel="1">' +
            '<div class="u-stack-1">' +
            '<h3 class="hosting-wizard-panel-title">' + escapeHtml(t('hosting_wizard_cms_title', 'Where do you log in?')) + '</h3>' +
            '<p class="hosting-wizard-panel-desc">' + escapeHtml(t('hosting_wizard_cms_desc', 'Admin panel, API, and birkly-client.js base URL.')) + '</p>' +
            '</div>' +
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
            '<small class="form-help">' + escapeHtml(t('hosting_wizard_public_url_help', 'Visitors see this URL in the browser.')) + ' ' + escapeHtml(t('hosting_wizard_public_url_help_extra', 'Do not include /project — use the hostname only (e.g. https://example.com).')) + '</small>' +
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
            '<label class="checkbox-label hosting-wizard-alias-redirect-field" hidden>' +
            '<input type="checkbox" class="hosting-wizard-alias-redirect" checked>' +
            '<span>' + escapeHtml(t('hosting_wizard_alias_redirect', 'Redirect alias hostnames to primary URL (301)')) + '</span>' +
            '</label>' +
            '<small class="form-help hosting-wizard-alias-redirect-help" hidden>' + escapeHtml(t('hosting_wizard_alias_redirect_help', 'When checked, birkly.io and other aliases send visitors to the primary public URL. Uncheck to serve each hostname directly.')) + '</small>' +
            '<div class="hosting-wizard-validation" hidden role="status"></div>' +
            '<div class="form-group hosting-wizard-aliases-field" hidden>' +
            '<label for="' + id + '-cms-aliases">' + escapeHtml(t('hosting_wizard_cms_aliases', 'CMS host aliases')) + '</label>' +
            '<input type="text" id="' + id + '-cms-aliases" class="input hosting-wizard-cms-aliases" placeholder="cms-alt.example.com, staging-cms.example.com">' +
            '<small class="form-help">' + escapeHtml(t('hosting_wizard_aliases_help', 'Comma-separated extra hostnames that serve admin/API (optional).')) + '</small>' +
            '<label for="' + id + '-public-aliases">' + escapeHtml(t('hosting_wizard_public_aliases', 'Public site aliases')) + '</label>' +
            '<input type="text" id="' + id + '-public-aliases" class="input hosting-wizard-public-aliases" placeholder="example.com">' +
            '</div>' +
            '</section>' +
            '<section class="hosting-wizard-panel" data-step-panel="3" hidden>' +
            '<h3 class="hosting-wizard-panel-title">' + escapeHtml(t('hosting_wizard_preview_title', 'Preview')) + '</h3>' +
            '<div class="hosting-wizard-preview card card--padded">' +
            '<p class="hosting-wizard-preview-line">' + escapeHtml(t('hosting_wizard_preview_manage', 'You manage content at')) + ' <strong class="hosting-wizard-preview-cms">—</strong></p>' +
            '<p class="hosting-wizard-preview-line hosting-wizard-preview-public-wrap" hidden>' + escapeHtml(t('hosting_wizard_preview_visitors', 'Visitors see')) + ' <strong class="hosting-wizard-preview-public">—</strong></p>' +
            '<p class="hosting-wizard-preview-line hosting-wizard-preview-none">' + escapeHtml(t('hosting_wizard_preview_none', 'No public site configured on this install.')) + '</p>' +
            '</div>' +
            '<div class="hosting-wizard-external-checklist card card--padded" hidden>' +
            '<h4 class="hosting-wizard-checklist-title">' + escapeHtml(t('hosting_wizard_external_checklist_title', 'External site checklist')) + '</h4>' +
            '<ul class="hosting-wizard-checklist hosting-wizard-external-list">' +
            '<li data-check="cms">' + escapeHtml(t('hosting_wizard_check_cms', 'CMS URL set')) + '</li>' +
            '<li data-check="public">' + escapeHtml(t('hosting_wizard_check_public', 'Public site URL set')) + '</li>' +
            '<li data-check="domain">' + escapeHtml(t('hosting_wizard_check_domain', 'Public host allowlisted in Domains')) + '</li>' +
            '<li data-check="cors">' + escapeHtml(t('hosting_wizard_check_cors', 'CORS ready for www↔apex and localhost')) + '</li>' +
            '<li data-check="snippet">' + escapeHtml(t('hosting_wizard_check_snippet', 'Snippet uses absolute CMS URL + data-cms-url')) + '</li>' +
            '</ul>' +
            '<p class="form-help hosting-wizard-registered-host" hidden></p>' +
            '<p class="form-help">' + escapeHtml(t('hosting_wizard_external_checklist_help', 'After save, paste the snippet on your external site. Domains allowlist enables CORS for page_bundle and public APIs.')) +
            ' <a href="' + escapeHtml(apiUrl('/admin/index.php?page=settings&tab=site-delivery') + '#domains') + '">' +
            escapeHtml(t('hosting_wizard_open_domains', 'Open Domains')) + '</a></p>' +
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
        this.publicHost = '';
        this.registeredPublicHost = '';
        this.mode = options.mode || 'settings';
    }

    HostingWizard.prototype.$ = function (sel) {
        return this.root ? this.root.querySelector(sel) : null;
    };

    HostingWizard.prototype.getPublicMode = function () {
        var checked = this.root.querySelector('input[name="' + this.id + '-public-mode"]:checked');
        return checked ? checked.value : 'none';
    };

    HostingWizard.prototype.parseAliasInput = function (value) {
        return String(value || '').split(',').map(function (part) {
            return part.trim().toLowerCase();
        }).filter(function (part) { return part !== ''; });
    };

    HostingWizard.prototype.formatAliasInput = function (aliases) {
        if (!Array.isArray(aliases)) return '';
        return aliases.join(', ');
    };

    HostingWizard.prototype.getHostingPayload = function () {
        var landingInput = this.root.querySelector('input[name="' + this.id + '-same-host"]:checked');
        return {
            cms_base_url: (this.$('.hosting-wizard-cms-url') || {}).value || '',
            cms_host_aliases: this.parseAliasInput((this.$('.hosting-wizard-cms-aliases') || {}).value),
            public_site_mode: this.getPublicMode(),
            public_site_url: (this.$('.hosting-wizard-public-url') || {}).value || '',
            public_host_aliases: this.parseAliasInput((this.$('.hosting-wizard-public-aliases') || {}).value),
            same_host_landing: landingInput ? landingInput.value : 'admin',
            strict_admin_host: this.$('.hosting-wizard-strict-admin') ? this.$('.hosting-wizard-strict-admin').checked : true,
            alias_redirect_to_primary: this.$('.hosting-wizard-alias-redirect') ? this.$('.hosting-wizard-alias-redirect').checked : true
        };
    };

    HostingWizard.prototype.togglePublicFields = function () {
        var mode = this.getPublicMode();
        var show = mode !== 'none';
        var publicField = this.$('.hosting-wizard-public-field');
        var sameHostField = this.$('.hosting-wizard-same-host-field');
        var aliasesField = this.$('.hosting-wizard-aliases-field');
        var aliasRedirectField = this.$('.hosting-wizard-alias-redirect-field');
        var aliasRedirectHelp = this.$('.hosting-wizard-alias-redirect-help');
        if (publicField) publicField.hidden = !show;
        if (aliasesField) aliasesField.hidden = !show;
        if (aliasRedirectField) aliasRedirectField.hidden = !show;
        if (aliasRedirectHelp) aliasRedirectHelp.hidden = !show;
        // Same-host landing only applies when Birkly serves the project on a shared host
        if (sameHostField) sameHostField.hidden = !(mode === 'project' && this.sameHost);
    };

    HostingWizard.prototype.hostFromUrl = function (url) {
        try {
            return new URL(String(url || '').trim()).hostname.toLowerCase();
        } catch (e) {
            return '';
        }
    };

    HostingWizard.prototype.updateValidationWarnings = function () {
        var box = this.$('.hosting-wizard-validation');
        if (!box) return;

        var payload = this.getHostingPayload();
        var messages = [];
        var cmsHost = this.hostFromUrl(payload.cms_base_url);
        var publicHost = this.hostFromUrl(payload.public_site_url);

        if (payload.public_site_mode !== 'none' && cmsHost !== '' && publicHost !== '' && cmsHost === publicHost) {
            messages.push({
                severity: 'info',
                text: 'CMS and public share ' + cmsHost + '. With “Root / opens admin”, the bundled site is served at /project/.'
            });
        }

        if (payload.cms_base_url && /\/admin\/?$/i.test(String(payload.cms_base_url).trim())) {
            messages.push({
                severity: 'error',
                text: 'CMS URL should be the site origin only (https://' + (cmsHost || 'example.com') + '), not …/admin.'
            });
        }

        if (payload.public_site_url && /\/project(\/|$)/i.test(String(payload.public_site_url).trim())) {
            messages.push({
                severity: 'error',
                text: t('hosting_wizard_public_url_no_project', 'Public URL must not include /project — use the hostname only.')
            });
        }

        var cmsAliases = payload.cms_host_aliases || [];
        var publicAliases = payload.public_host_aliases || [];
        cmsAliases.forEach(function (alias) {
            if (publicAliases.indexOf(alias) !== -1) {
                messages.push({
                    severity: 'warn',
                    text: '"' + alias + '" is on both CMS and public alias lists. Requests resolve as CMS — remove it from public aliases.'
                });
            }
        });

        if (!messages.length) {
            box.hidden = true;
            box.innerHTML = '';
            return;
        }

        box.hidden = false;
        box.innerHTML = messages.map(function (item) {
            var cls = item.severity === 'error' ? 'message-error' : (item.severity === 'warn' ? 'message-warning' : 'message-info');
            return '<div class="message ' + cls + '" role="alert">' + escapeHtml(item.text) + '</div>';
        }).join('');
    };

    HostingWizard.prototype.buildLiveSnippet = function (cmsUrl) {
        var base = String(cmsUrl || '').trim().replace(/\/$/, '');
        if (!base) {
            return '<script src="…/birkly-client.js" data-cms-url="…"></script>';
        }
        return '<script src="' + base + '/birkly-client.js" data-cms-url="' + base + '"></script>';
    };

    HostingWizard.prototype.updateExternalChecklist = function (payload, cmsUrl) {
        var box = this.$('.hosting-wizard-external-checklist');
        if (!box) return;
        var isExternal = payload.public_site_mode === 'external';
        box.hidden = !isExternal;
        if (!isExternal) return;

        var publicUrl = (payload.public_site_url || '').trim();
        var snippet = String(this.snippet || this.buildLiveSnippet(cmsUrl));
        var domainOk = !!(this.registeredPublicHost || this.publicHost || publicUrl);
        var checks = {
            cms: !!cmsUrl,
            public: !!publicUrl,
            domain: domainOk,
            cors: domainOk,
            snippet: !!(cmsUrl && snippet.indexOf('data-cms-url=') !== -1 && /^https?:\/\//i.test(snippet))
        };
        box.querySelectorAll('[data-check]').forEach(function (li) {
            var key = li.getAttribute('data-check');
            var ok = !!checks[key];
            li.classList.toggle('is-ok', ok);
            li.setAttribute('aria-label', ok
                ? t('hosting_wizard_checklist_done', 'Done: {item}', { item: li.textContent })
                : t('hosting_wizard_checklist_todo', 'Todo: {item}', { item: li.textContent }));
        });

        var regEl = this.$('.hosting-wizard-registered-host');
        if (regEl) {
            var host = this.registeredPublicHost || this.publicHost || '';
            if (host) {
                regEl.hidden = false;
                regEl.textContent = t('hosting_wizard_registered_host', 'Registered public host: {host}').replace('{host}', host);
            } else {
                regEl.hidden = true;
                regEl.textContent = '';
            }
        }
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
                publicUrl = '(configure public URL — do not use /project/ in browser)';
            }
            if (publicEl) publicEl.textContent = publicUrl || '(configure public URL)';
        }

        var liveSnippet = this.buildLiveSnippet(cmsUrl);
        if (payload.public_site_mode === 'external' || !this.snippet) {
            this.snippet = liveSnippet;
        }
        var snippetEl = this.$('.hosting-wizard-snippet');
        if (snippetEl) {
            snippetEl.textContent = this.snippet || liveSnippet;
        }
        this.updateExternalChecklist(payload, cmsUrl);
        this.updateValidationWarnings();
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
            saveBtn.hidden = this.step < this.maxStep;
        }
        if (this.step === this.maxStep) {
            this.updatePreview();
        }
    };

    HostingWizard.prototype.populateFromApi = function (data) {
        this.hosting = data.hosting || {};
        this.snippet = data.client_snippet || '';
        this.sameHost = !!data.same_host;
        this.publicHost = data.public_host || '';
        this.registeredPublicHost = data.registered_public_host || data.public_host || '';
        this.envManaged = !!(data.detect && data.detect.env_managed);

        var cmsInput = this.$('.hosting-wizard-cms-url');
        if (cmsInput) {
            cmsInput.value = this.hosting.cms_base_url || data.suggested_cms_url || '';
            cmsInput.readOnly = this.envManaged;
        }

        var cmsAliases = this.$('.hosting-wizard-cms-aliases');
        if (cmsAliases) cmsAliases.value = this.formatAliasInput(this.hosting.cms_host_aliases || []);

        var mode = this.hosting.public_site_mode || 'none';
        var modeInput = this.root.querySelector('input[name="' + this.id + '-public-mode"][value="' + mode + '"]');
        if (modeInput) modeInput.checked = true;

        var publicInput = this.$('.hosting-wizard-public-url');
        if (publicInput) {
            publicInput.value = this.hosting.public_site_url || '';
            publicInput.readOnly = this.envManaged;
        }

        var publicAliases = this.$('.hosting-wizard-public-aliases');
        if (publicAliases) publicAliases.value = this.formatAliasInput(this.hosting.public_host_aliases || []);

        var landing = this.hosting.same_host_landing || 'admin';
        var landingInput = this.root.querySelector('input[name="' + this.id + '-same-host"][value="' + landing + '"]');
        if (landingInput) landingInput.checked = true;

        var strict = this.$('.hosting-wizard-strict-admin');
        if (strict) strict.checked = this.hosting.strict_admin_host !== false;

        var aliasRedirect = this.$('.hosting-wizard-alias-redirect');
        if (aliasRedirect) aliasRedirect.checked = this.hosting.alias_redirect_to_primary !== false;

        var envBanner = this.$('.hosting-wizard-env-banner');
        if (envBanner) envBanner.hidden = !this.envManaged;

        this.togglePublicFields();
        this.updatePreview();
    };

    HostingWizard.prototype.renderProposal = function (payload) {
        var box = this.$('.hosting-wizard-proposal');
        if (!box || !payload) return;
        if (payload.env_locked) {
            box.hidden = true;
            return;
        }
        var changes = payload.changes || [];
        if (!changes.length) {
            box.hidden = true;
            return;
        }
        var list = box.querySelector('.hosting-wizard-proposal-changes');
        if (list) {
            list.innerHTML = changes.map(function (change) {
                var from = typeof change.from === 'object' ? JSON.stringify(change.from) : String(change.from == null ? '—' : change.from);
                var to = typeof change.to === 'object' ? JSON.stringify(change.to) : String(change.to == null ? '—' : change.to);
                return '<li><strong>' + escapeHtml(change.field) + ':</strong> ' + escapeHtml(from) + ' → ' + escapeHtml(to) + '</li>';
            }).join('');
        }
        box.hidden = false;
        this.pendingProposal = payload.proposal || null;
    };

    HostingWizard.prototype.loadProposal = function () {
        var self = this;
        return fetch(apiUrl('/api/hosting.php?action=detect'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') return;
                self.renderProposal(payload.data || {});
            })
            .catch(function () { /* optional */ });
    };

    HostingWizard.prototype.applyProposal = function () {
        var self = this;
        if (!this.pendingProposal) return Promise.resolve();
        return fetch(apiUrl('/api/hosting.php?action=apply_proposal'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ proposal: this.pendingProposal, _csrf: getCsrfToken() })
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status === 'ok' && payload.data) {
                    self.populateFromApi(payload.data);
                    var box = self.$('.hosting-wizard-proposal');
                    if (box) box.hidden = true;
                    self.pendingProposal = null;
                    if (global.BirklyFeedback && typeof global.BirklyFeedback.toast === 'function') {
                        global.BirklyFeedback.toast(t('hosting_wizard_proposal_applied', 'Suggested delivery setup applied'), 'success');
                    }
                    if (typeof global.initDeliveryStatus === 'function') {
                        global.initDeliveryStatus('#delivery-status-site-delivery');
                    }
                } else if (global.BirklyFeedback && typeof global.BirklyFeedback.toast === 'function') {
                    global.BirklyFeedback.toast(payload.message || t('hosting_wizard_proposal_apply_failed', 'Failed to apply suggestion'), 'error');
                }
                return payload;
            });
    };

    HostingWizard.prototype.load = function () {
        var self = this;
        return fetch(apiUrl('/api/hosting.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || t('hosting_wizard_load_failed', 'Failed to load hosting'));
                self.populateFromApi(payload.data || {});
                return self.loadProposal().then(function () { return payload; });
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
                    self.publicHost = payload.data.public_host || self.publicHost;
                    self.registeredPublicHost = payload.data.registered_public_host ||
                        payload.data.public_host || self.registeredPublicHost;
                    if (payload.data.hosting) {
                        self.hosting = payload.data.hosting;
                    }
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

        ['.hosting-wizard-cms-url', '.hosting-wizard-public-url', '.hosting-wizard-cms-aliases', '.hosting-wizard-public-aliases'].forEach(function (sel) {
            var el = self.$(sel);
            if (el) el.addEventListener('input', function () { self.updatePreview(); });
        });

        var applyProposalBtn = this.$('.hosting-wizard-apply-proposal');
        if (applyProposalBtn) {
            applyProposalBtn.addEventListener('click', function () {
                applyProposalBtn.disabled = true;
                self.applyProposal().finally(function () {
                    applyProposalBtn.disabled = false;
                });
            });
        }
        var dismissProposalBtn = this.$('.hosting-wizard-dismiss-proposal');
        if (dismissProposalBtn) {
            dismissProposalBtn.addEventListener('click', function () {
                var box = self.$('.hosting-wizard-proposal');
                if (box) box.hidden = true;
                self.pendingProposal = null;
            });
        }

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
                var payloadPreview = self.getHostingPayload();
                if (payloadPreview.public_site_mode !== 'none' && !String(payloadPreview.public_site_url || '').trim()) {
                    BirklyFeedback.toast(t('hosting_wizard_public_url_required', 'Public site URL is required for this mode'), 'error');
                    return;
                }
                saveBtn.disabled = true;
                self.save().then(function (payload) {
                    if (payload.status === 'ok') {
                        // Always toast (onSave is additive — e.g. toggle project link)
                        if (global.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
                            BirklyFeedback.toast(t('hosting_wizard_saved', 'Hosting settings saved'), 'success');
                        } else if (global.Birkly && global.Birkly.Utils && typeof global.Birkly.Utils.showToast === 'function') {
                            global.Birkly.Utils.showToast(t('hosting_wizard_saved', 'Hosting settings saved'), 'success');
                        }
                        if (typeof self.options.onSave === 'function') {
                            self.options.onSave(payload);
                        }
                        self.updatePreview();
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

    global.refreshHostingWizard = function () {
        Object.keys(instances).forEach(function (id) {
            if (instances[id] && typeof instances[id].load === 'function') {
                instances[id].load();
            }
        });
    };
})(window);
