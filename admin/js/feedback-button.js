/**
 * Floating alpha feedback button + submit modal (P3-M10)
 */
(function () {
    'use strict';

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function openModal() {
        const modal = document.getElementById('feedback-submit-modal');
        const err = document.getElementById('feedback-submit-error');
        const ok = document.getElementById('feedback-submit-success');
        if (err) { err.style.display = 'none'; err.textContent = ''; }
        if (ok) ok.style.display = 'none';
        if (modal) modal.style.display = 'flex';
        document.getElementById('feedback-title')?.focus();
    }

    function closeModal() {
        const modal = document.getElementById('feedback-submit-modal');
        if (modal) modal.style.display = 'none';
    }

    async function submitFeedback(e) {
        e.preventDefault();
        const btn = document.getElementById('feedback-submit-btn');
        const err = document.getElementById('feedback-submit-error');
        const ok = document.getElementById('feedback-submit-success');

        const type = document.getElementById('feedback-type')?.value || 'feedback';
        const title = document.getElementById('feedback-title')?.value?.trim() || '';
        const description = document.getElementById('feedback-description')?.value?.trim() || '';

        if (!title || !description) return;

        if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
        if (err) err.style.display = 'none';
        if (ok) ok.style.display = 'none';

        try {
            const res = await fetch('/api/feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'submit',
                    type,
                    title,
                    description,
                    page_context: window.location.pathname + window.location.search,
                    _csrf: csrf(),
                }),
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Submission failed');
            }

            document.getElementById('feedback-submit-form')?.reset();
            if (ok) ok.style.display = 'block';
            setTimeout(closeModal, 1800);
        } catch (error) {
            if (err) {
                err.textContent = error.message;
                err.style.display = 'block';
            }
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Submit'; }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('feedback-floating-btn')?.addEventListener('click', openModal);
        document.getElementById('feedback-submit-form')?.addEventListener('submit', submitFeedback);
        document.querySelectorAll('[data-close-feedback-submit]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });
    });
})();
