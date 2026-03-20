/**
 * Tiers Management JavaScript
 */

// Expose functions immediately to avoid timing issues
let currentTiers = [];
let isEditing = false;
let currentTierId = null;

// Functions are defined below and exposed at the end

function initTiersManagement() {
    loadTiers();
}

function loadTiers() {
    fetch('/api/index.php?route=user_tiers&action=list')
        .then(async res => {
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
            }
            
            if (!res.ok) {
                throw new Error(data.error || data.message || `HTTP ${res.status}: ${res.statusText}`);
            }
            return data;
        })
        .then(data => {
            if (data.tiers) {
                currentTiers = data.tiers;
                renderTiers(data.tiers);
            } else if (data.error) {
                showError('Failed to load tiers: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error loading tiers:', error);
            showError('Error loading tiers: ' + error.message);
        });
}

function renderTiers(tiers) {
    const container = document.getElementById('tiers-container');
    
    if (!tiers || tiers.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted">No tiers found. Create your first tier to get started.</div>';
        return;
    }
    
    let html = '<div class="row">';
    
    tiers.forEach(tier => {
        html += `
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="h5 m-0">${escapeHtml(tier.name || tier.id)}</h4>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="editTier('${tier.id}')" title="Edit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTier('${tier.id}')" title="Delete">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        ${tier.description ? `<p class="text-muted">${escapeHtml(tier.description)}</p>` : ''}
                        ${tier.features && tier.features.length > 0 ? `
                            <div class="mt-3">
                                <strong>Features:</strong>
                                <ul class="list-unstyled mt-1">
                                    ${tier.features.map(f => `<li>• ${escapeHtml(f)}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function openAddTierModal() {
    isEditing = false;
    currentTierId = null;
    document.getElementById('tier-modal-title').textContent = 'Create Tier';
    document.getElementById('tier-form').reset();
    document.getElementById('tier-id').value = '';
    document.getElementById('tier-id-field').disabled = false;
    document.getElementById('tier-modal').style.display = 'block';
}

function closeTierModal() {
    document.getElementById('tier-modal').style.display = 'none';
    isEditing = false;
    currentTierId = null;
}

function editTier(tierId) {
    const tier = currentTiers.find(t => t.id === tierId);
    if (!tier) {
        showError('Tier not found');
        return;
    }
    
    isEditing = true;
    currentTierId = tierId;
    document.getElementById('tier-modal-title').textContent = 'Edit Tier';
    document.getElementById('tier-id').value = tier.id;
    document.getElementById('tier-id-field').value = tier.id;
    document.getElementById('tier-id-field').disabled = true;
    document.getElementById('tier-name').value = tier.name || '';
    document.getElementById('tier-description').value = tier.description || '';
    
    // Load features
    const featuresList = document.getElementById('tier-features-list');
    featuresList.innerHTML = '';
    (tier.features || []).forEach(feature => {
        addFeatureToUI(feature);
    });
    
    document.getElementById('tier-modal').style.display = 'block';
}

function saveTier() {
    const form = document.getElementById('tier-form');
    const formData = new FormData(form);
    
    const tierData = {
        action: isEditing ? 'update' : 'create',
        csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '',
        name: formData.get('name'),
        description: formData.get('description')
    };
    
    if (!isEditing) {
        tierData.id = formData.get('tier_id');
        if (!tierData.id) {
            showError('Tier ID is required');
            return;
        }
    } else {
        tierData.tier_id = currentTierId;
    }
    
    // Get features from UI
    tierData.features = [];
    document.querySelectorAll('.tier-feature-item').forEach(item => {
        const featureName = item.dataset.feature;
        if (featureName) {
            tierData.features.push(featureName);
        }
    });
    
    // Permissions (empty for now, can be configured later)
    tierData.permissions = {
        collections: {},
        features: tierData.features
    };
    
    // Validate
    if (!tierData.name) {
        showError('Tier name is required');
        return;
    }
    
    const url = '/api/index.php?route=user_tiers';
    
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(tierData)
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
        }
        
        if (!res.ok) {
            throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            showSuccess(isEditing ? 'Tier updated successfully' : 'Tier created successfully');
            closeTierModal();
            loadTiers();
        } else {
            showError('Failed to save tier: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving tier:', error);
        showError('Error saving tier: ' + error.message);
    });
}

function deleteTier(tierId) {
    if (!confirm('Are you sure you want to delete this tier? This action cannot be undone.')) {
        return;
    }
    
    const url = '/api/index.php?route=user_tiers';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            csrf_token: csrfToken,
            tier_id: tierId
        })
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
        }
        
        if (!res.ok) {
            throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            showSuccess('Tier deleted successfully');
            loadTiers();
        } else {
            showError('Failed to delete tier: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error deleting tier:', error);
        showError('Error deleting tier: ' + error.message);
    });
}

function showError(message) {
    // Simple alert for now, can be replaced with better UI
    alert('Error: ' + message);
}

function showSuccess(message) {
    // Simple alert for now, can be replaced with better UI
    alert('Success: ' + message);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Feature management functions
function addTierFeature() {
    const input = document.getElementById('tier-feature-input');
    const featureName = input.value.trim();
    
    if (!featureName) {
        showError('Please enter a feature name');
        return;
    }
    
    // Check if already exists
    const existing = document.querySelector(`.tier-feature-item[data-feature="${escapeHtml(featureName)}"]`);
    if (existing) {
        showError('Feature already added');
        return;
    }
    
    addFeatureToUI(featureName);
    input.value = '';
}

function addFeatureToUI(featureName) {
    const container = document.getElementById('tier-features-list');
    const item = document.createElement('div');
    item.className = 'tier-feature-item badge badge-secondary mr-2 mb-2 d-inline-flex align-items-center';
    item.dataset.feature = featureName;
    item.innerHTML = `
        ${escapeHtml(featureName)}
        <button type="button" class="btn btn-sm btn-link text-white p-0 ml-2" onclick="removeTierFeature('${escapeHtml(featureName)}')" style="line-height: 1;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    `;
    container.appendChild(item);
}

function removeTierFeature(featureName) {
    const item = document.querySelector(`.tier-feature-item[data-feature="${escapeHtml(featureName)}"]`);
    if (item) {
        item.remove();
    }
}

// Limit management functions
function loadCollectionsForLimits() {
    // Load collections from API
    fetch('/api/index.php?route=user_settings&action=get_collections')
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('tier-limit-collection');
            if (!select) return;
            
            select.innerHTML = '<option value="">Select collection...</option>';
            if (data.success && data.collections) {
                data.collections.forEach(collection => {
                    const option = document.createElement('option');
                    option.value = collection.slug || collection.id;
                    option.textContent = collection.name || collection.slug || collection.id;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading collections:', error);
        });
}

function addTierLimit() {
    const select = document.getElementById('tier-limit-collection');
    const input = document.getElementById('tier-limit-value');
    const collection = select.value;
    const limit = parseInt(input.value);
    
    if (!collection) {
        showError('Please select a collection');
        return;
    }
    
    if (!limit || limit < 1) {
        showError('Please enter a valid limit (minimum 1)');
        return;
    }
    
    // Check if already exists
    const existing = document.querySelector(`.tier-limit-item[data-collection="${escapeHtml(collection)}"]`);
    if (existing) {
        showError('Limit for this collection already added');
        return;
    }
    
    addLimitToUI(collection, limit);
    select.value = '';
    input.value = '';
}

function addLimitToUI(collection, limit) {
    const container = document.getElementById('tier-limits-list');
    const item = document.createElement('div');
    item.className = 'tier-limit-item badge badge-info mr-2 mb-2 d-inline-flex align-items-center';
    item.dataset.collection = collection;
    item.dataset.limit = limit;
    item.innerHTML = `
        ${escapeHtml(collection)}: ${limit}
        <button type="button" class="btn btn-sm btn-link text-white p-0 ml-2" onclick="removeTierLimit('${escapeHtml(collection)}')" style="line-height: 1;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    `;
    container.appendChild(item);
}

function removeTierLimit(collection) {
    const item = document.querySelector(`.tier-limit-item[data-collection="${escapeHtml(collection)}"]`);
    if (item) {
        item.remove();
    }
}

// Expose functions to window immediately
window.initTiersManagement = initTiersManagement;
window.loadTiers = loadTiers;
window.openAddTierModal = openAddTierModal;
window.closeTierModal = closeTierModal;
window.editTier = editTier;
window.saveTier = saveTier;
window.deleteTier = deleteTier;
window.addTierFeature = addTierFeature;
window.removeTierFeature = removeTierFeature;
window.addTierLimit = addTierLimit;
window.removeTierLimit = removeTierLimit;