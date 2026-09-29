// Self-contained Relation Fieldtype for Birkly
window.HaruFieldtypes = window.HaruFieldtypes || {};

function relationContentApiUrl(query) {
  const base = (window.Birkly && typeof window.Birkly.url === 'function')
    ? window.Birkly.url('/api/content.php')
    : '/api/content.php';
  if (!query) {
    return base;
  }
  const normalized = String(query).replace(/^\?/, '');
  return normalized ? `${base}?${normalized}` : base;
}

function relationEscape(text) {
  return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function relationCollectionKey(collection) {
  return collection.id || collection.slug || '';
}

function relationCollectionLabel(collection) {
  return collection.name || collection.title || collection.slug || 'Unknown';
}

// Register Relation fieldtype configuration
if (window.HaruFieldtypeConfig) {
    window.HaruFieldtypeConfig.register('relation', {
        selectors: {
            container: '#relation_field_{fieldName}',
            hiddenInput: '#field_{fieldName}_value',
            list: '#relation_list_{fieldName}',
            controls: '.relation-controls',
            selectButton: '.select-relation-btn',
            removeAllButton: '.remove-all-relation-btn'
        },
        settings: {
            autoSave: true,
            validation: true,
            debug: true,
            allowMultiple: true,
            maxRelations: 10,
            showCollectionSelector: true
        },
        events: {
            onChange: true,
            onBlur: false,
            onFocus: false,
            onSelect: true
        }
    });
}

// Register Relation fieldtype validation rules
if (window.HaruFieldtypeValidation) {
    window.HaruFieldtypeValidation.registerValidationRules('relation', {
        '*': [
            { type: 'max_length', param: 10, message: 'Maximum 10 relations allowed' }
        ]
    });
    
    // Register custom validator for relations
    window.HaruFieldtypeValidation.registerValidator('relation_structure', (value, fieldName) => {
        if (!Array.isArray(value)) return { isValid: true };
        
        for (let i = 0; i < value.length; i++) {
            const relation = value[i];
            if (!relation || typeof relation !== 'object') {
                return { isValid: false, error: 'Invalid relation structure' };
            }
            if (!relation.id) {
                return { isValid: false, error: 'Relation must have an ID' };
            }
        }
        
        return { isValid: true };
    });
    
    // Register error handler for relations
    window.HaruFieldtypeValidation.registerErrorHandler('relation', (error) => {
        console.error('[Relation Fieldtype] Validation error:', error);
        
        // Show error in UI if possible
        if (error.field) {
            const container = document.querySelector(`#relation_field_${error.field}`);
            if (container) {
                const errorElement = document.createElement('div');
                errorElement.className = 'relation-validation-error field-error';
                errorElement.textContent = error.message;
                container.appendChild(errorElement);
            }
        }
    });
}

window.HaruFieldtypes['relation'] = {
  label: 'Relation',
  category: 'input',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    // fieldConfig: {name, label, collection, multiple, ...}
    // value: string (single) or array (multi)
    const fieldName = fieldConfig.name;
    const isMulti = !!fieldConfig.multiple;
    
    let html = `<div class="relation-field" id="relation_field_${fieldName}">`;
    html += `<div class="relation-list" id="relation_list_${fieldName}"></div>`;
    html += `<input type="hidden" name="${fieldName}" id="field_${fieldName}_value" value="">`;
    html += `<div class="relation-controls">`;
    const plusIcon = (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon('plus', { size: 13 }) : '';
    html += `<button type="button" class="button is-small select-relation-btn" data-field="${fieldName}" data-multiple="${isMulti}">`;
    html += `${plusIcon}${isMulti ? 'Add entries' : 'Choose entry'}`;
    html += `</button>`;
    html += `<button type="button" class="button is-quiet is-small remove-all-relation-btn" data-field="${fieldName}" hidden>Remove all</button>`;
    html += `</div>`;
    html += `</div>`;
    
    setTimeout(() => {
      window.HaruFieldtypes['relation'].setValue(fieldName, value);
    }, 0);
    
    return html;
  },
  
  getValue: function(fieldName) {
    const hiddenInputSelector = window.HaruFieldtypeConfig.getSelector('relation', 'hiddenInput', fieldName);
    const input = window.HaruFieldtypeInterface.getElement(hiddenInputSelector, 'Relation getValue');
    if (!input) return '';
    try {
      return JSON.parse(window.HaruFieldtypeInterface.getValue(input, 'Relation getValue'));
    } catch {
      return window.HaruFieldtypeInterface.getValue(input, 'Relation getValue');
    }
  },
  
  setValue: function(fieldName, value) {
    const toggleRemoveAll = function (name, count) {
      const btn = document.querySelector(`.remove-all-relation-btn[data-field="${name}"]`);
      if (btn) btn.hidden = !(count > 0);
    };
    const hiddenInputSelector = window.HaruFieldtypeConfig.getSelector('relation', 'hiddenInput', fieldName);
    const listSelector = window.HaruFieldtypeConfig.getSelector('relation', 'list', fieldName);
    
    const input = window.HaruFieldtypeInterface.getElement(hiddenInputSelector, 'Relation setValue');
    const list = window.HaruFieldtypeInterface.getElement(listSelector, 'Relation setValue');
    if (!input || !list) return;
    
    // Get old value for event emission
    const oldValue = this.getValue(fieldName);
    
    console.log('[Relation Field] setValue called for:', fieldName);
    console.log('[Relation Field] Raw value:', value);
    console.log('[Relation Field] Value type:', typeof value);
    
    let relations = [];
    
    // Handle different input formats
    if (!value) {
      relations = [];
    } else if (typeof value === 'string') {
      try {
        relations = JSON.parse(value);
        console.log('[Relation Field] Parsed JSON relations:', relations);
      } catch (e) {
        console.log('[Relation Field] Failed to parse JSON, treating as single value');
        relations = value.trim() ? [value] : [];
      }
    } else if (Array.isArray(value)) {
      relations = value;
    } else {
      relations = [value];
    }
    
    console.log('[Relation Field] Relations after initial processing:', relations);
    
    // Clean up the relations data
    relations = relations.filter(relation => {
      if (!relation) return false;
      if (typeof relation === 'string' && (relation === '[object Object]' || relation === 'undefined' || relation === 'null')) {
        console.log('[Relation Field] Filtering out corrupted relation:', relation);
        return false;
      }
      return true;
    }).map(relation => {
      console.log('[Relation Field] Processing relation:', relation);
      if (typeof relation === 'string') {
        // Old format: just an ID string
        console.log('[Relation Field] Converting string relation to object');
        return { id: relation, collection: 'unknown', collectionName: 'Unknown' };
      } else if (typeof relation === 'object' && relation !== null) {
        // New format: should have id, collection, collectionName
        console.log('[Relation Field] Processing object relation:', relation);
        return {
          id: relation.id || relation,
          collection: relation.collection || 'unknown',
          collectionName: relation.collectionName || 'Unknown'
        };
      }
      return relation;
    });
    
    console.log('[Relation Field] Final processed relations:', relations);
    
    // Store the cleaned data
    input.value = JSON.stringify(relations);
    
    // Validate the new value
    if (window.HaruFieldtypeValidation) {
      const validationResult = window.HaruFieldtypeValidation.validateField(fieldName, 'relation', relations);
      
      if (!validationResult.isValid) {
        console.warn('[Relation] Validation failed:', validationResult.errors);
        
        // Handle validation errors
        validationResult.errors.forEach(error => {
          window.HaruFieldtypeValidation.handleError('relation', error);
        });
      }
    }
    
    // Emit value change event
    if (window.HaruFieldtypeEvents) {
      window.HaruFieldtypeEvents.emitValueChange(fieldName, 'relation', oldValue, relations);
      
      // Emit fieldtype-specific event
      window.HaruFieldtypeEvents.emitFieldtype('relation', 'value:set', {
        fieldName,
        oldValue,
        newValue: relations,
        relationsCount: relations.length,
        collections: [...new Set(relations.map(r => r.collection))]
      });
    }
    
    // Display relations
    if (relations.length === 0) {
      list.innerHTML = '<div class="relation-empty">No linked entries yet</div>';
      toggleRemoveAll(fieldName, 0);
      return;
    }
    
    // Use haruFetch if available for authentication
    const fetchFunction = window.haruFetch || fetch;
    
    // Get all unique entry IDs - ensure they are valid strings
    const entryIds = relations.map(r => r.id || r).filter(id => {
      const isValid = id && 
             typeof id === 'string' && 
             id !== 'undefined' && 
             id !== 'null' && 
             id !== '[object Object]' &&
             id.length > 0;
      console.log('[Relation Field] Entry ID validation:', id, 'isValid:', isValid);
      return isValid;
    });
    
    console.log('[Relation Field] Final entry IDs to fetch:', entryIds);
    
    if (entryIds.length === 0) {
      list.innerHTML = '<div class="relation-empty">No linked entries yet</div>';
      toggleRemoveAll(fieldName, 0);
      return;
    }
    
    console.log('[Relation Field] Loading entries for IDs:', entryIds);
    
    fetchFunction(relationContentApiUrl(`action=get_entry_titles&ids=${encodeURIComponent(JSON.stringify(entryIds))}`), {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin'
    })
      .then(res => res.json())
      .then(data => {
        console.log('[Relation Field] API response:', data);
        if (data.status === 'ok' && data.data && Array.isArray(data.data.entries)) {
          const removeIcon = (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon('x', { size: 13 }) : '×';
          list.innerHTML = relations.map(relation => {
            const id = relation.id || relation;
            const entry = data.data.entries.find(e => e.id === id);
            const title = entry ? entry.title : id;
            const collection = relation.collectionName && relation.collectionName !== 'Unknown'
              ? relation.collectionName
              : (relation.collection && relation.collection !== 'unknown' ? relation.collection : '');
            return `<div class="relation-entry">
              <div class="relation-info">
                <span class="relation-title">${relationEscape(title)}</span>
                ${collection ? `<span class="collection-info">${relationEscape(collection)}</span>` : ''}
              </div>
              <button type="button" class="relation-remove" data-relation-remove="${relationEscape(id)}" data-field="${relationEscape(fieldName)}" title="Remove" aria-label="Remove ${relationEscape(title)}">${removeIcon}</button>
            </div>`;
          }).join('');
          toggleRemoveAll(fieldName, relations.length);
        } else {
          list.innerHTML = '<div class="text-danger">Failed to load relations</div>';
        }
      })
      .catch(error => {
        console.error('[Relation Field] Failed to load relation entries:', error);
        list.innerHTML = '<div class="text-danger">Failed to load relations</div>';
      });
  },
  
  removeRelation: function(fieldName, relationId) {
    const relations = this.getValue(fieldName);
    const filtered = relations.filter(r => (r.id || r) !== relationId);
    
    // Emit user interaction event
    if (window.HaruFieldtypeEvents) {
      const removedRelation = relations.find(r => (r.id || r) === relationId);
      window.HaruFieldtypeEvents.emitUserInteraction(fieldName, 'relation', 'remove_relation', {
        removedRelation,
        remainingRelations: filtered,
        removedRelationId: relationId
      });
    }
    
    this.setValue(fieldName, filtered);
  },
  
  updateHiddenInput: function(fieldName, value) {
    console.log('[Relation Field] updateHiddenInput called for:', fieldName, 'with value:', value);
    
    // Ensure value is properly serialized
    let serializedValue;
    if (Array.isArray(value)) {
      serializedValue = JSON.stringify(value);
    } else if (typeof value === 'object' && value !== null) {
      serializedValue = JSON.stringify([value]);
    } else if (typeof value === 'string') {
      try {
        // Check if it's already JSON
        JSON.parse(value);
        serializedValue = value;
      } catch {
        // It's a plain string, wrap in array
        serializedValue = JSON.stringify([value]);
      }
    } else {
      serializedValue = JSON.stringify([]);
    }
    
    console.log('[Relation Field] Setting hidden input value to:', serializedValue);
    window.HaruFieldtypeInterface.updateHiddenInput(fieldName, serializedValue, 'Relation updateHiddenInput');
  },
  
  renderConfig: function(config, onChange) {
    return `<div class="field-row">
      <label><input type="checkbox" name="multiple" ${config.multiple?'checked':''} onchange="this.onchange&&this.onchange(event)"> Allow multiple relations</label>
    </div>`;
  }
};

// Self-contained Relation Picker Modal
(function() {
  // Modal HTML (insert once)
  if (!document.getElementById('relationPickerModal')) {
    const modal = document.createElement('div');
    modal.id = 'relationPickerModal';
    modal.className = 'relation-picker-modal';
    modal.setAttribute('data-birch', '');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'relationPickerTitle');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
      <div class="modal-content relation-picker-panel">
        <div class="modal-header">
          <h3 class="modal-title" id="relationPickerTitle">Link entries</h3>
          <button type="button" class="modal-close" id="relationPickerCancelBtn" aria-label="Close"><span data-icon="x" aria-hidden="true"></span></button>
        </div>
        <div class="modal-body relation-picker-body">
          <div class="relation-picker-bar">
            <span class="select-box">
              <select id="relationPickerCollection" aria-label="Collection">
                <option value="">Choose a collection</option>
              </select>
              <span data-icon="chev-down" aria-hidden="true"></span>
            </span>
            <label class="search-field">
              <span data-icon="search" aria-hidden="true"></span>
              <input type="search" class="input" id="relationPickerSearch" placeholder="Search entries" aria-label="Search entries">
            </label>
          </div>
          <div class="relation-grid" id="relationPickerGrid">
            <div class="relation-picker-empty">Choose a collection to see its entries</div>
          </div>
        </div>
        <div class="modal-footer">
          <span class="relation-picker-count"><span id="relationPickerSelectedCount">0</span> selected</span>
          <button type="button" class="button" id="relationPickerCancelBtn2">Cancel</button>
          <button type="button" class="button is-main" id="relationPickerSelectBtn" disabled>Add selected</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }
  
  let relationPickerCallback = null;
  let relationPickerItems = [];
  let relationPickerSelected = [];
  let relationPickerIsMulti = false;
  let relationPickerField = '';
  let relationPickerCurrentCollection = '';
  let relationPickerCollections = [];
  
  function openRelationPicker({ fieldName, multiple, selected, callback }) {
    console.log('[Relation Picker] Opening for field:', fieldName);
    relationPickerIsMulti = !!multiple;
    relationPickerField = fieldName;
    relationPickerCallback = callback;
    relationPickerSelected = [];
    
    const pickerModal = document.getElementById('relationPickerModal');
    if (window.Birkly && typeof Birkly.initIcons === 'function') Birkly.initIcons(pickerModal);
    pickerModal.classList.add('is-open');
    pickerModal.setAttribute('aria-hidden', 'false');
    document.getElementById('relationPickerSelectBtn').disabled = true;
    document.getElementById('relationPickerSelectedCount').textContent = '0';
    
    // Load collections and setup
    console.log('[Relation Picker] Loading collections...');
    loadRelationPickerCollections();
    
    document.getElementById('relationPickerSearch').value = '';
    document.getElementById('relationPickerGrid').innerHTML = '<div class="relation-picker-empty">Choose a collection to see its entries</div>';
    
    console.log('[Relation Picker] Modal opened successfully');
  }
  
  function closeRelationPicker() {
    const pickerModal = document.getElementById('relationPickerModal');
    if (pickerModal) {
      pickerModal.classList.remove('is-open');
      pickerModal.setAttribute('aria-hidden', 'true');
    }
    relationPickerCallback = null;
    relationPickerSelected = [];
    relationPickerItems = [];
    relationPickerField = '';
    relationPickerCurrentCollection = '';
  }
  
  async function loadRelationPickerCollections() {
    console.log('[Relation Picker] Loading collections...');
    const select = document.getElementById('relationPickerCollection');
    if (!select) {
      console.error('[Relation Picker] Collection select element not found!');
      return;
    }
    
    select.innerHTML = '<option value="">Choose a collection</option>';
    
    try {
      console.log('[Relation Picker] Fetching collections from API...');
      const response = await fetch(relationContentApiUrl('action=list_collections'), { credentials: 'same-origin' });
      const data = await response.json();
      
      console.log('[Relation Picker] API response:', data);
      
      if (data.status === 'ok' && data.data && data.data.collections) {
        relationPickerCollections = data.data.collections;
        console.log('[Relation Picker] Found', relationPickerCollections.length, 'collections');
        
        data.data.collections.forEach(collection => {
          const key = relationCollectionKey(collection);
          if (!key) {
            return;
          }
          select.insertAdjacentHTML('beforeend', `<option value="${relationEscape(key)}">${relationEscape(relationCollectionLabel(collection))}</option>`);
        });
        
        console.log('[Relation Picker] Collections loaded into dropdown');
      } else {
        console.error('[Relation Picker] Invalid API response:', data);
      }
    } catch (error) {
      console.error('[Relation Picker] Failed to load collections:', error);
    }
  }
  
  async function loadRelationPickerEntries(collectionId, search) {
    if (!collectionId) {
      document.getElementById('relationPickerGrid').innerHTML = '<div class="relation-picker-empty">Choose a collection to see its entries</div>';
      return;
    }
    
    const grid = document.getElementById('relationPickerGrid');
    grid.innerHTML = '<div class="relation-picker-empty">Loading entries…</div>';
    
    // Get filter options from field config
    const fieldElement = document.querySelector(`[data-field="${relationPickerField}"]`);
    let filters = {};
    let sortOptions = {};
    let limit = 0;
    
    if (fieldElement && fieldElement.closest('.relation-field')) {
      const fieldContainer = fieldElement.closest('.relation-field');
      try {
        const filtersAttr = fieldContainer.getAttribute('data-filters');
        const sortAttr = fieldContainer.getAttribute('data-sort');
        const limitAttr = fieldContainer.getAttribute('data-limit');
        
        if (filtersAttr) filters = JSON.parse(filtersAttr);
        if (sortAttr) sortOptions = JSON.parse(sortAttr);
        if (limitAttr) limit = parseInt(limitAttr) || 0;
      } catch (e) {
        console.warn('[Relation Picker] Failed to parse field options:', e);
      }
    }
    
    try {
      const fetchFunction = window.haruFetch || fetch;
      const collection = relationPickerCollections.find(c => relationCollectionKey(c) === collectionId);
      const params = new URLSearchParams({ action: 'list_entries' });
      if (collection && collection.id) {
        params.append('id', collection.id);
      } else if (collection && collection.slug) {
        params.append('collection', collection.slug);
      } else if (collectionId) {
        params.append('collection', collectionId);
      }
      if (search) {
        params.append('search', search);
      }
      
      // Add filter parameters
      if (filters.status) {
        params.append('filter_status', filters.status);
      }
      if (filters.date_from) {
        params.append('filter_date_from', filters.date_from);
      }
      if (filters.date_to) {
        params.append('filter_date_to', filters.date_to);
      }
      if (filters.field && filters.field_value) {
        params.append(`filter_${filters.field}`, filters.field_value);
      }
      
      // Add sort parameters
      if (sortOptions.field) {
        params.append('sort', sortOptions.field);
        params.append('sort_direction', sortOptions.direction || 'asc');
      }
      
      // Add limit
      if (limit > 0) {
        params.append('limit', limit);
      }
      
      const res = await fetchFunction(relationContentApiUrl(params.toString()), {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
      });
      
      const data = await res.json();
      if (data.status === 'ok' && data.data && Array.isArray(data.data.entries)) {
        relationPickerItems = data.data.entries;
        relationPickerCurrentCollection = collectionId;
        
        if (!relationPickerItems.length) {
          grid.innerHTML = '<div class="relation-picker-empty">No entries in this collection yet</div>';
          return;
        }
        
        const collectionName = collection ? relationCollectionLabel(collection) : 'Unknown';
        
        // Get display mode from field config
        let displayMode = 'list';
        if (fieldElement && fieldElement.closest('.relation-field')) {
          const fieldContainer = fieldElement.closest('.relation-field');
          try {
            const displayAttr = fieldContainer.getAttribute('data-display');
            if (displayAttr) {
              const displayConfig = JSON.parse(displayAttr);
              displayMode = displayConfig.mode || 'list';
            }
          } catch (e) {
            // Use default
          }
        }
        
        // Rows are slabs; grid mode tiles them, compact drops the meta line.
        grid.classList.toggle('is-grid', displayMode === 'grid');
        grid.classList.toggle('is-compact', displayMode === 'compact');
        grid.innerHTML = relationPickerItems.map(e => {
          const alreadySelected = relationPickerSelected.some(r => r.id === e.id);
          return `<button type="button" class="relation-picker-item${alreadySelected ? ' selected' : ''}" data-id="${relationEscape(e.id)}" aria-pressed="${alreadySelected ? 'true' : 'false'}">
              <span class="relation-title">${relationEscape(e.title || e.id)}</span>
              <span class="relation-meta">${relationEscape(collectionName)}</span>
            </button>`;
        }).join('');

        grid.querySelectorAll('.relation-picker-item').forEach(itemDiv => {
          itemDiv.addEventListener('click', () => {
            const id = itemDiv.getAttribute('data-id');
            const isSelected = relationPickerSelected.some(r => r.id === id);
            
            if (isSelected) {
              relationPickerSelected = relationPickerSelected.filter(r => r.id !== id);
              itemDiv.classList.remove('selected');
              itemDiv.setAttribute('aria-pressed', 'false');
            } else {
              if (!relationPickerIsMulti) {
                // Single selection - clear others
                grid.querySelectorAll('.relation-picker-item').forEach(div => {
                  div.classList.remove('selected');
                  div.setAttribute('aria-pressed', 'false');
                });
                relationPickerSelected = [];
              }
              
              relationPickerSelected.push({ 
                id, 
                collection: relationPickerCurrentCollection,
                collectionName: (() => {
                  const selected = relationPickerCollections.find(c => relationCollectionKey(c) === relationPickerCurrentCollection);
                  return selected ? relationCollectionLabel(selected) : 'Unknown';
                })()
              });
              itemDiv.classList.add('selected');
              itemDiv.setAttribute('aria-pressed', 'true');
            }
            
            document.getElementById('relationPickerSelectBtn').disabled = relationPickerSelected.length === 0;
            document.getElementById('relationPickerSelectedCount').textContent = relationPickerSelected.length;
          });
          
          itemDiv.addEventListener('dblclick', () => {
            const id = itemDiv.getAttribute('data-id');
            relationPickerSelected = [{ 
              id, 
              collection: relationPickerCurrentCollection,
              collectionName: (() => {
                const selected = relationPickerCollections.find(c => relationCollectionKey(c) === relationPickerCurrentCollection);
                return selected ? relationCollectionLabel(selected) : 'Unknown';
              })()
            }];
            document.getElementById('relationPickerSelectBtn').disabled = false;
            document.getElementById('relationPickerSelectBtn').click();
          });
        });
        
        document.getElementById('relationPickerSelectBtn').disabled = relationPickerSelected.length === 0;
        document.getElementById('relationPickerSelectedCount').textContent = relationPickerSelected.length;
      } else {
        const message = (data && data.message) ? data.message : 'Failed to load entries';
        console.error('[Relation Picker] Failed to load entries:', data);
        grid.innerHTML = `<div class="relation-picker-empty is-error">${relationEscape(message)}</div>`;
      }
    } catch (error) {
      console.error('[DEBUG] Failed to load relation picker entries:', error);
      grid.innerHTML = '<div class="relation-picker-empty is-error">Failed to load entries</div>';
    }
  }
  
  // Event listeners
  document.getElementById('relationPickerModal').addEventListener('click', function (e) {
    if (e.target === this) closeRelationPicker();
  });
  document.getElementById('relationPickerCancelBtn').onclick = closeRelationPicker;
  document.getElementById('relationPickerCancelBtn2').onclick = closeRelationPicker;
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var picker = document.getElementById('relationPickerModal');
    if (picker && picker.classList.contains('is-open')) {
      closeRelationPicker();
    }
  });
  document.getElementById('relationPickerCollection').onchange = function() {
    loadRelationPickerEntries(this.value, document.getElementById('relationPickerSearch').value.trim());
  };
  document.getElementById('relationPickerSearch').oninput = function() {
    loadRelationPickerEntries(relationPickerCurrentCollection, this.value.trim());
  };
  document.getElementById('relationPickerSelectBtn').onclick = function() {
    if (relationPickerCallback) {
      const result = relationPickerIsMulti ? relationPickerSelected : relationPickerSelected[0] || null;
      relationPickerCallback(result);
      closeRelationPicker();
    }
  };
  
  // Attach event for Select Relation button
  document.addEventListener('click', function(e) {
    const selectBtn = e.target.closest('.select-relation-btn');
    if (selectBtn) {
      const btn = selectBtn;
      const fieldName = btn.getAttribute('data-field');
      let selected = [];
      const input = document.getElementById('field_' + fieldName + '_value');
      try { selected = JSON.parse(input.value); } catch { selected = input.value ? [input.value] : []; }
      const multiple = btn.getAttribute('data-multiple') === 'true';
      
      openRelationPicker({
        fieldName,
        multiple,
        selected,
        callback: function(selectedRelations) {
          if (selectedRelations) {
            const currentRelations = window.HaruFieldtypes['relation'].getValue(fieldName);
            const newRelations = Array.isArray(selectedRelations) ? selectedRelations : [selectedRelations];
            const allRelations = Array.isArray(currentRelations) ? [...currentRelations, ...newRelations] : newRelations;
            window.HaruFieldtypes['relation'].setValue(fieldName, allRelations);
          }
        }
      });
    }
    
    const removeOne = e.target.closest('[data-relation-remove]');
    if (removeOne) {
      window.HaruFieldtypes['relation'].removeRelation(removeOne.getAttribute('data-field'), removeOne.getAttribute('data-relation-remove'));
      return;
    }

    const removeAllBtn = e.target.closest('.remove-all-relation-btn');
    if (removeAllBtn) {
      const fieldName = removeAllBtn.getAttribute('data-field');
      window.HaruFieldtypes['relation'].setValue(fieldName, []);
    }
  });
})();

// Listen for save events to update hidden inputs
document.addEventListener('beforeSaveEntry', function() {
  console.log('=== Relation fieldtype received beforeSaveEntry event ===');
  const relationFields = window.HaruFieldtypeInterface.getElements('.relation-field', document, 'Relation beforeSaveEntry');
  console.log('Found relation fields:', relationFields.length);
  relationFields.forEach(field => {
    const fieldName = field.id.replace('relation_field_', '');
    console.log('Processing relation field:', fieldName);
    const relations = window.HaruFieldtypes['relation'].getValue(fieldName);
    console.log('Current relations for field:', fieldName, ':', relations);
    window.HaruFieldtypes['relation'].updateHiddenInput(fieldName, relations);
    console.log('Relation fieldtype updated hidden input for:', fieldName, 'with relations:', relations);
  });
});

// Auto-initialize relation fields
function initRelationField(fieldName) {
    console.log('[Relation Field] Initializing field:', fieldName);
    const field = window.HaruFieldtypeInterface.getElement('relation_field_' + fieldName, 'Relation initRelationField');
    if (!field) {
        console.error('[Relation Field] Field container not found:', fieldName);
        return;
    }
    
    const hiddenInput = field.querySelector('input[type="hidden"]');
    if (hiddenInput && hiddenInput.value) {
        console.log('[Relation Field] Found hidden input with value:', hiddenInput.value);
        try {
            const value = JSON.parse(hiddenInput.value);
            console.log('[Relation Field] Parsed value:', value);
            if (value && (Array.isArray(value) ? value.length > 0 : value)) {
                console.log('[Relation Field] Setting value for field:', fieldName);
                window.HaruFieldtypes['relation'].setValue(fieldName, value);
            }
        } catch (e) {
            console.error('[Relation Field] Failed to parse JSON:', e);
            // If not JSON, try as single value
            if (hiddenInput.value.trim()) {
                console.log('[Relation Field] Setting as single value:', hiddenInput.value);
                window.HaruFieldtypes['relation'].setValue(fieldName, hiddenInput.value);
            }
        }
    } else {
        console.log('[Relation Field] No hidden input or empty value for field:', fieldName);
    }
}

// Self-initializing function - automatically initializes all relation fields when loaded
function initializeAllRelationFields() {
    console.log('Initializing all relation fields...');
    const relationFields = window.HaruFieldtypeInterface.getElements('.relation-field', document, 'Relation initializeAllRelationFields');
    relationFields.forEach(field => {
        const fieldName = field.id.replace('relation_field_', '');
        console.log('Initializing relation field:', fieldName);
        initRelationField(fieldName);
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAllRelationFields);
} else {
    initializeAllRelationFields();
}

// Initialize new fields as they're added (native observer: this file loads before the fieldtype shim)
if (!window.relationObserver && typeof MutationObserver !== 'undefined') {
    window.relationObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) return;
                const fields = node.matches && node.matches('.relation-field') ? [node] : Array.from(node.querySelectorAll ? node.querySelectorAll('.relation-field') : []);
                fields.forEach(field => initRelationField(field.id.replace('relation_field_', '')));
            });
        });
    });
    if (document.body) {
        window.relationObserver.observe(document.body, { childList: true, subtree: true });
    }
}

window.initRelationField = initRelationField; 