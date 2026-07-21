// Dashboard page — onboarding handlers (widget grid: dashboard-widgets.js)
(function () {
    'use strict';

    function apiUrl(path) {
        if (window.Birkly && typeof Birkly.url === 'function') {
            return Birkly.url(path);
        }
        return path;
    }

    function setupOnboardingHandlers() {
        var onboardingModal = document.getElementById('onboarding-complete-modal');
        var closeModal = function () {
            if (onboardingModal) onboardingModal.style.display = 'none';
        };

        var closeBtn = document.getElementById('onboarding-complete-close');
        var closeBtn2 = document.getElementById('onboarding-complete-close-btn');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (closeBtn2) closeBtn2.addEventListener('click', closeModal);

        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('onboarding_complete') === 'true' && onboardingModal) {
            onboardingModal.style.display = 'flex';
            var demoUrl = urlParams.get('demo_website_url');
            var demoCard = document.getElementById('onboarding-complete-demo-card');
            var demoLink = document.getElementById('onboarding-complete-demo-link');
            if (demoUrl && demoCard && demoLink) {
                demoCard.style.display = '';
                demoLink.href = demoUrl;
            }
        }

        async function postOnboardingAction(action, body) {
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var res = await fetch(apiUrl('/api/onboarding.php?action=' + action), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({}, body, { _csrf: csrf ? csrf.content : '' }))
            });
            return res.json();
        }

        var hideBtn = document.getElementById('hide-checklist-btn');
        if (hideBtn) {
            hideBtn.addEventListener('click', async function () {
                await postOnboardingAction('hide_checklist', {});
                var panel = document.getElementById('onboarding-checklist-panel');
                if (panel) panel.remove();
            });
        }

        document.querySelectorAll('.checklist-dismiss').forEach(function (btn) {
            btn.addEventListener('click', async function (e) {
                e.preventDefault();
                var id = btn.getAttribute('data-dismiss-id');
                if (!id) return;
                await postOnboardingAction('dismiss_checklist_item', { item_id: id });
                var item = btn.closest('.checklist-item');
                if (item) item.remove();
            });
        });
    }

    function initDashboard() {
        if (document.getElementById('dashboard-widget-grid') || document.getElementById('onboarding-checklist-panel')) {
            setupOnboardingHandlers();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }
})();
