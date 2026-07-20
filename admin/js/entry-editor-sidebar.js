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
                socialImage.style.display = 'block';
            } else {
                socialImage.style.backgroundImage = '';
                socialImage.style.display = 'none';
            }
        }
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
        pickBtn.addEventListener('click', function () {
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
        btn.addEventListener('click', function () {
            textarea.value = generateStructuredData();
            markDirty();
        });
    }

    function copyText(text) {
        return navigator.clipboard.writeText(text);
    }

    function initCopyButtons() {
        document.querySelectorAll('.copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
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
            copyAll.addEventListener('click', function () {
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
            box.style.display = 'none';
            return issues;
        }
        box.style.display = 'block';
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

    function initDirtyTracking() {
        if (!form) return;
        initialSnapshot = formSnapshot();
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function () {
            formDirty = false;
            const badge = $('editor-unsaved-badge');
            if (badge) badge.classList.remove('is-visible');
        });
        window.addEventListener('beforeunload', function (e) {
            if (formDirty && formSnapshot() !== initialSnapshot) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
