(function (global) {
    'use strict';

    function cloneJson(value) {
        try {
            return JSON.parse(JSON.stringify(value || []));
        } catch (error) {
            return Array.isArray(value) ? value.slice() : [];
        }
    }

    function normalizeType(type) {
        return String(type || '')
            .trim()
            .toLowerCase()
            .replace(/[\s_-]+/g, '');
    }

    function formatTypeLabel(type) {
        return String(type || '')
            .trim()
            .replace(/[_-]+/g, ' ')
            .replace(/\b[a-z]/g, function (char) {
                return char.toUpperCase();
            }) || 'Unknown';
    }

    function normalizeField(field, index) {
        field = field || {};
        return {
            index: index,
            name: String(field.name || '').trim(),
            label: String(field.label || '').trim(),
            type: String(field.type || '').trim(),
            required: !!field.required
        };
    }

    function getFieldDisplayLabel(field, index) {
        return field.label || field.name || ('Field ' + String(index + 1));
    }

    function getSafeResponsePreview(text) {
        return String(text || '').replace(/\s+/g, ' ').trim().slice(0, 200);
    }

    function serializeFormState(form) {
        if (!form) return '[]';

        var entries = [];
        Array.prototype.forEach.call(form.elements || [], function (element) {
            if (!element || !element.name || element.disabled) return;

            var tagName = String(element.tagName || '').toLowerCase();
            var type = String(element.type || '').toLowerCase();
            if (tagName === 'button') return;
            if (type === 'submit' || type === 'button' || type === 'reset' || type === 'file') return;

            if (element.dataset && element.dataset.collectionEditorGenerated === 'true') {
                return;
            }

            if ((type === 'checkbox' || type === 'radio') && !element.checked) {
                return;
            }

            if (tagName === 'select' && element.multiple) {
                Array.prototype.forEach.call(element.options || [], function (option) {
                    if (option.selected) {
                        entries.push([element.name, option.value]);
                    }
                });
                return;
            }

            entries.push([element.name, element.value == null ? '' : String(element.value)]);
        });

        entries.sort(function (left, right) {
            if (left[0] === right[0]) {
                return String(left[1]).localeCompare(String(right[1]));
            }
            return left[0].localeCompare(right[0]);
        });

        return JSON.stringify(entries);
    }

    function replaceFieldIndexInName(name, oldIndex, newIndex) {
        if (!name) return name;

        var oldTopLevel = 'fields[' + oldIndex + ']';
        if (name.indexOf(oldTopLevel) !== -1) {
            return name.replace(oldTopLevel, 'fields[' + newIndex + ']');
        }

        var oldNested = '[fields][' + oldIndex + ']';
        if (name.indexOf(oldNested) !== -1) {
            return name.replace(oldNested, '[fields][' + newIndex + ']');
        }

        return name;
    }

    function buildSchemaFromContainer(container, sectionSelector) {
        if (!container) return [];

        return Array.from(container.querySelectorAll(sectionSelector || '.fieldtype-section')).map(function (section, index) {
            var labelInput = section.querySelector('input[name$="[label]"]');
            var nameInput = section.querySelector('input[name$="[name]"]');
            var typeSelect = section.querySelector('select[name$="[type]"]');
            var requiredInput = section.querySelector('input[type="checkbox"][name$="[required]"]');

            return normalizeField({
                label: labelInput ? labelInput.value : '',
                name: nameInput ? nameInput.value : '',
                type: typeSelect ? typeSelect.value : '',
                required: !!(requiredInput && requiredInput.checked)
            }, index);
        });
    }

    function findMatchingField(originalField, currentFields, usedIndices) {
        var match = null;

        if (originalField.name) {
            match = currentFields.find(function (field, index) {
                return !usedIndices.has(index) && field.name === originalField.name;
            });
            if (match) return match;
        }

        if (originalField.label) {
            match = currentFields.find(function (field, index) {
                return !usedIndices.has(index) && field.label === originalField.label;
            });
            if (match) return match;
        }

        if (typeof originalField.index === 'number' && currentFields[originalField.index] && !usedIndices.has(originalField.index)) {
            return currentFields[originalField.index];
        }

        return null;
    }

    function diffSchema(originalSchema, currentSchema) {
        var originalFields = (Array.isArray(originalSchema) ? originalSchema : []).map(function (field, index) {
            return normalizeField(field, index);
        });
        var currentFields = (Array.isArray(currentSchema) ? currentSchema : []).map(function (field, index) {
            return normalizeField(field, index);
        });
        var destructiveChanges = [];
        var usedCurrentIndices = new Set();

        originalFields.forEach(function (originalField, index) {
            if (!originalField.name && !originalField.label && !originalField.type) {
                return;
            }

            var matchingField = findMatchingField(originalField, currentFields, usedCurrentIndices);
            if (!matchingField) {
                destructiveChanges.push('Remove field "' + getFieldDisplayLabel(originalField, index) + '".');
                return;
            }

            usedCurrentIndices.add(matchingField.index);

            if (originalField.name && matchingField.name && originalField.name !== matchingField.name) {
                destructiveChanges.push('Rename API field "' + originalField.name + '" to "' + matchingField.name + '".');
            }

            if (originalField.type && matchingField.type && normalizeType(originalField.type) !== normalizeType(matchingField.type)) {
                destructiveChanges.push(
                    'Change "' + getFieldDisplayLabel(originalField, index) + '" from ' +
                    formatTypeLabel(originalField.type) + ' to ' + formatTypeLabel(matchingField.type) + '.'
                );
            }
        });

        return {
            destructiveChanges: Array.from(new Set(destructiveChanges)),
            hasDestructiveChanges: destructiveChanges.length > 0
        };
    }

    function init(options) {
        options = options || {};

        var form = document.getElementById(options.formId || 'collection-form');
        if (!form) return null;

        var schemaContainer = options.schemaContainerId ? document.getElementById(options.schemaContainerId) : null;
        var reorderContainer = options.reorderContainerId ? document.getElementById(options.reorderContainerId) : null;
        var schemaSectionSelector = options.schemaSectionSelector || '.fieldtype-section';
        var reorderSectionSelector = options.reorderSectionSelector || '.fieldtype-section';
        var reorderIndexAttribute = options.reorderIndexAttribute || 'data-index';
        var submitUrl = options.submitUrl || form.getAttribute('action') || '/api/admin_collections.php';
        var blockSubmitWhenActiveElementIds = Array.isArray(options.blockSubmitWhenActiveElementIds)
            ? options.blockSubmitWhenActiveElementIds
            : [];
        var isSubCollection = !!options.isSubCollection;
        var saveLabels = options.saveLabels || {};
        var contentOverviewUrl = options.contentOverviewUrl || '/admin?page=content';
        var entryEditorUrlTemplate = options.entryEditorUrlTemplate || '';
        var entriesListUrlTemplate = options.entriesListUrlTemplate || '';
        var originalSchema = cloneJson(options.originalSchema || global.__collectionOriginalSchema || []);
        var initialFormState = serializeFormState(form);
        var isDirty = false;
        var isSubmitting = false;
        var fieldtypeSorter = null;
        var saveModalPreviousFocus = null;
        var saveModalFocusTrap = null;

        function getSaveModalFocusable(modal) {
            return Array.from(modal.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function (el) {
                return el.offsetParent !== null || el === document.activeElement;
            });
        }

        function releaseSaveModalFocusTrap() {
            if (saveModalFocusTrap) {
                document.removeEventListener('keydown', saveModalFocusTrap);
                saveModalFocusTrap = null;
            }
        }

        function trapSaveModalFocus(modal) {
            releaseSaveModalFocusTrap();
            saveModalFocusTrap = function (event) {
                if (event.key !== 'Tab' || !modal.classList.contains('active')) return;
                var focusable = getSaveModalFocusable(modal);
                if (!focusable.length) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            };
            document.addEventListener('keydown', saveModalFocusTrap);
        }

        function buildCurrentSchema() {
            if (!schemaContainer) {
                return cloneJson(originalSchema);
            }
            return buildSchemaFromContainer(schemaContainer, schemaSectionSelector);
        }

        function updateDirtyState() {
            isDirty = serializeFormState(form) !== initialFormState;
            return isDirty;
        }

        function syncFieldIndices() {
            if (!reorderContainer) return;

            var sections = Array.from(reorderContainer.querySelectorAll(reorderSectionSelector));
            sections.forEach(function (section, newIndex) {
                var oldIndex = section.getAttribute(reorderIndexAttribute);
                section.setAttribute(reorderIndexAttribute, String(newIndex));

                if (oldIndex === String(newIndex)) return;

                section.querySelectorAll('input, select, textarea').forEach(function (input) {
                    if (input.name) {
                        input.name = replaceFieldIndexInName(input.name, oldIndex, newIndex);
                    }
                });

                var configContainer = section.querySelector('.fieldtype-config');
                if (configContainer && configContainer.hasAttribute('data-field-index')) {
                    configContainer.setAttribute('data-field-index', String(newIndex));
                }

                if (oldIndex !== null && oldIndex !== '') {
                    section.querySelectorAll('[id*="' + oldIndex + '"]').forEach(function (element) {
                        if (element.id) {
                            element.id = element.id.replace(oldIndex, String(newIndex));
                        }
                    });
                }
            });
        }

        function updateReorderButtonStates() {
            if (!reorderContainer) return;

            var sections = Array.from(reorderContainer.querySelectorAll(reorderSectionSelector));
            sections.forEach(function (section, index) {
                var moveUpButton = section.querySelector('.field-move-up-btn');
                var moveDownButton = section.querySelector('.field-move-down-btn');
                var disableUp = index === 0;
                var disableDown = index === sections.length - 1;

                if (moveUpButton) {
                    moveUpButton.disabled = disableUp;
                    moveUpButton.setAttribute('aria-disabled', disableUp ? 'true' : 'false');
                }

                if (moveDownButton) {
                    moveDownButton.disabled = disableDown;
                    moveDownButton.setAttribute('aria-disabled', disableDown ? 'true' : 'false');
                }
            });
        }

        function moveFieldSection(section, direction) {
            if (!reorderContainer || !section) return false;

            var sibling = direction === 'up' ? section.previousElementSibling : section.nextElementSibling;
            while (sibling && !sibling.matches(reorderSectionSelector)) {
                sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
            }

            if (!sibling) return false;

            if (direction === 'up') {
                reorderContainer.insertBefore(section, sibling);
            } else {
                reorderContainer.insertBefore(sibling, section);
            }

            syncFieldIndices();
            updateReorderButtonStates();
            updateDirtyState();

            return true;
        }

        function refreshFieldDragDrop() {
            if (fieldtypeSorter && typeof fieldtypeSorter.destroy === 'function') {
                fieldtypeSorter.destroy();
                fieldtypeSorter = null;
            }

            if (!reorderContainer || typeof global.MouseDragDrop !== 'function') {
                return;
            }

            fieldtypeSorter = new global.MouseDragDrop({
                container: reorderContainer,
                itemSelector: reorderSectionSelector,
                handleSelector: '.fieldtype-drag-handle',
                onReorder: function (fromIndex, toIndex, placeholder, draggedItem) {
                    if (!draggedItem || !placeholder || !placeholder.parentNode) {
                        return;
                    }

                    if (draggedItem.parentNode) {
                        draggedItem.remove();
                    }

                    placeholder.parentNode.insertBefore(draggedItem, placeholder);
                    placeholder.remove();

                    syncFieldIndices();
                    updateReorderButtonStates();
                    updateDirtyState();
                }
            });
        }

        function showToast(message, type) {
            if (global.BirklyFeedback && typeof global.BirklyFeedback.toast === 'function') {
                global.BirklyFeedback.toast(message, type);
                return;
            }
            if (type === 'error') {
                global.console.error(message);
            } else {
                global.console.log(message);
            }
        }

        function label(key, fallback) {
            return saveLabels[key] || fallback;
        }

        function buildEntryEditorUrl(collectionSlug) {
            if (!collectionSlug) return contentOverviewUrl;
            if (entryEditorUrlTemplate.indexOf('__SLUG__') !== -1) {
                return entryEditorUrlTemplate.replace('__SLUG__', encodeURIComponent(collectionSlug));
            }
            return '/admin?page=entry-editor&collection=' + encodeURIComponent(collectionSlug);
        }

        function buildEntriesListUrl(collectionSlug) {
            if (!collectionSlug) return contentOverviewUrl;
            if (entriesListUrlTemplate.indexOf('__SLUG__') !== -1) {
                return entriesListUrlTemplate.replace('__SLUG__', encodeURIComponent(collectionSlug));
            }
            return '/admin?page=entries&collection=' + encodeURIComponent(collectionSlug);
        }

        function openSaveSuccessModal(collectionSlug, wasSubCollectionCreate) {
            var modal = document.getElementById('col-save-success-modal');
            if (!modal) return;

            var titleEl = document.getElementById('col-save-success-title');
            var messageEl = document.getElementById('col-save-success-message');
            var addEntryEl = document.getElementById('col-save-add-entry');
            var backEl = document.getElementById('col-save-back');
            var continueEl = document.getElementById('col-save-continue');

            if (titleEl) {
                titleEl.textContent = wasSubCollectionCreate
                    ? label('subSuccessTitle', 'Sub-collection created')
                    : label('successTitle', 'Collection created');
            }
            if (messageEl) {
                messageEl.textContent = wasSubCollectionCreate
                    ? label('subSuccessMessage', 'Your sub-collection schema is saved.')
                    : label('successMessage', 'Your collection is ready.');
            }
            if (addEntryEl) {
                if (wasSubCollectionCreate) {
                    addEntryEl.textContent = label('viewEntries', 'View entries');
                    addEntryEl.href = buildEntriesListUrl(collectionSlug);
                } else {
                    addEntryEl.textContent = label('addFirstEntry', 'Add first entry');
                    addEntryEl.href = buildEntryEditorUrl(collectionSlug);
                }
                addEntryEl.hidden = !collectionSlug;
            }
            if (backEl) {
                backEl.textContent = label('backToCollections', 'Back to collections');
                backEl.href = contentOverviewUrl;
            }
            if (continueEl) {
                continueEl.textContent = label('continueEditing', 'Continue editing');
            }

            saveModalPreviousFocus = document.activeElement;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(function () {
                modal.classList.add('active');
                trapSaveModalFocus(modal);
                var firstFocus = addEntryEl && !addEntryEl.hidden ? addEntryEl : continueEl;
                if (firstFocus && firstFocus.focus) {
                    firstFocus.focus();
                }
            });
        }

        function closeSaveSuccessModal() {
            var modal = document.getElementById('col-save-success-modal');
            if (!modal) return;
            modal.classList.remove('active');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            releaseSaveModalFocusTrap();
            if (saveModalPreviousFocus && saveModalPreviousFocus.focus) {
                saveModalPreviousFocus.focus();
                saveModalPreviousFocus = null;
            }
        }

        function flashSavedState() {
            var saveBar = document.querySelector('.main-actions-bar--sticky');
            if (!saveBar) return;
            saveBar.classList.add('main-actions-bar--saved');
            global.setTimeout(function () {
                saveBar.classList.remove('main-actions-bar--saved');
            }, 2500);
        }

        function bindSaveSuccessModal() {
            var modal = document.getElementById('col-save-success-modal');
            if (!modal || modal.dataset.colSaveBound === 'true') return;
            modal.dataset.colSaveBound = 'true';

            modal.querySelectorAll('[data-col-modal-close]').forEach(function (el) {
                el.addEventListener('click', closeSaveSuccessModal);
            });

            var continueBtn = document.getElementById('col-save-continue');
            if (continueBtn) {
                continueBtn.addEventListener('click', closeSaveSuccessModal);
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('active')) {
                    closeSaveSuccessModal();
                }
            });
        }

        bindSaveSuccessModal();

        async function confirmDestructiveChanges(changes) {
            if (!changes || !changes.length) return true;

            if (global.BirklyFeedback && typeof global.BirklyFeedback.confirmDestructive === 'function') {
                return global.BirklyFeedback.confirmDestructive(
                    'Saving will apply schema changes that may affect existing content.',
                    changes,
                    {
                        title: 'Review schema changes',
                        confirmLabel: 'Save changes',
                        cancelLabel: 'Cancel'
                    }
                );
            }

            if (global.BirklyFeedback && typeof global.BirklyFeedback.confirm === 'function') {
                return global.BirklyFeedback.confirm(
                    'Saving will apply schema changes that may affect existing content.',
                    { danger: true }
                );
            }

            return Promise.resolve(false);
        }

        function collectGeneratedOptionInputs() {
            form.querySelectorAll('input[type="hidden"][data-collection-editor-generated="true"]').forEach(function (input) {
                input.remove();
            });

            form.querySelectorAll('.options-list').forEach(function (optionsList) {
                var fieldIndex = String(optionsList.id || '').replace('options_list_', '');
                if (!fieldIndex) return;

                optionsList.querySelectorAll('.option-item').forEach(function (item, optionIndex) {
                    var labelInput = item.querySelector('.option-label');
                    var valueInput = item.querySelector('.option-value');
                    if (!labelInput || !valueInput) return;

                    var labelHidden = document.createElement('input');
                    labelHidden.type = 'hidden';
                    labelHidden.name = 'fields[' + fieldIndex + '][option-label][]';
                    labelHidden.value = labelInput.value;
                    labelHidden.dataset.collectionEditorGenerated = 'true';

                    var valueHidden = document.createElement('input');
                    valueHidden.type = 'hidden';
                    valueHidden.name = 'fields[' + fieldIndex + '][option-value][]';
                    valueHidden.value = valueInput.value;
                    valueHidden.dataset.collectionEditorGenerated = 'true';

                    form.appendChild(labelHidden);
                    form.appendChild(valueHidden);

                    item.querySelectorAll('.option-label-lang').forEach(function (langInput) {
                        var langCode = langInput.dataset.lang || '';
                        if (!langCode) return;
                        var langHidden = document.createElement('input');
                        langHidden.type = 'hidden';
                        langHidden.name = 'fields[' + fieldIndex + '][option-labels][' + optionIndex + '][' + langCode + ']';
                        langHidden.value = langInput.value;
                        langHidden.dataset.collectionEditorGenerated = 'true';
                        form.appendChild(langHidden);
                    });
                });
            });
        }

        function ensureHiddenInput(name, value) {
            var existingInput = Array.from(form.querySelectorAll('input[type="hidden"]')).find(function (input) {
                return input.name === name;
            });

            if (!existingInput) {
                existingInput = document.createElement('input');
                existingInput.type = 'hidden';
                existingInput.name = name;
                form.appendChild(existingInput);
            }

            existingInput.value = value;
        }

        function syncPostSaveState(responseData) {
            responseData = responseData || {};
            var payload = responseData.data || {};
            var actionInput = form.querySelector('input[name="action"]');

            var previousAction = actionInput ? actionInput.value : '';
            if (actionInput) {
                if (actionInput.value === 'create') {
                    actionInput.value = 'update';
                } else if (actionInput.value === 'add_sub_collection') {
                    actionInput.value = 'update_sub_collection';
                }
            }

            if (previousAction === 'create' || previousAction === 'add_sub_collection') {
                var editTitle = label('editTitle', 'Edit collection');
                document.querySelectorAll('.breadcrumbs .current, #admin-topbar-breadcrumbs .current').forEach(function (el) {
                    el.textContent = editTitle;
                });
            }

            if (!isSubCollection && payload.slug) {
                ensureHiddenInput('collection_slug', payload.slug);
            }

            if (isSubCollection && payload.sub_collection && payload.sub_collection.slug) {
                ensureHiddenInput('sub_collection_slug', payload.sub_collection.slug);
            }

            try {
                if (!global.history || typeof global.history.replaceState !== 'function') {
                    return;
                }

                var url = new URL(global.location.href);
                if (!isSubCollection && payload.slug) {
                    url.searchParams.set('collection', payload.slug);
                } else if (isSubCollection && payload.sub_collection && payload.sub_collection.slug) {
                    url.searchParams.set('sub_collection', payload.sub_collection.slug);
                    var collectionSlugInput = form.querySelector('input[name="collection_slug"]');
                    if (collectionSlugInput && collectionSlugInput.value) {
                        url.searchParams.set('collection', collectionSlugInput.value);
                    }
                }
                global.history.replaceState({}, '', url.toString());
            } catch (error) {
                global.console.warn('Unable to update URL after save:', error);
            }
        }

        function isBlockedSubmitTarget(target) {
            if (!target || !target.id) return false;
            return blockSubmitWhenActiveElementIds.indexOf(target.id) !== -1;
        }

        async function handleSubmit(event) {
            var activeElement = document.activeElement;
            if (isBlockedSubmitTarget(activeElement)) {
                event.preventDefault();
                event.stopPropagation();
                showToast(label('blockedLanguage', 'Click outside the language field, then save again.'), 'warning');
                return false;
            }

            event.preventDefault();
            if (isSubmitting) return false;

            var actionInput = form.querySelector('input[name="action"]');
            var saveAction = actionInput ? actionInput.value : '';
            var wasCreate = saveAction === 'create' || saveAction === 'add_sub_collection';

            collectGeneratedOptionInputs();

            var schemaDiff = diffSchema(originalSchema, buildCurrentSchema());
            if (schemaDiff.hasDestructiveChanges) {
                var approved = await confirmDestructiveChanges(schemaDiff.destructiveChanges);
                if (!approved) {
                    return false;
                }
            }

            isSubmitting = true;

            try {
                var response = await fetch(submitUrl, {
                    method: 'POST',
                    body: new FormData(form)
                });

                if (!response.ok) {
                    var rawError = await response.text();
                    throw new Error('Server error: ' + response.status + ' - ' + getSafeResponsePreview(rawError));
                }

                var data = await response.json();
                if (data.status !== 'ok') {
                    showToast(label('errorPrefix', 'Error:') + ' ' + (data.message || label('unknownError', 'Unknown error')), 'error');
                    return false;
                }

                syncPostSaveState(data);
                originalSchema = buildCurrentSchema();
                global.__collectionOriginalSchema = cloneJson(originalSchema);
                initialFormState = serializeFormState(form);
                isDirty = false;

                var payload = data.data || {};
                var collectionSlug = payload.slug
                    || (form.querySelector('input[name="collection_slug"]') || {}).value
                    || '';

                if (wasCreate) {
                    openSaveSuccessModal(collectionSlug, saveAction === 'add_sub_collection' || isSubCollection);
                } else {
                    flashSavedState();
                    showToast(data.message || label('updated', 'Collection saved'), 'success');
                }
                return false;
            } catch (error) {
                global.console.error('Error saving collection:', error);
                showToast(label('errorSaving', 'Error saving collection:') + ' ' + error.message, 'error');
                return false;
            } finally {
                isSubmitting = false;
            }
        }

        async function handleReorderClicks(event) {
            if (!reorderContainer) return;

            var moveUpButton = event.target.closest('.field-move-up-btn');
            if (moveUpButton) {
                event.preventDefault();
                moveFieldSection(moveUpButton.closest(reorderSectionSelector), 'up');
                moveUpButton.focus();
                return;
            }

            var moveDownButton = event.target.closest('.field-move-down-btn');
            if (moveDownButton) {
                event.preventDefault();
                moveFieldSection(moveDownButton.closest(reorderSectionSelector), 'down');
                moveDownButton.focus();
                return;
            }

            var removeButton = event.target.closest('.remove-field-btn');
            if (!removeButton) return;

            event.preventDefault();
            var section = removeButton.closest(reorderSectionSelector);
            if (!section) return;

            var confirmed = true;
            if (global.BirklyFeedback && typeof global.BirklyFeedback.confirm === 'function') {
                confirmed = await global.BirklyFeedback.confirm(
                    (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function')
                        ? global.Birkly.i18n.t('admin.confirm_remove_field', 'Are you sure you want to remove this field?')
                        : 'Are you sure you want to remove this field?',
                    {
                    danger: true
                });
            } else {
                confirmed = false;
            }

            if (!confirmed) return;

            section.remove();
            syncFieldIndices();
            updateReorderButtonStates();
            updateDirtyState();
        }

        function handleBeforeUnload(event) {
            if (!isDirty || isSubmitting) return;
            event.preventDefault();
            event.returnValue = '';
            return '';
        }

        form.addEventListener('input', updateDirtyState, true);
        form.addEventListener('change', updateDirtyState, true);
        form.addEventListener('submit', handleSubmit);

        if (reorderContainer) {
            reorderContainer.addEventListener('click', handleReorderClicks);
            syncFieldIndices();
            updateReorderButtonStates();
            refreshFieldDragDrop();
        }

        global.addEventListener('beforeunload', handleBeforeUnload);

        return {
            syncFieldIndices: syncFieldIndices,
            updateReorderButtonStates: updateReorderButtonStates,
            moveFieldSection: moveFieldSection,
            refreshFieldDragDrop: refreshFieldDragDrop,
            notifyStructureChange: updateDirtyState,
            diffSchema: function () {
                return diffSchema(originalSchema, buildCurrentSchema());
            },
            isDirty: function () {
                return isDirty;
            }
        };
    }

    global.BirklyCollectionEditorCore = {
        init: init,
        diffSchema: diffSchema
    };
})(window);
