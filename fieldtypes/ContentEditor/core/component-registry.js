// ContentEditor component registry — API client for bundled + user components
window.ContentEditorComponentRegistry = {
  API_URL: '/api/components.php',

  csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  },

  async list() {
    const response = await fetch(`${this.API_URL}?action=list`);
    const result = await response.json();
    if (!result.success) {
      throw new Error(result.error || 'Failed to load components');
    }
    return result.components || [];
  },

  async get(slug) {
    const response = await fetch(`${this.API_URL}?action=get&slug=${encodeURIComponent(slug)}`);
    const result = await response.json();
    if (!result.success) {
      throw new Error(result.error || 'Component not found');
    }
    return result.component;
  },

  async save(data) {
    const payload = Object.assign({}, data, { _csrf: this.csrfToken() });
    const response = await fetch(`${this.API_URL}?action=save`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return response.json();
  },

  async delete(slug) {
    const response = await fetch(`${this.API_URL}?action=delete&slug=${encodeURIComponent(slug)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug, _csrf: this.csrfToken() }),
    });
    return response.json();
  },

  async findEntries(slug) {
    const response = await fetch(`${this.API_URL}?action=find_entries&slug=${encodeURIComponent(slug)}`);
    return response.json();
  },

  async updateEntries(slug) {
    const response = await fetch(`${this.API_URL}?action=update_entries`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug, _csrf: this.csrfToken() }),
    });
    return response.json();
  },

  isBundled(component) {
    return Boolean(component && (component.bundled || component.source === 'bundled'));
  },
};
