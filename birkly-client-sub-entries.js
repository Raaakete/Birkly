/**
 * Birkly Sub-Entries Client Library
 * 
 * JavaScript helpers for working with sub-entries in templates.
 */

(function(window) {
    'use strict';
    
    if (!window.Birkly) {
        window.Birkly = {};
    }
    
    const Birkly = window.Birkly;
    
    /**
     * Sub-Entries API
     */
    Birkly.SubEntries = {
        /**
         * Get sub-entries
         * 
         * @param {string} collection Parent collection slug
         * @param {string} entryId Parent entry ID
         * @param {string} subCollection Sub-collection slug
         * @param {object} options Options (filters, sort, order, limit, offset)
         * @returns {Promise} Promise resolving to entries array
         */
        get: function(collection, entryId, subCollection, options = {}) {
            const params = new URLSearchParams({
                action: 'list',
                collection: collection,
                entry_id: entryId,
                sub_collection: subCollection
            });
            
            if (options.filters) {
                params.append('filters', JSON.stringify(options.filters));
            }
            if (options.sort) {
                params.append('sort', options.sort);
            }
            if (options.order) {
                params.append('order', options.order);
            }
            if (options.limit) {
                params.append('limit', options.limit);
            }
            if (options.offset) {
                params.append('offset', options.offset);
            }
            
            return fetch(`/api/sub_entries.php?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        return data.data.entries || [];
                    }
                    throw new Error(data.message || 'Failed to fetch sub-entries');
                });
        },
        
        /**
         * Get single sub-entry
         */
        getOne: function(collection, entryId, subCollection, subEntryId) {
            return fetch(`/api/sub_entries.php?action=get&collection=${collection}&entry_id=${entryId}&sub_collection=${subCollection}&sub_entry_id=${subEntryId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        return data.data.entry;
                    }
                    throw new Error(data.message || 'Sub-entry not found');
                });
        },
        
        /**
         * Get children
         */
        getChildren: function(collection, entryId, subCollection = null, options = {}) {
            const params = new URLSearchParams({
                action: 'get_children',
                collection: collection,
                entry_id: entryId
            });
            
            if (subCollection) {
                params.append('sub_collection', subCollection);
            }
            if (options.sort) {
                params.append('sort', options.sort);
            }
            if (options.order) {
                params.append('order', options.order);
            }
            
            return fetch(`/api/sub_entries.php?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        return data.data.entries || [];
                    }
                    throw new Error(data.message || 'Failed to fetch children');
                });
        },
        
        /**
         * Get descendants (recursive)
         */
        getDescendants: function(collection, entryId, subCollection = null, maxDepth = null, filters = {}) {
            const params = new URLSearchParams({
                action: 'get_descendants',
                collection: collection,
                entry_id: entryId
            });
            
            if (subCollection) {
                params.append('sub_collection', subCollection);
            }
            if (maxDepth !== null) {
                params.append('max_depth', maxDepth);
            }
            if (Object.keys(filters).length > 0) {
                params.append('filters', JSON.stringify(filters));
            }
            
            return fetch(`/api/sub_entries.php?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        return data.data.entries || [];
                    }
                    throw new Error(data.message || 'Failed to fetch descendants');
                });
        },
        
        /**
         * Get ancestors
         */
        getAncestors: function(subCollection, subEntryId) {
            return fetch(`/api/sub_entries.php?action=get_ancestors&sub_collection=${subCollection}&sub_entry_id=${subEntryId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        return data.data.ancestors || [];
                    }
                    throw new Error(data.message || 'Failed to fetch ancestors');
                });
        },
        
        /**
         * Get siblings
         */
        getSiblings: function(collection, entryId, subCollection, subEntryId, options = {}) {
            const params = new URLSearchParams({
                action: 'siblings',
                collection: collection,
                entry_id: entryId,
                sub_collection: subCollection,
                sub_entry_id: subEntryId
            });
            
            if (options.sort) {
                params.append('sort', options.sort);
            }
            if (options.order) {
                params.append('order', options.order);
            }
            
            return fetch(`/api/sub_entries.php?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        return data.data.entries || [];
                    }
                    throw new Error(data.message || 'Failed to fetch siblings');
                });
        },
        
        /**
         * Get aggregations
         */
        getAggregations: function(collection, entryId, subCollection, filters = {}, aggregations = {}) {
            return fetch('/api/sub_entries.php?action=aggregations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    collection: collection,
                    entry_id: entryId,
                    sub_collection: subCollection,
                    filters: filters,
                    aggregations: aggregations
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    return data.data;
                }
                throw new Error(data.message || 'Failed to get aggregations');
            });
        },
        
        /**
         * Submit sub-entry (public API)
         */
        submit: function(parentCollection, parentEntryId, subCollection, data) {
            return fetch('/api/public_sub_entry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    parent_collection: parentCollection,
                    parent_entry_id: parentEntryId,
                    sub_collection: subCollection,
                    data: data
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    return data.data;
                }
                throw new Error(data.message || 'Failed to submit sub-entry');
            });
        },
        
        /**
         * Render sub-entries in list mode
         */
        renderList: function(container, collection, entryId, subCollection, options = {}) {
            return this.get(collection, entryId, subCollection, options)
                .then(entries => {
                    const html = entries.map(entry => this.renderItem(entry, options.template)).join('');
                    container.innerHTML = html || (options.emptyMessage || '<p>No entries found.</p>');
                    return entries;
                });
        },
        
        /**
         * Render sub-entries in tree mode
         */
        renderTree: function(container, collection, entryId, subCollection, options = {}) {
            return this.getDescendants(collection, entryId, subCollection, options.maxDepth, options.filters || {})
                .then(entries => {
                    const tree = this.buildTree(entries);
                    const html = this.renderTreeNode(tree, options.template);
                    container.innerHTML = html || (options.emptyMessage || '<p>No entries found.</p>');
                    return entries;
                });
        },
        
        /**
         * Render sub-entries in threaded mode
         */
        renderThreaded: function(container, collection, entryId, subCollection, options = {}) {
            return this.get(collection, entryId, subCollection, {
                filters: Object.assign({}, options.filters || {}, {
                    parent_sub_entry_id: null
                }),
                sort: options.sort || 'created_at',
                order: options.order || 'asc'
            })
            .then(entries => {
                const html = entries.map(entry => this.renderThreadedEntry(entry, collection, entryId, subCollection, 0, options.template)).join('');
                container.innerHTML = html || (options.emptyMessage || '<p>No entries found.</p>');
                return entries;
            });
        },
        
        /**
         * Build tree structure from flat list
         */
        buildTree: function(entries) {
            const indexed = {};
            const tree = [];
            
            // Index entries
            entries.forEach(entry => {
                indexed[entry.id] = Object.assign({}, entry, { children: [] });
            });
            
            // Build tree
            entries.forEach(entry => {
                const parentId = entry.parent_sub_entry_id;
                if (parentId && indexed[parentId]) {
                    indexed[parentId].children.push(indexed[entry.id]);
                } else {
                    tree.push(indexed[entry.id]);
                }
            });
            
            return tree;
        },
        
        /**
         * Render tree node
         */
        renderTreeNode: function(node, template, depth = 0) {
            if (Array.isArray(node)) {
                return node.map(n => this.renderTreeNode(n, template, depth)).join('');
            }
            
            const html = `<div class="tree-node" data-depth="${depth}">`;
            const itemHtml = template ? template(node) : this.renderItem(node);
            const childrenHtml = node.children && node.children.length > 0
                ? `<div class="tree-children">${this.renderTreeNode(node.children, template, depth + 1)}</div>`
                : '';
            
            return html + itemHtml + childrenHtml + '</div>';
        },
        
        /**
         * Render threaded entry
         */
        renderThreadedEntry: function(entry, collection, entryId, subCollection, depth = 0, template) {
            const html = `<div class="threaded-entry" data-depth="${depth}">`;
            const itemHtml = template ? template(entry) : this.renderItem(entry);
            
            // Get replies (would need to fetch nested entries)
            // For now, just render the entry
            return html + itemHtml + '</div>';
        },
        
        /**
         * Render single item
         */
        renderItem: function(entry, template) {
            if (template && typeof template === 'function') {
                return template(entry);
            }
            
            const title = entry.title || entry.name || entry.id;
            const content = entry.content || entry.comment || entry.text || '';
            const author = entry.author_name || entry.author || '';
            const date = entry.created_at ? new Date(entry.created_at).toLocaleString() : '';
            
            return `
                <div class="sub-entry-item" data-id="${entry.id}">
                    <h4 class="sub-entry-title">${this.escapeHtml(title)}</h4>
                    ${content ? `<div class="sub-entry-content">${this.escapeHtml(content)}</div>` : ''}
                    <div class="sub-entry-meta">
                        ${date ? `<span class="sub-entry-date">${date}</span>` : ''}
                        ${author ? `<span class="sub-entry-author">${this.escapeHtml(author)}</span>` : ''}
                    </div>
                </div>
            `;
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Export
    window.Birkly = Birkly;
    
})(window);
