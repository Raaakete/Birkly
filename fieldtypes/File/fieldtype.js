/**
 * File Fieldtype JavaScript
 * Handles file selection, upload, and preview
 */
(function() {
    'use strict';
    
    function initFileField(fieldName) {
        const container = document.getElementById('field_' + fieldName);
        if (!container) return;
        
        const selectBtn = container.querySelector('.select-file-btn');
        const uploadBtn = container.querySelector('.upload-file-btn');
        const removeBtn = container.querySelector('.remove-file-btn');
        const fileInput = container.querySelector('#file_input_' + fieldName);
        const hiddenInput = container.querySelector('#field_' + fieldName + '_value');
        const previewContainer = container.querySelector('.file-preview-container');
        
        if (!selectBtn || !fileInput || !hiddenInput) return;
        
        // Select file from media library
        if (selectBtn) {
            selectBtn.addEventListener('click', function() {
                // Check if media modal exists
                if (window.MediaModal && typeof window.MediaModal.open === 'function') {
                    window.MediaModal.open({
                        onSelect: function(media) {
                            setFileValue(media);
                        },
                        allowMultiple: false
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
            
            // Update preview
            updatePreview(media);
            
            // Show remove button
            if (removeBtn) removeBtn.style.display = 'block';
        }
        
        function updatePreview(fileInfo) {
            if (!previewContainer) return;
            
            let html = '';
            
            if (fileInfo && fileInfo.url) {
                const fileName = fileInfo.name || 'File';
                const fileSize = formatFileSize(fileInfo.size || 0);
                const fileType = fileInfo.type || '';
                
                // Check if it's an image
                if (fileType.startsWith('image/')) {
                    html = `<img src="${fileInfo.url}" alt="${fileName}" style="max-width:100px;max-height:100px;object-fit:cover;border-radius:4px;">`;
                } else {
                    html = `
                        <div class="file-preview">
                            <div class="file-icon">📄</div>
                            <div class="file-name">${fileName}</div>
                            <div class="file-size">${fileSize}</div>
                        </div>
                    `;
                }
            } else {
                html = '<div class="file-placeholder">No file selected</div>';
            }
            
            previewContainer.innerHTML = html;
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            fetch('/api/media.php?action=upload', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload File';
                
                if (data.status === 'ok' && data.data) {
                    setFileValue(data.data);
                } else {
                    alert('Upload failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload File';
                alert('Upload failed: ' + error.message);
            });
        }
        
        function clearFile() {
            hiddenInput.value = '';
            if (previewContainer) {
                previewContainer.innerHTML = '<div class="file-placeholder">No file selected</div>';
            }
            if (removeBtn) removeBtn.style.display = 'none';
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
            fetch(`/api/media.php?action=get&id=${hiddenInput.value}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok' && data.data) {
                        updatePreview(data.data);
                        if (removeBtn) removeBtn.style.display = 'block';
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
        if (e.detail && e.detail.fieldtype === 'file') {
            const fieldName = e.detail.fieldName || e.detail.element?.id?.replace('field_', '');
            if (fieldName) {
                initFileField(fieldName);
            }
        }
    });
    
    // Expose for manual initialization
    window.initFileField = initFileField;
})();

