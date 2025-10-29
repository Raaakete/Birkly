// Media Modal System for HaruCMS
window.HaruCMS = window.HaruCMS || {};
window.HaruCMS.MediaModal = {
    isOpen: false,
    currentCallback: null,
    
    // Open media modal
    open(callback) {
        console.log('MediaModal.open() called');
        
        // Prevent opening if already open
        if (this.isOpen) {
            console.log('Modal already open, closing first');
            this.close();
        }
        
        this.currentCallback = callback;
        this.isOpen = true;
        console.log('Rendering modal...');
        this.render();
        console.log('Appending modal to body...');
        document.body.appendChild(this.modal);
        console.log('Loading media...');
        this.loadMedia();
        
        // Add keyboard escape handler
        this.escapeHandler = (e) => {
            if (e.key === 'Escape') {
                console.log('Escape key pressed, closing modal');
                this.close();
            }
        };
        document.addEventListener('keydown', this.escapeHandler);
        
        console.log('Media modal opened successfully');
    },
    
    // Close media modal
    close() {
        console.log('MediaModal.close() called');
        
        // Remove keyboard escape handler
        if (this.escapeHandler) {
            document.removeEventListener('keydown', this.escapeHandler);
            this.escapeHandler = null;
        }
        
        if (this.modal && this.modal.parentNode) {
            console.log('Removing modal from DOM');
            this.modal.parentNode.removeChild(this.modal);
        } else {
            console.log('Modal not found in DOM');
        }
        
        this.isOpen = false;
        this.currentCallback = null;
        console.log('Modal closed successfully');
    },
    
    // Force close modal (emergency method)
    forceClose() {
        console.log('MediaModal.forceClose() called');
        
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
        console.log('Modal force closed');
    },
    
    // Render modal HTML
    render() {
        console.log('MediaModal.render() called');
        this.modal = document.createElement('div');
        this.modal.className = 'media-modal-overlay';
        this.modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;';
        this.modal.innerHTML = `
            <div class="media-modal" style="background: white; border-radius: 8px; width: 80%; max-width: 800px; max-height: 80vh; display: flex; flex-direction: column;">
                <div class="media-modal-header" style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0;">Select Media</h3>
                    <button type="button" class="media-modal-close" onclick="HaruCMS.MediaModal.close()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                </div>
                <div class="media-modal-content" style="padding: 20px; flex: 1; overflow-y: auto;">
                    <div class="media-upload-section" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                        <input type="file" id="media-upload" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('media-upload').click()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Upload New Media
                        </button>
                    </div>
                    <div class="media-grid" id="media-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                        <div class="media-loading" style="text-align: center; padding: 40px; color: #666;">Loading media...</div>
                    </div>
                </div>
                <div class="media-modal-footer" style="padding: 20px; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="HaruCMS.MediaModal.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </div>
        `;
        
        console.log('Modal HTML created:', this.modal.outerHTML.substring(0, 200) + '...');
        
        // Add event listeners
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                console.log('Modal overlay clicked, closing');
                this.close();
            }
        });
        
        // Handle file upload
        const fileInput = this.modal.querySelector('#media-upload');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                this.handleFileUpload(e.target.files[0]);
            });
        }
        
        console.log('Modal event listeners added');
    },
    
    // Load media from API
    async loadMedia() {
        console.log('MediaModal.loadMedia() called');
        
        // Check if modal is still open
        if (!this.isOpen || !this.modal) {
            console.log('Modal no longer open, skipping media load');
            return;
        }
        
        const grid = this.modal.querySelector('#media-grid');
        if (!grid) {
            console.log('Media grid not found');
            return;
        }
        
        grid.innerHTML = '<div class="media-loading" style="text-align: center; padding: 40px; color: #666;">Loading media...</div>';
        
        try {
            console.log('Fetching media from API...');
            const response = await fetch('/api/media.php?action=list');
            const result = await response.json();
            
            // Check if modal is still open before rendering
            if (!this.isOpen || !this.modal) {
                console.log('Modal closed while loading media, skipping render');
                return;
            }
            
            if (result.status === 'ok' && result.data) {
                console.log('Media loaded successfully, rendering grid');
                this.renderMediaGrid(result.data);
            } else {
                console.log('No media found or API error');
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
        console.log('MediaModal.renderMediaGrid() called with', media.length, 'items');
        
        // Check if modal is still open before rendering
        if (!this.isOpen || !this.modal) {
            console.log('Modal no longer open, skipping grid render');
            return;
        }
        
        const grid = this.modal.querySelector('#media-grid');
        if (!grid) {
            console.log('Media grid element not found');
            return;
        }
        
        grid.innerHTML = '';
        
        media.forEach((item, index) => {
            console.log('Creating media item', index, ':', item.name);
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
                console.log('Media item clicked:', item);
                // Check if modal is still open before selecting
                if (this.isOpen) {
                    this.selectMedia(item);
                } else {
                    console.log('Modal no longer open, ignoring click');
                }
            });
            
            grid.appendChild(mediaItem);
        });
        
        console.log('Media grid rendered with', media.length, 'items');
    },
    
    // Handle file upload
    async handleFileUpload(file) {
        if (!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await fetch('/api/media.php?action=upload', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'ok' && result.data) {
                this.selectMedia(result.data);
            } else {
                alert('Upload failed: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Upload failed: ' + error.message);
        }
    },
    
    // Select media and close modal
    selectMedia(media) {
        console.log('MediaModal.selectMedia() called with:', media);
        
        if (this.currentCallback) {
            console.log('Calling callback with selected media');
            this.currentCallback(media);
        } else {
            console.log('No callback found for media selection');
        }
        
        console.log('Closing modal after selection');
        this.close();
    }
};

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
    border-color: #007bff;
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