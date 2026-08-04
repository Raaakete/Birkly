/**
 * Entry editor sidebar — permalink, meta previews, SEO/AI helpers, validation.
 */
(function () {
    'use strict';

    const configEl = document.getElementById('entry-editor-sidebar-config');
    if (!configEl) return;

    let config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (e) {
        config = {};
    }

    const form = document.getElementById('entry-form');
    let formDirty = false;
    let initialSnapshot = '';

    function $(id) {
        return document.getElementById(id);
    }

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function getTitleValue() {
        return (
            $('field-title')?.value ||
            $('field_title')?.value ||
            document.querySelector('input[name="title"]')?.value ||
            ''
        ).trim();
    }

    function getSlugValue() {
        return ($('slug')?.value || '').trim();
    }

    function buildPermalink() {
        const slug = getSlugValue() || 'your-slug';
        const collection = config.collection_slug || 'collection';
        const siteUrl = (config.site_url || '').replace(/\/$/, '');
        const path = '/' + collection + '/' + slug;
        return siteUrl ? siteUrl + path : path;
    }

    function updatePermalinkPreview() {
        const code = $('permalink-preview-url');
        if (code) {
            code.textContent = buildPermalink();
        }
    }

    const statusMessages = {
        unpublished: 'Hidden from your live site and public API. Only visible in the admin.',
        draft: 'Hidden from your live site and public API. Only visible in the admin.',
        published: 'Visible on your site and included in the public API.',
        scheduled: 'Will publish automatically at the date you set. Hidden until then.',
        pending_review: 'Waiting for reviewer approval. Not visible on the live site.',
    };

    function updateStatusClarity() {
        const el = $('status-clarity-text');
        const status = $('status')?.value || 'unpublished';
        if (el) {
            el.textContent = statusMessages[status] || statusMessages.unpublished;
        }
    }

    function counterState(len, ideal, max) {
        if (len > max) return 'is-over';
        if (len > ideal) return 'is-warn';
        return '';
    }

    function bindCharCounter(inputId, counterId, ideal, max) {
        const input = $(inputId);
        const counter = $(counterId);
        if (!input || !counter) return;

        function refresh() {
            const len = (input.value || '').length;
            counter.textContent = len + ' / ' + ideal + ' (max ' + max + ')';
            counter.className = 'char-counter ' + counterState(len, ideal, max);
        }
        input.addEventListener('input', refresh);
        refresh();
    }

    function effectiveMetaTitle() {
        return ($('meta_title')?.value || '').trim() || getTitleValue() || 'Page title';
    }

    function effectiveMetaDescription() {
        return ($('meta_description')?.value || '').trim() || 'Add a meta description to improve search and AI summaries.';
    }

    function effectiveOgTitle() {
        return ($('og_title')?.value || '').trim() || ($('meta_title')?.value || '').trim() || getTitleValue() || 'Page title';
    }

    function effectiveOgDescription() {
        return ($('og_description')?.value || '').trim() || ($('meta_description')?.value || '').trim() || effectiveMetaDescription();
    }

    function updateMetaPreviews() {
        const serpTitle = $('serp-preview-title');
        const serpUrl = $('serp-preview-url');
        const serpDesc = $('serp-preview-desc');
        if (serpTitle) serpTitle.textContent = effectiveMetaTitle();
        if (serpUrl) serpUrl.textContent = buildPermalink();
        if (serpDesc) serpDesc.textContent = effectiveMetaDescription();

        const socialTitle = $('social-preview-title');
        const socialDesc = $('social-preview-desc');
        const socialImage = $('social-preview-image');
        if (socialTitle) socialTitle.textContent = effectiveOgTitle();
        if (socialDesc) socialDesc.textContent = effectiveOgDescription();
        if (socialImage) {
            const img = ($('og_image')?.value || '').trim();
            if (img) {
                socialImage.style.backgroundImage = 'url(' + img + ')';
                socialImage.hidden = false;
            } else {
                socialImage.style.backgroundImage = '';
                socialImage.hidden = true;
            }
        }
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function bindMetaPreviewInputs() {
        ['meta_title', 'meta_description', 'og_title', 'og_description', 'og_image', 'slug'].forEach(function (id) {
            const el = $(id);
            if (el) {
                el.addEventListener('input', function () {
                    updateMetaPreviews();
                    updatePermalinkPreview();
                });
            }
        });
        const titleField = document.querySelector('input[name="title"], #field-title, #field_title');
        if (titleField) {
            titleField.addEventListener('input', function () {
                updateMetaPreviews();
            });
        }
    }

    function initOgImagePicker() {
        const pickBtn = $('og-image-pick-btn');
        const input = $('og_image');
        if (!pickBtn || !input) return;
        pickBtn.addEventListener('click', async function () {
            if (window.Birkly && window.Birkly.MediaModal) {
                window.Birkly.MediaModal.open(function (media) {
                    const url = media?.url || media?.path || '';
                    if (url) {
                        input.value = url;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }
        });
    }

    function generateStructuredData() {
        const title = effectiveMetaTitle();
        const description = effectiveMetaDescription();
        const url = buildPermalink();
        const image = ($('og_image')?.value || '').trim();
        const payload = {
            '@context': 'https://schema.org',
            '@type': 'Article',
            headline: title,
            description: description,
            mainEntityOfPage: { '@type': 'WebPage', '@id': url },
        };
        if (image) {
            payload.image = [image];
        }
        const published = document.querySelector('[name="published_at"]')?.value;
        if (published) {
            payload.datePublished = published;
        }
        return JSON.stringify(payload, null, 2);
    }

    function initStructuredDataGenerator() {
        const btn = $('generate-structured-data-btn');
        const textarea = $('structured_data_json');
        if (!btn || !textarea) return;
        btn.addEventListener('click', async function () {
            textarea.value = generateStructuredData();
            markDirty();
        });
    }

    function copyText(text) {
        return navigator.clipboard.writeText(text);
    }

    function initCopyButtons() {
        document.querySelectorAll('.copy-btn').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const copyTextAttr = this.getAttribute('data-copy');
                let text = copyTextAttr || '';
                if (!text) {
                    const code = this.closest('.templating-section, .templating-meta-snippet')?.querySelector('code, pre');
                    text = code ? (code.textContent || '') : '';
                }
                if (!text) return;
                copyText(text).then(function () {
                    const original = btn.innerHTML;
                    btn.innerHTML = '✓';
                    setTimeout(function () { btn.innerHTML = original; }, 1500);
                });
            });
        });

        const copyAll = $('copy-all-bindings-btn');
        if (copyAll) {
            copyAll.addEventListener('click', async function () {
                const lines = ['{{ entry.title }}', '{{ entry.slug }}', '{{ entry.meta_title }}', '{{ entry.meta_description }}'];
                document.querySelectorAll('.field-slug-value code').forEach(function (code) {
                    const slug = (code.textContent || '').trim();
                    if (slug) {
                        lines.push('{{ entry.' + slug.replace(/[{}]/g, '') + ' }}');
                    }
                });
                copyText(lines.join('\n'));
            });
        }
    }

    function validateSidebar() {
        const issues = [];
        const slug = getSlugValue();
        if (slug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
            issues.push('Slug should use lowercase letters, numbers, and hyphens only.');
        }
        const metaTitle = ($('meta_title')?.value || '').length;
        if (metaTitle > 70) {
            issues.push('Meta title is longer than recommended (70 characters).');
        }
        const metaDesc = ($('meta_description')?.value || '').length;
        if (metaDesc > 0 && metaDesc < 50) {
            issues.push('Meta description is quite short — aim for 120–160 characters for SEO and AI summaries.');
        }
        const scheduled = $('status')?.value === 'scheduled';
        const publishAt = $('published_at')?.value;
        if (scheduled && !publishAt) {
            issues.push('Scheduled entries need a publish date.');
        }

        const box = $('sidebar-validation');
        if (!box) return issues;
        if (issues.length === 0) {
            box.innerHTML = '';
            box.hidden = true;
            return issues;
        }
        box.hidden = false;
        box.innerHTML = issues.map(function (msg) {
            return '<div class="sidebar-validation-item">' + msg + '</div>';
        }).join('');
        return issues;
    }

    function formSnapshot() {
        if (!form) return '';
        return new URLSearchParams(new FormData(form)).toString();
    }

    function markDirty() {
        formDirty = true;
        const badge = $('editor-unsaved-badge');
        if (badge) badge.classList.add('is-visible');
    }

    function resetDirtyState() {
        formDirty = false;
        initialSnapshot = formSnapshot();
        const badge = $('editor-unsaved-badge');
        if (badge) {
            badge.classList.remove('is-visible');
        }
    }

    function initDirtyTracking() {
        if (!form) return;
        initialSnapshot = formSnapshot();
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        document.addEventListener('entry:saved', function (event) {
            const entryId = event.detail && event.detail.entryId ? event.detail.entryId : '';
            if (entryId) {
                config.entry_id = entryId;
            }
            resetDirtyState();
        });
        window.addEventListener('beforeunload', function (e) {
            if (formDirty && formSnapshot() !== initialSnapshot) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    function setRevisionFeedback(message, tone) {
        const feedback = $('entry-revisions-feedback');
        if (!feedback) return;
        if (!message) {
            feedback.textContent = '';
            feedback.className = 'entry-revisions-feedback';
            feedback.hidden = true;
            return;
        }
        feedback.textContent = message;
        feedback.className = 'entry-revisions-feedback is-' + (tone || 'success');
        feedback.hidden = false;
    }

    function formatRevisionDate(value) {
        if (!value) return 'Unknown date';
        const date = new Date(value.replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }

    function renderRevisionHistory(versions) {
        const list = $('entry-revisions-list');
        if (!list) return;

        if (!Array.isArray(versions) || versions.length === 0) {
            list.innerHTML = '<div class="entry-revisions-empty">No saved revisions yet.</div>';
            return;
        }

        list.innerHTML = versions.map(function (version) {
            const number = Number(version.version || 0);
            const isCurrent = version.author === 'Current';
            const author = isCurrent ? 'Current version' : (version.author || 'Unknown author');
            const subtitle = author + ' · ' + formatRevisionDate(version.created_at || version.updated_at || '');
            return '<div class="entry-revision-item' + (isCurrent ? ' is-current' : '') + '">' +
                '<div class="entry-revision-meta">' +
                    '<div class="entry-revision-title">Version ' + number + (isCurrent ? ' · Current' : '') + '</div>' +
                    '<div class="entry-revision-subtitle">' + subtitle + '</div>' +
                '</div>' +
                '<div class="entry-revision-actions">' +
                    (isCurrent
                        ? '<span class="status-badge published" style="white-space:nowrap;">Current</span>'
                        : '<button type="button" class="btn btn-secondary btn-sm restore-entry-version-btn" data-version="' + number + '">Restore</button>') +
                '</div>' +
            '</div>';
        }).join('');
    }

    async function loadRevisionHistory() {
        const collectionSlug = config.collection_slug || '';
        const entryId = config.entry_id || '';
        const list = $('entry-revisions-list');
        if (!list || !collectionSlug || !entryId) return;

        list.innerHTML = '<div class="entry-revisions-empty">Loading revisions…</div>';
        setRevisionFeedback('');

        try {
            const response = await fetch(apiUrl('/api/admin_entries.php?action=list_versions&collection=' + encodeURIComponent(collectionSlug) + '&entry_id=' + encodeURIComponent(entryId)), {
                credentials: 'same-origin'
            });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Failed to load revisions');
            }
            renderRevisionHistory(payload.versions || []);
        } catch (error) {
            list.innerHTML = '<div class="entry-revisions-empty">Unable to load revisions.</div>';
            setRevisionFeedback(error.message || 'Unable to load revisions', 'error');
        }
    }

    async function restoreRevision(version) {
        const collectionSlug = config.collection_slug || '';
        const entryId = config.entry_id || '';
        if (!collectionSlug || !entryId || !version) return;

        if (!(await BirklyFeedback.confirm('Restore version ' + version + '? Your current draft will be saved to history first.'))) {
            return;
        }

        setRevisionFeedback('Restoring version ' + version + '…');

        try {
            const response = await fetch(apiUrl('/api/admin_entries.php?action=restore_version'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    collection: collectionSlug,
                    entry_id: entryId,
                    version: version,
                    _csrf: getCsrfToken()
                })
            });
            const payload = await response.json();
            if (!response.ok || payload.status !== 'ok') {
                throw new Error(payload.message || 'Failed to restore revision');
            }
            setRevisionFeedback('Revision restored. Reloading…', 'success');
            window.setTimeout(function () {
                window.location.reload();
            }, 500);
        } catch (error) {
            setRevisionFeedback(error.message || 'Failed to restore revision', 'error');
        }
    }

    function initRevisionHistory() {
        const list = $('entry-revisions-list');
        if (!list) return;

        $('refresh-entry-revisions-btn')?.addEventListener('click', async function () {
            loadRevisionHistory();
        });

        list.addEventListener('click', async function (event) {
            const button = event.target.closest('.restore-entry-version-btn');
            if (!button) return;
            restoreRevision(button.getAttribute('data-version') || '');
        });

        loadRevisionHistory();
    }

    function init() {
        updatePermalinkPreview();
        updateStatusClarity();
        bindCharCounter('meta_title', 'meta-title-counter', 60, 70);
        bindCharCounter('meta_description', 'meta-description-counter', 160, 200);
        bindMetaPreviewInputs();
        initOgImagePicker();
        initStructuredDataGenerator();
        initCopyButtons();
        initDirtyTracking();

        const slugField = $('slug');
        if (slugField) {
            slugField.addEventListener('input', function () {
                updatePermalinkPreview();
                validateSidebar();
            });
        }
        const statusField = $('status');
        if (statusField) {
            statusField.addEventListener('change', function () {
                updateStatusClarity();
                validateSidebar();
            });
        }
        ['meta_title', 'meta_description', 'published_at'].forEach(function (id) {
            const el = $(id);
            if (el) el.addEventListener('input', validateSidebar);
            if (el) el.addEventListener('change', validateSidebar);
        });

        updateMetaPreviews();
        validateSidebar();
        initRevisionHistory();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
