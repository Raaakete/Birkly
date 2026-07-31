/**
 * Entry Editor JavaScript
 * Handles form submission, editor sync, and fieldtype initialization
 */
(function() {
    'use strict';

    // Configuration (can be overridden via data attributes or global config)
    let config = window.entryEditorConfig || {};
    
    // Read from JSON config block when available
    const configEl = document.getElementById('entry-editor-config');
    if (configEl && configEl.textContent) {
        try {
            config = Object.assign({}, config, JSON.parse(configEl.textContent));
        } catch (err) {
            console.error('Failed to parse entry-editor-config:', err);
        }
    } else if (configEl) {
        const slug = configEl.getAttribute('data-collection-slug');
        if (slug) {
            config.collectionSlug = slug;
        }
    }

    let saveModalPreviousFocus = null;
    let isSubmittingEntry = false;

    function saveLabel(key, fallback) {
        return (config.saveLabels && config.saveLabels[key]) || fallback;
    }

    function getSaveSubmitButton() {
        return document.getElementById('entry-save-submit');
    }

    function setSaveSubmitting(isSubmitting) {
        const btn = getSaveSubmitButton();
        const bar = document.querySelector('.main-actions-bar--sticky');
        if (!btn) return;

        if (isSubmitting) {
            if (!btn.dataset.originalLabel) {
                btn.dataset.originalLabel = btn.textContent.trim();
            }
            btn.textContent = saveLabel('saving', 'Saving') + '…';
            btn.disabled = true;
            if (bar) bar.classList.add('main-actions-bar--saving');
        } else {
            btn.textContent = btn.dataset.originalLabel || btn.textContent;
            btn.disabled = false;
            if (bar) bar.classList.remove('main-actions-bar--saving');
        }
    }

    function flashEntrySavedState() {
        const bar = document.querySelector('.main-actions-bar--sticky');
        if (!bar) return;
        bar.classList.add('main-actions-bar--saved');
        setTimeout(function () {
            bar.classList.remove('main-actions-bar--saved');
        }, 2500);
    }

    function hideUnsavedBadge() {
        const badge = document.getElementById('editor-unsaved-badge');
        if (badge) {
            badge.hidden = true;
            badge.style.display = 'none';
        }
    }

    function showEntryUpdateBanner(message) {
        const banner = document.getElementById('entry-save-success-banner');
        const textEl = document.getElementById('entry-save-success-banner-text');
        if (!banner || !textEl) return;

        textEl.textContent = message;
        banner.hidden = false;
        requestAnimationFrame(function () {
            banner.classList.add('is-visible');
        });

        clearTimeout(banner._hideTimer);
        banner._hideTimer = setTimeout(function () {
            banner.classList.remove('is-visible');
            setTimeout(function () {
                banner.hidden = true;
            }, 350);
        }, 6000);
    }

    function trapEntrySaveModalFocus(modal) {
        const focusable = modal.querySelectorAll(
            'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        modal._focusTrapHandler = function (e) {
            if (e.key !== 'Tab') return;
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', modal._focusTrapHandler);
    }

    function releaseEntrySaveModalFocusTrap(modal) {
        if (modal && modal._focusTrapHandler) {
            document.removeEventListener('keydown', modal._focusTrapHandler);
            modal._focusTrapHandler = null;
        }
    }

    function closeEntrySaveSuccessModal() {
        const modal = document.getElementById('entry-save-success-modal');
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        releaseEntrySaveModalFocusTrap(modal);
        if (saveModalPreviousFocus && saveModalPreviousFocus.focus) {
            saveModalPreviousFocus.focus();
            saveModalPreviousFocus = null;
        }
    }

    function getEntryStatusFromSidebar() {
        const statusSelect = document.getElementById('status');
        const value = statusSelect ? String(statusSelect.value || '').trim() : '';
        if (value === 'draft') {
            return 'unpublished';
        }
        return value || 'unpublished';
    }

    function isUnpublishedStatus(status) {
        return !status || status === 'unpublished' || status === 'draft';
    }

    async function publishSavedEntry(entryId) {
        const form = document.getElementById('entry-form');
        if (!form || !entryId) {
            return false;
        }

        const publishData = new FormData(form);
        publishData.set('entry_id', entryId);
        publishData.set('status', 'published');
        if (!publishData.get('collection') && config.collectionSlug) {
            publishData.set('collection', config.collectionSlug);
        }

        const response = await fetch('/api/admin_entries.php?action=save', {
            method: 'POST',
            body: publishData,
        });
        const raw = await response.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (_e) {
            data = null;
        }

        if (!data || data.status !== 'ok') {
            throw new Error((data && data.message) || raw || 'Publish failed');
        }

        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            statusSelect.value = 'published';
            statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return true;
    }

    function openEntrySaveSuccessModal(isCreate, entryId, entryStatus) {
        const modal = document.getElementById('entry-save-success-modal');
        if (!modal) return;

        const titleEl = document.getElementById('entry-save-success-title');
        const messageEl = document.getElementById('entry-save-success-message');
        const viewEntriesEl = document.getElementById('entry-save-view-entries');
        const publishEl = document.getElementById('entry-save-publish');
        const addAnotherEl = document.getElementById('entry-save-add-another');
        const continueEl = document.getElementById('entry-save-continue');
        const unpublished = isUnpublishedStatus(entryStatus);

        if (titleEl) {
            titleEl.textContent = isCreate
                ? saveLabel('createTitle', 'Entry created')
                : saveLabel('updateTitle', 'Entry saved');
        }
        if (messageEl) {
            messageEl.textContent = isCreate
                ? saveLabel('createMessage', 'Your entry was saved.')
                : saveLabel('updateMessage', 'Your changes were saved successfully.');
        }
        if (viewEntriesEl) {
            viewEntriesEl.textContent = saveLabel('viewEntries', 'View all entries');
            viewEntriesEl.href = config.entriesListUrl || '#';
            viewEntriesEl.hidden = !config.entriesListUrl;
        }
        if (publishEl) {
            publishEl.textContent = saveLabel('publishNow', 'Publish entry');
            publishEl.hidden = !unpublished || !entryId;
            publishEl.dataset.entryId = entryId || '';
        }
        if (addAnotherEl) {
            addAnotherEl.textContent = saveLabel('addAnother', 'Add another entry');
            addAnotherEl.href = config.newEntryUrl || '#';
            addAnotherEl.hidden = !isCreate || !config.newEntryUrl;
        }
        if (continueEl) {
            continueEl.textContent = saveLabel('continueEditing', 'Continue editing');
        }

        saveModalPreviousFocus = document.activeElement;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () {
            modal.classList.add('active');
            trapEntrySaveModalFocus(modal);
            let firstFocus = continueEl;
            if (publishEl && !publishEl.hidden) {
                firstFocus = publishEl;
            } else if (viewEntriesEl && !viewEntriesEl.hidden) {
                firstFocus = viewEntriesEl;
            } else if (addAnotherEl && !addAnotherEl.hidden) {
                firstFocus = addAnotherEl;
            }
            if (firstFocus && firstFocus.focus) {
                firstFocus.focus();
            }
        });
    }

    function bindEntrySaveSuccessModal() {
        const modal = document.getElementById('entry-save-success-modal');
        if (!modal || modal.dataset.entrySaveBound === '1') return;
        modal.dataset.entrySaveBound = '1';

        modal.addEventListener('click', function (e) {
            const closeTarget = e.target.closest('[data-entry-modal-close]');
            if (closeTarget || e.target.classList.contains('modal-backdrop')) {
                e.preventDefault();
                closeEntrySaveSuccessModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeEntrySaveSuccessModal();
            }
        });

        const publishBtn = document.getElementById('entry-save-publish');
        if (publishBtn) {
            publishBtn.addEventListener('click', async function () {
                const entryId = publishBtn.dataset.entryId || '';
                if (!entryId) return;
                publishBtn.disabled = true;
                try {
                    await publishSavedEntry(entryId);
                    closeEntrySaveSuccessModal();
                    hideUnsavedBadge();
                    flashEntrySavedState();
                    BirklyFeedback.toast(saveLabel('publishedToast', 'Entry published'), 'success');
                } catch (err) {
                    BirklyFeedback.toast(err.message || 'Publish failed', 'error');
                } finally {
                    publishBtn.disabled = false;
                }
            });
        }
    }

    /**
     * Auto-generate slug from title (when auto checkbox is checked).
     */
    function normalizeSlug(value) {
        return (value || '')
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function findTitleField() {
        return document.getElementById('field-title')
            || document.getElementById('field_title')
            || document.querySelector('input[name="title"]');
    }

    function getEntryIdFallback() {
        const entryIdInput = document.querySelector('input[name="entry_id"]');
        return entryIdInput ? entryIdInput.value : '';
    }

    function toIsoDateTimeValue(value) {
        if (!value) {
            return '';
        }

        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toISOString();
    }

    function normalizeScheduleFieldValue(fieldName, value) {
        if ((fieldName === 'published_at' || fieldName === 'expires_at') && value) {
            return toIsoDateTimeValue(value);
        }

        return value;
    }

    function normalizeScheduleFieldsInFormData(formData) {
        ['published_at', 'expires_at'].forEach((fieldName) => {
            const value = formData.get(fieldName);
            if (typeof value === 'string' && value) {
                formData.set(fieldName, normalizeScheduleFieldValue(fieldName, value));
            }
        });
    }

    function ensureEntryIdInput(form, entryId) {
        if (!form || !entryId) {
            return null;
        }

        let input = form.querySelector('input[name="entry_id"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'entry_id';
            form.insertBefore(input, form.firstChild);
        }

        input.value = entryId;
        return input;
    }

    function updateEntryEditorUrl(entryId) {
        if (!entryId || !window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('entry', entryId);
        window.history.replaceState(window.history.state, '', url.toString());
    }

    function initSlugGeneration() {
        const titleField = findTitleField();
        const slugField = document.getElementById('slug');
        const autoSlugHidden = document.getElementById('auto_slug');
        const autoSlugCheckbox = document.getElementById('auto_slug_checkbox');

        if (!slugField) return;

        function syncAutoSlugHidden() {
            if (autoSlugHidden && autoSlugCheckbox) {
                autoSlugHidden.value = autoSlugCheckbox.checked ? '1' : '0';
            }
        }

        function regenerateSlug() {
            if (autoSlugCheckbox && !autoSlugCheckbox.checked) return;
            const title = titleField ? (titleField.value || '').trim() : '';
            const fallbackId = getEntryIdFallback();
            slugField.value = title ? normalizeSlug(title) : normalizeSlug(fallbackId);
        }

        if (autoSlugCheckbox) {
            autoSlugCheckbox.addEventListener('change', function() {
                syncAutoSlugHidden();
                if (this.checked) regenerateSlug();
            });
            syncAutoSlugHidden();
        }

        if (titleField) {
            titleField.addEventListener('input', regenerateSlug);
        }

        slugField.addEventListener('input', function() {
            if (autoSlugCheckbox) {
                autoSlugCheckbox.checked = false;
                syncAutoSlugHidden();
            }
        });
    }

    /**
     * Initialize scheduled publishing UI
     */
    function initScheduledPublishing() {
        const statusSelect = document.getElementById('status');
        const publishScheduleGroup = document.getElementById('publish-schedule-group');
        const expireScheduleGroup = document.getElementById('expire-schedule-group');
        const publishAtInput = document.getElementById('published_at');
        const expiresAtInput = document.getElementById('expires_at');
        
        if (!statusSelect) return;
        
        function toggleScheduleFields() {
            const status = statusSelect.value;
            const reviewerGroup = document.getElementById('reviewer-group');
            
            // Show publish date field when status is "scheduled"
            if (publishScheduleGroup) {
                if (status === 'scheduled') {
                    publishScheduleGroup.style.display = 'block';
                } else {
                    publishScheduleGroup.style.display = 'none';
                }
            }
            
            // Show unpublish date field when status is "published"
            if (expireScheduleGroup) {
                if (status === 'published') {
                    expireScheduleGroup.style.display = 'block';
                } else {
                    expireScheduleGroup.style.display = 'none';
                }
            }
            
            // Show reviewer field when status is "pending_review"
            if (reviewerGroup) {
                const reviewerSelect = document.getElementById('reviewer');
                const reviewerHidden = document.getElementById('reviewer_hidden');
                
                if (status === 'pending_review') {
                    reviewerGroup.style.display = 'block';
                    // When showing, sync the hidden input value to the select if select is empty
                    if (reviewerSelect && reviewerHidden) {
                        // If select is empty but hidden has value, try to set select from hidden
                        if (!reviewerSelect.value && reviewerHidden.value) {
                            // Try to find the option that matches the hidden value (could be ID or email)
                            const hiddenValue = reviewerHidden.value.toLowerCase().trim();
                            let found = false;
                            for (let option of reviewerSelect.options) {
                                const optionValue = option.value.toLowerCase().trim();
                                const optionText = option.text.toLowerCase();
                                // Match by value (ID) or by email in text
                                if (optionValue === hiddenValue || optionText.includes('(' + hiddenValue + ')')) {
                                    reviewerSelect.value = option.value;
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) {
                            }
                        } else if (reviewerSelect.value) {
                            // Select has value, sync to hidden
                            reviewerHidden.value = reviewerSelect.value;
                        }
                    }
                } else {
                    reviewerGroup.style.display = 'none';
                    // When hiding, sync the reviewer value to the hidden input to preserve it
                    if (reviewerSelect && reviewerHidden) {
                        // Always preserve the select value (user ID) in the hidden input
                        if (reviewerSelect.value) {
                            reviewerHidden.value = reviewerSelect.value;
                        } else if (reviewerHidden.value) {
                        }
                        // If select is empty but hidden has value, keep the hidden value
                    }
                }
            }
        }
        
        // Keep hidden reviewer input in sync with select field - DO THIS BEFORE toggleScheduleFields
        const reviewerSelect = document.getElementById('reviewer');
        const reviewerHidden = document.getElementById('reviewer_hidden');
        if (reviewerSelect && reviewerHidden) {
            // CRITICAL: Initialize hidden input with select value FIRST (before any toggling)
            // This preserves the reviewer value that was set by the PHP template
            if (reviewerSelect.value) {
                reviewerHidden.value = reviewerSelect.value;
            } else if (reviewerHidden.value) {
                // If select is empty but hidden has value, try to set select from hidden
                // This handles the case where the field was hidden when page loaded
                const hiddenValue = reviewerHidden.value.toLowerCase().trim();
                for (let option of reviewerSelect.options) {
                    const optionValue = option.value.toLowerCase().trim();
                    const optionText = option.text.toLowerCase();
                    // Match by value (ID) or by email in text
                    if (optionValue === hiddenValue || optionText.includes('(' + hiddenValue + ')')) {
                        reviewerSelect.value = option.value;
                        break;
                    }
                }
            }
            
            // Update hidden input when reviewer select changes
            reviewerSelect.addEventListener('change', function() {
                reviewerHidden.value = this.value || reviewerHidden.value;
            });
        }
        
        // Initial state - run AFTER initializing reviewer fields
        toggleScheduleFields();
        
        // Update on status change
        statusSelect.addEventListener('change', toggleScheduleFields);
        
        // Format datetime-local value from ISO string if needed
        function formatDateTimeForInput(isoString) {
            if (!isoString) return '';
            try {
                const dt = new Date(isoString);
                if (isNaN(dt.getTime())) return '';
                const year = dt.getFullYear();
                const month = String(dt.getMonth() + 1).padStart(2, '0');
                const day = String(dt.getDate()).padStart(2, '0');
                const hours = String(dt.getHours()).padStart(2, '0');
                const minutes = String(dt.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            } catch (e) {
                return '';
            }
        }
        
        // Try to get values from entry data (set via data attributes)
        const entryIdInput = document.querySelector('input[name="entry_id"]');
        if (entryIdInput && publishAtInput) {
            // Entry exists - get value from data attribute
            const publishedAtValue = publishAtInput.getAttribute('data-value') || '';
            if (publishedAtValue) {
                publishAtInput.value = formatDateTimeForInput(publishedAtValue);
            }
        }
        
        if (expiresAtInput) {
            const expiresAtValue = expiresAtInput.getAttribute('data-value') || '';
            if (expiresAtValue) {
                expiresAtInput.value = formatDateTimeForInput(expiresAtValue);
            }
        }
    }

    /**
     * Initialize content approval workflow
     */
    function initApprovalWorkflow() {
        const statusSelect = document.getElementById('status');
        const reviewerGroup = document.getElementById('reviewer-group');
        const reviewNotesGroup = document.getElementById('review-notes-group');
        const reviewActionGroup = document.getElementById('review-action-group');
        const approveBtn = document.getElementById('approve-entry');
        const rejectBtn = document.getElementById('reject-entry');
        const requestChangesBtn = document.getElementById('request-changes');
        
        if (!statusSelect) return;
        
        function toggleReviewFields() {
            const status = statusSelect.value;
            const isReviewStatus = ['pending_review', 'approved', 'rejected'].includes(status);
            
            if (reviewerGroup) {
                reviewerGroup.style.display = isReviewStatus ? 'block' : 'none';
            }
            if (reviewNotesGroup) {
                reviewNotesGroup.style.display = isReviewStatus ? 'block' : 'none';
            }
            if (reviewActionGroup) {
                reviewActionGroup.style.display = status === 'pending_review' ? 'block' : 'none';
            }
        }
        
        // Initial state
        toggleReviewFields();
        
        // Update on status change
        statusSelect.addEventListener('change', toggleReviewFields);
        
        // Load reviewers
        loadReviewers();
        
        // Setup review action buttons
        if (approveBtn) {
            approveBtn.addEventListener('click', async function () {
                submitReviewAction('approved');
            });
        }
        
        if (rejectBtn) {
            rejectBtn.addEventListener('click', async function () {
                showReviewModal('rejected', 'Reject Entry', 'Enter rejection reason:');
            });
        }
        
        if (requestChangesBtn) {
            requestChangesBtn.addEventListener('click', async function () {
                showReviewModal('draft', 'Request Changes', 'Enter change request details:');
            });
        }
    }
    
    function loadReviewers() {
        const reviewerSelect = document.getElementById('reviewer');
        const reviewerHidden = document.getElementById('reviewer_hidden');
        if (!reviewerSelect) return;
        
        // CRITICAL: Save the current reviewer value before clearing the select
        const currentReviewer = reviewerSelect.value || (reviewerHidden ? reviewerHidden.value : '');
        
        // Fetch users with admin or editor role
        fetch('/api/users.php?action=list_users')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data && data.data.users) {
                    reviewerSelect.innerHTML = '<option value="">Select a reviewer...</option>';
                    data.data.users.forEach(user => {
                        if (user.role === 'admin' || user.role === 'editor') {
                            const option = document.createElement('option');
                            // Use user ID as value, not email
                            option.value = user.id || user.email;
                            const displayName = (user.name || user.email) + (user.email && user.name !== user.email ? ' (' + user.email + ')' : '');
                            option.textContent = displayName;
                            reviewerSelect.appendChild(option);
                        }
                    });
                    
                    // Restore the saved reviewer value
                    if (currentReviewer) {
                        const normalizedCurrent = currentReviewer.toLowerCase().trim();
                        // Try to find matching option by ID or email
                        for (let option of reviewerSelect.options) {
                            const optionValue = option.value.toLowerCase().trim();
                            const optionText = option.text.toLowerCase();
                            if (optionValue === normalizedCurrent || optionText.includes('(' + normalizedCurrent + ')')) {
                                reviewerSelect.value = option.value;
                                // Also update hidden input
                                if (reviewerHidden) {
                                    reviewerHidden.value = option.value;
                                }
                                break;
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error loading reviewers:', error);
            });
    }
    
    function showReviewModal(action, title, label) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        modalContent.style.maxWidth = '500px';
        
        modalContent.innerHTML = `
            <div class="modal-header">
                <h2>${title}</h2>
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="review-notes-input">${label}</label>
                    <textarea id="review-notes-input" class="input" rows="4" placeholder="Enter notes..."></textarea>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button class="btn btn-primary" id="submit-review-btn">Submit</button>
                </div>
            </div>
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        const submitBtn = modalContent.querySelector('#submit-review-btn');
        const notesInput = modalContent.querySelector('#review-notes-input');
        
        submitBtn.addEventListener('click', async function () {
            const notes = notesInput.value.trim();
            modal.remove();
            submitReviewAction(action, notes);
        });
        
        // Close on backdrop click
        modal.addEventListener('click', async function (e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Focus textarea
        notesInput.focus();
    }
    
    function submitReviewAction(action, notes = '') {
        const form = document.getElementById('entry-form');
        if (!form) return;
        
        const entryId = document.querySelector('input[name="entry_id"]')?.value;
        const collectionSlug = form.querySelector('input[name="collection"]')?.value;
        const reviewer = document.getElementById('reviewer')?.value || '';
        
        if (!entryId || !collectionSlug) {
            BirklyFeedback.toast('Cannot submit review: missing entry or collection information', 'error')
            return;
        }
        
        const reviewData = {
            collection: collectionSlug,
            entry_id: entryId,
            action: action,
            reviewer: reviewer,
            notes: notes
        };
        
        fetch('/api/admin_entries.php?action=review', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(reviewData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                BirklyFeedback.toast('Review action submitted successfully. Reloading page...', 'success')
                window.location.reload();
            } else {
                BirklyFeedback.toast('Error submitting review: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Review error:', error);
            BirklyFeedback.toast('Error submitting review: ' + error.message, 'error')
        });
    }

    /**
     * Initialize version history viewer
     */
    function initVersionHistory() {
        const viewVersionsLink = document.getElementById('view-versions');
        if (!viewVersionsLink) return;
        
        viewVersionsLink.addEventListener('click', async function (e) {
            e.preventDefault();
            showVersionHistory();
        });
    }
    
    function showVersionHistory() {
        const form = document.getElementById('entry-form');
        if (!form) return;
        
        const entryId = document.querySelector('input[name="entry_id"]')?.value;
        const collectionSlug = form.querySelector('input[name="collection"]')?.value;
        
        if (!entryId || !collectionSlug) {
            BirklyFeedback.toast('Cannot view history: missing entry or collection information', 'error')
            return;
        }
        
        // Fetch version history
        fetch(`/api/admin_entries.php?action=versions&collection=${encodeURIComponent(collectionSlug)}&entry_id=${encodeURIComponent(entryId)}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.versions) {
                    renderVersionModal(data.versions, collectionSlug, entryId);
                } else {
                    BirklyFeedback.toast('Error loading version history: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Version history error:', error);
                BirklyFeedback.toast('Error loading version history: ' + error.message, 'error')
            });
    }
    
    function renderVersionModal(versions, collectionSlug, entryId) {
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        modalContent.style.maxWidth = '1200px';
        
        modalContent.innerHTML = `
            <div class="modal-header">
                <h2>Version History</h2>
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Compare Version 1:</label>
                        <select id="version1-select" class="form-control" style="width: 100%;">
                            <option value="">Select version...</option>
                            ${versions.map((version, index) => `
                                <option value="${version.version || 'current'}" ${index === 0 ? 'selected' : ''}>
                                    Version ${version.version || (versions.length - index)} ${index === 0 ? '(Current)' : ''}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Compare Version 2:</label>
                        <select id="version2-select" class="form-control" style="width: 100%;">
                            <option value="">Select version...</option>
                            ${versions.map((version, index) => `
                                <option value="${version.version || 'current'}" ${index === 1 ? 'selected' : ''}>
                                    Version ${version.version || (versions.length - index)} ${index === 0 ? '(Current)' : ''}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <button id="compare-versions-btn" class="btn btn-primary">Compare Versions</button>
                </div>
                <div id="diff-view" style="display: none;"></div>
                <div class="versions-list" id="versions-list">
                    <h3 style="margin-bottom: 12px;">All Versions</h3>
                    ${versions.map((version, index) => `
                        <div class="version-item" style="padding: 12px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong>Version ${version.version || (versions.length - index)}</strong>
                                    <div style="font-size: 0.875em; color: #666; margin-top: 4px;">
                                        ${new Date(version.created_at).toLocaleString()}
                                        ${version.author ? ` by ${version.author}` : ''}
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-sm" onclick="window.restoreVersion('${collectionSlug}', '${entryId}', '${version.version}')">
                                        Restore
                                    </button>
                                    ${index === 0 ? '<span style="margin-left: 8px; color: #0a0;">Current</span>' : ''}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        
        // Compare versions handler
        const compareBtn = modalContent.querySelector('#compare-versions-btn');
        const version1Select = modalContent.querySelector('#version1-select');
        const version2Select = modalContent.querySelector('#version2-select');
        const diffView = modalContent.querySelector('#diff-view');
        
        compareBtn.addEventListener('click', async function () {
            const v1 = version1Select.value;
            const v2 = version2Select.value;
            
            if (!v1 || !v2) {
                BirklyFeedback.toast('Please select both versions to compare', 'warning')
                return;
            }
            
            if (v1 === v2) {
                BirklyFeedback.toast('Please select two different versions', 'warning')
                return;
            }
            
            // Fetch diff
            fetch(`/api/admin_entries.php?action=diff&collection=${encodeURIComponent(collectionSlug)}&entry_id=${encodeURIComponent(entryId)}&version1=${encodeURIComponent(v1)}&version2=${encodeURIComponent(v2)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        renderDiffView(diffView, data.diff, data.version1, data.version2, v1, v2);
                        diffView.style.display = 'block';
                        document.getElementById('versions-list').style.display = 'none';
                    } else {
                        BirklyFeedback.toast('Error loading diff: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Diff error:', error);
                    BirklyFeedback.toast('Error loading diff: ' + error.message, 'error')
                });
        });
        
        // Close on backdrop click
        modal.addEventListener('click', async function (e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    function renderDiffView(container, diff, version1, version2, v1Label, v2Label) {
        if (diff.length === 0) {
            container.innerHTML = '<p style="padding: 20px; text-align: center; color: #666;">No differences found between these versions.</p>';
            return;
        }
        
        container.innerHTML = `
            <div style="margin-bottom: 20px;">
                <button class="btn btn-sm btn-secondary" onclick="document.getElementById('diff-view').style.display='none'; document.getElementById('versions-list').style.display='block';">Back to Versions</button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; margin-bottom: 20px;">
                <div style="background: #f5f5f5; padding: 12px; border-right: 1px solid #ddd;">
                    <h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;">Version ${v1Label}</h3>
                </div>
                <div style="background: #f5f5f5; padding: 12px;">
                    <h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600;">Version ${v2Label}</h3>
                </div>
            </div>
            <div style="max-height: 600px; overflow-y: auto;">
                ${diff.map(change => {
                    const oldValue = change.old || '<em style="color: #999;">(empty)</em>';
                    const newValue = change.new || '<em style="color: #999;">(empty)</em>';
                    let bgColor1 = '#fff';
                    let bgColor2 = '#fff';
                    let borderColor1 = '#ddd';
                    let borderColor2 = '#ddd';
                    
                    if (change.type === 'added') {
                        bgColor2 = '#e8f5e9';
                        borderColor2 = '#4caf50';
                    } else if (change.type === 'removed') {
                        bgColor1 = '#ffebee';
                        borderColor1 = '#f44336';
                    } else if (change.type === 'modified') {
                        bgColor1 = '#fff3e0';
                        bgColor2 = '#e3f2fd';
                        borderColor1 = '#ff9800';
                        borderColor2 = '#2196f3';
                    }
                    
                    return `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0; border-bottom: 1px solid #ddd;">
                            <div style="padding: 12px; background: ${bgColor1}; border-right: 1px solid ${borderColor1};">
                                <div style="font-size: 11px; color: #666; margin-bottom: 4px; font-weight: 600;">${change.path}</div>
                                <div style="white-space: pre-wrap; word-break: break-word; font-family: monospace; font-size: 12px;">${escapeHtml(oldValue)}</div>
                            </div>
                            <div style="padding: 12px; background: ${bgColor2}; border-left: 2px solid ${borderColor2};">
                                <div style="font-size: 11px; color: #666; margin-bottom: 4px; font-weight: 600;">${change.path}</div>
                                <div style="white-space: pre-wrap; word-break: break-word; font-family: monospace; font-size: 12px;">${escapeHtml(newValue)}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    window.restoreVersion = async function(collectionSlug, entryId, version) {
        if (!(await BirklyFeedback.confirm('Are you sure you want to restore this version? Current version will be saved as a new version.'))) {
            return;
        }
        
        fetch('/api/admin_entries.php?action=restore_version', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                collection: collectionSlug,
                entry_id: entryId,
                version: version
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                BirklyFeedback.toast('Version restored successfully. Reloading page...', 'success')
                window.location.reload();
            } else {
                BirklyFeedback.toast('Error restoring version: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Restore error:', error);
            BirklyFeedback.toast('Error restoring version: ' + error.message, 'error')
        });
    }

    /**
     * Sync ContentEditor fieldtypes before form submission
     */
    function syncContentEditorFields() {
        document.querySelectorAll('.content-editor-wrapper').forEach(function(wrapper) {
            if (window.ContentEditorSync && typeof window.ContentEditorSync.syncWrapperToHidden === 'function') {
                window.ContentEditorSync.syncWrapperToHidden(wrapper, true);
            }
        });
    }

    /**
     * Sync editor content to hidden input before form submission
     */
    function syncEditorContent(editorWrapper) {
        if (editorWrapper.querySelector('.content-editor-wrapper')) {
            return;
        }

        const hidden = editorWrapper.querySelector('input[type="hidden"][id$="_value"]');
        if (!hidden) return;

        // Determine current active mode
        const advanced = editorWrapper.querySelector('.ed-advanced');
        const wys = editorWrapper.querySelector('.ed-wysiwyg');
        const md = editorWrapper.querySelector('.ed-markdown');
        const ht = editorWrapper.querySelector('.ed-html');
        const block = editorWrapper.querySelector('.ed-block-surface');
        
        let activeMode = 'advanced'; // default
        if (advanced && (advanced.style.display === 'block' || advanced.style.display === '')) {
            activeMode = 'advanced';
        } else if (wys && wys.style.display === 'block') {
            activeMode = 'wysiwyg';
        } else if (md && md.style.display === 'block') {
            activeMode = 'markdown';
        } else if (ht && ht.style.display === 'block') {
            activeMode = 'html';
        } else if (block && block.style.display === 'block') {
            activeMode = 'block';
        }
        
        try {
            if (activeMode === 'advanced') {
                // Advanced mode: read directly from DOM (most reliable)
                // Advanced mode creates .advanced-content inside .ed-advanced
                const contentEl = advanced ? advanced.querySelector('.advanced-content') : null;
                if (contentEl && contentEl.innerHTML) {
                    hidden.value = contentEl.innerHTML;
                } else if (advanced && advanced.innerHTML) {
                    // Fallback: Advanced mode might store content directly in .ed-advanced
                    // But need to exclude toolbar
                    const toolbar = advanced.querySelector('.advanced-toolbar');
                    if (toolbar) {
                        // Clone advanced pane, remove toolbar, get innerHTML
                        const clone = advanced.cloneNode(true);
                        const cloneToolbar = clone.querySelector('.advanced-toolbar');
                        if (cloneToolbar) cloneToolbar.remove();
                        hidden.value = clone.innerHTML || '';
                    } else {
                        hidden.value = advanced.innerHTML || '';
                    }
                } else {
                    // Last resort: check container dataset
                    const containerValue = editorWrapper.dataset.value;
                    if (containerValue) {
                        hidden.value = containerValue;
                    }
                }
            } else if (activeMode === 'wysiwyg' && wys && wys.isContentEditable) {
                hidden.value = wys.innerHTML || '';
            } else if (activeMode === 'markdown' && md) {
                // Convert markdown to HTML
                const mdVal = md.value || '';
                hidden.value = mdVal
                    .replace(/^###\s+(.*)$/gm, '<h3>$1</h3>')
                    .replace(/^##\s+(.*)$/gm, '<h2>$1</h2>')
                    .replace(/^#\s+(.*)$/gm, '<h1>$1</h1>')
                    .replace(/^\*\s+(.*)$/gm, '<ul><li>$1</li></ul>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/`([^`]+)`/g, '<code>$1</code>');
            } else if (activeMode === 'html' && ht) {
                hidden.value = ht.value || '';
            } else {
                // Ultimate fallback: try to read from any visible surface
                const surface = advanced || wys || md || ht;
                if (surface) {
                    if (surface.isContentEditable || surface.tagName === 'DIV') {
                        hidden.value = surface.innerHTML || '';
                    } else {
                        hidden.value = surface.value || '';
                    }
                }
            }
        } catch (err) {
            console.error('Editor pre-submit sync error:', err);
            // Last resort fallback
            const surface = editorWrapper.querySelector('.ed-advanced') || 
                          editorWrapper.querySelector('.ed-wysiwyg') || 
                          editorWrapper.querySelector('.ed-html');
            if (surface && hidden) {
                hidden.value = surface.innerHTML || surface.value || '';
            }
        }
    }

    /**
     * Clear inline field validation state.
     */
    function clearFieldErrors() {
        document.querySelectorAll('.field-block--error, .field-block.error').forEach((block) => {
            block.classList.remove('field-block--error', 'error');
            const feedback = block.querySelector('.field-feedback.error');
            if (feedback) {
                feedback.textContent = '';
            }
            block.querySelectorAll('input, textarea, select').forEach((input) => {
                input.removeAttribute('aria-invalid');
                input.removeAttribute('aria-describedby');
            });
        });
    }

    /**
     * Apply API field_errors map to .field-block elements.
     * @param {Record<string, string>} fieldErrors
     * @returns {boolean} true if any field error was applied
     */
    function applyFieldErrors(fieldErrors) {
        if (!fieldErrors || typeof fieldErrors !== 'object') {
            return false;
        }

        let applied = false;
        let firstBlock = null;

        Object.keys(fieldErrors).forEach((fieldName) => {
            const message = fieldErrors[fieldName];
            if (!message) {
                return;
            }

            const block = document.querySelector(`.field-block[data-field-name="${CSS.escape(fieldName)}"]`)
                || document.querySelector(`#field_${CSS.escape(fieldName)}`)?.closest('.field-block')
                || document.querySelector(`[name="${CSS.escape(fieldName)}"]`)?.closest('.field-block');

            if (!block) {
                return;
            }

            block.classList.add('field-block--error');
            const feedback = block.querySelector('.field-feedback.error');
            if (feedback) {
                if (!feedback.id) {
                    feedback.id = 'field-error-' + fieldName;
                }
                feedback.textContent = message;
            }

            const input = block.querySelector('input, textarea, select');
            if (input) {
                input.setAttribute('aria-invalid', 'true');
                if (feedback && feedback.id) {
                    input.setAttribute('aria-describedby', feedback.id);
                }
            }

            if (!firstBlock) {
                firstBlock = block;
            }
            applied = true;
        });

        if (firstBlock) {
            firstBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return applied;
    }

    /**
     * Handle form submission
     */
    function initFormSubmission() {
        const form = document.getElementById('entry-form');
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (isSubmittingEntry) return;
            clearFieldErrors();
            isSubmittingEntry = true;
            setSaveSubmitting(true);
            
            // Sync all editor fields before submission
            syncContentEditorFields();
            document.querySelectorAll('.editor-field').forEach(syncEditorContent);

            // Let fieldtypes update their hidden inputs before save
            // Use a Promise to ensure all async updates complete
            const updateFieldtypes = new Promise((resolve) => {
            try {
                    const event = new CustomEvent('beforeSaveEntry');
                    document.dispatchEvent(event);
                    // Give fieldtypes a moment to update their hidden inputs
                    setTimeout(resolve, 100);
            } catch (err) {
                console.error('beforeSaveEntry event dispatch failed:', err);
                    resolve();
            }
            });

            // Wait for fieldtype updates before collecting form data
            await updateFieldtypes;

            const formData = new FormData(form);
            normalizeScheduleFieldsInFormData(formData);
            
            // Check if this is a sub-entry (creating or editing)
            const isSubEntry = form.querySelector('input[name="sub_collection"]') && form.querySelector('input[name="parent_entry_id"]');
            const actionInput = form.querySelector('input[name="action"]');
            const action = actionInput ? actionInput.value : (form.querySelector('input[name="sub_entry_id"]') ? 'update' : 'create');
            
            let fetchPromise;
            
            if (isSubEntry) {
                // Sub-entry create/update - use sub_entries API
                const parentCollection = formData.get('parent_collection') || formData.get('collection');
                const parentEntryId = formData.get('parent_entry_id');
                const subCollection = formData.get('sub_collection');
                const subEntryId = formData.get('sub_entry_id');
                const parentSubCollection = formData.get('parent_sub_collection');
                const parentSubEntryId = formData.get('parent_sub_entry_id');
                
                // Build data object from form (exclude meta fields)
                const data = {};
                for (const [key, value] of formData.entries()) {
                    // Skip meta fields
                    if (key === 'action' || key === 'collection' || key === 'parent_collection' || 
                        key === 'parent_entry_id' || key === 'sub_collection' || key === 'sub_entry_id' || 
                        key === 'parent_sub_collection' || key === 'parent_sub_entry_id' ||
                        key === '_csrf' || key.startsWith('_')) {
                        continue;
                    }
                    // Try to parse JSON values
                    try {
                        data[key] = JSON.parse(normalizeScheduleFieldValue(key, value));
                    } catch {
                        data[key] = normalizeScheduleFieldValue(key, value);
                    }
                }
                
                // Also collect from hidden inputs (for fieldtypes like Color)
                const hiddenInputs = form.querySelectorAll('input[type="hidden"]');
                hiddenInputs.forEach(input => {
                    if (input.name && !input.name.startsWith('_') && 
                        input.name !== 'action' && input.name !== 'collection' &&
                        input.name !== 'parent_collection' && input.name !== 'parent_entry_id' &&
                        input.name !== 'sub_collection' && input.name !== 'sub_entry_id' &&
                        input.name !== 'parent_sub_collection' && input.name !== 'parent_sub_entry_id') {
                        if (!data.hasOwnProperty(input.name)) {
                            try {
                                data[input.name] = JSON.parse(input.value);
                            } catch {
                                data[input.name] = input.value;
                            }
                        }
                    }
                });
                
                // Include parent sub-collection and sub-entry info for nested sub-entries
                // Check both formData and hidden inputs (in case formData didn't capture them)
                let finalParentSubCollection = parentSubCollection;
                let finalParentSubEntryId = parentSubEntryId;
                
                if (!finalParentSubCollection || !finalParentSubEntryId) {
                    const hiddenParentSubCollection = document.getElementById('hidden-parent-sub-collection');
                    const hiddenParentSubEntryId = document.getElementById('hidden-parent-sub-entry-id');
                    if (hiddenParentSubCollection && hiddenParentSubCollection.value) {
                        finalParentSubCollection = hiddenParentSubCollection.value;
                    }
                    if (hiddenParentSubEntryId && hiddenParentSubEntryId.value) {
                        finalParentSubEntryId = hiddenParentSubEntryId.value;
                    }
                }
                
                if (finalParentSubCollection && finalParentSubEntryId) {
                    data['parent_sub_collection'] = finalParentSubCollection;
                    data['parent_sub_entry_id'] = finalParentSubEntryId;
                }
                
                const requestBody = {
                    action: action,
                    parent_collection: parentCollection,
                    parent_entry_id: parentEntryId,
                    sub_collection: subCollection,
                    data: data,
                    _csrf: formData.get('_csrf')
                };
                
                // Only include sub_entry_id if updating (not creating)
                if (action === 'update' && subEntryId) {
                    requestBody.sub_entry_id = subEntryId;
                }
                
                fetchPromise = fetch('/api/sub_entries.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestBody)
                });
            } else {
                // Regular entry save
                // Build FormData explicitly to ensure all fields are included
                const finalFormData = new FormData();
                
                // Collect all fields in two passes:
                // 1. First pass: collect regular inputs from editor-main (not sidebar)
                // 2. Second pass: collect hidden inputs (which may override regular inputs)
                // This ensures fieldtype-updated hidden inputs (like tags, multiselect) take precedence
                
                const fieldMap = new Map(); // Use Map to track the latest value for each field
                
                // First pass: collect all non-hidden inputs from the main editor area AND sidebar
                const editorMain = form.querySelector('.editor-main');
                const editorSidebar = form.querySelector('.editor-sidebar');
                // Search in both main and sidebar, or whole form if neither exists
                const searchScopes = [];
                if (editorMain) searchScopes.push(editorMain);
                if (editorSidebar) searchScopes.push(editorSidebar);
                const searchScope = searchScopes.length > 0 ? searchScopes : [form];
                const regularInputs = [];
                searchScope.forEach(scope => {
                    regularInputs.push(...scope.querySelectorAll('input:not([type="hidden"]), select, textarea'));
                });
                
                regularInputs.forEach(input => {
                    const name = input.name;
                    if (!name) return;
                    
                    // Skip system fields
                    if (name === 'action' || name === '_csrf' || name.startsWith('_')) {
                        return;
                    }
                    
                    // Handle different input types
                    if (input.type === 'checkbox') {
                        fieldMap.set(name, input.checked ? (input.value || '1') : '');
                    } else if (input.type === 'radio') {
                        if (input.checked) {
                            fieldMap.set(name, input.value || '');
                        }
                    } else if (input.type === 'file') {
                        if (input.files && input.files.length > 0) {
                            // Files need special handling - store the input itself
                            fieldMap.set(name, input);
                        }
                    } else {
                        // For text, email, date, select, textarea, etc.
                        const value = normalizeScheduleFieldValue(name, input.value || '');
                        
                        // Only set if we don't already have a non-empty value for this field
                        // This prevents empty inputs from overwriting filled ones
                        if (!fieldMap.has(name) || fieldMap.get(name) === '') {
                            fieldMap.set(name, value);
                        } else if (value !== '') {
                            // If we already have a value but this one is also non-empty, use the last one
                            fieldMap.set(name, value);
                        } else {
                        }
                    }
                });
                
                // Second pass: collect hidden inputs from the entire form (fieldtypes use hidden inputs)
                // BUT: only if the hidden input has a non-empty value, or if no regular input exists
                const hiddenInputs = form.querySelectorAll('input[type="hidden"]');
                
                hiddenInputs.forEach(input => {
                    const name = input.name;
                    if (!name) return;
                    
                    // Skip system fields
                    if (name === 'action' || name === 'collection' || name === 'entry_id' || 
                        name === '_csrf' || name.startsWith('_')) {
                        return;
                    }
                    
                    const value = input.value || '';
                    
                    // Only use hidden input if:
                    // 1. It has a non-empty value, OR
                    // 2. No regular input with this name exists yet
                    const hasRegularInput = fieldMap.has(name);
                    const isEmpty = value === '';
                    
                    if (!isEmpty || !hasRegularInput) {
                        fieldMap.set(name, value);
                    } else {
                    }
                });
                
                // Now build FormData from the map
                for (const [name, value] of fieldMap.entries()) {
                    if (value && value.files) {
                        // Handle file inputs
                        for (let i = 0; i < value.files.length; i++) {
                            finalFormData.append(name, value.files[i]);
                        }
                    } else {
                        finalFormData.append(name, value);
                    }
                }
                
                // Add collection and entry_id if they exist
                const collection = formData.get('collection');
                const entryId = formData.get('entry_id');
                if (collection) finalFormData.append('collection', collection);
                if (entryId) finalFormData.append('entry_id', entryId);
                
                // Add CSRF token
                const csrf = formData.get('_csrf');
                if (csrf) finalFormData.append('_csrf', csrf);
                
                // Add status and slug from sidebar (these might not be in the main form inputs)
                // Only add if they weren't already collected from form inputs
                const status = formData.get('status');
                const slug = formData.get('slug');
                const autoSlug = formData.get('auto_slug');
                const publishedAt = formData.get('published_at');
                const expiresAt = formData.get('expires_at');
                if (status && !fieldMap.has('status')) finalFormData.append('status', status);
                if (slug !== null && !fieldMap.has('slug')) finalFormData.append('slug', slug || '');
                if (autoSlug !== null && !fieldMap.has('auto_slug')) finalFormData.append('auto_slug', autoSlug || '0');
                if (publishedAt && !fieldMap.has('published_at')) finalFormData.append('published_at', publishedAt);
                if (expiresAt && !fieldMap.has('expires_at')) finalFormData.append('expires_at', expiresAt);
                
                // Always include reviewer field if it exists in the form (even if hidden or empty)
                // This ensures the server can preserve the existing reviewer when the field is hidden
                const reviewerField = form.querySelector('select[name="reviewer"]');
                const reviewerHidden = form.querySelector('input[name="reviewer_hidden"]');
                
                // Sync: Update hidden input with select value before submission
                // Always prefer the select value (user ID) over hidden value (which might be email)
                if (reviewerField && reviewerHidden) {
                    if (reviewerField.value) {
                        // Select has a value (user ID) - use it
                        reviewerHidden.value = reviewerField.value;
                    }
                    // If select is empty, keep the hidden value (preserves existing reviewer when field is hidden)
                }
                
                // Use reviewer field value if available and not empty, otherwise use hidden input value
                // Always prefer select value (user ID) over hidden value
                let reviewerValue = '';
                if (reviewerField && reviewerField.value) {
                    reviewerValue = reviewerField.value; // This is always a user ID
                } else if (reviewerHidden && reviewerHidden.value) {
                    reviewerValue = reviewerHidden.value; // This might be ID or email, but server will handle it
                }
                
                // Always include reviewer in form submission (even if empty, so server knows to preserve)
                if (!fieldMap.has('reviewer')) {
                    finalFormData.append('reviewer', reviewerValue);
                } else {
                    // Update if already in map (from regular input collection)
                    finalFormData.set('reviewer', reviewerValue);
                }
                
                // Always include reviewer_hidden for server-side preservation logic
                if (reviewerHidden && !fieldMap.has('reviewer_hidden')) {
                    finalFormData.append('reviewer_hidden', reviewerHidden.value || '');
                }
                
                
                // Debug: Log all form data being sent
                for (const [key, value] of finalFormData.entries()) {
                }
                
                // Call the API explicitly with action=save to avoid server-side routing ambiguity
                fetchPromise = fetch('/api/admin_entries.php?action=save', {
                    method: 'POST',
                    body: finalFormData
                });
            }
            
            fetchPromise
            .then(async (response) => {
                const raw = await response.text();
                let data;
                try { data = JSON.parse(raw); } catch(_e) { data = null; }
                
                // Check JSON body first - it's the source of truth
                if (data && data.status === 'ok') {
                    // Success! Even if HTTP status code is 400 (server bug)
                    const savedEntryId = data.data && data.data.id ? data.data.id : (formData.get('entry_id') || '');
                    const hadEntryIdBeforeSave = !!(formData.get('entry_id') || form.querySelector('input[name="entry_id"]'));
                    const isNewRegularEntry = !isSubEntry && !hadEntryIdBeforeSave && !!savedEntryId;

                    if (savedEntryId) {
                        ensureEntryIdInput(form, savedEntryId);
                    }

                    if (isSubEntry) {
                        // For sub-entries, redirect back to parent entry detail page
                        const parentCollection = formData.get('parent_collection') || formData.get('collection');
                        const parentEntryId = formData.get('parent_entry_id');
                        const parentSubCollection = formData.get('parent_sub_collection');
                        const parentSubEntryId = formData.get('parent_sub_entry_id');
                        
                        // Build redirect URL - if nested sub-entry, include parent sub-entry info
                        const detailParams = {
                            collection: parentCollection,
                            entry: parentEntryId,
                        };
                        if (parentSubCollection && parentSubEntryId) {
                            detailParams.sub_collection = parentSubCollection;
                            detailParams.sub_entry = parentSubEntryId;
                        }
                        let redirectUrl = Birkly.adminUrl('entry-detail', detailParams);
                        redirectUrl += `&_t=${Date.now()}`;
                        window.location.href = redirectUrl;
                    } else {
                        if (isNewRegularEntry) {
                            updateEntryEditorUrl(savedEntryId);
                        }

                        document.dispatchEvent(new CustomEvent('entry:saved', {
                            detail: {
                                collection: formData.get('collection') || config.collectionSlug || '',
                                entryId: savedEntryId,
                                isNewEntry: isNewRegularEntry,
                                response: data
                            }
                        }));

                        hideUnsavedBadge();
                        flashEntrySavedState();

                        const savedStatus = getEntryStatusFromSidebar();
                        if (isNewRegularEntry || isUnpublishedStatus(savedStatus)) {
                            openEntrySaveSuccessModal(isNewRegularEntry, savedEntryId, savedStatus);
                        } else {
                            showEntryUpdateBanner(
                                (data.data && data.data.message) ||
                                saveLabel('updateMessage', 'Your changes were saved successfully.')
                            );
                        }
                    }
                } else if (data && data.status === 'error') {
                    if (!applyFieldErrors(data.field_errors)) {
                        BirklyFeedback.toast('Error: ' + (data.message || raw || 'Unknown error'), 'error');
                    }
                } else if (!response.ok) {
                    // HTTP error but no valid JSON
                    throw new Error((data && data.message) || (raw || ('HTTP ' + response.status + ' ' + response.statusText)));
                } else {
                    // Unexpected response format
                    BirklyFeedback.toast('Error: Unexpected response format', 'error')
                }
            })
            .catch(error => {
                console.error('Error saving entry:', error);
                BirklyFeedback.toast('Error saving entry: ' + error.message, 'error')
            })
            .finally(function () {
                isSubmittingEntry = false;
                setSaveSubmitting(false);
            });
        });
    }

    /**
     * Prevent Enter key in tags fieldtype from submitting form
     */
    function initTagsEnterKeyPrevention() {
        document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Enter' && ev.target && ev.target.closest && ev.target.closest('.tags-field-container input')) {
                ev.preventDefault();
                // Allow event to bubble to tags fieldtype handler
            }
        }, true);
    }

    /**
     * Initialize fieldtypes
     */
    function initFieldtypes() {
        // Trigger initialization for each fieldtype
        const fieldInputs = document.querySelectorAll('[data-fieldtype]');
        fieldInputs.forEach(function(input) {
            const fieldtype = input.getAttribute('data-fieldtype');
            const fieldName = input.name || input.id;
            
            // Skip generic dispatch for complex fieldtypes; they have explicit init below
            if (['editor', 'media', 'relation', 'dynamicitems', 'tags'].includes(fieldtype)) {
                return;
            }
            
            // Dispatch custom event for fieldtype initialization
            const initEvent = new CustomEvent('fieldtype:init', {
                detail: {
                    fieldtype: fieldtype,
                    fieldName: fieldName,
                    element: input
                }
            });
            document.dispatchEvent(initEvent);
            
            // Also try calling init function if it exists
            const initFnName = 'init' + fieldtype.charAt(0).toUpperCase() + fieldtype.slice(1) + 'Field';
            if (typeof window[initFnName] === 'function') {
                window[initFnName](input);
            }
        });

        // Initialize legacy multi-mode editor fieldtypes (not ContentEditor)
        document.querySelectorAll('.editor-field').forEach(function(wrap) {
            if (wrap.querySelector('.content-editor-wrapper')) {
                return;
            }
            if (!wrap.querySelector('.ed-advanced') && !wrap.querySelector('.ed-wysiwyg') && !wrap.querySelector('.ed-html')) {
                return;
            }

            const evt = new CustomEvent('fieldtype:init', { 
                detail: { fieldtype: 'editor', element: wrap } 
            });
            document.dispatchEvent(evt);
            
            // Post-init value restore for legacy Advanced mode surfaces
            const hidden = wrap.querySelector('input[type="hidden"][id$="_value"]');
            const adv = wrap.querySelector('.ed-advanced');
            const isAdvancedActive = adv && (adv.style.display === 'block' || adv.style.display === '');
            const initialRaw = wrap.getAttribute('data-initial-value') || '';
            
            if (hidden && !hidden.value && initialRaw) {
                const ta0 = document.createElement('textarea');
                ta0.innerHTML = initialRaw;
                hidden.value = ta0.value || '';
            }

            if (hidden && isAdvancedActive && (hidden.value || initialRaw)) {
                const decode = function(s) {
                    const ta = document.createElement('textarea');
                    ta.innerHTML = s || '';
                    return ta.value;
                };
                const val = decode(hidden.value || initialRaw || '');
                
                const contentEl = wrap.querySelector('.ed-advanced .advanced-content');
                if (contentEl) {
                    contentEl.innerHTML = val;
                } else if (adv) {
                    setTimeout(function() {
                        const contentEl2 = wrap.querySelector('.ed-advanced .advanced-content');
                        if (contentEl2) {
                            contentEl2.innerHTML = val;
                        }
                    }, 100);
                }
            }
            
            // Set up continuous sync for Advanced mode to keep hidden input updated
            // This ensures content is always synced, not just on form submit
            const setupContinuousSync = function() {
                const advContentEl = wrap.querySelector('.ed-advanced .advanced-content');
                if (!advContentEl) {
                    // Also try finding it directly in Advanced pane
                    const advPane = wrap.querySelector('.ed-advanced');
                    if (advPane) {
                        // Advanced mode might create content directly in .ed-advanced
                        const contentEl = advPane.querySelector('.advanced-content') || advPane;
                        if (contentEl && hidden) {
                            // Use MutationObserver to watch for content changes
                            const observer = new MutationObserver(function() {
                                const content = contentEl.innerHTML || '';
                                if (hidden.value !== content) {
                                    hidden.value = content;
                                }
                            });
                            
                            observer.observe(contentEl, {
                                childList: true,
                                subtree: true,
                                characterData: true
                            });
                            
                            // Also listen to input events as a backup
                            contentEl.addEventListener('input', function() {
                                hidden.value = contentEl.innerHTML || '';
                            });
                            
                            // Also listen to keyup for immediate feedback
                            contentEl.addEventListener('keyup', function() {
                                hidden.value = contentEl.innerHTML || '';
                            });
                            
                            // Store observer on wrapper for cleanup if needed
                            wrap._editorSyncObserver = observer;
                            return true;
                        }
                    }
                    return false;
                } else if (hidden) {
                    // Use MutationObserver to watch for content changes
                    const observer = new MutationObserver(function() {
                        const content = advContentEl.innerHTML || '';
                        if (hidden.value !== content) {
                            hidden.value = content;
                        }
                    });
                    
                    observer.observe(advContentEl, {
                        childList: true,
                        subtree: true,
                        characterData: true
                    });
                    
                    // Also listen to input events as a backup
                    advContentEl.addEventListener('input', function() {
                        hidden.value = advContentEl.innerHTML || '';
                    });
                    
                    // Also listen to keyup for immediate feedback
                    advContentEl.addEventListener('keyup', function() {
                        hidden.value = advContentEl.innerHTML || '';
                    });
                    
                    // Store observer on wrapper for cleanup if needed
                    wrap._editorSyncObserver = observer;
                    return true;
                }
                return false;
            };
            
            // Wait for Advanced content element to exist, then set up sync
            let syncAttempts = 0;
            const syncInterval = setInterval(function() {
                syncAttempts++;
                if (setupContinuousSync()) {
                    clearInterval(syncInterval);
                } else if (syncAttempts > 50) { // 5 seconds max wait
                    clearInterval(syncInterval);
                }
            }, 100);

            // As a final safety net, periodically mirror Advanced HTML into hidden input
            // This covers cases where Advanced re-renders and detaches observers
            const safetyMirror = setInterval(function(){
                if (!hidden) return;
                const advPane = wrap.querySelector('.ed-advanced');
                if (!advPane) return;
                const contentEl = advPane.querySelector('.advanced-content') || advPane;
                if (!contentEl) return;
                const html = contentEl.innerHTML || '';
                if (hidden.value !== html) hidden.value = html;
            }, 300);
            // Store for potential cleanup (not strictly necessary in MPA)
            wrap._editorSafetyMirror = safetyMirror;
        });

        // Initialize Media fieldtypes
        document.querySelectorAll('.media-field').forEach(function(wrap) {
            const name = wrap.id.replace('field_', '');
            if (typeof window.initMediaField === 'function') {
                window.initMediaField(name);
            }
            const evt = new CustomEvent('fieldtype:init', { 
                detail: { fieldtype: 'media', fieldName: name, element: wrap } 
            });
            document.dispatchEvent(evt);
        });

        // Initialize Relation fieldtypes
        document.querySelectorAll('.relation-field').forEach(function(wrap) {
            const name = (wrap.id || '').replace('relation_field_', '');
            if (typeof window.initRelationField === 'function') {
                window.initRelationField(name);
            }
            const evt = new CustomEvent('fieldtype:init', { 
                detail: { fieldtype: 'relation', fieldName: name, element: wrap } 
            });
            document.dispatchEvent(evt);
        });

        // Initialize Tags fieldtypes
        document.querySelectorAll('.tags-field-container').forEach(function(wrap) {
            const evt = new CustomEvent('fieldtype:init', { 
                detail: { fieldtype: 'tags', element: wrap } 
            });
            document.dispatchEvent(evt);
            if (window.HaruFieldtypes && window.HaruFieldtypes['tags'] && typeof window.HaruFieldtypes['tags'].init === 'function') {
                window.HaruFieldtypes['tags'].init(wrap);
            }
        });

        // Initialize DynamicItems (if provided by fieldtype bundle)
        if (typeof window.initializeAllDynamicItemsFields === 'function') {
            try {
                window.initializeAllDynamicItemsFields();
            } catch (err) {
                console.error('DynamicItems init failed:', err);
            }
        }
    }

    /**
     * Initialize language tabs (one entry, nested lang fields).
     */
    function initLanguageTabs() {
        const languageTabBtns = document.querySelectorAll('.language-tab-btn');
        const languageTabContents = document.querySelectorAll('.language-tab-content');
        
        languageTabBtns.forEach(btn => {
            btn.addEventListener('click', async function () {
                const langCode = this.dataset.lang;
                
                // Update buttons
                languageTabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update content
                languageTabContents.forEach(content => {
                    if (content.dataset.lang === langCode) {
                        content.style.display = 'block';
                        content.classList.add('active');
                    } else {
                        content.style.display = 'none';
                        content.classList.remove('active');
                    }
                });
            });
        });
    }

    /**
     * Main initialization
     */
    function init() {
        bindEntrySaveSuccessModal();
        initSlugGeneration();
        initFormSubmission();
        initTagsEnterKeyPrevention();
        initFieldtypes();
        initScheduledPublishing();
        initVersionHistory();
        initApprovalWorkflow();
        initLanguageTabs();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

})();

