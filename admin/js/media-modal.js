// Media Modal System for Birkly
window.Birkly = window.Birkly || {};
window.Birkly.MediaModal = {
    isOpen: false,
    currentCallback: null,
    
    // Open media modal
    open(options) {
        
        // Prevent opening if already open
        if (this.isOpen) {
            this.close();
        }
        
        // Handle both callback function and options object
        if (typeof options === 'function') {
            this.currentCallback = options;
        } else if (options && typeof options.onSelect === 'function') {
            this.currentCallback = options.onSelect;
        } else {
            this.currentCallback = null;
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
        this.modal.className = 'media-modal-overlay';
        this.modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 12000;';
        this.modal.innerHTML = `
            <div class="media-modal" style="background: white; border-radius: 8px; width: 80%; max-width: 800px; max-height: 80vh; display: flex; flex-direction: column;">
                <div class="media-modal-header" style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0;">Select Media</h3>
                    <button type="button" class="media-modal-close" onclick="Birkly.MediaModal.close()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div class="media-modal-content" style="padding: 20px; flex: 1; overflow-y: auto;">
                    <div class="media-upload-section" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                        <input type="file" id="media-upload" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('media-upload').click()" style="padding: 8px 16px; background: var(--color-primary, #7CE3BF); color: var(--text-inverse, #fff); border: none; border-radius: 4px; cursor: pointer;">
                            Upload New Media
                        </button>
                    </div>
                    <div class="media-grid" id="media-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                        <div class="media-loading" style="text-align: center; padding: 40px; color: #666;">Loading media...</div>
                    </div>
                </div>
                <div class="media-modal-footer" style="padding: 20px; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="Birkly.MediaModal.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </div>
        `;
        

        // Permission gating (UX only): hide upload controls if user can't upload
        try {
            const perms = window.Birkly?.currentUser?.permissions || [];
            const canUpload = Array.isArray(perms) && (perms.includes('*') || perms.includes('upload') || perms.includes('manage_media'));
            const uploadSection = this.modal.querySelector('.media-upload-section');
            if (!canUpload && uploadSection) {
                uploadSection.style.display = 'none';
            }
        } catch (_) {}
        
        // Add event listeners
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });
        
        // Handle file upload
        const fileInput = this.modal.querySelector('#media-upload');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                // Permission gating (UX only): block upload attempt for users without upload permission
                try {
                    const perms = window.Birkly?.currentUser?.permissions || [];
                    const canUpload = Array.isArray(perms) && (perms.includes('*') || perms.includes('upload') || perms.includes('manage_media'));
                    if (!canUpload) {
                        window.Birkly?.Utils?.showPermissionDenied?.({ action: 'Upload media' });
                        e.target.value = '';
                        return;
                    }
                } catch (_) {}
                this.handleFileUpload(e.target.files[0]);
            });
        }
        
    },
    
    // Load media from API
    async loadMedia() {
        
        // Check if modal is still open
        if (!this.isOpen || !this.modal) {
            return;
        }
        
        const grid = this.modal.querySelector('#media-grid');
        if (!grid) {
            return;
        }
        
        grid.innerHTML = '<div class="media-loading" style="text-align: center; padding: 40px; color: #666;">Loading media...</div>';
        
        try {
            const response = await fetch(Birkly.url('/api/admin_media.php?action=list'), { credentials: 'same-origin' });
            const result = await response.json();
            
            // Check if modal is still open before rendering
            if (!this.isOpen || !this.modal) {
                return;
            }
            
            if (result.status === 'ok' && result.data) {
                this.renderMediaGrid(result.data);
            } else {
                if (this.isOpen && this.modal) {
                    grid.innerHTML = '<div class="media-error" style="text-align: center; padding: 40px; color: #666;">No media found</div>';
                }
            }
        } catch (error) {
            console.error('Error loading media:', error);
            if (this.isOpen && this.modal) {
                grid.innerHTML = '<div class="media-error" style="text-align: center; padding: 40px; color: #666;">Error loading media</div>';
            }
        }
    },
    
    // Render media grid
    renderMediaGrid(media) {
        
        // Check if modal is still open before rendering
        if (!this.isOpen || !this.modal) {
            return;
        }
        
        const grid = this.modal.querySelector('#media-grid');
        if (!grid) {
            return;
        }
        
        grid.innerHTML = '';
        
        media.forEach((item, index) => {
            const mediaItem = document.createElement('div');
            mediaItem.className = 'media-item';
            mediaItem.style.cssText = 'border: 1px solid #ddd; border-radius: 4px; padding: 10px; cursor: pointer; transition: all 0.2s;';
            mediaItem.innerHTML = `
                <div class="media-item-preview" style="margin-bottom: 8px;">
                    <img src="${item.url}" alt="${item.name}" style="width: 100%; height: 100px; object-fit: cover; border-radius: 4px;">
                </div>
                <div class="media-item-info" style="text-align: center;">
                    <div class="media-item-name" style="font-weight: 500; font-size: 12px; margin-bottom: 4px;">${item.name}</div>
                    <div class="media-item-size" style="font-size: 10px; color: #666;">${item.size} bytes</div>
                </div>
            `;
            
            mediaItem.addEventListener('click', () => {
                // Check if modal is still open before selecting
                if (this.isOpen) {
                    this.selectMedia(item);
                } else {
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
                this.selectMedia(result.data);
            } else {
                BirklyFeedback.toast('Upload failed: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            BirklyFeedback.toast('Upload failed: ' + error.message, 'error')
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

// Add CSS for media modal
const mediaModalCSS = `
.media-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.media-modal {
    background: white;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.media-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.media-modal-header h3 {
    margin: 0;
}

.media-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.media-modal-content {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.media-upload-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.media-item {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.media-item:hover {
    border-color: var(--color-primary, #7CE3BF);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.media-item-preview {
    margin-bottom: 8px;
}

.media-item-preview img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
}

.media-item-info {
    text-align: center;
}

.media-item-name {
    font-weight: 500;
    font-size: 12px;
    margin-bottom: 4px;
}

.media-item-size {
    font-size: 10px;
    color: #666;
}

.media-loading, .media-error {
    text-align: center;
    padding: 40px;
    color: #666;
}

.media-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}
`;

// Inject CSS when DOM is ready
function injectMediaModalCSS() {
    // Check if CSS is already injected
    if (document.getElementById('media-modal-css')) {
        return;
    }
    
    const style = document.createElement('style');
    style.id = 'media-modal-css';
    style.textContent = mediaModalCSS;
    document.head.appendChild(style);
}

// Inject CSS immediately if DOM is ready, otherwise wait
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectMediaModalCSS);
} else {
    injectMediaModalCSS();
} 