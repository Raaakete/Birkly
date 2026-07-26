/**
 * Settings → Hosting tab — mounts shared hosting wizard (P44-M5).
 */
(function () {
    'use strict';

    var wizard = null;
    var initialized = false;

    function updateProjectLink(mode) {
        document.querySelectorAll('[data-hosting-project-link], .project-hosting-link').forEach(function (el) {
            el.style.display = mode === 'project' ? '' : 'none';
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
                    if (wizard) {
                        updateProjectLink(wizard.getPublicMode());
                    }
                }
            });
        }

        wizard.load().then(function () {
            if (wizard) {
                updateProjectLink(wizard.getPublicMode());
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

        var domainsTab = document.querySelector('[data-tab="domains"]');
        if (domainsTab) {
            domainsTab.addEventListener('click', function () {
                mountWizard();
            });
        }

        var domainsPane = document.getElementById('domains');
        if (domainsPane && domainsPane.classList.contains('active') && document.getElementById('hosting-wizard-mount')) {
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
