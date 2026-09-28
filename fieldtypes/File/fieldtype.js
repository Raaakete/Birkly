/**
 * File Fieldtype JavaScript
 * Handles file selection, upload, and preview
 */
(function() {
    'use strict';
    
    function resolveFileFieldName(fieldNameOrEl) {
        if (fieldNameOrEl && fieldNameOrEl.nodeType === 1) {
            const el = fieldNameOrEl.classList && fieldNameOrEl.classList.contains('file-field')
                ? fieldNameOrEl
                : (fieldNameOrEl.closest ? fieldNameOrEl.closest('.file-field') : null);
            const root = el || fieldNameOrEl;
            if (root.id && root.id.indexOf('field_') === 0) {
                return root.id.slice('field_'.length);
            }
        }
        let name = String(fieldNameOrEl || '');
        if (name.indexOf('field_') === 0) {
            name = name.slice('field_'.length);
        }
        return name;
    }

    function fileFieldNotice(message, type) {
        if (window.BirklyFeedback && typeof window.BirklyFeedback.toast === 'function') {
            window.BirklyFeedback.toast(message, type || 'error');
            return;
        }
        alert(message);
    }

    function initFileField(fieldNameOrEl) {
        const fieldName = resolveFileFieldName(fieldNameOrEl);
        const container = document.getElementById('field_' + fieldName);
        if (!container || container.dataset.fileFieldReady === '1') return;
        
        const selectBtn = container.querySelector('.select-file-btn');
        const uploadBtn = container.querySelector('.upload-file-btn');
        const removeBtn = container.querySelector('.remove-file-btn');
        const fileInput = container.querySelector('#file_input_' + fieldName);
        const hiddenInput = container.querySelector('#field_' + fieldName + '_value');
        const previewContainer = container.querySelector('.file-preview-container');
        const metaEl = container.querySelector('.file-meta');
        const selectLabel = container.querySelector('.file-select-label');
        const uploadLabel = container.querySelector('.file-upload-label');
        
        if (!selectBtn || !fileInput || !hiddenInput) return;
        container.dataset.fileFieldReady = '1';
        
        // Select file from media library
        if (selectBtn) {
            selectBtn.addEventListener('click', function() {
                // Check if media modal exists (try both possible locations)
                const mediaModal = window.Birkly?.MediaModal || window.MediaModal;
                if (mediaModal && typeof mediaModal.open === 'function') {
                    mediaModal.open({
                        onSelect: function(media) {
                            setFileValue(media);
                        },
                        allowMultiple: false,
                        accept: fileInput.getAttribute('accept') || ''
                    });
                } else {
                    // Fallback: trigger file input
                    fileInput.click();
                }
            });
        }
        
        // Upload new file
        if (uploadBtn && fileInput) {
            uploadBtn.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    uploadFile(file);
                }
            });
        }
        
        // Remove file
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                clearFile();
            });
        }
        
        function setFileValue(media) {
            const fileId = media.id || media;
            hiddenInput.value = fileId;
            
            updatePreview(media);
        }

        function escapeText(text) {
            return String(text == null ? '' : text).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }

        function describeFile(fileInfo) {
            const dims = fileInfo && fileInfo.dimensions;
            if (dims && dims.width && dims.height) {
                return dims.width + ' × ' + dims.height;
            }
            return formatFileSize((fileInfo && fileInfo.size) || 0);
        }

        function setState(filled, meta) {
            container.dataset.state = filled ? 'filled' : 'empty';
            if (removeBtn) removeBtn.hidden = !filled;
            if (selectLabel) selectLabel.textContent = filled ? 'Replace' : 'Choose file';
            if (metaEl) {
                metaEl.textContent = meta || '';
                metaEl.hidden = !meta;
            }
        }
        
        function updatePreview(fileInfo) {
            if (!previewContainer) return;

            if (!fileInfo || !fileInfo.url) {
                previewContainer.innerHTML = '<div class="file-placeholder">No file yet</div>';
                setState(false, '');
                return;
            }

            const fileName = escapeText(fileInfo.name || 'File');
            if ((fileInfo.type || '').startsWith('image/')) {
                previewContainer.innerHTML = '<img class="file-cover" src="' + escapeText(fileInfo.url) + '" alt="' + fileName + '">';
            } else {
                previewContainer.innerHTML = '<div class="file-doc"><span class="file-name">' + fileName + '</span></div>';
            }
            setState(true, (fileInfo.name || 'File') + ' · ' + describeFile(fileInfo));
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (csrf) formData.append('_csrf', csrf);

            uploadBtn.disabled = true;
            if (uploadLabel) uploadLabel.textContent = 'Uploading…';

            fetch(Birkly.url('/api/admin_media.php?action=upload'), {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                uploadBtn.disabled = false;
                if (uploadLabel) uploadLabel.textContent = 'Upload';

                if (data.status === 'ok' && data.data) {
                    setFileValue(data.data);
                } else {
                    fileFieldNotice('Upload failed: ' + (data.error || data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                uploadBtn.disabled = false;
                if (uploadLabel) uploadLabel.textContent = 'Upload';
                fileFieldNotice('Upload failed: ' + error.message, 'error');
            });
        }
        
        function clearFile() {
            hiddenInput.value = '';
            updatePreview(null);
            if (fileInput) fileInput.value = '';
        }
        
        function formatFileSize(bytes) {
            if (bytes == 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Check if there's a value already set
        if (hiddenInput.value) {
            // Try to load file info
            fetch(Birkly.url('/api/admin_media.php?action=get&id=' + encodeURIComponent(hiddenInput.value)), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok' && data.data) {
                        updatePreview(data.data);
                    }
                })
                .catch(error => {
                    console.error('Error loading file info:', error);
                });
        }
    }
    
    // Initialize all file fields
    function initAllFileFields() {
        document.querySelectorAll('.file-field').forEach(function(container) {
            const fieldName = container.id.replace('field_', '');
            if (fieldName) {
                initFileField(fieldName);
            }
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllFileFields);
    } else {
        initAllFileFields();
    }
    
    // Also initialize on custom event for dynamic fields
    document.addEventListener('fieldtype:init', function(e) {
        const ft = e.detail && e.detail.fieldtype;
        if (ft === 'file' || ft === 'media' || ft === 'image') {
            initFileField(e.detail.element || e.detail.fieldName);
        }
    });

    window.initMediaField = initFileField;
    window.initImageField = initFileField;
    
    // Expose for manual initialization
    window.initFileField = initFileField;
})();

