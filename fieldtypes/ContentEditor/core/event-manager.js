// Event management for ContentEditor
window.ContentEditorEventManager = {
  /**
   * Initialize event listeners for an editor instance
   * @param {Element} editor - The editor element
   * @param {Element} toolbar - The toolbar element
   * @param {Element} hiddenInput - The hidden input element
   * @param {string} fieldName - The field name
   */
  init(editor, toolbar, hiddenInput, fieldName) {
    // Store references
    this.editor = editor;
    this.toolbar = toolbar;
    this.hiddenInput = hiddenInput;
    this.fieldName = fieldName;
    
    // Initialize history manager
    if (window.ContentEditorHistory) {
      window.ContentEditorHistory.init(editor, hiddenInput);
    }
    
    // Initialize event listeners
    this.attachToolbarEvents();
    this.attachEditorEvents();
    this.attachKeyboardEvents();
    this.attachContentSync();
  },

  /**
   * Attach toolbar event listeners
   */
  attachToolbarEvents() {
    // Use event delegation at document level to handle clicks from all toolbars (inline and fullscreen)
    // Use a flag to prevent multiple listeners
    if (!window._contentEditorToolbarClickHandlerAttached) {
      window._contentEditorToolbarClickHandlerAttached = true;
      const manager = window.ContentEditorEventManager;
      document.addEventListener('click', (e) => {
        // Only handle clicks within toolbars
        const toolbar = e.target.closest('.content-editor-toolbar');
        if (!toolbar) return;
        
        // Don't handle clicks on color picker buttons - they have their own handlers
        if (e.target.closest('.color-picker-btn') || e.target.closest('.color-picker-input') || e.target.closest('.color-hex-input')) {
          return;
        }

        // Clear inline display:none set by admin dropdown.js on shared .dropdown-menu class
        toolbar.querySelectorAll('.toolbar-dropdown.active .dropdown-menu').forEach((menu) => {
          menu.style.removeProperty('display');
        });
        
        // Handle the click (use prototype — not first editor instance)
        manager.handleToolbarClick.call(manager, e);
      });
    }

    // Close dropdowns when clicking outside (works for all toolbars)
    // Use a flag to prevent multiple listeners
    if (!window._contentEditorDropdownHandlerAttached) {
      window._contentEditorDropdownHandlerAttached = true;
      document.addEventListener('mousedown', (e) => {
        // Don't close if clicking inside color picker dropdown or any input
        if (e.target.closest('.color-picker-menu') || 
            e.target.closest('.color-picker-content') ||
            e.target.closest('.color-hex-input') ||
            e.target.closest('.color-picker-btn') ||
            e.target.closest('.color-wheel') ||
            e.target.closest('.color-wheel-container') ||
            e.target.closest('.color-brightness') ||
            e.target.closest('.color-brightness-container')) {
          // Save selection when interacting with color picker and store on dropdown
          window.ContentEditorSelection.save();
          // Find the active color dropdown in any toolbar
          const allToolbars = document.querySelectorAll('.content-editor-toolbar');
          for (const toolbar of allToolbars) {
            const colorDropdown = toolbar.querySelector('.toolbar-dropdown.active');
            if (colorDropdown && colorDropdown.querySelector('.color-picker-menu')) {
              if (window.ContentEditorSelection.savedRange) {
                colorDropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
              }
              if (window.ContentEditorSelection.savedSelectionData) {
                colorDropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
              }
              break;
            }
          }
          return;
        }
        
        const clickedDropdown = e.target.closest('.toolbar-dropdown');
        if (!clickedDropdown) {
          // Clicking outside any dropdown - find all active dropdowns in all toolbars
          const allToolbars = document.querySelectorAll('.content-editor-toolbar');
          for (const toolbar of allToolbars) {
            const activeDropdown = toolbar.querySelector('.toolbar-dropdown.active');
            if (!activeDropdown) continue;
            
            // Find the editor for this toolbar
            const wrapper = toolbar.closest('.content-editor-wrapper');
            const editor = wrapper ? wrapper.querySelector('.content-editor-area') : null;
            
            if (activeDropdown.querySelector('.color-picker-menu')) {
              // Color picker dropdown
              // Save selection before closing color picker and store on dropdown
              window.ContentEditorSelection.save();
              if (window.ContentEditorSelection.savedRange) {
                activeDropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
              }
              if (window.ContentEditorSelection.savedSelectionData) {
                activeDropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
              }
              
              // Close the dropdown
              activeDropdown.classList.remove('active');
              activeDropdown._isLivePreviewActive = false;
              
              // Restore selection immediately after closing (to prevent loss)
              if (editor) {
                setTimeout(() => {
                  // Try to restore from dropdown's saved data
                  if (activeDropdown._savedSelectionData) {
                    try {
                      const startNode = window.ContentEditorSelection.getNodeFromPath(editor, activeDropdown._savedSelectionData.startPath);
                      const endNode = window.ContentEditorSelection.getNodeFromPath(editor, activeDropdown._savedSelectionData.endPath);
                      
                      if (startNode && endNode) {
                        const range = document.createRange();
                        const startOffset = Math.min(activeDropdown._savedSelectionData.startOffset, startNode.textContent ? startNode.textContent.length : 0);
                        const endOffset = Math.min(activeDropdown._savedSelectionData.endOffset, endNode.textContent ? endNode.textContent.length : 0);
                        range.setStart(startNode, startOffset);
                        range.setEnd(endNode, endOffset);
                        
                        const selection = window.getSelection();
                        selection.removeAllRanges();
                        selection.addRange(range);
                        return;
                      }
                    } catch (err) {
                      // Fall through to next strategy
                    }
                  }
                  
                  // Fallback: try to restore from saved range
                  if (activeDropdown._savedSelection) {
                    try {
                      const selection = window.getSelection();
                      selection.removeAllRanges();
                      selection.addRange(activeDropdown._savedSelection.cloneRange());
                    } catch (err) {
                      // Try global restore
                      window.ContentEditorSelection.restore(editor);
                    }
                  } else {
                    // Last resort: try global restore
                    window.ContentEditorSelection.restore(editor);
                  }
                }, 10);
              }
            } else {
              // Not a color picker dropdown, just close it
              activeDropdown.classList.remove('active');
            }
          }
        }
      });
    }
  },

  /**
   * Get editor/toolbar/hiddenInput from event target (works for both inline and fullscreen)
   */
  getEditorFromEvent(e) {
    // Find the toolbar that contains the clicked element
    const toolbar = e.target.closest('.content-editor-toolbar');
    if (!toolbar) return null;
    
    // Find the wrapper (works for both inline and fullscreen)
    const wrapper = toolbar.closest('.content-editor-wrapper');
    if (!wrapper) return null;
    
    // Find the editor
    const editor = wrapper.querySelector('.content-editor-area');
    if (!editor) return null;
    
    // Find the hidden input
    const fieldName = wrapper.dataset.fieldName || editor.dataset.fieldName;
    const hiddenInput = fieldName ? document.getElementById('field_' + fieldName) : null;
    
    return { toolbar, editor, hiddenInput, wrapper };
  },

  /**
   * Handle toolbar click events
   */
  handleToolbarClick(e) {
    // Get the correct editor/toolbar from the event (works for both inline and fullscreen)
    const editorInfo = this.getEditorFromEvent(e);
    if (!editorInfo) {
      // Fallback to stored references when click is outside a resolved wrapper
      return this.handleToolbarClickForInstance(e, {
        toolbar: this.toolbar,
        editor: this.editor,
        hiddenInput: this.hiddenInput,
        wrapper: null
      });
    }
    this.handleToolbarClickForInstance(e, editorInfo);
  },

  handleToolbarClickForInstance(e, editorInfo) {
    const { toolbar, editor, hiddenInput } = editorInfo;
    
    // Handle dropdown items
    const dropdownItem = e.target.closest('.dropdown-item');
    if (dropdownItem) {
      e.preventDefault();
      e.stopPropagation();
      
      // Save selection before toolbar interaction
      window.ContentEditorSelection.save();
      
      const command = dropdownItem.dataset.command;
      const value = dropdownItem.dataset.value;
      
      if (command === 'formatBlock' && value) {
        // Format block command - ensure we have a valid value
        window.ContentEditorCommandRegistry.execute('formatBlock', value);
        // Small delay to ensure DOM is updated
        setTimeout(() => {
          if (window.ContentEditorToolbar) {
            window.ContentEditorToolbar.updateState(editor, toolbar);
          }
          if (window.ContentEditorSync && hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }, 10);
      } else if (command && command.startsWith('justify')) {
        // Alignment commands
        window.ContentEditorCommandRegistry.execute(command, value);
        setTimeout(() => {
          if (window.ContentEditorToolbar) {
            window.ContentEditorToolbar.updateState(editor, toolbar);
          }
          if (window.ContentEditorSync && hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }, 10);
      } else if (command && (command === 'insertVideo' || command === 'insertAudio' || command === 'insertMaps' || command === 'insertLinkPreview' || command === 'insertIframe' || command === 'insertCustomEmbed')) {
        // Media embed commands
        window.ContentEditorCommandRegistry.execute(command, value);
        // Close dropdown after command
        const dropdown = dropdownItem.closest('.toolbar-dropdown');
        if (dropdown) {
          dropdown.classList.remove('active');
        }
        return;
      }
      
      // Close dropdown
      const dropdown = dropdownItem.closest('.toolbar-dropdown');
      if (dropdown) {
        dropdown.classList.remove('active');
        dropdown._isLivePreviewActive = false;
      }
      return;
    }
    
    const btn = e.target.closest('.toolbar-btn');
    if (!btn) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const command = btn.dataset.command;
    const value = btn.dataset.value;
    
    // Handle undo/redo commands
    if (command === 'undo') {
      if (window.ContentEditorHistory) {
        window.ContentEditorHistory.undo();
      }
      return;
    }
    
    if (command === 'redo') {
      if (window.ContentEditorHistory) {
        window.ContentEditorHistory.redo();
      }
      return;
    }
    
    // Save selection before toolbar interaction
    window.ContentEditorSelection.save();
    
    if (command === 'heading' || command === 'align' || command === 'textColor' || command === 'insertMedia') {
      // Dropdown toggle buttons - just toggle the dropdown
      const dropdown = btn.closest('.toolbar-dropdown');
      if (dropdown) {
        const isOpening = !dropdown.classList.contains('active');
        
        // For color picker, always save selection before any interaction
        if (command === 'textColor') {
          // Force save selection immediately
          window.ContentEditorSelection.save();
          // Also store it on the dropdown for later use (both range and serialized data)
          if (window.ContentEditorSelection.savedRange) {
            dropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
          }
          if (window.ContentEditorSelection.savedSelectionData) {
            dropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
          }
        }
        
        dropdown.classList.toggle('active');

        const menu = dropdown.querySelector('.dropdown-menu');
        if (menu) {
          menu.style.removeProperty('display');
        }
        
        // Initialize color picker if it's the textColor dropdown
        if (command === 'textColor' && dropdown.querySelector('.color-wheel')) {
          // Small delay to ensure dropdown is visible before drawing canvas
          setTimeout(() => {
            this.initColorPicker(dropdown);
            // Update colors from current selection when opening
            if (isOpening) {
              this.updateColorPickerFromSelection(dropdown);
            }
            // Enable live preview when dropdown opens
            dropdown._isLivePreviewActive = true;
          }, 10);
        }
      }
    } else {
      // Other commands
      window.ContentEditorCommandRegistry.execute(command, value);
      
      // Save state to history after command
      if (window.ContentEditorHistory) {
        setTimeout(() => {
          window.ContentEditorHistory.saveState();
        }, 50);
      }
      
      if (window.ContentEditorToolbar) {
        window.ContentEditorToolbar.updateState(editor, toolbar);
      }
      if (window.ContentEditorSync && hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
  },

  /**
   * Attach editor event listeners
   */
  attachEditorEvents() {
    // Save selection on mouseup in editor (when user finishes selecting)
    this.editor.addEventListener('mouseup', (e) => {
      // Don't save if clicking inside color picker
      const activeElement = document.activeElement;
      if (activeElement && (activeElement.type === 'color' || activeElement.classList.contains('color-hex-input'))) {
        return;
      }
      
      // Small delay to ensure selection is complete
      setTimeout(() => {
        window.ContentEditorSelection.save();
        // Update color picker if it's open
        this.updateOpenColorPicker();
      }, 10);
    });
    
    // Save selection on keyup (when user moves cursor with keyboard)
    this.editor.addEventListener('keyup', () => {
      window.ContentEditorSelection.save();
      // Update color picker if it's open
      this.updateOpenColorPicker();
    });
    
    // Save selection on selectionchange (most reliable)
    document.addEventListener('selectionchange', () => {
      // Don't update if native color picker is open
      const activeElement = document.activeElement;
      if (activeElement && activeElement.type === 'color') {
        return;
      }
      
      // Check if color picker dropdown is open
      const colorDropdown = this.toolbar.querySelector('.toolbar-dropdown.active');
      const isColorPickerOpen = colorDropdown && colorDropdown.querySelector('.color-picker-menu');
      
      // Only save if selection is within our editor
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        if (this.editor.contains(range.commonAncestorContainer)) {
          window.ContentEditorSelection.save();
          
          // If color picker is open, also store on dropdown
          if (isColorPickerOpen) {
            if (window.ContentEditorSelection.savedRange) {
              colorDropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
            }
            if (window.ContentEditorSelection.savedSelectionData) {
              colorDropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
            }
          }
        } else if (isColorPickerOpen && selection.isCollapsed) {
          // Selection was lost but color picker is open - restore it
          setTimeout(() => {
            if (colorDropdown._savedSelectionData && this.editor) {
              try {
                const startNode = window.ContentEditorSelection.getNodeFromPath(this.editor, colorDropdown._savedSelectionData.startPath);
                const endNode = window.ContentEditorSelection.getNodeFromPath(this.editor, colorDropdown._savedSelectionData.endPath);
                
                if (startNode && endNode) {
                  const range = document.createRange();
                  const startOffset = Math.min(colorDropdown._savedSelectionData.startOffset, startNode.textContent ? startNode.textContent.length : 0);
                  const endOffset = Math.min(colorDropdown._savedSelectionData.endOffset, endNode.textContent ? endNode.textContent.length : 0);
                  range.setStart(startNode, startOffset);
                  range.setEnd(endNode, endOffset);
                  
                  const sel = window.getSelection();
                  sel.removeAllRanges();
                  sel.addRange(range);
                }
              } catch (err) {
                // Try range fallback
                if (colorDropdown._savedSelection) {
                  try {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(colorDropdown._savedSelection.cloneRange());
                  } catch (e) {
                    // Ignore
                  }
                }
              }
            }
          }, 10);
        }
      }
      
      this.updateOpenColorPicker();
    });
    
    // Save selection before blur (when editor loses focus)
    this.editor.addEventListener('blur', (e) => {
      // Always save selection before blur if color picker is open
      const colorDropdown = this.toolbar.querySelector('.toolbar-dropdown.active');
      if (colorDropdown && colorDropdown.querySelector('.color-picker-menu')) {
        // Save selection before focus moves away
        window.ContentEditorSelection.save();
        // Store on dropdown as backup
        if (window.ContentEditorSelection.savedRange) {
          colorDropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
        }
        if (window.ContentEditorSelection.savedSelectionData) {
          colorDropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
        }
      }
    });
  },
  
  /**
   * Update color picker if dropdown is open
   */
  updateOpenColorPicker() {
    // Find color picker dropdown
    const allDropdowns = this.toolbar.querySelectorAll('.toolbar-dropdown');
    let colorDropdown = null;
    
    for (const dropdown of allDropdowns) {
      if (dropdown.classList.contains('active') && 
          (dropdown.querySelector('.color-picker-menu') || 
           dropdown.querySelector('[data-command="textColor"]'))) {
        colorDropdown = dropdown;
        break;
      }
    }
    
    if (colorDropdown) {
      this.updateColorPickerFromSelection(colorDropdown);
    }
  },

  /**
   * Attach keyboard event listeners
   */
  attachKeyboardEvents() {
    this.editor.addEventListener('keydown', (e) => {
      this.handleKeydown(e);
    });
  },

  /**
   * Handle keyboard events
   */
  handleKeydown(e) {
    // Save selection on keydown
    window.ContentEditorSelection.save();
    
    // Delegate to keyboard handler if it exists
    if (window.ContentEditorKeyboard && window.ContentEditorKeyboard.handle) {
      window.ContentEditorKeyboard.handle(e, this.editor, this.toolbar, this.hiddenInput);
    }
  },

  /**
   * Attach content synchronization
   */
  attachContentSync() {
    // Sync on input
    this.editor.addEventListener('input', () => {
      // Ensure content is wrapped in block elements
      this.ensureBlockStructure();
      
      if (window.ContentEditorSync) {
        window.ContentEditorSync.toHidden(this.editor, this.hiddenInput);
      }
      
      // Save state to history (debounced)
      if (window.ContentEditorHistory) {
        window.ContentEditorHistory.saveStateDebounced();
      }
    });
    
    // Smart paste detection for URLs and paste sanitization
    // Use event delegation to handle paste from any editor (inline or fullscreen)
    if (!window._contentEditorPasteHandlerAttached) {
      window._contentEditorPasteHandlerAttached = true;
      document.addEventListener('paste', (e) => {
        // Only handle paste events within editors
        const editor = e.target.closest('.content-editor-area');
        if (!editor) return;
        
        // Get pasted content from clipboard
        const clipboardData = e.clipboardData || window.clipboardData;
        const pastedText = clipboardData.getData('text/plain');
        const pastedHTML = clipboardData.getData('text/html');
        
        // Check if pasted content is a URL
        if (this.isURL(pastedText)) {
          e.preventDefault(); // Prevent default paste
          
          // Detect URL type and show smart paste dialog
          const urlType = this.detectURLType(pastedText);
          this.showSmartPasteDialog(pastedText, urlType, e);
          return;
        }
        
        // If we have HTML content, sanitize it
        if (pastedHTML) {
          e.preventDefault(); // Prevent default paste
          
          // Sanitize the HTML content
          const sanitizedHTML = this.sanitizePastedHTML(pastedHTML);
          
          // Insert sanitized content
          this.insertSanitizedContent(editor, sanitizedHTML);
          
          // Ensure block structure
          this.ensureBlockStructure(editor);
          
          // Sync to hidden input
          const wrapper = editor.closest('.content-editor-wrapper');
          const fieldName = wrapper ? (wrapper.dataset.fieldName || editor.dataset.fieldName) : null;
          const hiddenInput = fieldName ? document.getElementById('field_' + fieldName) : null;
          
          if (window.ContentEditorSync && hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
          
          // Save state to history after paste
          if (window.ContentEditorHistory) {
            const history = window.ContentEditorHistory;
            if (history.editor === editor) {
              history.saveState();
            }
          }
          
          return;
        }
        
        // Plain text paste - let it happen normally but ensure block structure
        setTimeout(() => {
          // Find the editor instance's hidden input
          const wrapper = editor.closest('.content-editor-wrapper');
          const fieldName = wrapper ? (wrapper.dataset.fieldName || editor.dataset.fieldName) : null;
          const hiddenInput = fieldName ? document.getElementById('field_' + fieldName) : null;
          
          // Ensure block structure
          this.ensureBlockStructure(editor);
          
          if (window.ContentEditorSync && hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
          
          // Save state to history after paste
          if (window.ContentEditorHistory) {
            // Find the history manager for this editor
            // History manager is initialized per editor, so we need to trigger it
            // For now, just save if available
            const history = window.ContentEditorHistory;
            if (history.editor === editor) {
              history.saveState();
            }
          }
        }, 10);
      }, true); // Use capture phase to intercept before default paste
    }
  },

  /**
   * Initialize color picker
   */
  initColorPicker(dropdown) {
    // Check if already initialized to avoid duplicate listeners
    if (dropdown._colorPickerInitialized) {
      return;
    }
    dropdown._colorPickerInitialized = true;
    
    const textColorPreview = dropdown.querySelector('#text-color-preview');
    const textColorHex = dropdown.querySelector('#text-color-hex');
    const textColorWheel = dropdown.querySelector('#text-color-wheel');
    const textColorMarker = dropdown.querySelector('#text-color-marker');
    const textColorBrightness = dropdown.querySelector('#text-color-brightness');
    const textColorBrightnessMarker = dropdown.querySelector('#text-color-brightness-marker');
    
    const bgColorPreview = dropdown.querySelector('#bg-color-preview');
    const bgColorHex = dropdown.querySelector('#bg-color-hex');
    const bgColorWheel = dropdown.querySelector('#bg-color-wheel');
    const bgColorMarker = dropdown.querySelector('#bg-color-marker');
    const bgColorBrightness = dropdown.querySelector('#bg-color-brightness');
    const bgColorBrightnessMarker = dropdown.querySelector('#bg-color-brightness-marker');
    
    const applyBtn = dropdown.querySelector('.color-picker-apply');
    const removeBtn = dropdown.querySelector('.color-picker-remove');
    
    // Initialize color picker state with reasonable defaults
    // Text color: red at medium brightness
    dropdown._textColorState = { hue: 0, saturation: 1, brightness: 0.5 };
    // Background color: start with a visible color (light blue) so wheel is interactive
    // Use brightness 0.5 instead of 0.95 to ensure colors are visible
    dropdown._bgColorState = { hue: 200, saturation: 0.3, brightness: 0.5 };
    
    // Store reference to editor for live preview
    const editor = this.editor;
    let livePreviewTimeout = null;
    const isLivePreviewActive = () => dropdown._isLivePreviewActive || false;
    
    // Update color from state (define these functions first)
    const updateTextColor = (applyLivePreview = false) => {
      if (!window.ContentEditorColorPicker) return;
      const state = dropdown._textColorState;
      const rgb = window.ContentEditorColorPicker.hslToRgb(state.hue / 360, state.saturation, state.brightness);
      const hex = window.ContentEditorColorPicker.rgbToHex(rgb[0], rgb[1], rgb[2]);
      if (textColorHex) textColorHex.value = hex;
      if (textColorPreview) textColorPreview.style.backgroundColor = hex;
      
      // Live preview
      if (applyLivePreview && isLivePreviewActive() && editor) {
        clearTimeout(livePreviewTimeout);
        livePreviewTimeout = setTimeout(() => {
          if (window.ContentEditorCommands && window.ContentEditorSelection) {
            window.ContentEditorSelection.restore();
            window.ContentEditorCommands.applyTextColor(hex);
          }
        }, 50);
      }
    };
    
    const updateBgColor = (applyLivePreview = false, skipHexUpdate = false) => {
      if (!window.ContentEditorColorPicker) return;
      // Ensure state exists
      if (!dropdown._bgColorState) {
        dropdown._bgColorState = { hue: 200, saturation: 0.3, brightness: 0.5 };
      }
      const state = dropdown._bgColorState;
      // If brightness is 1 (white), use a reasonable brightness for color calculation
      let brightness = state.brightness;
      if (brightness >= 0.99) {
        brightness = 0.5; // Use medium brightness so color is visible
      }
      const rgb = window.ContentEditorColorPicker.hslToRgb(state.hue / 360, state.saturation, brightness);
      const hex = window.ContentEditorColorPicker.rgbToHex(rgb[0], rgb[1], rgb[2]);
      
      // Always update hex input (use programmatic method to avoid triggering input event)
      // This ensures the apply button can always read the current color
      if (bgColorHex) {
        if (dropdown._setBgHexProgrammatic) {
          dropdown._setBgHexProgrammatic(hex);
        } else {
          bgColorHex.value = hex;
        }
      }
      if (bgColorPreview) bgColorPreview.style.backgroundColor = hex;
      
      // Live preview
      if (applyLivePreview && isLivePreviewActive() && editor) {
        clearTimeout(livePreviewTimeout);
        livePreviewTimeout = setTimeout(() => {
          if (window.ContentEditorCommands && window.ContentEditorSelection) {
            window.ContentEditorSelection.restore();
            window.ContentEditorCommands.applyBgColor(hex);
          }
        }, 50);
      }
    };
    
    // Initialize color wheels
    if (window.ContentEditorColorPicker) {
      // Draw color wheels with fixed brightness (0.5) to show full color spectrum
      // The actual selected brightness is controlled by the brightness slider
      if (textColorWheel) {
        window.ContentEditorColorPicker.drawColorWheel(textColorWheel, 0.5);
      }
      if (bgColorWheel) {
        window.ContentEditorColorPicker.drawColorWheel(bgColorWheel, 0.5);
      }
      // Draw initial brightness sliders
      if (textColorBrightness) {
        window.ContentEditorColorPicker.drawBrightnessSlider(textColorBrightness, dropdown._textColorState.hue / 360, dropdown._textColorState.saturation);
      }
      if (bgColorBrightness) {
        window.ContentEditorColorPicker.drawBrightnessSlider(bgColorBrightness, dropdown._bgColorState.hue / 360, dropdown._bgColorState.saturation);
      }
      
      // Set initial marker positions
      if (textColorWheel && textColorMarker) {
        const centerX = textColorWheel.width / 2;
        const centerY = textColorWheel.height / 2;
        const radius = Math.min(centerX, centerY) - 2;
        const innerRadius = radius * 0.3;
        const angle = (dropdown._textColorState.hue * Math.PI) / 180;
        const distance = dropdown._textColorState.saturation * (radius - innerRadius) + innerRadius;
        const markerX = centerX + Math.cos(angle) * distance;
        const markerY = centerY + Math.sin(angle) * distance;
        textColorMarker.style.left = (markerX - 6) + 'px';
        textColorMarker.style.top = (markerY - 6) + 'px';
        textColorMarker.style.display = 'block';
      }
      if (textColorBrightness && textColorBrightnessMarker) {
        const brightnessX = dropdown._textColorState.brightness * textColorBrightness.width;
        textColorBrightnessMarker.style.left = (brightnessX - 6) + 'px';
        textColorBrightnessMarker.style.display = 'block';
      }
      if (bgColorWheel && bgColorMarker) {
        const centerX = bgColorWheel.width / 2;
        const centerY = bgColorWheel.height / 2;
        const radius = Math.min(centerX, centerY) - 2;
        const innerRadius = radius * 0.3;
        const angle = (dropdown._bgColorState.hue * Math.PI) / 180;
        const distance = dropdown._bgColorState.saturation * (radius - innerRadius) + innerRadius;
        const markerX = centerX + Math.cos(angle) * distance;
        const markerY = centerY + Math.sin(angle) * distance;
        bgColorMarker.style.left = (markerX - 6) + 'px';
        bgColorMarker.style.top = (markerY - 6) + 'px';
        bgColorMarker.style.display = 'block';
      }
      if (bgColorBrightness && bgColorBrightnessMarker) {
        const brightnessX = dropdown._bgColorState.brightness * bgColorBrightness.width;
        bgColorBrightnessMarker.style.left = (brightnessX - 6) + 'px';
        bgColorBrightnessMarker.style.display = 'block';
      }
      
      // Update initial colors from state
      updateTextColor();
      updateBgColor();
    }
    
    // Handle color wheel interaction
    const setupColorWheel = (wheel, marker, brightnessCanvas, brightnessMarker, stateKey, updateFn) => {
      if (!wheel || !window.ContentEditorColorPicker) {
        console.warn('Color wheel setup failed:', { wheel: !!wheel, colorPicker: !!window.ContentEditorColorPicker });
        return;
      }
      
      if (!marker) {
        console.warn('Color wheel marker not found');
        return;
      }
      
      let isDragging = false;
      let wheelId = wheel.id || 'unknown';
      
      const handleWheelInteraction = (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Mark that user is actively interacting
        dropdown._isUserInteracting = true;
        
        // Save selection before interaction
        window.ContentEditorSelection.save();
        if (window.ContentEditorSelection.savedRange) {
          dropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
        }
        if (window.ContentEditorSelection.savedSelectionData) {
          dropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
        }
        
        const rect = wheel.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const colorData = window.ContentEditorColorPicker.getColorFromWheel(wheel, x, y);
        if (colorData) {
          // Update state first - ensure we're modifying the actual state object
          if (!dropdown[stateKey]) {
            dropdown[stateKey] = { hue: 0, saturation: 0, brightness: 0.5 };
          }
          // Update hue and saturation, preserve existing brightness
          // But if brightness is 1 (white), set it to a reasonable default (0.5) so colors are visible
          let currentBrightness = dropdown[stateKey].brightness || 0.5;
          if (currentBrightness >= 0.99) {
            // If brightness is at maximum (white), use a medium brightness so colors are visible
            currentBrightness = 0.5;
          }
          dropdown[stateKey].hue = colorData.hue;
          dropdown[stateKey].saturation = colorData.saturation;
          dropdown[stateKey].brightness = currentBrightness;
          
          // Update marker position immediately (before calling updateFn)
          const centerX = wheel.width / 2;
          const centerY = wheel.height / 2;
          const radius = Math.min(centerX, centerY) - 2;
          const innerRadius = radius * 0.3;
          const angle = (colorData.hue * Math.PI) / 180;
          const distance = colorData.saturation * (radius - innerRadius) + innerRadius;
          const markerX = centerX + Math.cos(angle) * distance;
          const markerY = centerY + Math.sin(angle) * distance;
          marker.style.left = (markerX - 6) + 'px';
          marker.style.top = (markerY - 6) + 'px';
          marker.style.display = 'block';
          
          // Update brightness slider (keep wheel at 0.5 brightness for visibility)
          if (brightnessCanvas && window.ContentEditorColorPicker) {
            window.ContentEditorColorPicker.drawBrightnessSlider(brightnessCanvas, colorData.hue / 360, colorData.saturation);
          }
          
          // Update color preview and hex
          if (stateKey === '_bgColorState') {
            // Force update to ensure hex input is synced
            const state = dropdown._bgColorState;
            // If brightness is 1, use a reasonable brightness for color calculation
            let brightness = state.brightness;
            if (brightness >= 0.99) {
              brightness = 0.5; // Use medium brightness so color is visible
            }
            const rgb = window.ContentEditorColorPicker.hslToRgb(state.hue / 360, state.saturation, brightness);
            const hex = window.ContentEditorColorPicker.rgbToHex(rgb[0], rgb[1], rgb[2]);
            if (bgColorHex && dropdown._setBgHexProgrammatic) {
              dropdown._setBgHexProgrammatic(hex);
            }
            updateBgColor(true, false); // Enable live preview, update hex input
          } else {
            updateFn(true); // Enable live preview for text color
          }
        }
      };
      
      wheel.addEventListener('mousedown', (e) => {
        isDragging = true;
        handleWheelInteraction(e);
      });
      
      const handleMouseUp = () => {
        isDragging = false;
        // Clear interaction flag after a short delay
        setTimeout(() => {
          dropdown._isUserInteracting = false;
        }, 100);
      };
      
      const handleMouseMove = (e) => {
        if (isDragging) {
          handleWheelInteraction(e);
        }
      };
      
      document.addEventListener('mousemove', handleMouseMove);
      document.addEventListener('mouseup', handleMouseUp);
      
      // Store cleanup function
      wheel._cleanupColorWheel = () => {
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
      };
    };
    
    // Handle brightness slider interaction
    const setupBrightnessSlider = (slider, marker, wheel, stateKey, updateFn) => {
      if (!slider || !window.ContentEditorColorPicker) {
        console.warn('Brightness slider setup failed:', { slider: !!slider, colorPicker: !!window.ContentEditorColorPicker });
        return;
      }
      
      if (!marker) {
        console.warn('Brightness slider marker not found');
        return;
      }
      
      let isDragging = false;
      
      const handleSliderInteraction = (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Mark that user is actively interacting
        dropdown._isUserInteracting = true;
        
        // Save selection before interaction
        window.ContentEditorSelection.save();
        if (window.ContentEditorSelection.savedRange) {
          dropdown._savedSelection = window.ContentEditorSelection.savedRange.cloneRange();
        }
        if (window.ContentEditorSelection.savedSelectionData) {
          dropdown._savedSelectionData = JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData));
        }
        
        const rect = slider.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const brightness = window.ContentEditorColorPicker.getBrightnessFromSlider(slider, x);
        
        // Ensure state object exists
        if (!dropdown[stateKey]) {
          dropdown[stateKey] = { hue: 0, saturation: 0, brightness: 0.5 };
        }
        dropdown[stateKey].brightness = brightness;
        updateFn(true); // Enable live preview
        
        // Update marker position
        marker.style.left = (x - 6) + 'px';
        marker.style.display = 'block';
        
        // Keep color wheel at fixed brightness (0.5) for visibility
        // Don't redraw wheel based on brightness - it should always show full spectrum
      };
      
      slider.addEventListener('mousedown', (e) => {
        isDragging = true;
        handleSliderInteraction(e);
      });
      
      const handleMouseMove = (e) => {
        if (isDragging) {
          handleSliderInteraction(e);
        }
      };
      
      const handleMouseUp = () => {
        isDragging = false;
        // Clear interaction flag after a short delay
        setTimeout(() => {
          dropdown._isUserInteracting = false;
        }, 100);
      };
      
      document.addEventListener('mousemove', handleMouseMove);
      document.addEventListener('mouseup', handleMouseUp);
      
      // Store cleanup function
      slider._cleanupBrightnessSlider = () => {
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
      };
    };
    
    // Setup text color wheel and brightness
    setupColorWheel(textColorWheel, textColorMarker, textColorBrightness, textColorBrightnessMarker, '_textColorState', updateTextColor);
    setupBrightnessSlider(textColorBrightness, textColorBrightnessMarker, textColorWheel, '_textColorState', updateTextColor);
    
    // Setup bg color wheel and brightness
    setupColorWheel(bgColorWheel, bgColorMarker, bgColorBrightness, bgColorBrightnessMarker, '_bgColorState', updateBgColor);
    setupBrightnessSlider(bgColorBrightness, bgColorBrightnessMarker, bgColorWheel, '_bgColorState', updateBgColor);
    
    // Sync hex input with preview and update color wheel state
    if (textColorHex && textColorPreview && window.ContentEditorColorPicker) {
      textColorHex.addEventListener('input', (e) => {
        e.stopPropagation();
        const value = e.target.value.trim().toUpperCase();
        if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
          textColorPreview.style.backgroundColor = value;
          
          // Update color wheel state from hex
          const rgb = window.ContentEditorColorPicker.hexToRgb(value);
          if (rgb) {
            const hsl = window.ContentEditorColorPicker.rgbToHsl(rgb[0], rgb[1], rgb[2]);
            dropdown._textColorState = {
              hue: hsl[0],
              saturation: hsl[1],
              brightness: hsl[2]
            };
            
            // Update wheel and brightness
            if (textColorWheel) {
              window.ContentEditorColorPicker.drawColorWheel(textColorWheel, 0.5);
            }
            if (textColorBrightness) {
              window.ContentEditorColorPicker.drawBrightnessSlider(textColorBrightness, hsl[0] / 360, hsl[1]);
            }
            
            // Update markers
            if (textColorMarker && textColorWheel) {
              const centerX = textColorWheel.width / 2;
              const centerY = textColorWheel.height / 2;
              const angle = (hsl[0] * Math.PI) / 180;
              const distance = hsl[1] * (Math.min(centerX, centerY) - 2);
              const markerX = centerX + Math.cos(angle) * distance;
              const markerY = centerY + Math.sin(angle) * distance;
              textColorMarker.style.left = (markerX - 6) + 'px';
              textColorMarker.style.top = (markerY - 6) + 'px';
            }
            if (textColorBrightnessMarker && textColorBrightness) {
              const brightnessX = hsl[2] * textColorBrightness.width;
              textColorBrightnessMarker.style.left = (brightnessX - 6) + 'px';
            }
            
            // Live preview
            updateTextColor(true);
          }
        }
      });
      
      textColorHex.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          e.stopPropagation();
          if (applyBtn) applyBtn.click();
        }
      });
    }
    
    if (bgColorHex && bgColorPreview && window.ContentEditorColorPicker) {
      let isProgrammaticUpdate = false;
      
      bgColorHex.addEventListener('input', (e) => {
        // Skip if this is a programmatic update
        if (isProgrammaticUpdate) {
          isProgrammaticUpdate = false;
          return;
        }
        
        e.stopPropagation();
        const value = e.target.value.trim().toUpperCase();
        if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
          bgColorPreview.style.backgroundColor = value;
          
          // Update color wheel state from hex
          const rgb = window.ContentEditorColorPicker.hexToRgb(value);
          if (rgb) {
            const hsl = window.ContentEditorColorPicker.rgbToHsl(rgb[0], rgb[1], rgb[2]);
            dropdown._bgColorState = {
              hue: hsl[0],
              saturation: hsl[1],
              brightness: hsl[2]
            };
            
            // Update wheel and brightness
            if (bgColorWheel) {
              window.ContentEditorColorPicker.drawColorWheel(bgColorWheel, 0.5);
            }
            if (bgColorBrightness) {
              window.ContentEditorColorPicker.drawBrightnessSlider(bgColorBrightness, hsl[0] / 360, hsl[1]);
            }
            
            // Update markers
            if (bgColorMarker && bgColorWheel) {
              const centerX = bgColorWheel.width / 2;
              const centerY = bgColorWheel.height / 2;
              const radius = Math.min(centerX, centerY) - 2;
              const innerRadius = radius * 0.3;
              const angle = (hsl[0] * Math.PI) / 180;
              const distance = hsl[1] * (radius - innerRadius) + innerRadius;
              const markerX = centerX + Math.cos(angle) * distance;
              const markerY = centerY + Math.sin(angle) * distance;
              bgColorMarker.style.left = (markerX - 6) + 'px';
              bgColorMarker.style.top = (markerY - 6) + 'px';
              bgColorMarker.style.display = 'block';
            }
            if (bgColorBrightnessMarker && bgColorBrightness) {
              const brightnessX = hsl[2] * bgColorBrightness.width;
              bgColorBrightnessMarker.style.left = (brightnessX - 6) + 'px';
              bgColorBrightnessMarker.style.display = 'block';
            }
            
            // Live preview
            updateBgColor(true, true); // Skip hex update to avoid loop
          }
        }
      });
      
      // Store flag setter for programmatic updates
      dropdown._setBgHexProgrammatic = (value) => {
        isProgrammaticUpdate = true;
        if (bgColorHex) {
          bgColorHex.value = value;
        }
      };
      
      bgColorHex.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          e.stopPropagation();
          if (applyBtn) applyBtn.click();
        }
      });
    }
    
    // Apply colors
    if (applyBtn) {
      applyBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Disable live preview before applying (final apply)
        dropdown._isLivePreviewActive = false;
        
        // Try to restore selection - use multiple fallback strategies
        let selectionRestored = false;
        
        // Strategy 1: Try to restore from dropdown's serialized data
        if (dropdown._savedSelectionData && this.editor) {
          try {
            const startNode = window.ContentEditorSelection.getNodeFromPath(this.editor, dropdown._savedSelectionData.startPath);
            const endNode = window.ContentEditorSelection.getNodeFromPath(this.editor, dropdown._savedSelectionData.endPath);
            
            if (startNode && endNode) {
              const range = document.createRange();
              const startOffset = Math.min(dropdown._savedSelectionData.startOffset, startNode.textContent ? startNode.textContent.length : 0);
              const endOffset = Math.min(dropdown._savedSelectionData.endOffset, endNode.textContent ? endNode.textContent.length : 0);
              range.setStart(startNode, startOffset);
              range.setEnd(endNode, endOffset);
              
              const selection = window.getSelection();
              selection.removeAllRanges();
              selection.addRange(range);
              selectionRestored = true;
            }
          } catch (err) {
            console.warn('Failed to restore from serialized data:', err);
          }
        }
        
        // Strategy 2: Try to restore from dropdown's saved range
        if (!selectionRestored && dropdown._savedSelection) {
          try {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(dropdown._savedSelection.cloneRange());
            selectionRestored = true;
          } catch (err) {
            console.warn('Failed to restore selection from dropdown range:', err);
          }
        }
        
        // Strategy 3: Try to restore from global saved selection
        if (!selectionRestored) {
          selectionRestored = window.ContentEditorSelection.restore(this.editor);
        }
        
        // Strategy 4: Last resort - try to get current selection
        if (!selectionRestored) {
          const selection = window.getSelection();
          if (selection.rangeCount === 0 || selection.isCollapsed) {
            // No valid selection - try to find the last saved selection in the editor
            console.warn('No selection available to apply color to');
          }
        }
        
        // Get colors from state (more reliable than hex input)
        let textColor = '';
        let bgColor = '';
        
        // Try to get from state first, then fall back to hex input
        if (dropdown._textColorState && window.ContentEditorColorPicker) {
          try {
            const state = dropdown._textColorState;
            const rgb = window.ContentEditorColorPicker.hslToRgb(state.hue / 360, state.saturation, state.brightness);
            textColor = window.ContentEditorColorPicker.rgbToHex(rgb[0], rgb[1], rgb[2]);
          } catch (e) {
            console.warn('Error calculating text color from state:', e);
          }
        }
        // Fallback to hex input if state didn't work
        if (!textColor && textColorHex) {
          textColor = textColorHex.value.trim().toUpperCase();
        }
        
        if (dropdown._bgColorState && window.ContentEditorColorPicker) {
          try {
            const state = dropdown._bgColorState;
            // If brightness is 1, use a reasonable brightness for color calculation
            let brightness = state.brightness;
            if (brightness >= 0.99) {
              brightness = 0.5; // Use medium brightness so color is visible
            }
            const rgb = window.ContentEditorColorPicker.hslToRgb(state.hue / 360, state.saturation, brightness);
            bgColor = window.ContentEditorColorPicker.rgbToHex(rgb[0], rgb[1], rgb[2]);
          } catch (e) {
            console.warn('Error calculating bg color from state:', e);
          }
        }
        // Fallback to hex input if state didn't work
        if (!bgColor && bgColorHex) {
          bgColor = bgColorHex.value.trim().toUpperCase();
        }
        
        // Debug logging
        // Debug logging has been removed
        
        // If bgColor is still white/default, try to recalculate from state one more time
        if (bgColor === '#FFFFFF' && dropdown._bgColorState && window.ContentEditorColorPicker) {
          const state = dropdown._bgColorState;
          // Only recalculate if state has non-default values
          if (state.hue !== 0 || state.saturation !== 0 || state.brightness !== 1) {
            // If brightness is 1, it will always be white - use a reasonable brightness instead
            let brightness = state.brightness;
            if (brightness >= 0.99) {
              brightness = 0.5; // Use medium brightness so color is visible
            }
            const rgb = window.ContentEditorColorPicker.hslToRgb(state.hue / 360, state.saturation, brightness);
            bgColor = window.ContentEditorColorPicker.rgbToHex(rgb[0], rgb[1], rgb[2]);
          }
        }
        
        // Only apply if colors are valid and different from defaults
        let applied = false;
        
        if (textColor && /^#[0-9A-Fa-f]{6}$/.test(textColor) && textColor !== '#000000') {
          try {
            window.ContentEditorCommands.applyTextColor(textColor);
            applied = true;
          } catch (e) {
            console.error('Error applying text color:', e);
          }
        }
        
        if (bgColor && /^#[0-9A-Fa-f]{6}$/.test(bgColor)) {
          // Don't skip white - apply it if user selected it
          try {
            window.ContentEditorCommands.applyBgColor(bgColor);
            applied = true;
          } catch (e) {
            console.error('Error applying bg color:', e);
          }
        }
        
        // Close dropdown
        dropdown.classList.remove('active');
        dropdown._isLivePreviewActive = false;
        
        // Save to history only if we actually applied something
        if (applied && window.ContentEditorHistory) {
          setTimeout(() => {
            window.ContentEditorHistory.saveState();
          }, 50);
        }
      });
    }
    
    // Remove colors
    if (removeBtn) {
      removeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Save selection before removing
        window.ContentEditorSelection.save();
        
        window.ContentEditorCommands.removeTextColor();
        window.ContentEditorCommands.removeBgColor();
        
        // Close dropdown
        dropdown.classList.remove('active');
        dropdown._isLivePreviewActive = false;
        
        // Save to history
        if (window.ContentEditorHistory) {
          setTimeout(() => {
            window.ContentEditorHistory.saveState();
          }, 50);
        }
      });
    }
    
    // Detect current colors from selection
    this.updateColorPickerFromSelection(dropdown);
  },

  /**
   * Update color picker values from current selection
   */
  updateColorPickerFromSelection(dropdown) {
    // Don't update if user is actively interacting with the color picker
    if (dropdown._isUserInteracting) {
      return;
    }
    
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      // No selection, clear color picker
      this.clearColorPicker(dropdown);
      return;
    }
    
    const range = selection.getRangeAt(0);
    
    // Convert to hex if needed
    const toHex = (color) => {
      if (!color) return '';
      if (color.startsWith('#')) return color.toUpperCase();
      
      // Try to parse rgb/rgba
      const match = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
      if (match) {
        const r = parseInt(match[1]).toString(16).padStart(2, '0');
        const g = parseInt(match[2]).toString(16).padStart(2, '0');
        const b = parseInt(match[3]).toString(16).padStart(2, '0');
        return `#${r}${g}${b}`.toUpperCase();
      }
      
      return color;
    };
    
    // Find all elements in the selection range
    let textColor = '';
    let bgColor = '';
    
    if (range.collapsed) {
      // Collapsed selection - check current block
      const block = window.ContentEditorCommands ? window.ContentEditorCommands.getCurrentBlock() : null;
      if (block) {
        if (block.style && block.style.color) {
          textColor = block.style.color;
        }
        if (block.style && block.style.backgroundColor) {
          bgColor = block.style.backgroundColor;
        }
      }
    } else {
      // Has selection - find colors in selected elements
      const walker = document.createTreeWalker(
        range.commonAncestorContainer,
        NodeFilter.SHOW_ELEMENT,
        {
          acceptNode: (node) => {
            if (range.intersectsNode(node)) {
              return NodeFilter.FILTER_ACCEPT;
            }
            return NodeFilter.FILTER_SKIP;
          }
        }
      );
      
      let node;
      while (node = walker.nextNode()) {
        if (node.style) {
          // Get computed style to handle inherited colors
          const computed = window.getComputedStyle(node);
          
          if (!textColor && computed.color && computed.color !== 'rgb(0, 0, 0)' && computed.color !== 'rgb(17, 24, 39)') {
            // Check if it's different from default
            textColor = computed.color;
          }
          if (!bgColor && computed.backgroundColor && computed.backgroundColor !== 'rgba(0, 0, 0, 0)' && computed.backgroundColor !== 'transparent') {
            bgColor = computed.backgroundColor;
          }
          
          // Also check inline styles (they take precedence)
          if (node.style.color) {
            textColor = node.style.color;
          }
          if (node.style.backgroundColor) {
            bgColor = node.style.backgroundColor;
          }
        }
      }
      
      // If no colors found in elements, check the block
      if (!textColor && !bgColor) {
        const block = window.ContentEditorCommands ? window.ContentEditorCommands.getCurrentBlock() : null;
        if (block) {
          const computed = window.getComputedStyle(block);
          if (computed.color && computed.color !== 'rgb(0, 0, 0)' && computed.color !== 'rgb(17, 24, 39)') {
            textColor = computed.color;
          }
          if (computed.backgroundColor && computed.backgroundColor !== 'rgba(0, 0, 0, 0)' && computed.backgroundColor !== 'transparent') {
            bgColor = computed.backgroundColor;
          }
        }
      }
    }
    
    const textColorHex = dropdown.querySelector('#text-color-hex');
    const bgColorHex = dropdown.querySelector('#bg-color-hex');
    const textColorPreview = dropdown.querySelector('#text-color-preview');
    const bgColorPreview = dropdown.querySelector('#bg-color-preview');
    
    if (textColor) {
      const hex = toHex(textColor);
      if (textColorHex) textColorHex.value = hex;
      if (textColorPreview) textColorPreview.style.backgroundColor = hex;
    } else {
      // No color found, reset to default
      if (textColorHex) textColorHex.value = '#000000';
      if (textColorPreview) textColorPreview.style.backgroundColor = '#000000';
    }
    
    if (bgColor) {
      const hex = toHex(bgColor);
      if (bgColorHex) bgColorHex.value = hex;
      if (bgColorPreview) bgColorPreview.style.backgroundColor = hex;
      
      // Update color wheel state from detected color
      if (window.ContentEditorColorPicker) {
        const rgb = window.ContentEditorColorPicker.hexToRgb(hex);
        if (rgb) {
          const hsl = window.ContentEditorColorPicker.rgbToHsl(rgb[0], rgb[1], rgb[2]);
          dropdown._bgColorState = {
            hue: hsl[0],
            saturation: hsl[1],
            brightness: hsl[2]
          };
          
          // Update wheel and brightness
          const bgColorWheel = dropdown.querySelector('#bg-color-wheel');
          const bgColorBrightness = dropdown.querySelector('#bg-color-brightness');
          const bgColorMarker = dropdown.querySelector('#bg-color-marker');
          const bgColorBrightnessMarker = dropdown.querySelector('#bg-color-brightness-marker');
          
          if (bgColorWheel) {
            window.ContentEditorColorPicker.drawColorWheel(bgColorWheel, 0.5);
          }
          if (bgColorBrightness) {
            window.ContentEditorColorPicker.drawBrightnessSlider(bgColorBrightness, hsl[0] / 360, hsl[1]);
          }
          
          // Update markers
          if (bgColorMarker && bgColorWheel) {
            const centerX = bgColorWheel.width / 2;
            const centerY = bgColorWheel.height / 2;
            const radius = Math.min(centerX, centerY) - 2;
            const innerRadius = radius * 0.3;
            const angle = (hsl[0] * Math.PI) / 180;
            const distance = hsl[1] * (radius - innerRadius) + innerRadius;
            const markerX = centerX + Math.cos(angle) * distance;
            const markerY = centerY + Math.sin(angle) * distance;
            bgColorMarker.style.left = (markerX - 6) + 'px';
            bgColorMarker.style.top = (markerY - 6) + 'px';
          }
          if (bgColorBrightnessMarker && bgColorBrightness) {
            const brightnessX = hsl[2] * bgColorBrightness.width;
            bgColorBrightnessMarker.style.left = (brightnessX - 6) + 'px';
          }
        }
      }
    } else {
      // No background color found, reset to default
      if (bgColorHex) bgColorHex.value = '#FFFFFF';
      if (bgColorPreview) bgColorPreview.style.backgroundColor = '#FFFFFF';
      
      // Reset wheel state to white
      dropdown._bgColorState = { hue: 0, saturation: 0, brightness: 1 };
      
      // Update wheel and brightness
      const bgColorWheel = dropdown.querySelector('#bg-color-wheel');
      const bgColorBrightness = dropdown.querySelector('#bg-color-brightness');
      const bgColorMarker = dropdown.querySelector('#bg-color-marker');
      const bgColorBrightnessMarker = dropdown.querySelector('#bg-color-brightness-marker');
      
      if (bgColorWheel && window.ContentEditorColorPicker) {
        window.ContentEditorColorPicker.drawColorWheel(bgColorWheel, 0.5);
      }
      if (bgColorBrightness && window.ContentEditorColorPicker) {
        window.ContentEditorColorPicker.drawBrightnessSlider(bgColorBrightness, 0, 0);
      }
      
      // Update markers
      if (bgColorMarker && bgColorWheel) {
        const centerX = bgColorWheel.width / 2;
        const centerY = bgColorWheel.height / 2;
        bgColorMarker.style.left = (centerX - 6) + 'px';
        bgColorMarker.style.top = (centerY - 6) + 'px';
      }
      if (bgColorBrightnessMarker && bgColorBrightness) {
        const brightnessX = bgColorBrightness.width;
        bgColorBrightnessMarker.style.left = (brightnessX - 6) + 'px';
      }
    }
  },
  
  /**
   * Clear color picker values
   */
  clearColorPicker(dropdown) {
    const textColorHex = dropdown.querySelector('#text-color-hex');
    const bgColorHex = dropdown.querySelector('#bg-color-hex');
    const textColorPreview = dropdown.querySelector('#text-color-preview');
    const bgColorPreview = dropdown.querySelector('#bg-color-preview');
    
    if (textColorHex) textColorHex.value = '#000000';
    if (textColorPreview) textColorPreview.style.backgroundColor = '#000000';
    if (bgColorHex) bgColorHex.value = '#FFFFFF';
    if (bgColorPreview) bgColorPreview.style.backgroundColor = '#FFFFFF';
  },

  /**
   * Ensure editor content is wrapped in block elements
   * Preserves list structures (UL/OL)
   */
  ensureBlockStructure(editor = null) {
    if (!editor) editor = this.editor;
    const children = Array.from(editor.childNodes);
    let needsNormalization = false;
    
    // Check if we have text nodes or non-block elements directly in editor
    // But preserve list structures (UL/OL are valid block elements)
    children.forEach(node => {
      if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
        needsNormalization = true;
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE', 'DIV'];
        if (!blockTags.includes(node.tagName)) {
          needsNormalization = true;
        }
      }
    });
    
    if (needsNormalization) {
      // Wrap loose content in paragraphs
      // IMPORTANT: Preserve list structures (UL/OL) - don't wrap them
      const fragment = document.createDocumentFragment();
      let currentP = null;
      
      children.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
          if (node.textContent.trim()) {
            if (!currentP) {
              currentP = document.createElement('p');
              fragment.appendChild(currentP);
            }
            currentP.appendChild(node.cloneNode(true));
          }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
          const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE'];
          if (blockTags.includes(node.tagName)) {
            currentP = null; // Reset paragraph collector
            // Preserve list structures as-is
            fragment.appendChild(node.cloneNode(true));
          } else {
            // Non-block element, wrap in paragraph
            if (!currentP) {
              currentP = document.createElement('p');
              fragment.appendChild(currentP);
            }
            currentP.appendChild(node.cloneNode(true));
          }
        }
      });
      
      // Save selection before replacing content
      window.ContentEditorSelection.save();
      
      editor.innerHTML = '';
      editor.appendChild(fragment);
      
      // Restore selection
      window.ContentEditorSelection.restore();
    }
  },

  /**
   * Sanitize pasted HTML content by removing styles, classes, and unwanted attributes
   * while preserving structure and basic formatting
   */
  sanitizePastedHTML(html) {
    if (!html) return '';
    
    // Create a temporary container to parse the HTML
    const temp = document.createElement('div');
    temp.innerHTML = html;
    
    // Allowed block elements
    const allowedBlocks = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'ul', 'ol', 'li', 'hr', 'br'];
    
    // Allowed inline elements
    const allowedInline = ['b', 'strong', 'i', 'em', 'u', 's', 'strike', 'code', 'a', 'span'];
    
    // Recursive function to clean nodes
    const cleanNode = (node) => {
      // Remove script, style, and other dangerous elements
      if (node.nodeType === Node.ELEMENT_NODE) {
        const tagName = node.tagName.toLowerCase();
        
        // Remove dangerous elements
        if (['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'meta', 'link'].includes(tagName)) {
          return null;
        }
        
        // Process allowed elements
        if (allowedBlocks.includes(tagName) || allowedInline.includes(tagName) || tagName === 'img') {
          // Remove all attributes except href for links and src/alt for images
          const allowedAttrs = [];
          if (tagName === 'a') {
            allowedAttrs.push('href');
          } else if (tagName === 'img') {
            allowedAttrs.push('src', 'alt');
          }
          
          // Create a new element with the same tag
          const cleanElement = document.createElement(tagName);
          
          // Copy only allowed attributes
          allowedAttrs.forEach(attr => {
            if (node.hasAttribute(attr)) {
              cleanElement.setAttribute(attr, node.getAttribute(attr));
            }
          });
          
          // Recursively clean children
          Array.from(node.childNodes).forEach(child => {
            const cleaned = cleanNode(child);
            if (cleaned) {
              cleanElement.appendChild(cleaned);
            }
          });
          
          return cleanElement;
        }
        
        // For other elements, just process children and convert to text or inline
        if (node.childNodes.length > 0) {
          const fragment = document.createDocumentFragment();
          Array.from(node.childNodes).forEach(child => {
            const cleaned = cleanNode(child);
            if (cleaned) {
              fragment.appendChild(cleaned);
            }
          });
          return fragment.childNodes.length > 0 ? fragment : null;
        }
        
        return null;
      }
      
      // Text nodes - keep as is
      if (node.nodeType === Node.TEXT_NODE) {
        return node.cloneNode(true);
      }
      
      return null;
    };
    
    // Clean all nodes
    const cleaned = document.createDocumentFragment();
    Array.from(temp.childNodes).forEach(child => {
      const cleanedChild = cleanNode(child);
      if (cleanedChild) {
        // If it's a document fragment, append its children
        if (cleanedChild.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
          Array.from(cleanedChild.childNodes).forEach(c => cleaned.appendChild(c));
        } else {
          cleaned.appendChild(cleanedChild);
        }
      }
    });
    
    // Convert to HTML string
    const result = document.createElement('div');
    result.appendChild(cleaned);
    return result.innerHTML;
  },

  /**
   * Insert sanitized content at the current cursor position
   */
  insertSanitizedContent(editor, html) {
    if (!html) return;
    
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      // No selection, append to editor
      const temp = document.createElement('div');
      temp.innerHTML = html;
      Array.from(temp.childNodes).forEach(node => {
        editor.appendChild(node);
      });
      return;
    }
    
    const range = selection.getRangeAt(0);
    
    // Delete selected content if any
    if (!range.collapsed) {
      range.deleteContents();
    }
    
    // Create a temporary container to parse the HTML
    const temp = document.createElement('div');
    temp.innerHTML = html;
    
    // Insert nodes
    const fragment = document.createDocumentFragment();
    Array.from(temp.childNodes).forEach(node => {
      fragment.appendChild(node);
    });
    
    // Insert fragment
    if (range.collapsed) {
      // Insert nodes one by one to handle multiple top-level nodes correctly
      const nodes = Array.from(fragment.childNodes);
      if (nodes.length === 0) return;
      
      // Insert first node
      range.insertNode(nodes[0]);
      
      // Insert remaining nodes after the first
      let lastNode = nodes[0];
      for (let i = 1; i < nodes.length; i++) {
        const nextRange = document.createRange();
        nextRange.setStartAfter(lastNode);
        nextRange.collapse(true);
        nextRange.insertNode(nodes[i]);
        lastNode = nodes[i];
      }
      
      // Move cursor to end of inserted content
      const finalRange = document.createRange();
      finalRange.setStartAfter(lastNode);
      finalRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(finalRange);
    } else {
      // Insert fragment (replaces selection)
      range.insertNode(fragment);
    }
  },

  /**
   * Check if text is a URL
   */
  isURL(text) {
    if (!text || typeof text !== 'string') return false;
    
    // Trim whitespace
    const trimmed = text.trim();
    
    // Basic URL pattern
    const urlPattern = /^(https?:\/\/|www\.)[^\s]+$/i;
    
    // Also check for URLs without protocol
    const urlPatternNoProtocol = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+([\/?#].*)?$/i;
    
    return urlPattern.test(trimmed) || (urlPatternNoProtocol.test(trimmed) && trimmed.includes('.'));
  },

  /**
   * Detect URL type (youtube, vimeo, maps, etc.)
   */
  detectURLType(url) {
    if (!url) return 'generic';
    
    const lowerUrl = url.toLowerCase();
    
    // YouTube
    if (lowerUrl.includes('youtube.com') || lowerUrl.includes('youtu.be')) {
      return 'youtube';
    }
    
    // Vimeo
    if (lowerUrl.includes('vimeo.com')) {
      return 'vimeo';
    }
    
    // Google Maps
    if (lowerUrl.includes('google.com/maps') || lowerUrl.includes('maps.google.com')) {
      return 'google-maps';
    }
    
    // OpenStreetMap
    if (lowerUrl.includes('openstreetmap.org') || lowerUrl.includes('osm.org')) {
      return 'openstreetmap';
    }
    
    // Generic map URLs
    if (lowerUrl.includes('/maps/') || lowerUrl.includes('map?') || lowerUrl.includes('maps?')) {
      return 'maps';
    }
    
    // Generic video/embed platforms
    if (lowerUrl.includes('video') || lowerUrl.includes('embed')) {
      return 'video';
    }
    
    return 'generic';
  },

  /**
   * Show smart paste dialog with options based on URL type
   */
  showSmartPasteDialog(url, urlType, originalEvent) {
    // Get the active editor (works for both inline and fullscreen)
    const editor = this.getActiveEditor();
    if (!editor) return;
    
    // Save selection before showing dialog
    window.ContentEditorSelection.save();
    
    // Create dialog overlay
    const overlay = document.createElement('div');
    overlay.className = 'content-editor-smart-paste-overlay';
    overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 12000;';
    
    // Determine available options based on URL type
    const options = [];
    
    if (urlType === 'youtube' || urlType === 'vimeo' || urlType === 'video') {
      // Video URLs: URL, URL as Link, Custom Link, Video Embed, Link Preview
      options.push(
        { value: 'url', label: 'Paste as URL', icon: '🔗' },
        { value: 'url-as-link', label: 'Paste URL as Link', icon: '📎' },
        { value: 'custom-link', label: 'Paste as Custom Link', icon: '🔖' },
        { value: 'video', label: 'Embed Video', icon: '▶️' },
        { value: 'link-preview', label: 'Link Preview', icon: '📋' }
      );
    } else if (urlType === 'google-maps' || urlType === 'openstreetmap' || urlType === 'maps') {
      // Map URLs: URL, URL as Link, Custom Link, Map Embed, Link Preview
      options.push(
        { value: 'url', label: 'Paste as URL', icon: '🔗' },
        { value: 'url-as-link', label: 'Paste URL as Link', icon: '📎' },
        { value: 'custom-link', label: 'Paste as Custom Link', icon: '🔖' },
        { value: 'maps', label: 'Embed Map', icon: '🗺️' },
        { value: 'link-preview', label: 'Link Preview', icon: '📋' }
      );
    } else {
      // Generic URLs: URL, URL as Link, Custom Link, Link Preview
      options.push(
        { value: 'url', label: 'Paste as URL', icon: '🔗' },
        { value: 'url-as-link', label: 'Paste URL as Link', icon: '📎' },
        { value: 'custom-link', label: 'Paste as Custom Link', icon: '🔖' },
        { value: 'link-preview', label: 'Link Preview', icon: '📋' }
      );
    }
    
    // Add cancel option
    options.push({ value: 'cancel', label: 'Cancel', icon: '✕' });
    
    // Create dialog HTML
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-smart-paste-dialog';
    dialog.style.cssText = 'background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);';
    
    dialog.innerHTML = `
      <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1f2937;">Paste URL</h3>
      <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px; word-break: break-all;">${url}</p>
      <div class="smart-paste-options" style="display: flex; flex-direction: column; gap: 8px;">
        ${options.map(opt => `
          <button type="button" class="smart-paste-option" data-value="${opt.value}" style="
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            text-align: left;
            font-size: 14px;
            color: #374151;
            transition: all 0.2s;
          ">
            <span style="font-size: 18px;">${opt.icon}</span>
            <span>${opt.label}</span>
          </button>
        `).join('')}
      </div>
    `;
    
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    
    // Add click handlers for options
    dialog.querySelectorAll('.smart-paste-option').forEach(btn => {
      btn.addEventListener('click', () => {
        const value = btn.dataset.value;
        this.handleSmartPasteChoice(value, url, editor, overlay);
      });
      
      // Hover effect
      btn.addEventListener('mouseenter', () => {
        btn.style.background = '#f3f4f6';
        btn.style.borderColor = '#d1d5db';
      });
      btn.addEventListener('mouseleave', () => {
        btn.style.background = 'white';
        btn.style.borderColor = '#e5e7eb';
      });
    });
    
    // Close on overlay click (outside dialog)
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        // Cancel - just paste as plain text
        this.handleSmartPasteChoice('url', url, editor, overlay);
      }
    });
    
    // Close on Escape key
    const escapeHandler = (e) => {
      if (e.key === 'Escape') {
        // Cancel - just paste as plain text
        this.handleSmartPasteChoice('url', url, editor, overlay);
        document.removeEventListener('keydown', escapeHandler);
      }
    };
    document.addEventListener('keydown', escapeHandler);
  },

  /**
   * Handle user's choice from smart paste dialog
   */
  handleSmartPasteChoice(choice, url, editor, overlay) {
    // Remove dialog
    if (overlay && overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
    }
    
    // Restore selection
    window.ContentEditorSelection.restore(editor);
    
    if (choice === 'cancel') {
      // User cancelled, do nothing
      return;
    }
    
    // Get current selection/block
    const selection = window.getSelection();
    let range = null;
    if (selection.rangeCount > 0) {
      range = selection.getRangeAt(0);
    }
    
    if (choice === 'url') {
      // Paste as plain URL text
      if (range) {
        range.deleteContents();
        const textNode = document.createTextNode(url);
        range.insertNode(textNode);
        range.setStartAfter(textNode);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        // No selection, insert at cursor
        const block = window.ContentEditorCommands.getCurrentBlock();
        if (block) {
          const textNode = document.createTextNode(url);
          block.appendChild(textNode);
          const newRange = document.createRange();
          newRange.setStartAfter(textNode);
          newRange.collapse(true);
          selection.removeAllRanges();
          selection.addRange(newRange);
        }
      }
    } else if (choice === 'url-as-link') {
      // Paste URL as link (URL itself is the link text)
      const link = document.createElement('a');
      link.href = url;
      link.textContent = url;
      if (range) {
        range.deleteContents();
        range.insertNode(link);
        range.setStartAfter(link);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        const block = window.ContentEditorCommands.getCurrentBlock();
        if (block) {
          block.appendChild(link);
          const newRange = document.createRange();
          newRange.setStartAfter(link);
          newRange.collapse(true);
          selection.removeAllRanges();
          selection.addRange(newRange);
        }
      }
    } else if (choice === 'custom-link') {
      // Show dialog to ask for custom link text
      this.showCustomLinkDialog(url, editor, overlay);
      return; // Don't close overlay yet, wait for custom link dialog
    } else if (choice === 'video') {
      // Insert video embed
      if (window.ContentEditorCommands && window.ContentEditorCommands.insertMediaEmbed) {
        const urlType = this.detectURLType(url);
        window.ContentEditorCommands.insertMediaEmbed('video', {
          url: url,
          type: urlType === 'youtube' ? 'youtube' : (urlType === 'vimeo' ? 'vimeo' : 'native'),
          width: '',
          height: '',
          autoplay: false,
          controls: true,
          loop: false,
          muted: false
        });
      }
    } else if (choice === 'maps') {
      // Insert map embed
      if (window.ContentEditorCommands && window.ContentEditorCommands.insertMediaEmbed) {
        const mapData = this.extractMapDataFromUrl(url);
        window.ContentEditorCommands.insertMediaEmbed('maps', mapData);
      }
    } else if (choice === 'link-preview') {
      // Insert link preview embed
      if (window.ContentEditorCommands && window.ContentEditorCommands.insertMediaEmbed) {
        window.ContentEditorCommands.insertMediaEmbed('link-preview', {
          url: url,
          title: '',
          description: '',
          image: '',
          site_name: ''
        });
      }
    }
    
    // Sync and save history
    if (window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + (editor.dataset.fieldName || ''));
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
    
    // Focus editor
    editor.focus();
  },

  /**
   * Show dialog to get custom link text
   */
  showCustomLinkDialog(url, editor, parentOverlay) {
    // Create dialog overlay
    const overlay = document.createElement('div');
    overlay.className = 'content-editor-custom-link-overlay';
    overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 12001;';
    
    // Create dialog HTML
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-custom-link-dialog';
    dialog.style.cssText = 'background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);';
    
    dialog.innerHTML = `
      <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1f2937;">Custom Link Text</h3>
      <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 14px;">Enter the text to display for this link:</p>
      <input type="text" id="custom-link-text" placeholder="Link text..." style="
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 16px;
        box-sizing: border-box;
      " autofocus>
      <div style="display: flex; gap: 8px; justify-content: flex-end;">
        <button type="button" class="custom-link-cancel" style="
          padding: 8px 16px;
          border: 1px solid #e5e7eb;
          border-radius: 6px;
          background: white;
          cursor: pointer;
          font-size: 14px;
          color: #374151;
        ">Cancel</button>
        <button type="button" class="custom-link-apply" style="
          padding: 8px 16px;
          border: none;
          border-radius: 6px;
          background: #3b82f6;
          cursor: pointer;
          font-size: 14px;
          color: white;
          font-weight: 500;
        ">Apply</button>
      </div>
    `;
    
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    
    const textInput = dialog.querySelector('#custom-link-text');
    const cancelBtn = dialog.querySelector('.custom-link-cancel');
    const applyBtn = dialog.querySelector('.custom-link-apply');
    
    // Focus input
    setTimeout(() => textInput.focus(), 100);
    
    // Handle Enter key
    textInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyBtn.click();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        cancelBtn.click();
      }
    });
    
    // Cancel button
    cancelBtn.addEventListener('click', () => {
      if (overlay && overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
      // Also close parent overlay if it exists
      if (parentOverlay && parentOverlay.parentNode) {
        parentOverlay.parentNode.removeChild(parentOverlay);
      }
    });
    
    // Apply button
    applyBtn.addEventListener('click', () => {
      const linkText = textInput.value.trim();
      
      // Remove dialogs
      if (overlay && overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
      if (parentOverlay && parentOverlay.parentNode) {
        parentOverlay.parentNode.removeChild(parentOverlay);
      }
      
      // Restore selection
      window.ContentEditorSelection.restore(editor);
      
      // Create link with custom text
      const selection = window.getSelection();
      let range = null;
      if (selection.rangeCount > 0) {
        range = selection.getRangeAt(0);
      }
      
      const link = document.createElement('a');
      link.href = url;
      link.textContent = linkText || url; // Use URL as fallback if empty
      
      if (range) {
        range.deleteContents();
        range.insertNode(link);
        range.setStartAfter(link);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        const block = window.ContentEditorCommands.getCurrentBlock();
        if (block) {
          block.appendChild(link);
          const newRange = document.createRange();
          newRange.setStartAfter(link);
          newRange.collapse(true);
          selection.removeAllRanges();
          selection.addRange(newRange);
        }
      }
      
      // Sync and save history
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + (editor.dataset.fieldName || ''));
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      
      if (window.ContentEditorHistory) {
        setTimeout(() => {
          window.ContentEditorHistory.saveState();
        }, 50);
      }
      
      // Focus editor
      editor.focus();
    });
    
    // Close on overlay click (outside dialog)
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        cancelBtn.click();
      }
    });
  },

  /**
   * Extract map data from a map URL
   */
  extractMapDataFromUrl(url) {
    const lowerUrl = url.toLowerCase();
    const mapData = {
      provider: 'google',
      input_method: 'embed_url',
      embed_url: url,
      width: '100%',
      height: '400',
      zoom: '15'
    };
    
    try {
      const urlObj = new URL(url);
      
      // Google Maps
      if (lowerUrl.includes('google.com/maps') || lowerUrl.includes('maps.google.com')) {
        mapData.provider = 'google';
        
        // Check if it's already an embed URL
        if (urlObj.pathname.includes('/embed') || (urlObj.searchParams.has('output') && urlObj.searchParams.get('output') === 'embed')) {
          mapData.input_method = 'embed_url';
          mapData.embed_url = url;
        } else {
          // For regular Google Maps URLs, try to convert to embed format
          // First, try to preserve the original URL structure and just add output=embed
          let embedUrl;
          
          // Try the simplest approach: add output=embed to the original URL
          try {
            const embedUrlObj = new URL(url);
            // If the URL has a hash (like #map=15/40.7128/-74.0060), we need to handle it differently
            if (embedUrlObj.hash) {
              // Extract coordinates from hash if present
              const hashMatch = embedUrlObj.hash.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*),?(\d+)?z?/);
              if (hashMatch) {
                const lat = hashMatch[1];
                const lng = hashMatch[2];
                const zoom = hashMatch[3] || '15';
                embedUrl = `https://www.google.com/maps?q=${lat},${lng}&output=embed&z=${zoom}`;
              } else {
                // Just add output=embed to the URL
                embedUrlObj.searchParams.set('output', 'embed');
                embedUrl = embedUrlObj.toString();
              }
            } else {
              // Extract the query or path to preserve location info
              const q = embedUrlObj.searchParams.get('q');
              const pathMatch = embedUrlObj.pathname.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*),?(\d+)?z?/);
              const placeMatch = embedUrlObj.pathname.match(/\/place\/([^/@]+)/);
              
              if (pathMatch) {
                // Extract coordinates from @lat,lng,zoom format in path
                const lat = pathMatch[1];
                const lng = pathMatch[2];
                const zoom = pathMatch[3] || '15';
                embedUrl = `https://www.google.com/maps?q=${lat},${lng}&output=embed&z=${zoom}`;
              } else if (q) {
                // Use the q parameter
                embedUrlObj.searchParams.set('output', 'embed');
                embedUrl = embedUrlObj.toString();
              } else if (placeMatch) {
                // Extract place name
                const place = decodeURIComponent(placeMatch[1].replace(/\+/g, ' '));
                embedUrl = `https://www.google.com/maps?q=${encodeURIComponent(place)}&output=embed`;
              } else {
                // Fallback: add output=embed to original URL
                embedUrlObj.searchParams.set('output', 'embed');
                embedUrl = embedUrlObj.toString();
              }
            }
          } catch (e) {
            // If URL manipulation fails, try to extract and rebuild
            const q = urlObj.searchParams.get('q');
            const pathMatch = urlObj.pathname.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*),?(\d+)?z?/);
            
            if (pathMatch) {
              const lat = pathMatch[1];
              const lng = pathMatch[2];
              const zoom = pathMatch[3] || '15';
              embedUrl = `https://www.google.com/maps?q=${lat},${lng}&output=embed&z=${zoom}`;
            } else if (q) {
              embedUrl = `https://www.google.com/maps?q=${encodeURIComponent(q)}&output=embed`;
            } else {
              // Last resort: use original URL with output=embed
              embedUrl = url + (url.includes('?') ? '&' : '?') + 'output=embed';
            }
          }
          
          // Always use embed_url method for pasted URLs to preserve the converted URL
          mapData.input_method = 'embed_url';
          mapData.embed_url = embedUrl;
        }
      }
      // OpenStreetMap
      else if (lowerUrl.includes('openstreetmap.org') || lowerUrl.includes('osm.org')) {
        mapData.provider = 'openstreetmap';
        
        // Check if it's already an embed URL
        if (urlObj.pathname.includes('/export/embed')) {
          mapData.input_method = 'embed_url';
          mapData.embed_url = url;
        } else {
          // Try to extract coordinates from URL
          let hasCoords = false;
          
          // OpenStreetMap URLs: /#map=zoom/lat/lng
          const hashMatch = urlObj.hash.match(/map=(\d+)\/(-?\d+\.?\d*)\/(-?\d+\.?\d*)/);
          if (hashMatch) {
            mapData.input_method = 'coordinates';
            mapData.zoom = hashMatch[1];
            mapData.latitude = hashMatch[2];
            mapData.longitude = hashMatch[3];
            hasCoords = true;
          } else {
            // Try query parameters
            const lat = urlObj.searchParams.get('lat') || urlObj.searchParams.get('mlat');
            const lng = urlObj.searchParams.get('lon') || urlObj.searchParams.get('mlon') || urlObj.searchParams.get('lng');
            if (lat && lng) {
              mapData.input_method = 'coordinates';
              mapData.latitude = lat;
              mapData.longitude = lng;
              hasCoords = true;
            }
          }
          
          // If we couldn't extract coordinates, use as embed URL
          if (!hasCoords) {
            mapData.input_method = 'embed_url';
            mapData.embed_url = url;
          }
        }
      }
      // Generic map URL - use as embed URL
      else {
        mapData.provider = 'embed';
        mapData.input_method = 'embed_url';
        mapData.embed_url = url;
      }
    } catch (e) {
      // If URL parsing fails, use as embed URL
      mapData.provider = 'embed';
      mapData.input_method = 'embed_url';
      mapData.embed_url = url;
    }
    
    return mapData;
  },

  /**
   * Get active editor (works for both inline and fullscreen)
   */
  getActiveEditor() {
    // Check if there's an active editor state
    if (window.ContentEditorState && window.ContentEditorState.activeEditor) {
      return window.ContentEditorState.activeEditor;
    }
    
    // Fallback: find editor from selection
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      const editor = range.commonAncestorContainer.closest?.('.content-editor-area');
      if (editor) return editor;
    }
    
    // Last fallback: use stored editor
    return this.editor;
  }
};



