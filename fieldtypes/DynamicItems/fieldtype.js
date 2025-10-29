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
            typeSelect: '.block-type-select',
            removeButton: '.remove-block-btn'
        },
        settings: {
            autoSave: true,
            validation: true,
            debug: true,
            allowEmptyBlocks: false,
            maxBlocks: 20,
            defaultBlockType: 'text'
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

// Block type registry - keep it simple and reliable
window.HaruBlockTypes = {
  text: {
    label: 'Text',
    render: function(value, blockIdx) {
      value = value || '';
      return `<input type="text" class="input" id="block_${blockIdx}_text_value" placeholder="Enter text..." value="${value}">`;
    },
    getValue: function(blockIdx) {
      const input = document.getElementById(`block_${blockIdx}_text_value`);
      return input ? input.value : '';
    }
  },
  textarea: {
    label: 'Textarea',
    render: function(value, blockIdx) {
      value = value || '';
      return `<textarea class="input" id="block_${blockIdx}_textarea_value" rows="3" placeholder="Enter text...">${value}</textarea>`;
    },
    getValue: function(blockIdx) {
      const textarea = document.getElementById(`block_${blockIdx}_textarea_value`);
      return textarea ? textarea.value : '';
    }
  },
  quote: {
    label: 'Quote',
    render: function(value, blockIdx) {
      value = value || { text: '', author: '' };
      return `<textarea class='input' id='block_${blockIdx}_quote_text' rows='2' placeholder='Quote...'>${value.text || ''}</textarea><input type='text' class='input' id='block_${blockIdx}_quote_author' placeholder='Author' value='${value.author || ''}'>`;
    },
    getValue: function(blockIdx) {
      return {
        text: document.getElementById('block_' + blockIdx + '_quote_text')?.value || '',
        author: document.getElementById('block_' + blockIdx + '_quote_author')?.value || ''
      };
    }
  },
  number: {
    label: 'Number',
    render: function(value, blockIdx) {
      value = value || '';
      return `<input type="number" class="input" id="block_${blockIdx}_number_value" placeholder="Enter number..." value="${value}">`;
    },
    getValue: function(blockIdx) {
      const input = document.getElementById(`block_${blockIdx}_number_value`);
      return input ? input.value : '';
    }
  },
  date: {
    label: 'Date',
    render: function(value, blockIdx) {
      value = value || '';
      return `<input type="date" class="input" id="block_${blockIdx}_date_value" value="${value}">`;
    },
    getValue: function(blockIdx) {
      const input = document.getElementById(`block_${blockIdx}_date_value`);
      return input ? input.value : '';
    }
  },
  checkbox: {
    label: 'Checkbox',
    render: function(value, blockIdx) {
      value = value || false;
      return `<input type="checkbox" class="input" id="block_${blockIdx}_checkbox_value" ${value ? 'checked' : ''}> <label for="block_${blockIdx}_checkbox_value">Check this option</label>`;
    },
    getValue: function(blockIdx) {
      const input = document.getElementById(`block_${blockIdx}_checkbox_value`);
      return input ? input.checked : false;
    }
  }
};

// Function to dynamically load additional block types from fieldtypes
function loadAdditionalBlockTypes() {
  // Add other fieldtypes as block types if they're available
  const additionalTypes = ['select', 'media', 'datetime'];
  
  additionalTypes.forEach(type => {
    if (window.HaruFieldtypes && window.HaruFieldtypes[type]) {
      window.HaruBlockTypes[type] = {
        label: window.HaruFieldtypes[type].label || type.charAt(0).toUpperCase() + type.slice(1),
        render: function(value, blockIdx) {
          // Create a simple input for the block
          return `<input type="text" class="input" id="block_${blockIdx}_${type}_value" placeholder="${this.label}..." value="${value || ''}">`;
        },
        getValue: function(blockIdx) {
          const input = document.getElementById(`block_${blockIdx}_${type}_value`);
          return input ? input.value : '';
        }
      };
    }
  });
}

// DynamicItems fieldtype implementation
window.HaruFieldtypes['dynamicitems'] = {
  render: function(fieldConfig, value) {
    value = Array.isArray(value) ? value : [];
    const allowedBlocks = fieldConfig.allowedBlocks || ['text', 'textarea', 'quote'];
    let blocksHtml = value.map((block, idx) => this.renderBlock(block, idx, allowedBlocks)).join('');
    
    return `<div class='block-field' id='field_${fieldConfig.name}' data-field-config='${JSON.stringify(fieldConfig)}'>
      <div class='block-items' ondragover='event.preventDefault()'>${blocksHtml}</div>
      <div class='block-controls'>
        <select class='input' id='add_block_type_${fieldConfig.name}'>
          ${allowedBlocks.map(type => `<option value='${type}'>${window.HaruBlockTypes[type]?.label || type}</option>`).join('')}
        </select>
        <button type='button' class='btn btn-secondary' onclick='window.HaruFieldtypes["dynamicitems"].addBlock("${fieldConfig.name}")'>Add Block</button>
      </div>
      <input type='hidden' name='${fieldConfig.name}' id='field_${fieldConfig.name}_value' value='${JSON.stringify(value)}'>
    </div>`;
  },
  
  renderBlock: function(block, idx, allowedBlocks) {
    const type = block.type || 'text';
    const blockType = window.HaruBlockTypes[type];
    if (!blockType) return '';
    
    let value = block.value || '';
    let blockHtml = `<div class='block-item' data-idx='${idx}' draggable='true' ondragstart='window.HaruFieldtypes["dynamicitems"].onDragStart(event, ${idx})' ondrop='window.HaruFieldtypes["dynamicitems"].onDrop(event, ${idx})'>`;
    blockHtml += `<select class='input block-type-select' onchange='window.HaruFieldtypes["dynamicitems"].changeBlockType(${idx}, this.value)'>`;
    allowedBlocks.forEach(t => {
      blockHtml += `<option value='${t}'${t === type ? ' selected' : ''}>${window.HaruBlockTypes[t]?.label || t}</option>`;
    });
    blockHtml += `</select>`;
    blockHtml += blockType.render(value, idx);
    blockHtml += `<button type='button' class='btn btn-danger btn-sm' onclick='window.HaruFieldtypes["dynamicitems"].removeBlock(${idx})'>Remove</button>`;
    blockHtml += `</div>`;
    return blockHtml;
  },
  
  getValue: function(fieldName) {
    const containerSelector = window.HaruFieldtypeConfig.getSelector('dynamicitems', 'container', fieldName);
    const blockSelector = window.HaruFieldtypeConfig.getSelector('dynamicitems', 'block', fieldName);
    const hiddenInputSelector = window.HaruFieldtypeConfig.getSelector('dynamicitems', 'hiddenInput', fieldName);
    
    const container = window.HaruFieldtypeInterface.getElement(containerSelector, 'DynamicItems getValue');
    if (!container) return [];
    
    // If there are existing block items in the DOM, read from them
    const items = Array.from(window.HaruFieldtypeInterface.getElements(blockSelector, container, 'DynamicItems getValue'));
    if (items.length > 0) {
      return items.map((itemDiv, idx) => {
        const typeSelect = itemDiv.querySelector('.block-type-select');
        const type = typeSelect ? typeSelect.value : window.HaruFieldtypeConfig.getSetting('dynamicitems', 'defaultBlockType');
        const blockType = window.HaruBlockTypes[type];
        let value = blockType ? blockType.getValue(idx) : '';
        return { type, value };
      });
    }
    
    // Otherwise, try to read from the hidden input (initialization case)
    const hiddenInput = window.HaruFieldtypeInterface.getElement(hiddenInputSelector, 'DynamicItems getValue');
    if (hiddenInput && window.HaruFieldtypeInterface.getValue(hiddenInput, 'DynamicItems getValue')) {
      try {
        const parsed = JSON.parse(window.HaruFieldtypeInterface.getValue(hiddenInput, 'DynamicItems getValue'));
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        console.log('Failed to parse Dynamic Items value, using empty array');
        return [];
      }
    }
    
    return [];
  },
  
  setValue: function(fieldName, value) {
    const container = window.HaruFieldtypeInterface.getElement('field_' + fieldName, 'DynamicItems setValue');
    if (!container) return;
    
    // Re-render with new value
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    // Ensure value is an array
    value = Array.isArray(value) ? value : [];
    
    // Re-render the entire container with the new value
    container.innerHTML = this.render(fieldConfig, value);
    
    // Update the hidden input
    this.updateHiddenInput(fieldName, value);
    
    // Attach event listeners to the new inputs
    this.attachInputListeners(fieldName);
    
    console.log('[DynamicItems] Set value for field:', fieldName, 'with blocks:', value);
  },
  
  addBlock: function(fieldName) {
    const container = window.HaruFieldtypeInterface.getElement('field_' + fieldName, 'DynamicItems addBlock');
    if (!container) return;
    
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    const value = this.getValue(fieldName);
    const select = document.getElementById('add_block_type_' + fieldName);
    const type = select ? select.value : 'text';
    
    let blockValue = '';
    if (type === 'quote') blockValue = { text: '', author: '' };
    
    value.push({ type, value: blockValue });
    container.innerHTML = this.render(fieldConfig, value);
    this.updateHiddenInput(fieldName, value);
    this.attachInputListeners(fieldName);
  },
  
  removeBlock: function(idx) {
    const items = document.querySelectorAll('.block-item');
    const item = items[idx];
    if (!item) return;
    
    const container = item.closest('.block-field');
    if (!container) return;
    
    const fieldName = container.id.replace('field_', '');
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    const value = this.getValue(fieldName);
    value.splice(idx, 1);
    container.innerHTML = this.render(fieldConfig, value);
    this.updateHiddenInput(fieldName, value);
    this.attachInputListeners(fieldName);
  },
  
  changeBlockType: function(idx, newType) {
    const items = document.querySelectorAll('.block-item');
    const item = items[idx];
    if (!item) return;
    
    const container = item.closest('.block-field');
    if (!container) return;
    
    const fieldName = container.id.replace('field_', '');
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    const value = this.getValue(fieldName);
    let blockValue = '';
    if (newType === 'quote') blockValue = { text: '', author: '' };
    
    value[idx] = { type: newType, value: blockValue };
    container.innerHTML = this.render(fieldConfig, value);
    this.updateHiddenInput(fieldName, value);
    this.attachInputListeners(fieldName);
  },
  
  onDragStart: function(event, idx) {
    event.dataTransfer.setData('text/plain', idx);
    event.dataTransfer.effectAllowed = 'move';
  },
  
  onDrop: function(event, targetIdx) {
    event.preventDefault();
    const sourceIdx = parseInt(event.dataTransfer.getData('text/plain'), 10);
    if (isNaN(sourceIdx) || sourceIdx === targetIdx) return;
    
    const items = document.querySelectorAll('.block-item');
    const container = items[targetIdx]?.closest('.block-field');
    if (!container) return;
    
    const fieldName = container.id.replace('field_', '');
    const fieldConfig = this.getFieldConfigFromDom(fieldName);
    if (!fieldConfig) return;
    
    const value = this.getValue(fieldName);
    const [moved] = value.splice(sourceIdx, 1);
    value.splice(targetIdx, 0, moved);
    container.innerHTML = this.render(fieldConfig, value);
    this.updateHiddenInput(fieldName, value);
    this.attachInputListeners(fieldName);
  },
  
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
  
  updateHiddenInput: function(fieldName, value) {
    const hiddenInput = document.getElementById('field_' + fieldName + '_value');
    if (hiddenInput) {
      hiddenInput.value = JSON.stringify(value);
      console.log('Updated Dynamic Items hidden input:', value);
    }
  },
  
  // Add event listeners to update hidden input when any input changes
  attachInputListeners: function(fieldName) {
    const container = document.getElementById('field_' + fieldName);
    if (!container) return;
    
    // Add event listeners to all inputs in the container
    const inputs = container.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
      input.addEventListener('input', () => {
        const value = this.getValue(fieldName);
        this.updateHiddenInput(fieldName, value);
      });
      input.addEventListener('change', () => {
        const value = this.getValue(fieldName);
        this.updateHiddenInput(fieldName, value);
      });
    });
  }
};

// Initialize function for DynamicItems fieldtype
function initDynamicItemsField(fieldName) {
  console.log('[DynamicItems] Initializing field:', fieldName);
  
  // Load additional block types from available fieldtypes
  loadAdditionalBlockTypes();
  
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
        // Use setValue to properly re-render the blocks
        window.HaruFieldtypes['dynamicitems'].setValue(fieldName, value);
      }
    } catch (e) {
      console.error('[DynamicItems] Failed to parse value:', e);
      // If parsing fails, clear the hidden input to prevent future errors
      hiddenInput.value = '[]';
    }
  }
  
  // Update hidden input when blocks change (DOM mutation)
  const blockObserver = new MutationObserver(() => {
    const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);
    if (hiddenInput) {
      hiddenInput.value = JSON.stringify(blocks);
      console.log('[DynamicItems] Updated hidden input with blocks:', blocks);
    }
  });
  
  const blocksContainer = field.querySelector('.block-items');
  if (blocksContainer) {
    blockObserver.observe(blocksContainer, { childList: true, subtree: true });
    // Patch: Listen for input/change events on all block fields
    blocksContainer.addEventListener('input', function() {
      const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);
      if (hiddenInput) {
        hiddenInput.value = JSON.stringify(blocks);
        console.log('[DynamicItems] [input event] Updated hidden input with blocks:', blocks);
      }
    });
    blocksContainer.addEventListener('change', function() {
      const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);
      if (hiddenInput) {
        hiddenInput.value = JSON.stringify(blocks);
        console.log('[DynamicItems] [change event] Updated hidden input with blocks:', blocks);
      }
    });
  }
  
  // Attach input listeners to ensure hidden input is updated when users type
  window.HaruFieldtypes['dynamicitems'].attachInputListeners(fieldName);
  
  console.log('[DynamicItems] Field initialized successfully:', fieldName);
}

// Self-initializing function - automatically initializes all dynamic items fields inv fields when loaded
function initializeAllDynamicItemsFields() {
  console.log('Initializing all dynamic items fields...');
  const dynamicItemsFields = window.HaruFieldtypeInterface.getElements('.block-field', document, 'DynamicItems initializeAllDynamicItemsFields');
  dynamicItemsFields.forEach(field => {
    const fieldName = field.id.replace('field_', '');
    console.log('Initializing dynamic items field:', fieldName);
    initDynamicItemsField(fieldName);
  });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAllDynamicItemsFields);
} else {
  initializeAllDynamicItemsFields();
}

// Also initialize when new content is added (for dynamic loading)
if (!window.dynamicItemsGlobalObserver) {
  window.dynamicItemsGlobalObserver = window.HaruFieldtypeInterface.createObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          const dynamicItemsFields = window.HaruFieldtypeInterface.getElements('.block-field', node, 'DynamicItems observer');
          dynamicItemsFields.forEach(field => {
            const fieldName = field.id.replace('field_', '');
            console.log('Found new dynamic items field:', fieldName);
            initDynamicItemsField(fieldName);
          });
        }
      });
    });
  }, { childList: true, subtree: true }, 'dynamicitems');

  window.HaruFieldtypeInterface.getElements('body')[0] && window.dynamicItemsGlobalObserver.observe(document.body, { childList: true, subtree: true });
}

// Listen for save events to update hidden inputs
document.addEventListener('beforeSaveEntry', function() {
  console.log('=== DynamicItems fieldtype received beforeSaveEntry event ===');
  const dynamicItemsFields = window.HaruFieldtypeInterface.getElements('.block-field', document, 'DynamicItems beforeSaveEntry');
  console.log('Found dynamic items fields:', dynamicItemsFields.length);
  dynamicItemsFields.forEach(field => {
    const fieldName = field.id.replace('field_', '');
    console.log('Processing dynamic items field:', fieldName);
    const blocks = window.HaruFieldtypes['dynamicitems'].getValue(fieldName);
    console.log('Current blocks for field:', fieldName, ':', blocks);
    window.HaruFieldtypes['dynamicitems'].updateHiddenInput(fieldName, blocks);
    console.log('DynamicItems fieldtype updated hidden input for:', fieldName, 'with blocks:', blocks);
  });
});

// Expose the function globally for backward compatibility
window.initDynamicItemsField = initDynamicItemsField; 