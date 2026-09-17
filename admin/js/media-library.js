/**
 * Media Library JavaScript
 * Handles file uploads, media grid, and media management
 */

(function() {
    'use strict';

    function mdT(key, fallback, params) {
        let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
            ? Birkly.i18n.t('admin.' + key, fallback)
            : (fallback != null ? fallback : key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

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
                    BirklyFeedback.toast(
                        mdT('media_library_js_err_upload', 'Error uploading file:') + ' ' + (data.error || mdT('error_unknown', 'Unknown error')),
                        'error'
                    );
                }
            })
            .catch(error => {
                BirklyFeedback.toast(mdT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
            });
        });
    }
    
    // Media grid interactions
    const mediaItems = document.querySelectorAll('.media-item');
    mediaItems.forEach(item => {
        item.addEventListener('click', async function () {
            // Handle media item selection
        });
    });
    
    // Delete media handler
    const deleteButtons = document.querySelectorAll('.delete-media-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.stopPropagation();
            const mediaId = this.getAttribute('data-media-id');
            
            if (await BirklyFeedback.confirm(mdT('media_library_js_confirm_delete', 'Are you sure you want to delete this media file?'))) {
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
                        BirklyFeedback.toast(
                            mdT('media_library_js_err_delete', 'Error deleting media:') + ' ' + (data.error || mdT('error_unknown', 'Unknown error')),
                            'error'
                        );
                    }
                })
                .catch(error => {
                    BirklyFeedback.toast(mdT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
                });
            }
        });
    });
})();
