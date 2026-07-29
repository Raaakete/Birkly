window.HaruFieldtypes = window.HaruFieldtypes || {};

// Register DynamicItems fieldtype configuration
if (window.HaruFieldtypeConfig) {
    window.HaruFieldtypeConfig.register('dynamicitems', {
        selectors: {
            container: '#field_{fieldName}',
            hiddenInput: '#field_{fieldName}_value',
            blocks: '.block-items',
            block: '.block-item',
            addButton: '.add-block-btn',
            removeButton: '.remove-block-btn'
        },
        settings: {
            autoSave: true,
            validation: true,
            debug: false,
            allowEmptyBlocks: false,
            maxBlocks: 50
        },
        events: {
            onChange: true,
            onBlur: false,
            onFocus: false,
            onAddBlock: true,
            onRemoveBlock: true
        }
    });
}

// DynamicItems fieldtype implementation
window.HaruFieldtypes['dynamicitems'] = {
  // Resolve DynamicItems field name from a DOM node inside .block-field (multilang-safe).
  resolveFieldNameFromNode: function(node) {
    const field = node && node.closest ? node.closest('.block-field') : null;
    if (!field || !field.id) {
      return null;
    }
    return field.id.replace(/^field_/, '');
  },

  // Resolve field container id from the Add Block button (multilang-safe).
  showAddBlockModalFromButton: function(button) {
    const field = button && button.closest ? button.closest('.block-field') : null;
    if (!field || !field.id) {
      console.error('[DynamicItems] Could not find block-field container from button');
      return;
    }
    const fieldName = field.id.replace(/^field_/, '');
    this.showAddBlockModal(fieldName);
  },

  resolveUrl: function(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
  },

  // Show modal for adding a new block
  showAddBlockModal: function(fieldName) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return;
    
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    const availableFieldtypes = fieldConfig.availableFieldtypes || [];
    
    // Create modal HTML
    const modalId = 'dynamicitems-add-block-modal';
    let existingModal = document.getElementById(modalId);
    if (existingModal) {
      existingModal.remove();
    }
    
    // Group fieldtypes by category
    const fieldtypesByCategory = {};
    availableFieldtypes.forEach(ft => {
      const cat = ft.category || 'Misc';
      if (!fieldtypesByCategory[cat]) {
        fieldtypesByCategory[cat] = [];
      }
      fieldtypesByCategory[cat].push(ft);
    });
    
    let fieldtypeOptionsHtml = '';
    Object.keys(fieldtypesByCategory).sort().forEach(category => {
      fieldtypeOptionsHtml += `<optgroup label="${category}">`;
      fieldtypesByCategory[category].forEach(ft => {
        fieldtypeOptionsHtml += `<option value="${ft.name}">${ft.label}</option>`;
      });
      fieldtypeOptionsHtml += `</optgroup>`;
    });
    
    const modalHtml = `
      <div id="${modalId}" class="dynamicitems-modal-overlay" data-dynamicitems-field="${this.escapeHtml(fieldName)}">
        <div class="dynamicitems-modal">
          <div class="dynamicitems-modal-header">
            <h3>Add Block</h3>
            <button type="button" class="dynamicitems-modal-close" data-dynamicitems-action="modal-close" aria-label="Close">&times;</button>
          </div>
          <div class="dynamicitems-modal-body">
            <div class="dynamicitems-form-group">
              <label>Field Type <span class="required">*</span></label>
              <select id="block-fieldtype-select" class="input" required>
                <option value="">Select a fieldtype...</option>
                ${fieldtypeOptionsHtml}
              </select>
            </div>
            <div class="dynamicitems-form-group">
              <label>Label <span class="required">*</span></label>
              <input type="text" id="block-label-input" class="input" placeholder="e.g., Title" required>
            </div>
            <div class="dynamicitems-form-group">
              <label>API Name <span class="required">*</span></label>
              <input type="text" id="block-name-input" class="input" placeholder="e.g., title" required pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores">
              <small>Used in API responses. Only lowercase letters, numbers, and underscores.</small>
            </div>
            <div class="dynamicitems-form-group">
              <label>
                <input type="checkbox" id="block-required-checkbox">
                Required field
              </label>
            </div>
            <div id="block-settings-container" class="dynamicitems-form-group" style="display: none;">
              <label>Field Settings</label>
              <div id="block-settings-content"></div>
            </div>
          </div>
          <div class="dynamicitems-modal-footer">
            <button type="button" class="btn btn-secondary" data-dynamicitems-action="modal-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" data-dynamicitems-action="modal-confirm">Add Block</button>
          </div>
        </div>
      </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Auto-generate API name from label
    const labelInput = document.getElementById('block-label-input');
    const nameInput = document.getElementById('block-name-input');
    const fieldtypeSelect = document.getElementById('block-fieldtype-select');
    
    labelInput.addEventListener('input', function() {
      if (!nameInput.value) {
        const apiName = this.value.toLowerCase()
          .replace(/[^a-z0-9\s]/g, '')
          .replace(/\s+/g, '_')
          .replace(/^_+|_+$/g, '');
        nameInput.value = apiName;
      }
    });
    
    // Load fieldtype settings when type is selected
    const dynamicItemsApi = this;
    fieldtypeSelect.addEventListener('change', function() {
      const fieldtypeName = this.value;
      const settingsContainer = document.getElementById('block-settings-container');
      const settingsContent = document.getElementById('block-settings-content');
      
      if (!fieldtypeName) {
        settingsContainer.style.display = 'none';
        return;
      }
      
      // Load fieldtype config UI
      fetch(dynamicItemsApi.resolveUrl(`/api/admin_fieldtypes.php?action=get_config_ui&fieldtype=${encodeURIComponent(fieldtypeName)}&fieldIndex=0&existingData={}`))
        .then(response => response.json())
        .then(data => {
          if (data.status === 'ok' && data.html) {
            settingsContent.innerHTML = data.html;
            settingsContainer.style.display = 'block';
            
            // Extract and execute any inline scripts
            const scripts = settingsContent.querySelectorAll('script');
            scripts.forEach(function(oldScript) {
              const newScript = document.createElement('script');
              Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
              });
              newScript.textContent = oldScript.innerHTML;
              document.head.appendChild(newScript);
              oldScript.remove();
            });
          } else {
            settingsContainer.style.display = 'none';
          }
        })
        .catch(err => {
          console.error('Error loading fieldtype config:', err);
          settingsContainer.style.display = 'none';
        });
    });
    
    // Close modal on overlay click
    document.getElementById(modalId).addEventListener('click', function(e) {
      if (e.target === this) {
        this.remove();
      }
    });
  },
  
  // Add block from modal
  addBlockFromModal: function(fieldName) {
    const modal = document.getElementById('dynamicitems-add-block-modal');
    if (!modal) return;
    
    const fieldtypeSelect = document.getElementById('block-fieldtype-select');
    const labelInput = document.getElementById('block-label-input');
    const nameInput = document.getElementById('block-name-input');
    const requiredCheckbox = document.getElementById('block-required-checkbox');
    const settingsContent = document.getElementById('block-settings-content');
    
    // Validate
    if (!fieldtypeSelect.value || !labelInput.value || !nameInput.value) {
      alert('Please fill in all required fields');
      return;
    }
    
    // Validate API name format
    if (!/^[a-z0-9_]+$/.test(nameInput.value)) {
      alert('API name can only contain lowercase letters, numbers, and underscores');
      return;
    }
    
    // Check for duplicate API names
    const existingBlocks = this.getValue(fieldName);
    if (existingBlocks.some(block => block.name === nameInput.value)) {
      alert('A block with this API name already exists');
      return;
    }
    
    // Collect settings from config UI
    const settings = {};
    if (settingsContent) {
      const inputs = settingsContent.querySelectorAll('input, select, textarea');
      inputs.forEach(input => {
        if (input.name && input.name.startsWith('config[')) {
          // Parse config[key] format
          const match = input.name.match(/config\[(.+)\]/);
          if (match) {
            const key = match[1];
            if (input.type === 'checkbox') {
              settings[key] = input.checked;
            } else {
              settings[key] = input.value;
            }
          }
        }
      });
    }
    
    // Create block data
    const blockData = {
      type: fieldtypeSelect.value,
      name: nameInput.value,
      label: labelInput.value,
      required: requiredCheckbox.checked,
      settings: settings,
      value: null
    };
    
    // Add block
    this.addBlock(fieldName, blockData);
    
    // Close modal
    modal.remove();
  },
  
  // Add a block (either from modal or programmatically)
  addBlock: function(fieldName, blockData) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return;
    
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    const value = this.getValue(fieldName);
    
    // If blockData is provided, use it; otherwise create default
    if (!blockData) {
      blockData = {
        type: 'text',
        name: 'block_' + Date.now(),
        label: 'New Block',
        required: false,
        settings: {},
        value: null
      };
    }
    
    value.push(blockData);
    this.renderBlocks(fieldName, value);
    this.updateHiddenInput(fieldName, value);
  },
  
  // Render all blocks
  renderBlocks: function(fieldName, blocks) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return;
    
    const blocksContainer = container.querySelector('.block-items');
    if (!blocksContainer) return;
    
    // Clear existing blocks
    blocksContainer.innerHTML = '';
    
    // Render each block
    blocks.forEach((block, idx) => {
      this.renderBlock(fieldName, block, idx, blocksContainer);
    });
    
    // Initialize fieldtype JavaScript after rendering
    this.initializeFieldtypeScripts(fieldName);
  },
  
  // Render a single block
  renderBlock: function(fieldName, block, idx, container) {
    const blockId = `block_${fieldName}_${idx}`;
    const blockElement = document.createElement('div');
    blockElement.className = 'block-item';
    blockElement.id = blockId;
    blockElement.setAttribute('data-block-idx', idx);
    blockElement.setAttribute('data-block-name', block.name);
    blockElement.setAttribute('data-block-config', JSON.stringify(block));
    
    // Block header
    const headerHtml = `
      <div class="block-item-header">
        <div class="block-item-info">
          <span class="block-item-label">${this.escapeHtml(block.label)}</span>
          <span class="block-item-type">${this.escapeHtml(block.type)}</span>
          <span class="block-item-name">${this.escapeHtml(block.name)}</span>
        </div>
        <div class="block-item-actions">
          <button type="button" class="btn btn-sm btn-secondary" data-dynamicitems-action="edit-block" data-block-idx="${idx}" title="Edit">
            ✎ Edit
          </button>
          <button type="button" class="btn btn-sm btn-danger" data-dynamicitems-action="remove-block" data-block-idx="${idx}" title="Remove">
            × Remove
          </button>
        </div>
      </div>
      <div class="block-item-content" id="${blockId}_content">
        <div class="block-loading">Loading fieldtype...</div>
      </div>
    `;
    
    blockElement.innerHTML = headerHtml;
    container.appendChild(blockElement);
    
    // Load fieldtype via API
    this.loadFieldtypeInstance(fieldName, block, idx, blockElement.querySelector(`#${blockId}_content`));
  },
  
  // Load fieldtype instance via API
  loadFieldtypeInstance: function(fieldName, block, idx, contentContainer) {
    const formData = new FormData();
    formData.append('action', 'render_for_dynamicitems');
    formData.append('fieldtype', block.type);
    formData.append('blockName', block.name);
    formData.append('blockLabel', block.label);
    formData.append('blockRequired', block.required ? 'true' : 'false');
    if (block.value !== null && block.value !== undefined) {
      formData.append('blockValue', typeof block.value === 'object' ? JSON.stringify(block.value) : block.value);
    }
    if (block.settings && Object.keys(block.settings).length > 0) {
      formData.append('blockSettings', JSON.stringify(block.settings));
    }
    
    fetch(this.resolveUrl('/api/admin_fieldtypes.php'), {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'ok') {
          // Insert HTML
          contentContainer.innerHTML = data.html;
          
          // Inject CSS if provided
          if (data.css) {
            const styleId = `dynamicitems-css-${fieldName}-${idx}`;
            let styleElement = document.getElementById(styleId);
            if (!styleElement) {
              styleElement = document.createElement('style');
              styleElement.id = styleId;
              document.head.appendChild(styleElement);
            }
            styleElement.textContent = data.css;
          }
          
          // Execute JavaScript if provided
          if (data.javascript) {
            try {
              // Wrap in IIFE to avoid conflicts
              const wrappedJs = `(function() { ${data.javascript} })();`;
              eval(wrappedJs);
            } catch (e) {
              console.error('Error executing fieldtype JavaScript:', e);
            }
          }
          
          // Trigger fieldtype initialization event
          const fieldtypeElements = contentContainer.querySelectorAll('[data-fieldtype]');
          fieldtypeElements.forEach(el => {
            const event = new CustomEvent('fieldtype:init', {
              detail: { fieldtype: block.type, name: block.name }
            });
            el.dispatchEvent(event);
          });
        } else {
          contentContainer.innerHTML = `<div class="block-error">Error loading fieldtype: ${data.message || 'Unknown error'}</div>`;
        }
      })
      .catch(err => {
        console.error('Error loading fieldtype:', err);
        contentContainer.innerHTML = `<div class="block-error">Error loading fieldtype: ${err.message}</div>`;
      });
  },
  
  // Initialize fieldtype scripts
  initializeFieldtypeScripts: function(fieldName) {
    // This is called after blocks are rendered to ensure all scripts are loaded
    // Fieldtype-specific initialization should happen in their own JavaScript
  },
  
  // Edit a block
  editBlock: function(fieldName, idx) {
    const blocks = this.getValue(fieldName);
    const block = blocks[idx];
    if (!block) return;
    
    // For now, just show an alert - can be enhanced with a proper edit modal
    alert('Edit functionality coming soon. For now, remove and re-add the block.');
  },
  
  // Remove a block
  removeBlock: function(fieldName, idx) {
    if (!confirm('Are you sure you want to remove this block?')) {
      return;
    }
    
    const blocks = this.getValue(fieldName);
    blocks.splice(idx, 1);
    this.renderBlocks(fieldName, blocks);
    this.updateHiddenInput(fieldName, blocks);
  },
  
  // Get value from DOM
  getValue: function(fieldName) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return [];
    
    const blocks = [];
    const blockElements = container.querySelectorAll('.block-item');
    
    blockElements.forEach((blockElement) => {
      // Use actual data attributes from the element instead of forEach index
      // This ensures correct retrieval even if blocks were deleted or reordered
      const blockIdx = blockElement.getAttribute('data-block-idx');
      const blockName = blockElement.getAttribute('data-block-name');
      const blockData = this.getBlockDataFromDom(fieldName, blockName, blockIdx);
      if (blockData) {
        blocks.push(blockData);
      }
    });
    
    // If no blocks in DOM, try to read from hidden input
    if (blocks.length === 0) {
      const hiddenInput = document.getElementById('field_' + fieldName + '_value');
      if (hiddenInput && hiddenInput.value) {
        try {
          const parsed = JSON.parse(hiddenInput.value);
          return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
          console.error('Failed to parse Dynamic Items value:', e);
          return [];
        }
      }
    }
    
    return blocks;
  },
  
  // Get block data from DOM
  getBlockDataFromDom: function(fieldName, blockName, idx) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return null;
    
    const blockElement = container.querySelector(`[data-block-idx="${idx}"]`) || container.querySelector(`[data-block-name="${blockName}"]`);
    if (!blockElement) return null;
    
    // Get block metadata from data attributes or stored config
    const blockConfig = blockElement.getAttribute('data-block-config');
    if (blockConfig) {
      try {
        const config = JSON.parse(blockConfig);
        // Get current value from the fieldtype input
        const contentContainer = blockElement.querySelector('.block-item-content');
        if (contentContainer) {
          const value = this.extractFieldtypeValue(contentContainer, config.type);
          config.value = value;
        }
        return config;
      } catch (e) {
        console.error('Failed to parse block config:', e);
      }
    }
    
    return null;
  },
  
  // Extract value from fieldtype DOM
  extractFieldtypeValue: function(container, fieldtype) {
    if (!container) return null;
    
    // Try to find hidden input first (many fieldtypes use this)
    const hiddenInput = container.querySelector('input[type="hidden"]');
    if (hiddenInput && hiddenInput.value) {
      try {
        const parsed = JSON.parse(hiddenInput.value);
        return parsed;
      } catch (e) {
        return hiddenInput.value;
      }
    }
    
    // Check for multi-checkbox first (before single checkbox handling)
    // This prevents early return that would skip multi-checkbox handling
    const allCheckboxes = container.querySelectorAll('input[type="checkbox"]:not([type="hidden"])');
    if (allCheckboxes.length > 1) {
      // Multiple checkboxes: return array of checked values
      const checkedBoxes = container.querySelectorAll('input[type="checkbox"]:checked');
      if (checkedBoxes.length > 0) {
        return Array.from(checkedBoxes).map(cb => cb.value || cb.checked);
      }
      return [];
    }
    
    // Try to find the main input/textarea/select
    const input = container.querySelector('input:not([type="hidden"]), textarea, select');
    if (input) {
      if (input.type === 'checkbox') {
        // Single checkbox: return boolean
        return input.checked;
      }
      if (input.type === 'radio') {
        const checked = container.querySelector('input[type="radio"]:checked');
        return checked ? checked.value : null;
      }
      return input.value || null;
    }
    
    // For color picker
    const colorInput = container.querySelector('input[type="color"]');
    if (colorInput) {
      return colorInput.value;
    }
    
    // For range slider
    const rangeInput = container.querySelector('input[type="range"]');
    if (rangeInput) {
      return rangeInput.value;
    }
    
    return null;
  },
  
  // Set value
  setValue: function(fieldName, value) {
    value = Array.isArray(value) ? value : [];
    this.renderBlocks(fieldName, value);
    this.updateHiddenInput(fieldName, value);
  },
  
  // Update hidden input
  updateHiddenInput: function(fieldName, blocks) {
    const hiddenInput = document.getElementById('field_' + fieldName + '_value');
    if (hiddenInput) {
      // Get current values from DOM before saving
      const blocksWithValues = blocks.map((block, idx) => {
        const container = document.getElementById('field_' + fieldName);
        if (!container) return block;
        
        const blockElement = container.querySelector(`[data-block-idx="${idx}"]`);
        if (!blockElement) return block;
        
        const contentContainer = blockElement.querySelector('.block-item-content');
        if (contentContainer) {
          const value = this.extractFieldtypeValue(contentContainer, block.type);
          return { ...block, value: value };
        }
        
        return block;
      });
      
      hiddenInput.value = JSON.stringify(blocksWithValues);
    }
  },
  
  // Get field config from DOM
  getFieldConfigFromDom: function(fieldName) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return null;
    
    const configStr = container.getAttribute('data-field-config');
    if (!configStr) return null;
    
    try {
      return JSON.parse(configStr);
    } catch (e) {
      console.error('Failed to parse field config:', e);
      return null;
    }
  },
  
  // Escape HTML
  escapeHtml: function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};

// CSP blocks inline onclick in admin (P16-M5) — use delegated listeners.
function bindDynamicItemsDelegatedEvents() {
  if (document.documentElement.dataset.dynamicitemsDelegationBound === '1') {
    return;
  }
  document.documentElement.dataset.dynamicitemsDelegationBound = '1';

  document.addEventListener('click', function(e) {
    const api = window.HaruFieldtypes && window.HaruFieldtypes['dynamicitems'];
    if (!api) {
      return;
    }

    const actionEl = e.target.closest('[data-dynamicitems-action]');
    if (!actionEl) {
      return;
    }

    const action = actionEl.getAttribute('data-dynamicitems-action');
    if (!action) {
      return;
    }

    if (action === 'add-block') {
      e.preventDefault();
      api.showAddBlockModalFromButton(actionEl);
      return;
    }

    const fieldName = api.resolveFieldNameFromNode(actionEl);
    if (!fieldName && action !== 'modal-close' && action !== 'modal-cancel' && action !== 'modal-confirm') {
      return;
    }

    if (action === 'edit-block') {
      e.preventDefault();
      const idx = parseInt(actionEl.getAttribute('data-block-idx') || '', 10);
      if (!Number.isNaN(idx)) {
        api.editBlock(fieldName, idx);
      }
      return;
    }

    if (action === 'remove-block') {
      e.preventDefault();
      const idx = parseInt(actionEl.getAttribute('data-block-idx') || '', 10);
      if (!Number.isNaN(idx)) {
        api.removeBlock(fieldName, idx);
      }
      return;
    }

    const modal = actionEl.closest('.dynamicitems-modal-overlay');
    if (!modal) {
      return;
    }

    if (action === 'modal-close' || action === 'modal-cancel') {
      e.preventDefault();
      modal.remove();
      return;
    }

    if (action === 'modal-confirm') {
      e.preventDefault();
      const modalFieldName = modal.getAttribute('data-dynamicitems-field') || fieldName;
      if (modalFieldName) {
        api.addBlockFromModal(modalFieldName);
      }
    }
  });
}

bindDynamicItemsDelegatedEvents();

// Initialize function for DynamicItems fieldtype
function initDynamicItemsField(fieldName) {
  console.log('[DynamicItems] Initializing field:', fieldName);
  
  const field = document.getElementById('field_' + fieldName);
  if (!field) {
    console.error('[DynamicItems] Field container not found:', fieldName);
    return;
  }
  
  const hiddenInput = field.querySelector('input[type="hidden"]');
  if (hiddenInput && hiddenInput.value) {
    try {
      const value = JSON.parse(hiddenInput.value);
      if (Array.isArray(value) && value.length > 0) {
        console.log('[DynamicItems] Loading existing blocks:', value);
        window.HaruFieldtypes['dynamicitems'].setValue(fieldName, value);
      }
    } catch (e) {
      console.error('[DynamicItems] Failed to parse value:', e);
      hiddenInput.value = '[]';
    }
  }
  
  // Update hidden input when blocks change
  const blockObserver = new MutationObserver(() => {
    const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);
    window.HaruFieldtypes['dynamicitems'].updateHiddenInput(fieldName, blocks);
  });
  
  const blocksContainer = field.querySelector('.block-items');
  if (blocksContainer) {
    blockObserver.observe(blocksContainer, { childList: true, subtree: true });
  }
  
  // Listen for input/change events to update values
  blocksContainer.addEventListener('input', function() {
    window.HaruFieldtypes['dynamicitems'].updateHiddenInput(fieldName, window.HaruFieldtypes['dynamicitems'].getValue(fieldName));
  });
  blocksContainer.addEventListener('change', function() {
    window.HaruFieldtypes['dynamicitems'].updateHiddenInput(fieldName, window.HaruFieldtypes['dynamicitems'].getValue(fieldName));
  });
  
  console.log('[DynamicItems] Field initialized successfully:', fieldName);
}

// Self-initializing function
function initializeAllDynamicItemsFields() {
  const dynamicItemsFields = document.querySelectorAll('.block-field');
  dynamicItemsFields.forEach(field => {
    const fieldName = field.id.replace('field_', '');
    initDynamicItemsField(fieldName);
  });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAllDynamicItemsFields);
} else {
  initializeAllDynamicItemsFields();
}

// Listen for save events
document.addEventListener('beforeSaveEntry', function() {
  const dynamicItemsFields = document.querySelectorAll('.block-field');
  dynamicItemsFields.forEach(field => {
    const fieldName = field.id.replace('field_', '');
    const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);
    window.HaruFieldtypes['dynamicitems'].updateHiddenInput(fieldName, blocks);
  });
});

// Expose the function globally
window.initDynamicItemsField = initDynamicItemsField;
