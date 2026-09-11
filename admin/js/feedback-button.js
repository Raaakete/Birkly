/**
 * Floating alpha feedback button + submit modal (P3-M10, P62-A3/A4)
 */
(function () {
    'use strict';

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function feedbackApiUrl() {
        if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
            return Birkly.url('/api/feedback.php');
        }
        return '/api/feedback.php';
    }

    function openModal() {
        const modal = document.getElementById('feedback-submit-modal');
        const err = document.getElementById('feedback-submit-error');
        const ok = document.getElementById('feedback-submit-success');
        if (err) { err.hidden = true; err.textContent = ''; }
        if (ok) ok.hidden = true;
        if (modal) {
            modal.classList.add('show');
            modal.style.removeProperty('display');
        }
        document.getElementById('feedback-title')?.focus();
    }

    function closeModal() {
        const modal = document.getElementById('feedback-submit-modal');
        if (modal) {
            modal.classList.remove('show', 'active', 'is-open');
            modal.style.removeProperty('display');
        }
    }

    function isFeedbackModalOpen() {
        const modal = document.getElementById('feedback-submit-modal');
        return !!(modal && modal.classList.contains('show'));
    }

    function fbT(key, fallback) {
        if (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function') {
            return Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback || key;
    }

    function submitLabel() {
        return fbT('feedback_submit_btn', 'Submit');
    }

    async function submitFeedback(e) {
        e.preventDefault();
        const btn = document.getElementById('feedback-submit-btn');
        const err = document.getElementById('feedback-submit-error');
        const ok = document.getElementById('feedback-submit-success');

        const type = document.getElementById('feedback-type')?.value || 'feedback';
        const category = document.getElementById('feedback-category')?.value || 'other';
        const title = document.getElementById('feedback-title')?.value?.trim() || '';
        const description = document.getElementById('feedback-description')?.value?.trim() || '';

        if (!title || !description) return;

        if (btn) { btn.disabled = true; btn.textContent = fbT('feedback_sending', 'Sending…'); }
        if (err) err.hidden = true;
        if (ok) ok.hidden = true;

        try {
            const res = await fetch(feedbackApiUrl(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'submit',
                    type,
                    category,
                    title,
                    description,
                    page_context: window.location.pathname + window.location.search,
                    _csrf: csrf(),
                }),
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') {
                throw new Error(data.message || fbT('feedback_submit_failed', 'Submission failed'));
            }

            document.getElementById('feedback-submit-form')?.reset();
            if (ok) ok.hidden = false;
            setTimeout(closeModal, 1800);
        } catch (error) {
            console.error('[Birkly feedback]', error);
            if (err) {
                err.textContent = error.message;
                err.hidden = false;
            }
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = submitLabel(); }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.Birkly = window.Birkly || {};
        window.Birkly.openFeedbackModal = openModal;
        window.Birkly.closeFeedbackModal = closeModal;

        document.getElementById('feedback-floating-btn')?.addEventListener('click', openModal);
        document.getElementById('feedback-submit-form')?.addEventListener('submit', submitFeedback);
        document.querySelectorAll('[data-close-feedback-submit]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isFeedbackModalOpen()) {
                e.preventDefault();
                closeModal();
            }
        });
    });
})();
