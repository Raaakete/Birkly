/**
 * Gallery Fieldtype JavaScript
 * Handles multiple image selection, upload, and preview grid
 */
(function() {
    'use strict';
    
    function initGalleryField(fieldName) {
        const container = document.getElementById('field_' + fieldName);
        if (!container) return;
        
        const addBtn = container.querySelector('.add-gallery-images');
        const uploadBtn = container.querySelector('.upload-gallery-images');
        const clearBtn = container.querySelector('.clear-gallery');
        const fileInput = container.querySelector('#gallery_input_' + fieldName);
        const hiddenInput = container.querySelector('#field_' + fieldName + '_value');
        const previewContainer = container.querySelector('.gallery-preview');
        
        if (!hiddenInput || !previewContainer) return;
        
        let currentImages = [];
        
        // Load current value
        try {
            const value = hiddenInput.value || '[]';
            currentImages = JSON.parse(value);
            if (!Array.isArray(currentImages)) {
                currentImages = [];
            }
        } catch (e) {
            currentImages = [];
        }
        
        // Add images from media library
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                // Check if media modal exists (try both possible locations)
                const mediaModal = window.Birkly?.MediaModal || window.MediaModal;
                if (mediaModal && typeof mediaModal.open === 'function') {
                    mediaModal.open({
                        onSelect: function(media) {
                            addImage(media);
                        },
                        allowMultiple: true
                    });
                } else {
                    // Fallback: trigger file input
                    fileInput.click();
                }
            });
        }
        
        // Upload new images
        if (uploadBtn && fileInput) {
            uploadBtn.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                if (files.length > 0) {
                    uploadImages(files);
                }
            });
        }
        
        // Clear all images
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to clear all images?')) {
                    clearAll();
                }
            });
        }
        
        // Remove single image (delegated event)
        previewContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('gallery-remove') || e.target.closest('.gallery-remove')) {
                const btn = e.target.classList.contains('gallery-remove') ? e.target : e.target.closest('.gallery-remove');
                const mediaId = btn.getAttribute('data-id');
                if (mediaId) {
                    removeImage(mediaId);
                }
            }
        });
        
        function addImage(media) {
            const mediaId = media.id || media;
            if (!currentImages.includes(mediaId)) {
                currentImages.push(mediaId);
                updateValue();
                updatePreview();
            }
        }
        
        function removeImage(mediaId) {
            currentImages = currentImages.filter(id => id !== mediaId);
            updateValue();
            updatePreview();
        }
        
        function clearAll() {
            currentImages = [];
            updateValue();
            updatePreview();
        }
        
        function updateValue() {
            hiddenInput.value = JSON.stringify(currentImages);
            
            // Show/hide clear button
            if (clearBtn) {
                clearBtn.hidden = currentImages.length === 0;
            }
        }
        
        function updatePreview() {
            if (!previewContainer) return;
            
            if (currentImages.length === 0) {
                previewContainer.innerHTML = '<div class="gallery-empty">No images yet</div>';
                return;
            }
            
            // Load media info for all images
            Promise.all(currentImages.map(id => loadMediaInfo(id)))
                .then(items => {
                    const removeIcon = (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon('x', { size: 13 }) : '×';
                    let html = '<div class="gallery-grid">';
                    items.forEach(item => {
                        if (item) {
                            const mediaId = item.id || '';
                            const url = item.url || '';
                            const name = (item.name || mediaId).replace(/"/g, '&quot;');
                            const mime = String(item.type || '');
                            const mediaTag = mime.indexOf('video/') === 0
                                ? '<video src="' + url + '" controls preload="metadata"></video>'
                                : '<img src="' + url + '" alt="' + name + '">';
                            html += `
                                <div class="gallery-item" data-id="${mediaId}">
                                    ${mediaTag}
                                    <button type="button" class="gallery-remove" data-id="${mediaId}" title="Remove" aria-label="Remove ${name}">${removeIcon}</button>
                                </div>
                            `;
                        }
                    });
                    html += '</div>';
                    previewContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading gallery preview:', error);
                    previewContainer.innerHTML = '<div class="gallery-empty">Error loading images</div>';
                });
        }
        
        function loadMediaInfo(mediaId) {
            return fetch(Birkly.url('/api/admin_media.php?action=get&id=' + encodeURIComponent(mediaId)), { credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok' && data.data) {
                        return data.data;
                    }
                    return null;
                })
                .catch(error => {
                    console.error('Error loading media info:', error);
                    return null;
                });
        }
        
        function uploadImages(files) {
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            const uploadPromises = Array.from(files).map(file => {
                const formData = new FormData();
                formData.append('file', file);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                if (csrf) formData.append('_csrf', csrf);

                return fetch(Birkly.url('/api/admin_media.php?action=upload'), {
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
                    if (data.status === 'ok' && data.data) {
                        return data.data;
                    }
                    throw new Error(data.error || data.message || 'Upload failed');
                });
            });
            
            Promise.all(uploadPromises)
                .then(results => {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload Images';
                    
                    results.forEach(media => {
                        if (media && media.id) {
                            addImage(media);
                        }
                    });
                    
                    // Clear file input
                    if (fileInput) fileInput.value = '';
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload Images';
                    alert('Upload failed: ' + (error.message || 'Unknown error'));
                });
        }
        
        // Initial preview
        updatePreview();
    }
    
    // Initialize all gallery fields
    function initAllGalleryFields() {
        document.querySelectorAll('.gallery-field').forEach(function(container) {
            const fieldName = container.id.replace('field_', '');
            if (fieldName) {
                initGalleryField(fieldName);
            }
        });
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllGalleryFields);
    } else {
        initAllGalleryFields();
    }
    
    // Also initialize on custom event for dynamic fields
    document.addEventListener('fieldtype:init', function(e) {
        if (e.detail && e.detail.fieldtype === 'gallery') {
            const fieldName = e.detail.fieldName || e.detail.element?.id?.replace('field_', '');
            if (fieldName) {
                initGalleryField(fieldName);
            }
        }
    });
    
    // Expose for manual initialization
    window.initGalleryField = initGalleryField;
})();

