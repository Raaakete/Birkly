window.HaruFieldtypes = window.HaruFieldtypes || {};

// Register Tags fieldtype configuration
if (window.HaruFieldtypeConfig) {
    window.HaruFieldtypeConfig.register('tags', {
        selectors: {
            container: '#tags_field_{fieldName}',
            hiddenInput: '#field_{fieldName}_value',
            input: '#field_{fieldName}_input',
            chips: '.tags-chips',
            chip: '.tag-chip',
            removeButton: '.tag-remove'
        },
        settings: {
            autoSave: true,
            validation: true,
            debug: true,
            allowDuplicates: false,
            maxTags: 50
        },
        events: {
            onChange: true,
            onBlur: false,
            onFocus: false,
            onKeyDown: true
        }
    });
}

// Register Tags fieldtype validation rules
if (window.HaruFieldtypeValidation) {
    window.HaruFieldtypeValidation.registerValidationRules('tags', {
        '*': [
            { type: 'max_length', param: 50, message: 'Maximum 50 tags allowed' }
        ]
    });
    
    // Register custom validator for tags
    window.HaruFieldtypeValidation.registerValidator('tags_duplicate', (value, fieldName, allowDuplicates) => {
        if (!Array.isArray(value)) return { isValid: true };
        
        const duplicates = value.filter((tag, index) => value.indexOf(tag) !== index);
        if (duplicates.length > 0 && !allowDuplicates) {
            return { isValid: false, error: 'Duplicate tags are not allowed' };
        }
        
        return { isValid: true };
    });
    
    // Register error handler for tags
    window.HaruFieldtypeValidation.registerErrorHandler('tags', (error) => {
        console.error('[Tags Fieldtype] Validation error:', error);
        
        // Show error in UI if possible
        if (error.field) {
            const container = document.querySelector(`#tags_field_${error.field}`);
            if (container) {
                const errorElement = document.createElement('div');
                errorElement.className = 'tags-validation-error';
                errorElement.style.color = '#dc3545';
                errorElement.style.fontSize = '0.875rem';
                errorElement.style.marginTop = '0.25rem';
                errorElement.textContent = error.message;
                container.appendChild(errorElement);
            }
        }
    });
}

// Register Tags fieldtype performance optimizations
if (window.HaruFieldtypePerformance) {
    // Register lazy loader for tags
    window.HaruFieldtypePerformance.registerLazyLoader('tags', async (element) => {
        // Simulate lazy loading of tags functionality
        await new Promise(resolve => setTimeout(resolve, 50));
        console.log('[Tags] Lazy loaded for element:', element.id);
    }, {
        threshold: 200,
        once: true
    });
    
    // Cache common tag operations
    window.HaruFieldtypePerformance.cache('tags_default_config', {
        maxTags: 50,
        allowDuplicates: false,
        separator: ','
    }, 300000); // 5 minutes
}

// Chips carry data attributes; removal is delegated (the admin CSP blocks inline handlers).
function tagEscape(text) {
  return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function tagChipHtml(fieldName, tag) {
  const safe = tagEscape(tag);
  return `<span class="tag-chip">${safe}<button type="button" class="tag-remove" data-tags-field="${tagEscape(fieldName)}" data-tag="${safe}" aria-label="Remove ${safe}">&times;</button></span>`;
}

document.addEventListener('click', function (event) {
  const btn = event.target.closest ? event.target.closest('.tag-remove[data-tag]') : null;
  if (!btn || !window.HaruFieldtypes || !window.HaruFieldtypes.tags) return;
  event.preventDefault();
  window.HaruFieldtypes.tags.removeTag(btn.getAttribute('data-tags-field'), btn.getAttribute('data-tag'));
});

window.HaruFieldtypes['tags'] = {
  label: 'Tags',
  category: 'content',
  dynamicItemsEligible: true,
  
  render: function(fieldConfig, value) {
    value = Array.isArray(value) ? value : [];
    const tagsHtml = value.map((tag, idx) =>
      tagChipHtml(fieldConfig.name, tag)
    ).join('');
    
    return `<div class="tags-field-container" id="field_${fieldConfig.name}">
      <div class="tags-field" id="tags_field_${fieldConfig.name}">
        <div class="tags-chips">${tagsHtml}</div>
        <input type="text" class="tags-field-input" id="field_${fieldConfig.name}_input" placeholder="Add tag and press Enter">
      </div>
      <input type="hidden" name="${fieldConfig.name}" id="field_${fieldConfig.name}_value" value="">
    </div>`;
  },
  
  getValue: function(fieldName) {
    console.log('[Tags] getValue called for field:', fieldName);
    
    // Use configuration for selectors
    const containerSelector = window.HaruFieldtypeConfig.getSelector('tags', 'container', fieldName);
    const chipSelector = window.HaruFieldtypeConfig.getSelector('tags', 'chip', fieldName);
    
    const container = window.HaruFieldtypeInterface.getElement(containerSelector, 'Tags getValue');
    if (!container) return [];
    
    const chips = window.HaruFieldtypeInterface.getElements(chipSelector, container, 'Tags getValue');
    console.log('[Tags] Found tag chips:', chips.length);
    const tags = Array.from(chips).map(chip => {
      const textContent = chip.childNodes[0].textContent;
      console.log('[Tags] Tag chip text:', textContent);
      return textContent;
    });
    console.log('[Tags] Returning tags:', tags);
    return tags;
  },
  
  setValue: function(fieldName, value) {
    console.log('[Tags] setValue called for:', fieldName, 'with value:', value);
    
    // Get old value for event emission
    const oldValue = this.getValue(fieldName);
    
    // Use cached configuration if available
    let config = null;
    if (window.HaruFieldtypePerformance) {
      config = window.HaruFieldtypePerformance.getCached('tags_default_config');
    }
    
    // Use configuration for selectors
    const containerSelector = window.HaruFieldtypeConfig.getSelector('tags', 'container', fieldName);
    const chipsSelector = window.HaruFieldtypeConfig.getSelector('tags', 'chips', fieldName);
    const removeButtonSelector = window.HaruFieldtypeConfig.getSelector('tags', 'removeButton', fieldName);
    
    // Use performance-optimized DOM query
    const container = window.HaruFieldtypePerformance ? 
      window.HaruFieldtypePerformance.queryDOM(containerSelector, document, `tags_container_${fieldName}`)[0] :
      window.HaruFieldtypeInterface.getElement(containerSelector, 'Tags setValue');
      
    if (!container) {
      console.error('[Tags] Container not found for field:', fieldName);
      return;
    }
    
    // Batch DOM operations for better performance
    if (window.HaruFieldtypePerformance) {
      window.HaruFieldtypePerformance.batchDOMOperations([
        () => {
          // Update the hidden input using the interface
          const jsonValue = JSON.stringify(value);
          window.HaruFieldtypeInterface.updateHiddenInput(fieldName, jsonValue, 'Tags setValue');
          console.log('[Tags] Updated hidden input with value:', jsonValue);
        },
        () => {
          // Update only the visible tag chips
          const chipsContainer = container.querySelector(chipsSelector);
          if (chipsContainer) {
            const tagsHtml = value.map((tag, idx) =>
              tagChipHtml(fieldName, tag)
            ).join('');
            chipsContainer.innerHTML = tagsHtml;
          }
        }
      ]);
    } else {
      // Fallback to regular operations
      const jsonValue = JSON.stringify(value);
      window.HaruFieldtypeInterface.updateHiddenInput(fieldName, jsonValue, 'Tags setValue');
      
      const chipsContainer = container.querySelector(chipsSelector);
      if (chipsContainer) {
        const tagsHtml = value.map((tag, idx) =>
          tagChipHtml(fieldName, tag)
        ).join('');
        chipsContainer.innerHTML = tagsHtml;
      }
    }
    
    console.log('[Tags] Container updated without re-rendering');
    this.attachInputHandler(fieldName);
    
    // Validate the new value
    if (window.HaruFieldtypeValidation) {
      const validationResult = window.HaruFieldtypeValidation.validateField(fieldName, 'tags', value);
      
      if (!validationResult.isValid) {
        console.warn('[Tags] Validation failed:', validationResult.errors);
        
        // Handle validation errors
        validationResult.errors.forEach(error => {
          window.HaruFieldtypeValidation.handleError('tags', error);
        });
      }
    }
    
    // Emit value change event
    if (window.HaruFieldtypeEvents) {
      window.HaruFieldtypeEvents.emitValueChange(fieldName, 'tags', oldValue, value);
      
      // Emit fieldtype-specific event
      window.HaruFieldtypeEvents.emitFieldtype('tags', 'value:set', {
        fieldName,
        oldValue,
        newValue: value,
        tagsCount: value.length
      });
    }
  },
  
  attachInputHandler: function(fieldName) {
    console.log('[Tags] Attaching input handler for field:', fieldName);
    
    // Use configuration for selectors
    const inputSelector = window.HaruFieldtypeConfig.getSelector('tags', 'input', fieldName);
    const input = window.HaruFieldtypeInterface.getElement(inputSelector, 'Tags attachInputHandler');
    if (!input) {
      console.error('[Tags] Input element not found for field:', fieldName);
      return;
    }
    
    const self = this;
    const handler = function(e) {
      console.log('[Tags] Key pressed:', e.key);
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const val = window.HaruFieldtypeInterface.getValue(input, 'Tags input handler').trim();
        console.log('[Tags] Adding tag:', val);
        if (val) {
          let tags = self.getValue(fieldName);
          console.log('[Tags] Current tags:', tags);
          
          // Check configuration settings
          const allowDuplicates = window.HaruFieldtypeConfig.getSetting('tags', 'allowDuplicates');
          const maxTags = window.HaruFieldtypeConfig.getSetting('tags', 'maxTags');
          
          if (!allowDuplicates && tags.includes(val)) {
            console.log('[Tags] Duplicate tag not allowed:', val);
            
            // Emit validation error event
            if (window.HaruFieldtypeEvents) {
              window.HaruFieldtypeEvents.emitValidation(fieldName, 'tags', false, ['Duplicate tag not allowed']);
            }
            return;
          }
          
          if (maxTags && tags.length >= maxTags) {
            console.log('[Tags] Maximum tags reached:', maxTags);
            
            // Emit validation error event
            if (window.HaruFieldtypeEvents) {
              window.HaruFieldtypeEvents.emitValidation(fieldName, 'tags', false, [`Maximum ${maxTags} tags allowed`]);
            }
            return;
          }
          
          // Emit user interaction event
          if (window.HaruFieldtypeEvents) {
            window.HaruFieldtypeEvents.emitUserInteraction(fieldName, 'tags', 'add_tag', {
              tagValue: val,
              currentTags: tags,
              newTagCount: tags.length + 1
            });
          }
          
          tags.push(val);
          console.log('[Tags] Updated tags:', tags);
          self.setValue(fieldName, tags);
        }
        window.HaruFieldtypeInterface.setValue(input, '', 'Tags input handler');
      }
    };
    
    // Use interface to add event listener with context for cleanup
    window.HaruFieldtypeInterface.addEventListener(input, 'keydown', handler, `tags_${fieldName}`);
  },
  
  removeTag: function(fieldName, tagValue) {
    console.log('[Tags] removeTag called for field:', fieldName, 'tag value:', tagValue);
    let tags = this.getValue(fieldName);
    console.log('[Tags] Current tags before removal:', tags);
    const index = tags.indexOf(tagValue);
    if (index > -1) {
      tags.splice(index, 1);
      console.log('[Tags] Tags after removal:', tags);
      
      // Emit user interaction event
      if (window.HaruFieldtypeEvents) {
        window.HaruFieldtypeEvents.emitUserInteraction(fieldName, 'tags', 'remove_tag', {
          removedTag: tagValue,
          remainingTags: tags,
          removedIndex: index
        });
      }
      
      this.setValue(fieldName, tags);
    } else {
      console.log('[Tags] Tag not found:', tagValue);
      
      // Emit error event
      if (window.HaruFieldtypeEvents) {
        window.HaruFieldtypeEvents.emit('fieldtype:error:occurred', {
          error: `Tag '${tagValue}' not found in field '${fieldName}'`,
          fieldName,
          fieldtypeName: 'tags'
        }, 'tags');
      }
    }
  },
  
  updateHiddenInput: function(fieldName, value) {
    console.log('[Tags] updateHiddenInput called for:', fieldName, 'with value:', value);
    const jsonValue = JSON.stringify(value);
    window.HaruFieldtypeInterface.updateHiddenInput(fieldName, jsonValue, 'Tags updateHiddenInput');
    console.log('[Tags] Updated hidden input with value:', jsonValue);
  },
  
  renderConfig: function(config, onChange) {
    return null;
  },
  
  afterRender: function(fieldName) {
    this.attachInputHandler(fieldName);
  }
};

// Simple initialization - just attach handlers to existing fields
function initializeTagsFields() {
  console.log('[Tags] Initializing all tags fields...');
  const tagsFields = window.HaruFieldtypeInterface.getElements('.tags-field', document, 'Tags initialization');
  console.log('[Tags] Found tags fields:', tagsFields.length);
  tagsFields.forEach(field => {
    const fieldName = field.id.replace('tags_field_', '');
    console.log('[Tags] Initializing tags field:', fieldName);
    window.HaruFieldtypes['tags'].attachInputHandler(fieldName);
  });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeTagsFields);
} else {
  initializeTagsFields();
}

// Initialize new fields as they're added using the interface
if (!window.tagsObserver && window.HaruFieldtypeInterface && typeof window.HaruFieldtypeInterface.createObserver === 'function') {
  const observerCallback = (mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) {
          const tagsFields = window.HaruFieldtypeInterface.getElements('.tags-field', node, 'Tags observer');
          tagsFields.forEach(field => {
            const fieldName = field.id.replace('tags_field_', '');
            console.log('Found new tags field:', fieldName);
            window.HaruFieldtypes['tags'].attachInputHandler(fieldName);
          });
        }
      });
    });
  };

  try {
  window.tagsObserver = window.HaruFieldtypeInterface.createObserver(observerCallback, { childList: true, subtree: true }, 'tags');
    const body = window.HaruFieldtypeInterface.getElements('body')[0];
    if (body && window.tagsObserver) {
      window.tagsObserver.observe(body, { childList: true, subtree: true });
    }
  } catch (e) {
    console.warn('[Tags] Could not create observer:', e);
  }
}

// Listen for save events to update hidden inputs
// Use a flag to prevent multiple handlers
if (!window._tagsBeforeSaveHandlerAdded) {
  window._tagsBeforeSaveHandlerAdded = true;
document.addEventListener('beforeSaveEntry', function() {
  console.log('=== Tags fieldtype received beforeSaveEntry event ===');
    
    // Use standard DOM methods as fallback
    const tagsFields = document.querySelectorAll('.tags-field');
  console.log('Found tags fields:', tagsFields.length);
    
  tagsFields.forEach(field => {
    const fieldName = field.id.replace('tags_field_', '');
      if (!fieldName) return;
      
    console.log('Processing tags field:', fieldName);
      
      // Get tags from DOM chips (most reliable)
      const chips = field.querySelectorAll('.tag-chip');
      const tags = Array.from(chips).map(chip => {
        const removeBtn = chip.querySelector('.tag-remove');
        if (removeBtn) {
          // Get text content and remove the × character
          return chip.textContent.trim().replace(/×$/, '').trim();
        }
        return chip.textContent.trim();
      }).filter(tag => tag !== '');
      
    console.log('Current tags for field:', fieldName, ':', tags);
    
      // Update hidden input directly
      const hiddenInput = document.getElementById('field_' + fieldName + '_value');
    if (hiddenInput) {
        const jsonValue = JSON.stringify(tags);
        hiddenInput.value = jsonValue;
        console.log('Tags fieldtype updated hidden input for:', fieldName, 'with tags:', tags, 'JSON:', jsonValue);
    } else {
      console.error('Hidden input not found for field:', fieldName);
    }
  });
});
}

// Backward compatibility
window.initTagsField = function(fieldName) {
  window.HaruFieldtypes['tags'].attachInputHandler(fieldName);
}; 