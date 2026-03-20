/**
 * Media Library JavaScript
 * Handles file uploads, media grid, and media management
 */

(function() {
    'use strict';

    const mediaData = window.mediaData || {};
    const csrfToken = mediaData.csrfToken || '';
    
    console.log('Media library initialized');
    
    // File upload handling
    const uploadForm = document.getElementById('media-upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/api/media.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
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
            console.log('Media item clicked:', this);
        });
    });
    
    // Delete media handler
    const deleteButtons = document.querySelectorAll('.delete-media-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const mediaId = this.getAttribute('data-media-id');
            
            if (confirm('Are you sure you want to delete this media file?')) {
                fetch('/api/media.php?id=' + mediaId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-Token': csrfToken,
                        'Content-Type': 'application/json'
                    }
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
