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
        if (err) { err.hidden = true; setMessage(err, ''); }
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

    function setMessage(el, text) {
        const target = el.querySelector('[data-feedback-message]') || el;
        target.textContent = text;
    }

    function renderFileList() {
        const input = document.getElementById('feedback-attachments');
        const list = document.getElementById('feedback-attachments-list');
        if (!input || !list) return;
        const names = input.files ? Array.from(input.files).map((file) => file.name) : [];
        list.textContent = names.length ? names.join(', ') : (list.dataset.empty || '');
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

        const fileInput = document.getElementById('feedback-attachments');
        const files = fileInput?.files ? Array.from(fileInput.files) : [];
        const contactAllowed = document.getElementById('feedback-contact-allowed')?.checked === true;
        const useMultipart = files.length > 0 || contactAllowed;

        try {
            let res;
            if (useMultipart) {
                const formData = new FormData();
                formData.append('action', 'submit');
                formData.append('type', type);
                formData.append('category', category);
                formData.append('title', title);
                formData.append('description', description);
                formData.append('page_context', window.location.pathname + window.location.search);
                formData.append('_csrf', csrf());
                if (contactAllowed) {
                    formData.append('contact_allowed', '1');
                }
                files.forEach((file) => formData.append('attachments[]', file));
                res = await fetch(feedbackApiUrl(), {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: formData,
                });
            } else {
                res = await fetch(feedbackApiUrl(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'submit',
                        type,
                        category,
                        title,
                        description,
                        page_context: window.location.pathname + window.location.search,
                        contact_allowed: contactAllowed,
                        _csrf: csrf(),
                    }),
                });
            }
            const raw = await res.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (parseErr) {
                throw new Error(fbT('feedback_submit_failed', 'Submission failed'));
            }
            if (!res.ok || data.status !== 'ok') {
                throw new Error(data.message || data.error || fbT('feedback_submit_failed', 'Submission failed'));
            }

            document.getElementById('feedback-submit-form')?.reset();
            renderFileList();
            if (ok) ok.hidden = false;
            setTimeout(closeModal, 1800);
        } catch (error) {
            console.error('[Birkly feedback]', error);
            if (err) {
                setMessage(err, error.message);
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
        document.getElementById('feedback-attachments')?.addEventListener('change', renderFileList);
        document.querySelector('[data-feedback-pick-files]')?.addEventListener('click', () => {
            document.getElementById('feedback-attachments')?.click();
        });
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
