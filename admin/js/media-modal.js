// Media Modal System for Birkly
window.Birkly = window.Birkly || {};

function birklyMediaModalResolveUrl(url) {
    if (!url) return '';
    if (/^https?:\/\//i.test(url)) return url;
    if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
        return Birkly.url(url.replace(/^\//, ''));
    }
    return url;
}

function birklyMediaModalHasPermission(permission) {
    const perms = window.Birkly?.currentUser?.permissions;
    // An empty list means the shell did not publish permissions. The upload API still checks auth.
    if (!Array.isArray(perms) || perms.length === 0) return true;
    if (perms.includes('*') || perms.includes(permission)) return true;
    if (permission === 'read_media' && perms.includes('manage_media')) return true;
    if (permission === 'upload' && (perms.includes('manage_media') || perms.includes('upload'))) return true;
    return false;
}

function birklyMediaModalItemMatchesAccept(item, accept) {
    const raw = String(accept || '').trim().toLowerCase();
    if (!raw || raw === '*/*' || raw === '*') return true;
    const parts = raw.split(',').map(function (part) { return part.trim(); }).filter(Boolean);
    if (!parts.length) return true;
    const mime = String(item.type || '').toLowerCase();
    const name = String(item.name || item.url || '').toLowerCase();
    const ext = name.indexOf('.') >= 0 ? name.slice(name.lastIndexOf('.')) : '';
    const videoExt = ['.mp4', '.m4v', '.webm', '.ogv', '.mov'];
    const audioExt = ['.mp3', '.wav', '.ogg'];
    return parts.some(function (part) {
        if (part === '*/*' || part === '*') return true;
        if (part.charAt(0) === '.') return ext === part;
        if (part.endsWith('/*')) {
            const prefix = part.slice(0, -1);
            if (mime.indexOf(prefix) === 0) return true;
            if (part === 'video/*') return videoExt.indexOf(ext) !== -1;
            if (part === 'audio/*') return audioExt.indexOf(ext) !== -1;
            if (part === 'image/*') return ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'].indexOf(ext) !== -1;
            return false;
        }
        return mime === part;
    });
}

function birklyMediaModalEscapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}

function birklyMediaModalIcon(name, size) {
    return (typeof Birkly !== 'undefined' && typeof Birkly.icon === 'function')
        ? Birkly.icon(name, { size: size })
        : '';
}

function birklyMediaModalT(key, fallback, params) {
    if ((typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')) {
        return Birkly.i18n.t('admin.' + key, fallback, params);
    }
    let text = fallback != null ? fallback : key;
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            var value = String(params[name]);
            text = String(text).split('{{' + name + '}}').join(value);
            text = text.split('{' + name + '}').join(value);
        });
    }
    return text;
}

window.Birkly.MediaModal = {
    isOpen: false,
    currentCallback: null,
    accept: '',
    
    // Open media modal
    open(options) {
        
        // Prevent opening if already open
        if (this.isOpen) {
            this.close();
        }
        
        // Handle both callback function and options object
        this.accept = '';
        if (typeof options === 'function') {
            this.currentCallback = options;
        } else if (options && typeof options.onSelect === 'function') {
            this.currentCallback = options.onSelect;
            this.accept = String(options.accept || '');
        } else {
            this.currentCallback = null;
            if (options && typeof options === 'object') {
                this.accept = String(options.accept || '');
            }
        }
        
        this.isOpen = true;
        this.render();
        document.body.appendChild(this.modal);
        this.loadMedia();
        
        // Add keyboard escape handler
        this.escapeHandler = (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        };
        document.addEventListener('keydown', this.escapeHandler);
        
    },
    
    // Close media modal
    close() {
        
        // Remove keyboard escape handler
        if (this.escapeHandler) {
            document.removeEventListener('keydown', this.escapeHandler);
            this.escapeHandler = null;
        }
        
        if (this.modal && this.modal.parentNode) {
            this.modal.parentNode.removeChild(this.modal);
        } else {
        }
        
        this.isOpen = false;
        this.currentCallback = null;
    },
    
    // Force close modal (emergency method)
    forceClose() {
        
        // Remove all modal overlays
        const overlays = document.querySelectorAll('.media-modal-overlay');
        overlays.forEach(overlay => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        });
        
        this.isOpen = false;
        this.currentCallback = null;
        this.modal = null;
    },
    
    // Render modal HTML
    render() {
        this.modal = document.createElement('div');
        this.modal.className = 'modal-backdrop media-modal-overlay show';
        this.modal.setAttribute('role', 'dialog');
        this.modal.setAttribute('aria-modal', 'true');
        this.modal.setAttribute('data-birch', '');
        this.modal.innerHTML = `
            <div class="modal-content modal--lg">
                <div class="modal-header">
                    <h3 class="modal-title">Select media</h3>
                    <button type="button" class="modal-close" data-media-modal-action="close" aria-label="Close">${birklyMediaModalIcon('x', 13) || '&times;'}</button>
                </div>
                <div class="modal-body media-picker-body">
                    <div class="media-picker-bar">
                    <span class="select-box">
                        <select id="birkly-media-modal-folder" aria-label="Folder">
                            <option value="all">All media</option>
                            <option value="">Root folder</option>
                        </select>
                        ${birklyMediaModalIcon('chev-down', 13)}
                    </span>
                    <div class="media-upload-section">
                        <input type="file" id="birkly-media-modal-upload" accept="${birklyMediaModalEscapeHtml((window.Birkly && Birkly.mediaUploadAccept) || '')}" hidden>
                        <button type="button" class="button" data-media-modal-action="upload-trigger">${birklyMediaModalIcon('upload', 13)}Upload new media</button>
                    </div>
                    </div>
                    <div class="media-picker-grid" id="birkly-media-modal-grid">
                        <div class="loading-state"><p class="empty-state-text">Loading media…</p></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button" data-media-modal-action="close">Cancel</button>
                </div>
            </div>
        `;
        

        // Permission gating (UX only): hide upload controls if user can't upload
        try {
            const uploadSection = this.modal.querySelector('.media-upload-section');
            if (!birklyMediaModalHasPermission('upload') && uploadSection) {
                uploadSection.hidden = true;
            }
        } catch (_) {}
        
        // Bind actions on the overlay (CSP-safe — no inline handlers).
        // Backdrop close uses e.target === this.modal, so inner dialog clicks
        // must NOT stopPropagation or Cancel / Upload never reach this handler.
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
                return;
            }
            const actionEl = e.target.closest('[data-media-modal-action]');
            if (!actionEl || !this.modal.contains(actionEl)) return;
            const action = actionEl.getAttribute('data-media-modal-action');
            if (action === 'close') {
                this.close();
            } else if (action === 'upload-trigger') {
                const fileInput = this.modal.querySelector('#birkly-media-modal-upload');
                if (fileInput) fileInput.click();
            }
        });

        const folderSelect = this.modal.querySelector('#birkly-media-modal-folder');
        if (folderSelect) {
            folderSelect.addEventListener('change', () => this.loadMedia());
            this.loadFolders(folderSelect);
        }

        const fileInput = this.modal.querySelector('#birkly-media-modal-upload');
        if (fileInput && this.accept && this.accept !== '*/*' && this.accept !== '*') {
            fileInput.setAttribute('accept', this.accept);
        }
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (!birklyMediaModalHasPermission('upload')) {
                    window.Birkly?.Utils?.showPermissionDenied?.({ action: 'Upload media' });
                    e.target.value = '';
                    return;
                }
                this.handleFileUpload(e.target.files[0]);
            });
        }
        
    },
    
    // Folders for the picker's filter (media page folder tree, flattened)
    async loadFolders(select) {
        try {
            const response = await fetch(Birkly.url('/api/admin_media.php?action=folders'), { credentials: 'same-origin' });
            const result = await response.json();
            if (result.status !== 'ok' || !Array.isArray(result.data)) return;
            result.data.forEach((folder) => {
                const option = document.createElement('option');
                option.value = folder.path || '';
                option.textContent = '\u00a0\u00a0'.repeat(Number(folder.level || 0)) + (folder.name || folder.path || '');
                select.appendChild(option);
            });
        } catch (_) { /* the filter stays at All media / Root folder */ }
    },

    currentFolder() {
        const select = this.modal && this.modal.querySelector('#birkly-media-modal-folder');
        return select ? select.value : 'all';
    },

    // Load media from API
    async loadMedia() {
        
        // Check if modal is still open
        if (!this.isOpen || !this.modal) {
            return;
        }
        
        const grid = this.modal.querySelector('#birkly-media-modal-grid');
        if (!grid) {
            return;
        }
        
        grid.innerHTML = '<div class="loading-state"><p class="empty-state-text">Loading media…</p></div>';
        
        try {
            const response = await fetch(Birkly.url('/api/admin_media.php?action=list&folder=' + encodeURIComponent(this.currentFolder())), { credentials: 'same-origin' });
            const result = await response.json();
            
            // Check if modal is still open before rendering
            if (!this.isOpen || !this.modal) {
                return;
            }
            
            if (result.status === 'ok' && Array.isArray(result.data)) {
                const visible = result.data.filter((item) => birklyMediaModalItemMatchesAccept(item, this.accept));
                if (!visible.length) {
                    grid.innerHTML = '<div class="empty-state empty-state--compact"><p class="empty-state-text">No media files yet. Upload one above.</p></div>';
                    return;
                }
                this.renderMediaGrid(visible);
            } else {
                const msg = result.message || result.error || (response.status === 403 ? 'You do not have permission to view media.' : 'Failed to load media');
                grid.innerHTML = '<div class="error-state"><p class="empty-state-text">' + birklyMediaModalEscapeHtml(msg) + '</p></div>';
            }
        } catch (error) {
            console.error('Error loading media:', error);
            if (this.isOpen && this.modal) {
                grid.innerHTML = '<div class="error-state"><p class="empty-state-text">Error loading media</p></div>';
            }
        }
    },
    
    // Render media grid
    renderMediaGrid(media) {
        
        // Check if modal is still open before rendering
        if (!this.isOpen || !this.modal) {
            return;
        }
        
        const grid = this.modal.querySelector('#birkly-media-modal-grid');
        if (!grid) {
            return;
        }
        
        grid.innerHTML = '';
        
        media.forEach((item) => {
            const mediaItem = document.createElement('button');
            mediaItem.type = 'button';
            mediaItem.className = 'media-picker-item';
            const imgUrl = birklyMediaModalResolveUrl(item.url || item.thumbnail || '');
            const isImage = String(item.type || item.category || '').indexOf('image') === 0 || /\.(jpe?g|png|gif|webp|svg)$/i.test(item.name || '');
            const previewHtml = isImage && imgUrl
                ? '<img src="' + birklyMediaModalEscapeHtml(imgUrl) + '" alt="' + birklyMediaModalEscapeHtml(item.name || '') + '">'
                : (birklyMediaModalIcon('content', 26) || birklyMediaModalEscapeHtml(item.category || item.type || 'File'));
            mediaItem.innerHTML =
                '<span class="media-picker-thumb">' + previewHtml + '</span>' +
                '<span class="media-picker-name">' + birklyMediaModalEscapeHtml(item.name || item.title || item.id || 'Media') + '</span>' +
                '<span class="media-picker-size">' + birklyMediaModalEscapeHtml(String(item.size_formatted || item.size || '')) + '</span>';
            
            mediaItem.addEventListener('click', () => {
                if (this.isOpen) {
                    this.selectMedia(item);
                }
            });
            
            grid.appendChild(mediaItem);
        });
        
    },
    
    // Handle file upload
    async handleFileUpload(file) {
        if (!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        const folder = this.currentFolder();
        formData.append('folder', folder === 'all' ? '' : folder);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (csrf) formData.append('_csrf', csrf);

        try {
            const response = await fetch(Birkly.url('/api/admin_media.php?action=upload'), {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'ok' && result.data) {
                if (window.BirklyFeedback && typeof window.BirklyFeedback.toast === 'function') {
                    window.BirklyFeedback.toast(birklyMediaModalT('media_js_upload_success', 'Media uploaded'), 'success');
                }
                await this.loadMedia();
                this.selectMedia(result.data);
            } else {
                const msg = result.message || result.error || birklyMediaModalT('error_unknown', 'Unknown error');
                const failPrefix = birklyMediaModalT('media_js_upload_failed_prefix', 'Upload failed:');
                if (window.BirklyFeedback && typeof window.BirklyFeedback.toast === 'function') {
                    window.BirklyFeedback.toast(failPrefix + ' ' + msg, 'error');
                } else {
                    alert(failPrefix + ' ' + msg);
                }
            }
        } catch (error) {
            console.error('Upload error:', error);
            const failPrefix = birklyMediaModalT('media_js_upload_failed_prefix', 'Upload failed:');
            const errMsg = (error && error.message) ? error.message : birklyMediaModalT('error_unknown', 'Unknown error');
            if (window.BirklyFeedback && typeof window.BirklyFeedback.toast === 'function') {
                window.BirklyFeedback.toast(failPrefix + ' ' + errMsg, 'error');
            } else {
                alert(failPrefix + ' ' + errMsg);
            }
        }
    },
    
    // Select media and close modal
    selectMedia(media) {
        
        if (this.currentCallback) {
            this.currentCallback(media);
        } else {
        }
        
        this.close();
    }
};

// Create alias for easier access
window.MediaModal = window.Birkly.MediaModal;
