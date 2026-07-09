/**
 * Media Library JavaScript
 * Handles file uploads, media grid, and media management
 */

(function() {
    'use strict';

    const mediaData = window.mediaData || {};
    const csrfToken = mediaData.csrfToken || '';
    
    
    // File upload handling
    const uploadForm = document.getElementById('media-upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const csrf = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (csrf) formData.append('_csrf', csrf);

            fetch(Birkly.url('/api/admin_media.php?action=upload'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': csrf
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error uploading file: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });
    }
    
    // Media grid interactions
    const mediaItems = document.querySelectorAll('.media-item');
    mediaItems.forEach(item => {
        item.addEventListener('click', function() {
            // Handle media item selection
        });
    });
    
    // Delete media handler
    const deleteButtons = document.querySelectorAll('.delete-media-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const mediaId = this.getAttribute('data-media-id');
            
            if (confirm('Are you sure you want to delete this media file?')) {
                const csrf = csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
                const body = new FormData();
                body.append('id', mediaId);
                if (csrf) body.append('_csrf', csrf);

                fetch(Birkly.url('/api/admin_media.php?action=delete'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-Token': csrf
                    },
                    body: body
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting media: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        });
    });
})();
