/**
 * Agent Guide setup wizard (P49-M3).
 * Modal/full-screen guided flow: Welcome → Identity → Sources → Enrich → Claims → Publish → Success.
 */
(function (global) {
    'use strict';

    var STEP_IDS = ['welcome', 'identity', 'sources', 'enrich', 'claims', 'publish', 'success'];
    var MAX_STEP = STEP_IDS.length;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function t(key, fallback) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback || key;
    }

    function getFocusable(container) {
        return Array.from(container.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) {
            return el.offsetParent !== null;
        });
    }

    function AgentGuideWizard(options) {
        this.options = options || {};
        this.step = 1;
        this.dirty = false;
        this.publishing = false;
        this.modal = null;
        this.focusTrap = null;
        this.previousFocus = null;
        this.wizardSuggestions = [];
    }

    AgentGuideWizard.prototype.renderShell = function () {
        var stepsHtml = STEP_IDS.map(function (id, i) {
            var num = i + 1;
            var label = t('ag_wizard_step_' + id, id);
            return '<button type="button" class="tab ag-wizard-step-btn" data-step="' + num + '" role="tab" aria-selected="false" disabled>' +
                escapeHtml(String(num) + '. ' + label) + '</button>';
        }).join('');

        return '' +
            '<div id="ag-wizard-modal" class="modal ap-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ag-wizard-title">' +
            '<div class="modal-backdrop" data-ag-wizard-close="1"></div>' +
            '<div class="modal-content modal--lg ag-wizard-content">' +
            '<div class="modal-header">' +
            '<h2 id="ag-wizard-title" class="modal-title">' + escapeHtml(t('ag_wizard_title', 'Set up your Agent Guide')) + '</h2>' +
            '<button type="button" class="modal-close" data-ag-wizard-close="1" aria-label="' + escapeHtml(t('close', 'Close')) + '">&times;</button>' +
            '</div>' +
            '<div class="tabs ag-wizard-steps" role="tablist" aria-label="' + escapeHtml(t('ag_wizard_steps_label', 'Setup steps')) + '">' + stepsHtml + '</div>' +
            '<div class="modal-body u-stack-4" id="ag-wizard-panels"></div>' +
            '<div class="modal-footer ag-wizard-footer">' +
            '<button type="button" class="btn btn-secondary ag-wizard-back" hidden>' + escapeHtml(t('ag_wizard_back', 'Back')) + '</button>' +
            '<button type="button" class="btn btn-link ag-wizard-skip-step" hidden>' + escapeHtml(t('ag_wizard_skip_step', 'Skip this step')) + '</button>' +
            '<div class="u-fill"></div>' +
            '<button type="button" class="btn btn-primary ag-wizard-next">' + escapeHtml(t('ag_wizard_continue', 'Continue')) + '</button>' +
            '<button type="button" class="btn btn-primary ag-wizard-publish" hidden>' + escapeHtml(t('publish_ai_presence', 'Publish')) + '</button>' +
            '<button type="button" class="btn btn-primary ag-wizard-done" hidden>' + escapeHtml(t('ag_wizard_done', 'Go to Overview')) + '</button>' +
            '</div>' +
            '<div class="ag-wizard-loading" id="ag-wizard-loading" hidden aria-live="polite" aria-busy="true">' +
            '<div class="spinner spinner-lg" aria-hidden="true"></div>' +
            '<p id="ag-wizard-loading-msg"></p></div>' +
            '</div></div>';
    };

    AgentGuideWizard.prototype.panelHtml = function () {
        return {
            welcome: '' +
                '<section class="ag-wizard-panel u-stack-4" data-step-panel="1">' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_welcome_lede', 'Publish a structured guide so AI systems can discover who you are, what you offer, and what you stand behind.')) + '</p>' +
                '<div class="u-stack-1">' +
                '<h3 class="card-section-title u-m-0">' + escapeHtml(t('ag_wizard_why_title', 'Why set this up?')) + '</h3>' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_why_body', 'After publish, your guide is available at a public URL. Chatbots and agents can read it instead of guessing from scattered pages.')) + '</p>' +
                '</div>' +
                '<div class="u-grid-2">' +
                '<button type="button" class="card card--interactive u-p-4 u-stack-1 u-align-text-left ag-wizard-choice-guided">' +
                '<strong>' + escapeHtml(t('ag_wizard_choice_guided', 'Guided setup')) + '</strong>' +
                '<span>' + escapeHtml(t('ag_wizard_choice_guided_desc', 'Step-by-step — best for first publish (~15 min).')) + '</span></button>' +
                '<button type="button" class="card card--interactive u-p-4 u-stack-1 u-align-text-left ag-wizard-choice-advanced">' +
                '<strong>' + escapeHtml(t('ag_wizard_choice_advanced', 'Advanced editing')) + '</strong>' +
                '<span>' + escapeHtml(t('ag_wizard_choice_advanced_desc', 'Skip the wizard and use Essentials or full tabs.')) + '</span></button>' +
                '</div></section>',
            identity: '' +
                '<section class="ag-wizard-panel u-stack-4" data-step-panel="2" hidden>' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_identity_why', 'Your official name and short description appear in public JSON and help agents introduce you correctly.')) + '</p>' +
                '<div class="form-group"><label for="ag-wiz-name">' + escapeHtml(t('ai_presence_official_name', 'Official name')) + '</label>' +
                '<input type="text" id="ag-wiz-name" class="input" required></div>' +
                '<div class="form-group"><label for="ag-wiz-short">' + escapeHtml(t('ai_presence_short_description', 'Short description')) + '</label>' +
                '<textarea id="ag-wiz-short" class="input" rows="2" required></textarea></div>' +
                '<div class="form-group"><label for="ag-wiz-website">' + escapeHtml(t('ai_presence_website', 'Website')) + '</label>' +
                '<input type="url" id="ag-wiz-website" class="input" placeholder="https://…"></div>' +
                '<p class="callout callout-accent callout--block u-text-sm u-m-0">' + escapeHtml(t('ag_wizard_identity_outcome', 'After publish: agents see your name and summary in the public Agent Guide JSON.')) + '</p></section>',
            sources: '' +
                '<section class="ag-wizard-panel u-stack-4" data-step-panel="3" hidden>' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_sources_why', 'Pick at least one narrative source — where agents should read the truth about you (About page, homepage, etc.).')) + '</p>' +
                '<div class="form-group"><label for="ag-wiz-source-collection">' + escapeHtml(t('collection', 'Collection')) + '</label>' +
                '<select id="ag-wiz-source-collection" class="input"><option value="">' + escapeHtml(t('select_collection', 'Select collection')) + '</option></select></div>' +
                '<div class="form-group"><label for="ag-wiz-source-entry">' + escapeHtml(t('entry', 'Entry')) + ' <span class="u-fg-secondary">(' + escapeHtml(t('ag_wizard_optional', 'optional')) + ')</span></label>' +
                '<select id="ag-wiz-source-entry" class="input"><option value="">' + escapeHtml(t('select_entry', 'Select entry')) + '</option></select></div>' +
                '<div class="form-group"><label for="ag-wiz-source-url">' + escapeHtml(t('ag_wizard_or_url', 'Or external URL')) + '</label>' +
                '<input type="url" id="ag-wiz-source-url" class="input" placeholder="https://…"></div>' +
                '<p class="callout callout-accent callout--block u-text-sm u-m-0">' + escapeHtml(t('ag_wizard_sources_outcome', 'Published JSON links to these sources so agents can cite them.')) + '</p></section>',
            enrich: '' +
                '<section class="ag-wizard-panel u-stack-4" data-step-panel="4" hidden>' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_enrich_why', 'Optional: add key positioning messages — what you are best known for and one differentiator.')) + '</p>' +
                '<div class="form-group"><label for="ag-wiz-best-known">' + escapeHtml(t('ai_presence_best_known_for', 'Best known for')) + '</label>' +
                '<textarea id="ag-wiz-best-known" class="input" rows="2" placeholder="' + escapeHtml(t('ag_wizard_one_per_line', 'One per line')) + '"></textarea></div>' +
                '<div class="form-group"><label for="ag-wiz-diff">' + escapeHtml(t('ai_presence_differentiators', 'Differentiators')) + '</label>' +
                '<textarea id="ag-wiz-diff" class="input" rows="2" placeholder="' + escapeHtml(t('ag_wizard_one_per_line', 'One per line')) + '"></textarea></div>' +
                '<div class="u-stack-3">' +
                '<button type="button" class="btn btn-secondary btn-sm" id="ag-wiz-suggest-enrich">' + escapeHtml(t('ag_wizard_suggest_from_sources', 'Suggest from sources')) + '</button>' +
                '<ul id="ag-wiz-suggestions" class="ap-card-list"></ul></div>' +
                '<p class="callout callout-accent callout--block u-text-sm u-m-0">' + escapeHtml(t('ag_wizard_enrich_outcome', 'These messages help agents answer “what makes you different?”')) + '</p></section>',
            claims: '' +
                '<section class="ag-wizard-panel u-stack-4" data-step-panel="5" hidden>' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_claims_why', 'Add 1–3 factual claims with a proof URL. Supported status needs evidence — link a press release, case study, or review page.')) + '</p>' +
                '<div class="form-group"><label for="ag-wiz-claim">' + escapeHtml(t('ai_presence_claim_statement', 'Claim statement')) + '</label>' +
                '<textarea id="ag-wiz-claim" class="input" rows="2"></textarea></div>' +
                '<div class="form-group"><label for="ag-wiz-claim-url">' + escapeHtml(t('ag_wizard_claim_proof_url', 'Proof URL')) + '</label>' +
                '<input type="url" id="ag-wiz-claim-url" class="input" placeholder="https://…"></div>' +
                '<p class="callout callout-accent callout--block u-text-sm u-m-0">' + escapeHtml(t('ag_wizard_claims_outcome', 'Supported claims appear in the public summary view for agents.')) + '</p></section>',
            publish: '' +
                '<section class="ag-wizard-panel u-stack-4" data-step-panel="6" hidden>' +
                '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(t('ag_wizard_publish_why', 'Publishing writes your draft to the public Agent Guide URL. You can update anytime — republish when ready.')) + '</p>' +
                '<label class="u-row-2 u-text-sm"><input type="checkbox" id="ag-wiz-pub-enabled" checked> ' +
                escapeHtml(t('ai_presence_enable_publishing', 'Enable public publishing for AI systems')) + '</label>' +
                '<dl class="ai-presence-urls" id="ag-wiz-public-url-preview"></dl>' +
                '<p class="callout callout-accent callout--block u-text-sm u-m-0">' + escapeHtml(t('ag_wizard_publish_outcome', 'After publish you get a success screen with your live URL and version number.')) + '</p></section>',
            success: '' +
                '<section class="ag-wizard-panel u-stack-4 u-align-text-center" data-step-panel="7" hidden>' +
                '<div class="ag-wizard-success-icon" aria-hidden="true">✓</div>' +
                '<h3>' + escapeHtml(t('ag_wizard_success_title', 'Agent Guide is live')) + '</h3>' +
                '<p id="ag-wiz-success-meta" class="u-text-sm u-fg-secondary u-m-0"></p>' +
                '<dl class="ai-presence-urls"><dt>' + escapeHtml(t('ag_wizard_public_json', 'Public JSON')) + '</dt>' +
                '<dd><code id="ag-wiz-success-url"></code></dd></dl>' +
                '<div class="u-cluster u-justify-center">' +
                '<button type="button" class="btn btn-primary" id="ag-wiz-copy-url">' + escapeHtml(t('ag_wizard_copy_url', 'Copy URL')) + '</button>' +
                '<a class="btn btn-secondary" id="ag-wiz-open-json" target="_blank" rel="noopener">' + escapeHtml(t('ag_wizard_open_json', 'Open public JSON')) + '</a>' +
                '<button type="button" class="btn btn-secondary" id="ag-wiz-preview-q">' + escapeHtml(t('ag_wizard_try_preview', 'Try a preview question')) + '</button>' +
                '</div></section>'
        };
    };

    AgentGuideWizard.prototype.ensureModal = function () {
        if (this.modal) return;
        var wrap = document.createElement('div');
        wrap.innerHTML = this.renderShell();
        this.modal = wrap.firstElementChild;
        document.body.appendChild(this.modal);
        var panels = this.panelHtml();
        var container = this.modal.querySelector('#ag-wizard-panels');
        container.innerHTML = panels.welcome + panels.identity + panels.sources + panels.enrich + panels.claims + panels.publish + panels.success;
        this.bindModalEvents();
    };

    AgentGuideWizard.prototype.bindModalEvents = function () {
        var self = this;
        this.modal.querySelectorAll('[data-ag-wizard-close]').forEach(function (el) {
            el.addEventListener('click', function () { self.requestClose(); });
        });
        this.modal.querySelector('.ag-wizard-back').addEventListener('click', function () { self.goBack(); });
        this.modal.querySelector('.ag-wizard-next').addEventListener('click', function () { self.goNext(); });
        this.modal.querySelector('.ag-wizard-skip-step').addEventListener('click', function () { self.skipStep(); });
        this.modal.querySelector('.ag-wizard-publish').addEventListener('click', function () { self.doPublish(); });
        this.modal.querySelector('.ag-wizard-done').addEventListener('click', function () { self.finish(); });
        this.modal.querySelector('.ag-wizard-choice-guided').addEventListener('click', function () {
            self.startGuided();
        });
        this.modal.querySelector('.ag-wizard-choice-advanced').addEventListener('click', function () {
            self.skipToAdvanced();
        });
        var collSel = this.modal.querySelector('#ag-wiz-source-collection');
        if (collSel) {
            collSel.addEventListener('change', function () {
                self.loadEntries(collSel.value);
            });
        }
        var suggestBtn = this.modal.querySelector('#ag-wiz-suggest-enrich');
        if (suggestBtn) {
            suggestBtn.addEventListener('click', function () { self.suggestEnrich(); });
        }
        var sugList = this.modal.querySelector('#ag-wiz-suggestions');
        if (sugList) {
            sugList.addEventListener('click', function (e) {
                var id = e.target.getAttribute('data-id');
                if (!id) return;
                if (e.target.classList.contains('ap-accept-suggestion')) {
                    self.acceptWizardSuggestion(id);
                }
                if (e.target.classList.contains('ap-reject-suggestion')) {
                    self.wizardSuggestions = self.wizardSuggestions.filter(function (s) { return s.id !== id; });
                    self.renderWizardSuggestions();
                }
            });
        }
        var copyBtn = this.modal.querySelector('#ag-wiz-copy-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var url = (self.modal.querySelector('#ag-wiz-success-url') || {}).textContent || '';
                if (url && navigator.clipboard) {
                    navigator.clipboard.writeText(url.trim()).then(function () {
                        if (self.options.toast) self.options.toast(t('ag_wizard_url_copied', 'URL copied'), 'success');
                    });
                }
            });
        }
        var previewBtn = this.modal.querySelector('#ag-wiz-preview-q');
        if (previewBtn) {
            previewBtn.addEventListener('click', function () {
                self.finish();
                if (self.options.onPreview) self.options.onPreview();
            });
        }
        this.modal.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                self.requestClose();
            }
            if (e.key === 'Tab' && self.modal.classList.contains('active')) {
                self.trapTab(e);
            }
        });
    };

    AgentGuideWizard.prototype.trapTab = function (e) {
        var focusable = getFocusable(this.modal.querySelector('.ag-wizard-content'));
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    };

    AgentGuideWizard.prototype.setLoading = function (on, message) {
        var el = this.modal.querySelector('#ag-wizard-loading');
        var msg = this.modal.querySelector('#ag-wizard-loading-msg');
        if (el) el.hidden = !on;
        if (msg) msg.textContent = message || '';
        this.modal.querySelectorAll('.ag-wizard-footer button, .ag-wizard-panel input, .ag-wizard-panel select, .ag-wizard-panel textarea')
            .forEach(function (node) {
                if (on) node.setAttribute('disabled', 'disabled');
                else node.removeAttribute('disabled');
            });
    };

    AgentGuideWizard.prototype.requestClose = function () {
        var self = this;
        if (this.step >= MAX_STEP) {
            this.close();
            return;
        }
        if (this.dirty && this.options.confirm) {
            this.options.confirm(t('ag_wizard_close_confirm', 'Close setup? Unsaved changes in this step will be lost.'))
                .then(function (ok) { if (ok) self.close(); });
            return;
        }
        this.close();
    };

    AgentGuideWizard.prototype.open = function () {
        this.ensureModal();
        this.step = 1;
        this.dirty = false;
        this.publishing = false;
        this.setLoading(false);
        this.previousFocus = document.activeElement;
        this.modal.style.display = 'flex';
        this.modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () {
            this.modal.classList.add('active');
            this.syncStepUi();
            this.prefillIdentity();
            this.populateCollections();
            this.updatePublicUrlPreview();
            var first = this.modal.querySelector('.ag-wizard-choice-guided');
            if (first) first.focus();
        }.bind(this));
        if (this.options.onSetupStart) {
            this.options.onSetupStart();
        }
    };

    AgentGuideWizard.prototype.close = function () {
        if (!this.modal) return;
        this.modal.classList.remove('active');
        this.modal.style.display = 'none';
        this.modal.setAttribute('aria-hidden', 'true');
        if (this.previousFocus && this.previousFocus.focus) {
            this.previousFocus.focus();
        }
    };

    AgentGuideWizard.prototype.startGuided = function () {
        this.step = 2;
        this.syncStepUi();
    };

    AgentGuideWizard.prototype.skipToAdvanced = function () {
        var self = this;
        if (this.options.onSkip) {
            this.options.onSkip().then(function () {
                self.close();
            }).catch(function () {
                self.close();
            });
        } else {
            this.close();
        }
    };

    AgentGuideWizard.prototype.goBack = function () {
        if (this.step <= 2) {
            this.step = 1;
        } else {
            this.step -= 1;
        }
        this.syncStepUi();
    };

    AgentGuideWizard.prototype.skipStep = function () {
        if (this.step === 3 || this.step === 4 || this.step === 5) {
            this.step += 1;
            this.syncStepUi();
        }
    };

    AgentGuideWizard.prototype.goNext = function () {
        var self = this;
        this.applyStepToDraft().then(function () {
            if (self.step >= 6) return;
            self.step += 1;
            self.dirty = false;
            self.syncStepUi();
        }).catch(function (err) {
            if (self.options.toast) self.options.toast(err.message || 'Error', 'error');
        });
    };

    AgentGuideWizard.prototype.syncStepUi = function () {
        var step = this.step;
        this.modal.querySelectorAll('.ag-wizard-step-btn').forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-step'), 10);
            var active = n === step;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            btn.disabled = n > step;
        });
        this.modal.querySelectorAll('.ag-wizard-panel').forEach(function (panel) {
            var n = parseInt(panel.getAttribute('data-step-panel'), 10);
            panel.hidden = n !== step;
        });
        var back = this.modal.querySelector('.ag-wizard-back');
        var skip = this.modal.querySelector('.ag-wizard-skip-step');
        var next = this.modal.querySelector('.ag-wizard-next');
        var pub = this.modal.querySelector('.ag-wizard-publish');
        var done = this.modal.querySelector('.ag-wizard-done');
        if (back) back.hidden = step <= 1;
        if (skip) skip.hidden = !(step === 3 || step === 4 || step === 5);
        if (next) next.hidden = step === 1 || step >= 6;
        if (pub) pub.hidden = step !== 6;
        if (done) done.hidden = step !== 7;
        if (step === 6) this.updatePublicUrlPreview();
        if (step === 7) this.renderSuccess();
    };

    AgentGuideWizard.prototype.prefillIdentity = function () {
        var draft = (this.options.getDraft && this.options.getDraft()) || {};
        var identity = draft.identity || {};
        var nameEl = this.modal.querySelector('#ag-wiz-name');
        var shortEl = this.modal.querySelector('#ag-wiz-short');
        var webEl = this.modal.querySelector('#ag-wiz-website');
        if (nameEl) nameEl.value = identity.official_name || '';
        if (shortEl) shortEl.value = identity.short_description || '';
        if (webEl) webEl.value = identity.website || '';
        var pos = draft.positioning || {};
        var bk = this.modal.querySelector('#ag-wiz-best-known');
        var diff = this.modal.querySelector('#ag-wiz-diff');
        if (bk) bk.value = Array.isArray(pos.best_known_for) ? pos.best_known_for.join('\n') : '';
        if (diff) diff.value = Array.isArray(pos.differentiators) ? pos.differentiators.join('\n') : '';
        var pub = draft.publication_settings || {};
        var pubEl = this.modal.querySelector('#ag-wiz-pub-enabled');
        if (pubEl) pubEl.checked = pub.enabled !== false;
    };

    AgentGuideWizard.prototype.populateCollections = function () {
        var collections = (this.options.getCollections && this.options.getCollections()) || [];
        var sel = this.modal.querySelector('#ag-wiz-source-collection');
        if (!sel) return;
        sel.innerHTML = '<option value="">' + escapeHtml(t('select_collection', 'Select collection')) + '</option>';
        collections.forEach(function (c) {
            var key = c.dir || c.slug || c.handle || c.id || '';
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = (c.name || key) + (c.entries_count != null ? ' (' + c.entries_count + ')' : '');
            sel.appendChild(opt);
        });
    };

    AgentGuideWizard.prototype.loadEntries = function (collection) {
        var sel = this.modal.querySelector('#ag-wiz-source-entry');
        if (!sel || !collection) {
            if (sel) sel.innerHTML = '<option value="">' + escapeHtml(t('select_entry', 'Select entry')) + '</option>';
            return Promise.resolve();
        }
        if (this.options.loadEntries) {
            return this.options.loadEntries(collection, sel);
        }
        return Promise.resolve();
    };

    AgentGuideWizard.prototype.applyStepToDraft = function () {
        var draft = (this.options.getDraft && this.options.getDraft()) || {};
        if (this.step === 2) {
            var name = (this.modal.querySelector('#ag-wiz-name') || {}).value || '';
            var shortDesc = (this.modal.querySelector('#ag-wiz-short') || {}).value || '';
            var website = (this.modal.querySelector('#ag-wiz-website') || {}).value || '';
            if (!name.trim() || !shortDesc.trim()) {
                return Promise.reject(new Error(t('ag_wizard_identity_required', 'Official name and short description are required.')));
            }
            draft.identity = draft.identity || {};
            draft.identity.official_name = name.trim();
            draft.identity.short_description = shortDesc.trim();
            draft.identity.website = website.trim();
            this.dirty = true;
        }
        if (this.step === 3) {
            var col = (this.modal.querySelector('#ag-wiz-source-collection') || {}).value || '';
            var entry = (this.modal.querySelector('#ag-wiz-source-entry') || {}).value || '';
            var url = (this.modal.querySelector('#ag-wiz-source-url') || {}).value || '';
            if (!col && !url.trim()) {
                return Promise.reject(new Error(t('ag_wizard_source_required', 'Add a CMS source or external URL.')));
            }
            draft.sources_of_truth = draft.sources_of_truth || [];
            if (col) {
                var entrySel = this.modal.querySelector('#ag-wiz-source-entry');
                var label = entry && entrySel && entrySel.selectedOptions.length
                    ? entrySel.selectedOptions[0].textContent : col;
                draft.sources_of_truth.push({
                    id: 'truth_' + Math.random().toString(16).slice(2, 10),
                    type: entry ? 'cms_entry' : 'cms_collection',
                    role: 'narrative',
                    label: label,
                    url: '',
                    ref: { collection: col, entry_id: entry || '', slug: entry || col, label: label, ref_type: entry ? 'entry' : 'collection' }
                });
            }
            if (url.trim()) {
                draft.sources_of_truth.push({
                    id: 'truth_' + Math.random().toString(16).slice(2, 10),
                    type: 'website',
                    role: 'narrative',
                    label: url.trim(),
                    url: url.trim()
                });
            }
            this.dirty = true;
        }
        if (this.step === 4) {
            draft.positioning = draft.positioning || {};
            var bkEl = this.modal.querySelector('#ag-wiz-best-known');
            var diffEl = this.modal.querySelector('#ag-wiz-diff');
            draft.positioning.best_known_for = bkEl ? bkEl.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean) : [];
            draft.positioning.differentiators = diffEl ? diffEl.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean) : [];
            this.dirty = true;
        }
        if (this.step === 5) {
            var claimText = (this.modal.querySelector('#ag-wiz-claim') || {}).value || '';
            var claimUrl = (this.modal.querySelector('#ag-wiz-claim-url') || {}).value || '';
            if (claimText.trim()) {
                draft.claims = draft.claims || [];
                draft.evidence = draft.evidence || [];
                var claimId = 'claim_' + Math.random().toString(16).slice(2, 10);
                var evId = 'evidence_' + Math.random().toString(16).slice(2, 10);
                var sources = [];
                if (claimUrl.trim()) {
                    sources.push({ id: 'src_' + Math.random().toString(16).slice(2, 8), role: 'proof', type: 'other', label: claimUrl.trim(), url: claimUrl.trim() });
                    draft.evidence.push({
                        id: evId,
                        type: 'external_url',
                        title: claimUrl.trim(),
                        url: claimUrl.trim(),
                        claim_ids: [claimId],
                        verification_status: 'user_verified'
                    });
                }
                draft.claims.push({
                    id: claimId,
                    statement: claimText.trim(),
                    status: claimUrl.trim() ? 'supported' : 'pending_review',
                    evidence_ids: claimUrl.trim() ? [evId] : [],
                    sources: sources
                });
            }
            this.dirty = true;
        }
        if (this.step === 6) {
            draft.publication_settings = draft.publication_settings || {};
            var enabledEl = this.modal.querySelector('#ag-wiz-pub-enabled');
            draft.publication_settings.enabled = enabledEl ? enabledEl.checked : true;
            if (!draft.publication_settings.public_path) {
                draft.publication_settings.public_path = '/.well-known/agent-guide.json';
            }
            this.dirty = true;
        }
        if (this.options.setDraft) {
            this.options.setDraft(draft);
        }
        if (this.options.saveDraft && this.dirty) {
            return this.options.saveDraft();
        }
        return Promise.resolve();
    };

    AgentGuideWizard.prototype.updatePublicUrlPreview = function () {
        var el = this.modal.querySelector('#ag-wiz-public-url-preview');
        if (!el || !this.options.getPublicUrls) return;
        var urls = this.options.getPublicUrls();
        el.innerHTML = '<dt>Agent Guide</dt><dd><code>' + escapeHtml(urls.well_known_url || '—') + '</code></dd>';
    };

    AgentGuideWizard.prototype.doPublish = function () {
        if (this.publishing) return;
        var self = this;
        this.publishing = true;
        this.setLoading(true, t('ag_wizard_publishing', 'Publishing…'));
        this.applyStepToDraft().then(function () {
            if (!self.options.publish) {
                return Promise.reject(new Error('Publish unavailable'));
            }
            return self.options.publish();
        }).then(function (result) {
            self.publishing = false;
            self.setLoading(false);
            self.step = 7;
            self.publishResult = result || {};
            self.syncStepUi();
            if (self.options.onPublishSuccess) {
                self.options.onPublishSuccess(result, true);
            }
        }).catch(function (err) {
            self.publishing = false;
            self.setLoading(false);
            if (self.options.toast) self.options.toast(err.message || 'Publish failed', 'error');
        });
    };

    AgentGuideWizard.prototype.renderSuccess = function () {
        var urls = this.options.getPublicUrls ? this.options.getPublicUrls() : {};
        var meta = this.publishResult && this.publishResult.meta ? this.publishResult.meta : {};
        var version = meta.published_version || (this.publishResult && this.publishResult.version) || '';
        var at = meta.last_published_at || '';
        var urlEl = this.modal.querySelector('#ag-wiz-success-url');
        var metaEl = this.modal.querySelector('#ag-wiz-success-meta');
        var openEl = this.modal.querySelector('#ag-wiz-open-json');
        if (urlEl) urlEl.textContent = urls.well_known_url || '';
        if (metaEl) {
            metaEl.textContent = t('ag_wizard_success_meta', 'Version {version} · Published {time}')
                .replace('{version}', String(version || '1'))
                .replace('{time}', at ? new Date(at).toLocaleString() : 'now');
        }
        if (openEl && urls.well_known_url) openEl.href = urls.well_known_url;
    };

    AgentGuideWizard.prototype.finish = function () {
        var self = this;
        if (this.options.onComplete) {
            this.options.onComplete().then(function () { self.close(); });
        } else {
            this.close();
        }
    };

    AgentGuideWizard.prototype.suggestEnrich = function () {
        var self = this;
        if (!this.options.runCopilot) {
            if (this.options.toast) this.options.toast(t('ag_wizard_enrich_manual_ok', 'Continue manually — AI is optional.'), 'info');
            return;
        }
        this.setLoading(true, t('ag_wizard_suggesting', 'Generating suggestions…'));
        this.options.runCopilot('fill_gaps').then(function (suggestions) {
            self.wizardSuggestions = suggestions || [];
            self.renderWizardSuggestions();
        }).catch(function (err) {
            if (self.options.toast) self.options.toast(err.message, 'warning');
        }).finally(function () {
            self.setLoading(false);
        });
    };

    AgentGuideWizard.prototype.renderWizardSuggestions = function () {
        var list = this.modal.querySelector('#ag-wiz-suggestions');
        if (!list) return;
        list.innerHTML = '';
        this.wizardSuggestions.forEach(function (s) {
            var li = document.createElement('li');
            li.className = 'card card--sunken u-p-3 u-stack-2';
            var valPreview = typeof s.value === 'string' ? s.value : JSON.stringify(s.value);
            if (valPreview.length > 120) valPreview = valPreview.slice(0, 120) + '…';
            li.innerHTML = '<strong>' + escapeHtml(s.label || s.field) + '</strong><p>' + escapeHtml(valPreview) + '</p>' +
                '<div class="u-cluster u-gap-2">' +
                '<button type="button" class="btn btn-sm btn-primary ap-accept-suggestion" data-id="' + escapeHtml(s.id) + '">' +
                escapeHtml(t('accept', 'Accept')) + '</button> ' +
                '<button type="button" class="btn btn-sm btn-secondary ap-reject-suggestion" data-id="' + escapeHtml(s.id) + '">' +
                escapeHtml(t('reject', 'Reject')) + '</button></div>';
            list.appendChild(li);
        });
    };

    AgentGuideWizard.prototype.acceptWizardSuggestion = function (id) {
        var self = this;
        var suggestion = this.wizardSuggestions.find(function (s) { return s.id === id; });
        if (!suggestion || !this.options.acceptSuggestion) return;
        this.options.acceptSuggestion(suggestion).then(function () {
            self.wizardSuggestions = self.wizardSuggestions.filter(function (s) { return s.id !== id; });
            self.renderWizardSuggestions();
            self.prefillIdentity();
        });
    };

    global.AgentGuideWizard = AgentGuideWizard;
})(window);
