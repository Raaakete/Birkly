/**
 * Entries list — bulk field edit modal with full fieldtype controls.
 */
(function () {
    'use strict';

    var bulkFields = Array.isArray(window.entriesBulkEditFields) ? window.entriesBulkEditFields : [];
    var fieldByName = {};
    bulkFields.forEach(function (field) {
        if (field && field.name) {
            fieldByName[field.name] = field;
        }
    });

    var modal = document.getElementById('entries-bulk-edit-modal');
    if (!modal || bulkFields.length === 0) {
        return;
    }

    var fieldSelect = document.getElementById('entries-bulk-edit-field-select');
    var fieldMount = document.getElementById('entries-bulk-edit-field-mount');
    var fieldControl = document.getElementById('entries-bulk-edit-field-control');
    var applyBtn = document.getElementById('entries-bulk-edit-apply');
    var closeBtn = document.getElementById('entries-bulk-edit-close');
    var cancelBtn = document.getElementById('entries-bulk-edit-cancel');
    var injectedStyleId = 'entries-bulk-edit-fieldtype-css';

    var activeFieldName = '';
    var renderRequestId = 0;

    function t(key, fallback, params) {
        var text = (window.Birkly && window.Birkly.i18n && typeof window.Birkly.i18n.t === 'function')
            ? window.Birkly.i18n.t('admin.' + key, fallback != null ? fallback : key)
            : (fallback != null ? fallback : key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function openModal() {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.classList.add('entries-bulk-edit-modal-open');
        if (fieldSelect) {
            fieldSelect.focus();
        }
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        document.body.classList.remove('entries-bulk-edit-modal-open');
        resetFieldControl();
        if (fieldSelect) {
            fieldSelect.value = '';
        }
    }

    function resetFieldControl() {
        activeFieldName = '';
        renderRequestId += 1;
        if (fieldControl) {
            fieldControl.innerHTML = '';
        }
        if (fieldMount) {
            fieldMount.hidden = true;
        }
        if (applyBtn) {
            applyBtn.disabled = true;
        }
        var styleEl = document.getElementById(injectedStyleId);
        if (styleEl) {
            styleEl.textContent = '';
        }
    }

    function buildRenderConfig(fieldDef) {
        var config = {
            name: fieldDef.name,
            label: fieldDef.label || fieldDef.name,
            value: fieldDef.type === 'checkbox' ? '' : '',
            required: false,
            help: fieldDef.help || '',
            group: fieldDef.group || 'Content',
        };
        if (fieldDef.options) {
            config.options = fieldDef.options;
        }
        Object.keys(fieldDef).forEach(function (key) {
            if (['name', 'label', 'type', 'width', 'min_width', 'html'].indexOf(key) !== -1) {
                return;
            }
            if (!(key in config)) {
                config[key] = fieldDef[key];
            }
        });
        return config;
    }

    function runScripts(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('script').forEach(function (oldScript) {
            var newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function (attr) {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            document.head.appendChild(newScript);
            oldScript.remove();
        });
    }

    function injectCss(cssText) {
        var styleEl = document.getElementById(injectedStyleId);
        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = injectedStyleId;
            document.head.appendChild(styleEl);
        }
        styleEl.textContent = cssText || '';
    }

    function initFieldtypesInContainer(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('[data-fieldtype]').forEach(function (input) {
            var fieldtype = input.getAttribute('data-fieldtype');
            var fieldName = input.name || input.id;
            if (['editor', 'media', 'relation', 'dynamicitems', 'tags'].includes(fieldtype)) {
                return;
            }
            document.dispatchEvent(new CustomEvent('fieldtype:init', {
                detail: { fieldtype: fieldtype, fieldName: fieldName, element: input },
            }));
            var initFnName = 'init' + fieldtype.charAt(0).toUpperCase() + fieldtype.slice(1) + 'Field';
            if (typeof window[initFnName] === 'function') {
                window[initFnName](input);
            }
        });

        container.querySelectorAll('.editor-field').forEach(function (wrap) {
            if (wrap.querySelector('.content-editor-wrapper')) {
                return;
            }
            document.dispatchEvent(new CustomEvent('fieldtype:init', {
                detail: { fieldtype: 'editor', element: wrap },
            }));
        });

        container.querySelectorAll('.media-field').forEach(function (wrap) {
            var name = wrap.id.replace('field_', '');
            if (typeof window.initMediaField === 'function') {
                window.initMediaField(name);
            }
            document.dispatchEvent(new CustomEvent('fieldtype:init', {
                detail: { fieldtype: 'media', fieldName: name, element: wrap },
            }));
        });

        container.querySelectorAll('.relation-field').forEach(function (wrap) {
            var name = (wrap.id || '').replace('relation_field_', '');
            if (typeof window.initRelationField === 'function') {
                window.initRelationField(name);
            }
            document.dispatchEvent(new CustomEvent('fieldtype:init', {
                detail: { fieldtype: 'relation', fieldName: name, element: wrap },
            }));
        });

        container.querySelectorAll('.tags-field-container').forEach(function (wrap) {
            document.dispatchEvent(new CustomEvent('fieldtype:init', {
                detail: { fieldtype: 'tags', element: wrap },
            }));
            if (window.HaruFieldtypes && window.HaruFieldtypes.tags && typeof window.HaruFieldtypes.tags.init === 'function') {
                window.HaruFieldtypes.tags.init(wrap);
            }
        });

        if (typeof window.initializeAllDynamicItemsFields === 'function') {
            try {
                window.initializeAllDynamicItemsFields();
            } catch (err) {
                console.error('DynamicItems init failed:', err);
            }
        }
    }

    async function renderFieldControl(fieldDef) {
        var requestId = ++renderRequestId;
        if (!fieldControl || !fieldMount) {
            return;
        }
        fieldControl.innerHTML = '<p class="text-secondary">' + t('entries_bulk_loading_field', 'Loading field control…') + '</p>';
        fieldMount.hidden = false;
        if (applyBtn) {
            applyBtn.disabled = true;
        }

        var formData = new FormData();
        formData.append('action', 'render');
        formData.append('fieldtype', fieldDef.type);
        formData.append('config', JSON.stringify(buildRenderConfig(fieldDef)));

        var response = await fetch(apiUrl('/api/admin_fieldtypes.php'), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        });
        var data = await response.json();
        if (requestId !== renderRequestId) {
            return;
        }
        if (data.error) {
            fieldControl.innerHTML = '<p class="text-danger">' + String(data.error) + '</p>';
            return;
        }

        injectCss(data.css || '');
        fieldControl.innerHTML = data.html || '';
        if (data.javascript) {
            var scriptEl = document.createElement('script');
            scriptEl.textContent = data.javascript;
            document.head.appendChild(scriptEl);
        }
        runScripts(fieldControl);
        initFieldtypesInContainer(fieldControl);
        if (applyBtn) {
            applyBtn.disabled = false;
        }
    }

    function parseFieldValue(raw) {
        if (raw == null) {
            return '';
        }
        if (typeof raw !== 'string') {
            return raw;
        }
        var trimmed = raw.trim();
        if (trimmed === '') {
            return '';
        }
        if (trimmed[0] === '[' || trimmed[0] === '{') {
            try {
                return JSON.parse(trimmed);
            } catch (error) {
                return raw;
            }
        }
        return raw;
    }

    async function collectFieldValue(fieldName, mount) {
        await new Promise(function (resolve) {
            try {
                document.dispatchEvent(new CustomEvent('beforeSaveEntry'));
            } catch (error) {
                console.error(error);
            }
            setTimeout(resolve, 100);
        });

        var fieldMap = new Map();
        var scopes = [mount];
        scopes.forEach(function (scope) {
            scope.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(function (input) {
                var name = input.name;
                if (!name || name.startsWith('_')) {
                    return;
                }
                if (input.type === 'checkbox') {
                    fieldMap.set(name, input.checked ? (input.value || '1') : '');
                } else if (input.type === 'radio') {
                    if (input.checked) {
                        fieldMap.set(name, input.value || '');
                    }
                } else if (input.type === 'file') {
                    if (input.files && input.files.length > 0) {
                        fieldMap.set(name, input);
                    }
                } else {
                    var value = input.value || '';
                    if (!fieldMap.has(name) || fieldMap.get(name) === '' || value !== '') {
                        fieldMap.set(name, value);
                    }
                }
            });

            scope.querySelectorAll('input[type="hidden"]').forEach(function (input) {
                var name = input.name;
                if (!name || name.startsWith('_')) {
                    return;
                }
                var value = input.value || '';
                var hasRegular = fieldMap.has(name);
                if (value !== '' || !hasRegular) {
                    fieldMap.set(name, value);
                }
            });
        });

        return parseFieldValue(fieldMap.get(fieldName));
    }

    async function applyBulkFieldEdit(entryIds) {
        if (!activeFieldName || !entryIds || entryIds.length === 0) {
            return;
        }
        var fieldValue = await collectFieldValue(activeFieldName, fieldControl);
        var entityLabel = entryIds.length === 1
            ? t('entries_bulk_entity_entry', 'entry')
            : t('entries_bulk_entity_entries', 'entries');
        var confirmMsg = t('entries_bulk_apply_confirm', 'Apply this field update to %count% selected %entity%?')
            .replace('%count%', String(entryIds.length))
            .replace('%entity%', entityLabel);
        if (!(await BirklyFeedback.confirm(confirmMsg))) {
            return;
        }

        var progress = document.createElement('div');
        progress.className = 'bulk-progress';
        progress.style.cssText = 'position:fixed;top:20px;right:20px;background:var(--bg-elevated);padding:var(--space-3);border-radius:var(--radius-sm,8px);box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:1000;';
        progress.textContent = t('entries_bulk_updating_fields', 'Updating field values…');
        document.body.appendChild(progress);

        try {
            var response = await fetch(apiUrl('/api/admin_entries.php?action=bulk_field_edit'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    collection: window.collectionSlug || '',
                    entry_ids: entryIds,
                    field_name: activeFieldName,
                    value: fieldValue,
                    _csrf: getCSRFToken(),
                }),
            });
            var text = await response.text();
            var result = null;
            try {
                result = JSON.parse(text);
            } catch (error) {
                throw new Error(t('entries_invalid_json', 'Invalid JSON response'));
            }
            if (!response.ok || result?.status === 'error') {
                throw new Error(result?.message || ('HTTP ' + response.status));
            }
            progress.remove();
            closeModal();
            BirklyFeedback.toast(result.message || t('entries_bulk_field_updated', 'Field updated successfully.'), 'success');
            window.location.reload();
        } catch (error) {
            progress.remove();
            var message = error && error.message ? error.message : t('entries_load_failed', 'Failed to load entries');
            BirklyFeedback.toast(t('entries_error_prefix', 'Error: {message}', { message: message }), 'error');
        }
    }

    function onFieldSelectChanged() {
        if (!fieldSelect) {
            return;
        }
        var name = fieldSelect.value || '';
        if (!name || !fieldByName[name]) {
            resetFieldControl();
            return;
        }
        activeFieldName = name;
        renderFieldControl(fieldByName[name]).catch(function (error) {
            console.error(error);
            if (fieldControl) {
                fieldControl.innerHTML = '<p class="text-danger">' + (error.message || 'Failed to load field') + '</p>';
            }
            if (fieldMount) {
                fieldMount.hidden = false;
            }
        });
    }

    fieldSelect?.addEventListener('change', onFieldSelectChanged);
    fieldSelect?.addEventListener('input', onFieldSelectChanged);

    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    window.BirklyEntriesBulkEdit = {
        open: function (entryIds) {
            if (!entryIds || entryIds.length === 0) {
                return;
            }
            window.__entriesBulkEditTargetIds = entryIds.slice();
            resetFieldControl();
            if (fieldSelect) {
                fieldSelect.value = '';
            }
            openModal();
        },
        apply: function () {
            var ids = window.__entriesBulkEditTargetIds || [];
            return applyBulkFieldEdit(ids);
        },
    };

    applyBtn?.addEventListener('click', function () {
        window.BirklyEntriesBulkEdit.apply();
    });
})();
