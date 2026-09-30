/**
 * Settings → Site delivery — mounts shared hosting wizard (P44-M5 / P61-M1).
 */
(function () {
    'use strict';

    var wizard = null;
    var initialized = false;

    function ht(key, fallback) {
        if (window.Birkly && window.Birkly.i18n && typeof window.Birkly.i18n.t === 'function') {
            return window.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback;
    }

    function summaryRow(label, value) {
        return '<div class="setting-row"><div class="setting-label"><span>' + label + '</span></div><div class="setting-value">' + value + '</div></div>';
    }

    function renderHostingSummary() {
        var rows = document.getElementById('hosting-summary-rows');
        if (!rows || !wizard || typeof wizard.getHostingPayload !== 'function') return;
        var payload = wizard.getHostingPayload();
        var mode = payload.public_site_mode || 'none';
        var modeLabel = mode === 'project'
            ? ht('hosting_wizard_public_project', 'In Birkly (project/ folder)')
            : (mode === 'external'
                ? ht('hosting_wizard_public_external', 'Hosted elsewhere (Netlify, separate server, etc.)')
                : ht('hosting_wizard_public_none', 'None — CMS only (no public site on this install)'));
        var cms = payload.cms_base_url ? '<code>' + escapeHtml(payload.cms_base_url) + '</code>' : '—';
        var pub = payload.public_site_url ? '<code>' + escapeHtml(payload.public_site_url) + '</code>' : '—';
        rows.innerHTML =
            summaryRow(ht('hosting_wizard_cms_url', 'CMS URL'), cms) +
            summaryRow(ht('hosting_wizard_public_title', 'Where is your public website?'), escapeHtml(modeLabel)) +
            (mode === 'none' ? '' : summaryRow(ht('hosting_wizard_public_url', 'Public site URL'), pub));
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function setHostingSheet(open) {
        var sheet = document.getElementById('hosting-setup-sheet');
        if (!sheet) return;
        if (open) {
            sheet.style.display = 'flex';
            sheet.classList.add('active');
        } else {
            sheet.classList.remove('active');
            sheet.style.display = 'none';
        }
    }

    function bindHostingSheet() {
        var openBtn = document.getElementById('hosting-open-setup');
        var closeBtn = document.getElementById('hosting-setup-close');
        var sheet = document.getElementById('hosting-setup-sheet');
        if (openBtn && openBtn.dataset.bound !== '1') {
            openBtn.dataset.bound = '1';
            openBtn.addEventListener('click', function () { setHostingSheet(true); });
        }
        if (closeBtn && closeBtn.dataset.bound !== '1') {
            closeBtn.dataset.bound = '1';
            closeBtn.addEventListener('click', function () { setHostingSheet(false); });
        }
        if (sheet && sheet.dataset.bound !== '1') {
            sheet.dataset.bound = '1';
            sheet.addEventListener('click', function (event) {
                if (event.target === sheet) setHostingSheet(false);
            });
        }
    }

    function updateProjectLink(mode) {
        document.querySelectorAll('[data-hosting-project-link], .project-hosting-link').forEach(function (el) {
            el.hidden = mode !== 'project';
            el.style.display = '';
        });
    }

    function mountWizard() {
        var mount = document.getElementById('hosting-wizard-mount');
        if (!mount || !window.BirklyHostingWizard) return;

        if (!wizard) {
            wizard = window.BirklyHostingWizard.create(mount, {
                id: 'settings-hosting-wizard',
                mode: 'settings',
                onSave: function () {
                    // Wizard already shows a success toast (P61-B8).
                    if (wizard) {
                        updateProjectLink(wizard.getPublicMode());
                        renderHostingSummary();
                    }
                    if (typeof window.initDeliveryStatus === 'function') {
                        window.initDeliveryStatus('#delivery-status-site-delivery');
                    }
                    if (typeof window.loadDomains === 'function') {
                        window.loadDomains();
                    }
                    if (typeof window.loadDeliveryMatrix === 'function') {
                        window.loadDeliveryMatrix();
                    }
                }
            });
        }

        bindHostingSheet();
        wizard.load().then(function () {
            if (wizard) {
                updateProjectLink(wizard.getPublicMode());
                renderHostingSummary();
            }
        }).catch(function (e) {
            console.error(e);
        });
    }

    function initHostingSettings() {
        if (!document.getElementById('hosting-wizard-mount')) return;
        mountWizard();
    }

    function init() {
        if (initialized) return;
        initialized = true;
        // Site delivery tab init (settings-page.js) calls initHostingSettings.
        // Mount immediately when the wizard container is already in the DOM.
        if (document.getElementById('hosting-wizard-mount')) {
            mountWizard();
        }
    }

    window.initHostingSettings = initHostingSettings;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
