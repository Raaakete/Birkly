/**
 * Automation node/canvas editor — connectable graph with smart inspectors.
 */
(function (global) {
  'use strict';

  const Serializer = global.AutomationNodeSerializer;
  if (!Serializer) return;

  let nodeSeq = 0;
  const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'pending_review', label: 'Pending Review' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' }
  ];

  const KIND_TABS = [
    { id: 'all', label: 'All' },
    { id: 'trigger', label: 'Triggers' },
    { id: 'condition', label: 'Conditions' },
    { id: 'action', label: 'Actions' }
  ];

  function nextNodeId(kind) {
    nodeSeq += 1;
    return 'node_' + kind + '_' + nodeSeq + '_' + Date.now().toString(36);
  }

  class AutomationNodeEditor {
    constructor(options) {
      this.container = options.container;
      this.paletteEl = options.palette;
      this.inspectorEl = options.inspector || null;
      this.blocks = options.blocks || { triggers: {}, conditions: {}, actions: {} };
      this.collections = Array.isArray(options.collections) ? options.collections : [];
      this.canvas = { nodes: [], edges: [] };
      this.selectedNodeId = null;
      this.selectedEdgeKey = null;
      this.onChange = options.onChange || function () {};
      this.connectState = null;
      this.paletteKind = 'all';
      this.paletteQuery = '';
      this._onKeyDown = this._onKeyDown.bind(this);

      this._buildPalette();
      this._render();
      document.addEventListener('keydown', this._onKeyDown);
    }

    destroy() {
      document.removeEventListener('keydown', this._onKeyDown);
    }

    setBlocks(blocks) {
      this.blocks = blocks || { triggers: {}, conditions: {}, actions: {} };
      this._buildPalette();
    }

    setCollections(collections) {
      this.collections = Array.isArray(collections) ? collections : [];
      this._buildPalette();
      this._render();
    }

    loadFromWorkflow(automation) {
      this.canvas = Serializer.canvasFromWorkflow(automation || {});
      this.selectedNodeId = null;
      this.selectedEdgeKey = null;
      this._render();
      this.onChange();
    }

    loadCanvas(canvas) {
      this.canvas = Serializer.normalizeCanvas(canvas || {});
      this.selectedNodeId = null;
      this.selectedEdgeKey = null;
      this._render();
      this.onChange();
    }

    toWorkflow() {
      return Serializer.workflowFromCanvas(this.canvas);
    }

    getCanvas() {
      return Serializer.normalizeCanvas(this.canvas);
    }

    validate() {
      return Serializer.validateCanvas(this.canvas);
    }

    exportJson() {
      return JSON.stringify(this.getCanvas(), null, 2);
    }

    importJson(json) {
      const data = typeof json === 'string' ? JSON.parse(json) : json;
      if (!data || !Array.isArray(data.nodes)) {
        throw new Error('Invalid canvas JSON');
      }
      this.loadCanvas(data);
    }

    _collectionTitle(slug) {
      if (!slug) return '';
      const found = this.collections.find((c) => c.slug === slug);
      return found ? (found.title || found.slug) : slug;
    }

    _subCollectionTitle(collectionSlug, subSlug) {
      if (!subSlug) return '';
      const col = this.collections.find((c) => c.slug === collectionSlug);
      const sub = (col?.sub_collections || []).find((s) => (s.slug || s) === subSlug);
      return sub ? (sub.title || sub.slug || subSlug) : subSlug;
    }

    _statusLabel(value) {
      const found = STATUS_OPTIONS.find((o) => o.value === value);
      return found ? found.label : value;
    }

    _nodeSummary(node) {
      const cfg = node.config || {};
      const lines = [];

      if (node.kind === 'trigger') {
        if (cfg.collection) lines.push('Collection: ' + this._collectionTitle(cfg.collection));
        else lines.push('Any collection');
        if (cfg.sub_collection) lines.push('Sub: ' + this._subCollectionTitle(cfg.collection, cfg.sub_collection));
        if (cfg.from || cfg.to) {
          lines.push((cfg.from ? this._statusLabel(cfg.from) : 'any') + ' → ' + (cfg.to ? this._statusLabel(cfg.to) : 'any'));
        }
        if (cfg.webhook_id) lines.push('Webhook: ' + cfg.webhook_id);
        if (cfg.schedule) lines.push('Schedule: ' + cfg.schedule);
      } else if (node.kind === 'condition') {
        if (node.type === 'status_check' && cfg.status) lines.push('Status is ' + this._statusLabel(cfg.status));
        if (node.type === 'collection_filter' && cfg.collection) lines.push(this._collectionTitle(cfg.collection));
        if (cfg.field) lines.push('Field: ' + cfg.field + (cfg.value != null && cfg.value !== '' ? ' = ' + cfg.value : ''));
        if (cfg.operator && cfg.value != null && !cfg.field) lines.push(cfg.operator + ' ' + cfg.value);
        if (!lines.length) lines.push('Configure condition…');
      } else if (node.kind === 'action') {
        if (node.type === 'email') {
          if (cfg.to) lines.push('To: ' + cfg.to);
          if (cfg.subject) lines.push(truncate(cfg.subject, 42));
          if (!cfg.to) lines.push('Set recipient…');
        } else if (node.type === 'webhook') {
          if (cfg.method || cfg.url) lines.push((cfg.method || 'POST') + ' ' + truncate(cfg.url || '', 36));
          else lines.push('Set webhook URL…');
        } else if (node.type === 'set_status' && cfg.status) {
          lines.push('Set status → ' + this._statusLabel(cfg.status));
        } else if (node.type === 'set_field') {
          if (cfg.field) lines.push(cfg.field + ' = ' + truncate(String(cfg.value || ''), 28));
          else lines.push('Set field…');
        } else if (node.type === 'log') {
          lines.push(truncate(cfg.message || 'Log message…', 48));
        } else if (node.type === 'slack' || node.type === 'teams') {
          lines.push(truncate(cfg.webhook_url || cfg.url || 'Set webhook…', 40));
        } else if (cfg.collection) {
          lines.push(this._collectionTitle(cfg.collection));
        } else if (Object.keys(cfg).length) {
          const first = Object.keys(cfg).find((k) => cfg[k] !== '' && cfg[k] != null);
          if (first) lines.push(first + ': ' + truncate(String(cfg[first]), 36));
        } else {
          lines.push('Configure action…');
        }
      }

      return lines.slice(0, 3);
    }

    _missingRequired(node) {
      const meta = this._blockMeta(node.kind, node.type);
      const schema = meta.schema || {};
      const missing = [];
      Object.keys(schema).forEach((field) => {
        if (!schema[field].required) return;
        const val = (node.config || {})[field];
        if (val == null || String(val).trim() === '') missing.push(schema[field].label || field);
      });
      return missing;
    }

    _allBlocks() {
      const list = [];
      const kinds = [
        { key: 'triggers', kind: 'trigger' },
        { key: 'conditions', kind: 'condition' },
        { key: 'actions', kind: 'action' }
      ];
      kinds.forEach(({ key, kind }) => {
        const bucket = this.blocks[key] || {};
        Object.keys(bucket).forEach((blockId) => {
          list.push({ kind, type: blockId, meta: bucket[blockId] || {} });
        });
      });
      return list;
    }

    _buildPalette() {
      if (!this.paletteEl) return;
      this.paletteEl.innerHTML = '';

      const toolbar = document.createElement('div');
      toolbar.className = 'node-palette-toolbar';

      const search = document.createElement('input');
      search.type = 'search';
      search.className = 'input node-palette-search';
      search.placeholder = 'Filter blocks…';
      search.value = this.paletteQuery;
      search.setAttribute('aria-label', 'Filter blocks');
      search.addEventListener('input', () => {
        this.paletteQuery = search.value || '';
        this._renderPaletteList();
      });

      const tabs = document.createElement('div');
      tabs.className = 'node-palette-tabs';
      tabs.setAttribute('role', 'tablist');
      KIND_TABS.forEach((tab) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'node-palette-tab' + (this.paletteKind === tab.id ? ' active' : '');
        btn.textContent = tab.label;
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-selected', this.paletteKind === tab.id ? 'true' : 'false');
        btn.addEventListener('click', () => {
          this.paletteKind = tab.id;
          this._buildPalette();
        });
        tabs.appendChild(btn);
      });

      toolbar.appendChild(search);
      toolbar.appendChild(tabs);
      this.paletteEl.appendChild(toolbar);

      const help = document.createElement('p');
      help.className = 'form-help node-palette-help';
      help.textContent = 'Click to add. Drag ports to connect. Conditions use If true / Else.';
      this.paletteEl.appendChild(help);

      const listHost = document.createElement('div');
      listHost.className = 'node-palette-list';
      listHost.id = 'node-palette-list';
      this.paletteEl.appendChild(listHost);
      this._renderPaletteList();
    }

    _renderPaletteList() {
      const host = this.paletteEl?.querySelector('#node-palette-list');
      if (!host) return;
      host.innerHTML = '';

      const q = this.paletteQuery.trim().toLowerCase();
      const sections = {};
      const sectionOrder = [];

      this._allBlocks().forEach((item) => {
        if (this.paletteKind !== 'all' && item.kind !== this.paletteKind) return;
        const name = item.meta.name || item.type;
        const desc = item.meta.description || '';
        const category = item.meta.category || 'Other';
        const hay = (name + ' ' + desc + ' ' + item.type + ' ' + category + ' ' + item.kind).toLowerCase();
        if (q && !hay.includes(q)) return;

        if (!sections[category]) {
          sections[category] = [];
          sectionOrder.push(category);
        }
        sections[category].push(item);
      });

      if (!sectionOrder.length) {
        host.innerHTML = '<p class="form-help">No blocks match this filter.</p>';
        return;
      }

      sectionOrder.sort((a, b) => a.localeCompare(b)).forEach((category) => {
        const sectionEl = document.createElement('div');
        sectionEl.className = 'node-palette-section';
        const title = document.createElement('h4');
        title.className = 'node-palette-section-title';
        title.textContent = category;
        sectionEl.appendChild(title);

        sections[category]
          .sort((a, b) => (a.meta.name || a.type).localeCompare(b.meta.name || b.type))
          .forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'node-palette-block';
            btn.title = item.meta.description || item.meta.name || item.type;
            btn.innerHTML = `
              <span class="node-palette-block-kind">${item.kind}</span>
              <span class="node-palette-block-name">${escapeHtml(item.meta.name || item.type)}</span>
              <span class="node-palette-block-desc">${escapeHtml(item.meta.description || '')}</span>
            `;
            btn.addEventListener('click', () => this._addNode(item.kind, item.type));
            sectionEl.appendChild(btn);
          });

        host.appendChild(sectionEl);
      });
    }

    _schemaDefaults(kind, type) {
      const meta = this._blockMeta(kind, type);
      const schema = meta.schema || {};
      const config = {};
      Object.keys(schema).forEach((field) => {
        if (schema[field] && schema[field].default != null && schema[field].default !== '') {
          config[field] = schema[field].default;
        }
      });
      return config;
    }

    _addNode(kind, type) {
      if (kind === 'trigger') {
        const existing = this.canvas.nodes.find((n) => n.kind === 'trigger');
        if (existing) {
          existing.type = type;
          existing.config = {};
          this.selectedNodeId = existing.id;
          this._render();
          this.onChange();
          return;
        }
      }

      const x = 60 + (this.canvas.nodes.length % 4) * 280;
      const y = 60 + Math.floor(this.canvas.nodes.length / 4) * 180;
      const node = {
        id: nextNodeId(kind),
        kind,
        type,
        config: this._schemaDefaults(kind, type),
        x,
        y
      };
      this.canvas.nodes.push(node);

      if (this.selectedNodeId) {
        const from = this.canvas.nodes.find((n) => n.id === this.selectedNodeId);
        if (from) {
          const branch = from.kind === 'condition' ? 'true' : 'default';
          if (!this._hasOutgoing(from.id, branch)) {
            this.canvas.edges.push({ from: from.id, to: node.id, branch });
          }
        }
      }

      this.selectedNodeId = node.id;
      this.selectedEdgeKey = null;
      this._render();
      this.onChange();
    }

    _hasOutgoing(fromId, branch) {
      return this.canvas.edges.some((e) => e.from === fromId && (e.branch || 'default') === branch);
    }

    _removeNode(nodeId) {
      this.canvas.nodes = this.canvas.nodes.filter((n) => n.id !== nodeId);
      this.canvas.edges = this.canvas.edges.filter((e) => e.from !== nodeId && e.to !== nodeId);
      if (this.selectedNodeId === nodeId) this.selectedNodeId = null;
      this.selectedEdgeKey = null;
      this._render();
      this.onChange();
    }

    _edgeKey(edge) {
      return (edge.from || '') + '|' + (edge.to || '') + '|' + (edge.branch || 'default');
    }

    _removeSelectedEdge() {
      if (!this.selectedEdgeKey) return;
      this.canvas.edges = this.canvas.edges.filter((e) => this._edgeKey(e) !== this.selectedEdgeKey);
      this.selectedEdgeKey = null;
      this._render();
      this.onChange();
    }

    _onKeyDown(e) {
      if (!this.container || !this.container.isConnected) return;
      const tag = (e.target && e.target.tagName) || '';
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable) return;
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (this.selectedEdgeKey) {
          e.preventDefault();
          this._removeSelectedEdge();
        } else if (this.selectedNodeId) {
          e.preventDefault();
          this._removeNode(this.selectedNodeId);
        }
      }
      if (e.key === 'Escape') {
        this.connectState = null;
        this.selectedEdgeKey = null;
        this._render();
      }
    }

    _render() {
      if (!this.container) return;

      this.container.innerHTML = `
        <svg class="node-canvas-edges" aria-hidden="true"></svg>
        <div class="node-canvas-nodes"></div>
        <div class="node-canvas-hint">Drag a port to connect · Click a line to select · Delete to remove</div>
      `;

      const nodesLayer = this.container.querySelector('.node-canvas-nodes');
      const edgesLayer = this.container.querySelector('.node-canvas-edges');

      this.canvas.nodes.forEach((node) => {
        nodesLayer.appendChild(this._buildNodeEl(node));
      });

      this._drawEdges(edgesLayer);
      this._renderInspector(this.inspectorEl);
    }

    _buildNodeEl(node) {
      const meta = this._blockMeta(node.kind, node.type);
      const missing = this._missingRequired(node);
      const summary = this._nodeSummary(node);
      const el = document.createElement('div');
      el.className = 'canvas-node canvas-node-' + node.kind
        + (this.selectedNodeId === node.id ? ' selected' : '')
        + (missing.length ? ' needs-config' : '');
      el.dataset.nodeId = node.id;
      el.style.left = (node.x || 0) + 'px';
      el.style.top = (node.y || 0) + 'px';

      const portsOut = node.kind === 'condition'
        ? `
          <button type="button" class="canvas-port canvas-port-out canvas-port-true" data-port="true" title="If true" aria-label="If true output"></button>
          <button type="button" class="canvas-port canvas-port-out canvas-port-false" data-port="false" title="Else" aria-label="Else output"></button>
          <span class="canvas-port-label canvas-port-label-true">If true</span>
          <span class="canvas-port-label canvas-port-label-false">Else</span>
        `
        : `<button type="button" class="canvas-port canvas-port-out" data-port="default" title="Connect out" aria-label="Output"></button>`;

      const portIn = node.kind === 'trigger'
        ? ''
        : `<button type="button" class="canvas-port canvas-port-in" data-port="in" title="Connect in" aria-label="Input"></button>`;

      const summaryHtml = summary.map((line) => `<div class="canvas-node-summary-line">${escapeHtml(line)}</div>`).join('');

      el.innerHTML = `
        ${portIn}
        <div class="canvas-node-header">
          <span class="canvas-node-kind">${node.kind}</span>
          ${missing.length ? '<span class="canvas-node-badge" title="Missing required settings">Setup</span>' : ''}
          <button type="button" class="canvas-node-remove" title="Remove" aria-label="Remove node">&times;</button>
        </div>
        <div class="canvas-node-title">${escapeHtml(meta.name || node.type || 'Untitled')}</div>
        <div class="canvas-node-summary">${summaryHtml}</div>
        ${portsOut}
      `;

      el.addEventListener('mousedown', (e) => {
        if (e.target.closest('.canvas-port, .canvas-node-remove')) return;
        this._startDrag(e, node);
      });
      el.addEventListener('click', (e) => {
        if (e.target.closest('.canvas-port, .canvas-node-remove')) return;
        this.selectedNodeId = node.id;
        this.selectedEdgeKey = null;
        this._render();
      });
      el.querySelector('.canvas-node-remove').addEventListener('click', (e) => {
        e.stopPropagation();
        this._removeNode(node.id);
      });

      el.querySelectorAll('.canvas-port-out').forEach((port) => {
        port.addEventListener('mousedown', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this._startConnect(e, node.id, port.getAttribute('data-port') || 'default');
        });
      });

      el.querySelectorAll('.canvas-port-in').forEach((port) => {
        port.addEventListener('mouseup', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this._finishConnect(node.id);
        });
      });

      return el;
    }

    _startConnect(e, fromId, branch) {
      this.connectState = {
        fromId,
        branch: branch === 'true' || branch === 'false' ? branch : 'default',
        x: e.clientX,
        y: e.clientY
      };
      this.selectedEdgeKey = null;

      const onMove = (ev) => {
        if (!this.connectState) return;
        this.connectState.x = ev.clientX;
        this.connectState.y = ev.clientY;
        this._drawEdges(this.container.querySelector('.node-canvas-edges'));
      };

      const onUp = (ev) => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        const target = document.elementFromPoint(ev.clientX, ev.clientY);
        const inPort = target && target.closest ? target.closest('.canvas-port-in') : null;
        const nodeEl = inPort ? inPort.closest('[data-node-id]') : null;
        if (nodeEl && inPort) {
          this._finishConnect(nodeEl.getAttribute('data-node-id'));
        } else {
          this.connectState = null;
          this._drawEdges(this.container.querySelector('.node-canvas-edges'));
        }
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
      this._drawEdges(this.container.querySelector('.node-canvas-edges'));
    }

    _finishConnect(toId) {
      if (!this.connectState) return;
      const fromId = this.connectState.fromId;
      const branch = this.connectState.branch || 'default';
      this.connectState = null;

      if (!toId || toId === fromId) {
        this._render();
        return;
      }

      const fromNode = this.canvas.nodes.find((n) => n.id === fromId);
      const toNode = this.canvas.nodes.find((n) => n.id === toId);
      if (!fromNode || !toNode || toNode.kind === 'trigger') {
        this._render();
        return;
      }

      this.canvas.edges = this.canvas.edges.filter((e) => !(e.from === fromId && (e.branch || 'default') === branch));
      this.canvas.edges.push({ from: fromId, to: toId, branch });
      this.selectedNodeId = toId;
      this._render();
      this.onChange();
    }

    _drawEdges(svg) {
      if (!svg) return;
      const rect = this.container.getBoundingClientRect();
      const width = Math.max(rect.width, 900);
      const height = Math.max(rect.height, 560);
      svg.setAttribute('width', String(width));
      svg.setAttribute('height', String(height));
      svg.setAttribute('viewBox', `0 0 ${width} ${height}`);

      const cr = this.container.getBoundingClientRect();
      const parts = [];

      this.canvas.edges.forEach((edge) => {
        const fromEl = this.container.querySelector(`[data-node-id="${edge.from}"]`);
        const toEl = this.container.querySelector(`[data-node-id="${edge.to}"]`);
        if (!fromEl || !toEl) return;

        const branch = edge.branch || 'default';
        const outPort = fromEl.querySelector(`.canvas-port-out[data-port="${branch}"]`)
          || fromEl.querySelector('.canvas-port-out');
        const inPort = toEl.querySelector('.canvas-port-in') || toEl;

        const fr = (outPort || fromEl).getBoundingClientRect();
        const tr = (inPort || toEl).getBoundingClientRect();
        const x1 = fr.left + fr.width / 2 - cr.left;
        const y1 = fr.top + fr.height / 2 - cr.top;
        const x2 = tr.left + tr.width / 2 - cr.left;
        const y2 = tr.top + tr.height / 2 - cr.top;
        const midX = (x1 + x2) / 2;
        const key = this._edgeKey(edge);
        const selected = this.selectedEdgeKey === key ? ' selected' : '';
        const branchClass = branch === 'true' ? ' edge-true' : (branch === 'false' ? ' edge-false' : '');

        parts.push(`
          <path class="canvas-edge-hit" data-edge-key="${escapeAttr(key)}" d="M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}" />
          <path class="canvas-edge${branchClass}${selected}" data-edge-key="${escapeAttr(key)}" d="M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}" />
        `);
      });

      if (this.connectState) {
        const fromEl = this.container.querySelector(`[data-node-id="${this.connectState.fromId}"]`);
        if (fromEl) {
          const branch = this.connectState.branch || 'default';
          const outPort = fromEl.querySelector(`.canvas-port-out[data-port="${branch}"]`)
            || fromEl.querySelector('.canvas-port-out');
          const fr = (outPort || fromEl).getBoundingClientRect();
          const x1 = fr.left + fr.width / 2 - cr.left;
          const y1 = fr.top + fr.height / 2 - cr.top;
          const x2 = this.connectState.x - cr.left;
          const y2 = this.connectState.y - cr.top;
          const midX = (x1 + x2) / 2;
          parts.push(`<path class="canvas-edge canvas-edge-draft" d="M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}" />`);
        }
      }

      svg.innerHTML = parts.join('');

      svg.querySelectorAll('.canvas-edge-hit, .canvas-edge').forEach((path) => {
        path.addEventListener('click', (e) => {
          e.stopPropagation();
          const key = path.getAttribute('data-edge-key');
          if (!key) return;
          this.selectedEdgeKey = key;
          this.selectedNodeId = null;
          this._drawEdges(svg);
          if (this.inspectorEl) {
            this.inspectorEl.innerHTML = `
              <h4 class="node-inspector-title">Connection</h4>
              <p class="form-help">Selected connection. Press Delete to remove it.</p>
              <button type="button" class="btn btn-sm btn-danger" id="canvas-delete-edge">Delete connection</button>
            `;
            this.inspectorEl.querySelector('#canvas-delete-edge')?.addEventListener('click', () => this._removeSelectedEdge());
          }
        });
      });
    }

    _renderInspector(inspector) {
      if (!inspector) return;
      if (this.selectedEdgeKey && !this.selectedNodeId) return;

      const node = this.canvas.nodes.find((n) => n.id === this.selectedNodeId);
      if (!node) {
        inspector.innerHTML = `
          <h4 class="node-inspector-title">Workflow canvas</h4>
          <p class="form-help">Select a node to configure it. Collection fields use searchable dropdowns. Node cards show a live summary of their settings.</p>
        `;
        return;
      }

      const meta = this._blockMeta(node.kind, node.type);
      const schema = meta.schema || {};
      const fields = Object.keys(schema);
      const missing = this._missingRequired(node);

      inspector.innerHTML = `
        <h4 class="node-inspector-title">${escapeHtml(meta.name || node.type)}</h4>
        <p class="form-help">${escapeHtml(meta.description || '')}</p>
        ${missing.length ? `<div class="node-inspector-alert">Required: ${escapeHtml(missing.join(', '))}</div>` : ''}
        <div class="node-inspector-fields"></div>
        <div class="node-inspector-connections-wrap">
          <h5>Connections</h5>
          <div class="node-inspector-connections-host"></div>
        </div>
      `;

      const fieldsHost = inspector.querySelector('.node-inspector-fields');
      if (!fields.length) {
        fieldsHost.innerHTML = '<p class="form-help">No configuration required.</p>';
      } else {
        fields.forEach((field) => {
          fieldsHost.appendChild(this._buildField(node, field, schema[field]));
        });
      }

      const outs = this.canvas.edges.filter((e) => e.from === node.id);
      const connHost = inspector.querySelector('.node-inspector-connections-host');
      if (!outs.length) {
        connHost.innerHTML = '<p class="form-help">No outgoing connections yet.</p>';
      } else {
        connHost.innerHTML = '<ul class="node-inspector-connections">' + outs.map((e) => {
          const target = this.canvas.nodes.find((n) => n.id === e.to);
          const label = e.branch === 'true' ? 'If true' : (e.branch === 'false' ? 'Else' : 'Next');
          return `<li><strong>${label}</strong> → ${escapeHtml(target?.type || e.to)}</li>`;
        }).join('') + '</ul>';
      }
    }

    _buildField(node, field, fs) {
      const wrap = document.createElement('div');
      wrap.className = 'form-group';
      const label = document.createElement('label');
      label.textContent = (fs.label || field) + (fs.required ? ' *' : '');
      wrap.appendChild(label);
      if (fs.description) {
        const help = document.createElement('p');
        help.className = 'form-help';
        help.textContent = fs.description;
        wrap.appendChild(help);
      }

      const type = fs.type || 'string';
      let val = (node.config || {})[field];
      if ((val == null || val === '') && fs.default != null && fs.default !== '') {
        val = fs.default;
        if (!node.config) node.config = {};
        // Persist schema defaults so Save keeps useful email subject/body templates.
        if (node.config[field] == null || node.config[field] === '') {
          node.config[field] = fs.default;
        }
      }
      if (val == null) val = '';

      if (type === 'collection_picker') {
        wrap.appendChild(this._buildSearchableCollectionPicker(node, field, val));
        return wrap;
      }
      if (type === 'sub_collection_picker') {
        wrap.appendChild(this._buildSubCollectionPicker(node, field, val));
        return wrap;
      }
      if (type === 'status_select' || (type === 'select' && field === 'status') || type === 'status') {
        const select = document.createElement('select');
        select.className = 'input';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = fs.required ? 'Select status…' : '— Any —';
        select.appendChild(empty);
        const opts = Array.isArray(fs.options) && fs.options.length ? fs.options : STATUS_OPTIONS;
        opts.forEach((o) => {
          const opt = document.createElement('option');
          opt.value = o.value ?? o;
          opt.textContent = o.label || o.value || o;
          if (String(val) === String(opt.value)) opt.selected = true;
          select.appendChild(opt);
        });
        select.addEventListener('change', () => this._setConfig(node, field, select.value));
        wrap.appendChild(select);
        return wrap;
      }
      if (type === 'select' && Array.isArray(fs.options)) {
        const select = document.createElement('select');
        select.className = 'input';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '—';
        select.appendChild(empty);
        fs.options.forEach((o) => {
          const opt = document.createElement('option');
          opt.value = o.value ?? o;
          opt.textContent = o.label || o.value || o;
          if (String(val) === String(opt.value)) opt.selected = true;
          select.appendChild(opt);
        });
        select.addEventListener('change', () => this._setConfig(node, field, select.value));
        wrap.appendChild(select);
        return wrap;
      }
      if (type === 'textarea') {
        const input = document.createElement('textarea');
        input.className = 'input';
        input.rows = fs.rows || 3;
        input.value = val;
        input.addEventListener('input', () => this._setConfig(node, field, input.value, false));
        input.addEventListener('change', () => this._setConfig(node, field, input.value));
        wrap.appendChild(input);
        return wrap;
      }

      const input = document.createElement('input');
      input.className = 'input';
      if (type === 'email') input.type = 'email';
      else if (type === 'url') input.type = 'url';
      else if (type === 'number') input.type = 'number';
      else if (type === 'time') input.type = 'time';
      else input.type = 'text';
      input.value = val;
      if (fs.placeholder) input.placeholder = fs.placeholder;
      input.addEventListener('input', () => this._setConfig(node, field, input.value, false));
      input.addEventListener('change', () => this._setConfig(node, field, input.value));
      wrap.appendChild(input);
      return wrap;
    }

    _buildSearchableCollectionPicker(node, field, value) {
      const wrap = document.createElement('div');
      wrap.className = 'smart-picker';

      if (!this.collections.length) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'input';
        input.value = value || '';
        input.placeholder = 'Collection slug (optional)';
        input.addEventListener('change', () => this._setConfig(node, field, input.value));
        wrap.appendChild(input);
        const note = document.createElement('p');
        note.className = 'form-help';
        note.textContent = 'No collections loaded yet — you can still type a slug.';
        wrap.appendChild(note);
        return wrap;
      }

      const search = document.createElement('input');
      search.type = 'search';
      search.className = 'input smart-picker-search';
      search.placeholder = 'Search collections…';
      search.setAttribute('aria-label', 'Search collections');

      const select = document.createElement('select');
      select.className = 'input smart-picker-select';
      select.size = Math.min(8, this.collections.length + 1);

      const renderOptions = (query) => {
        const q = (query || '').trim().toLowerCase();
        select.innerHTML = '';
        const any = document.createElement('option');
        any.value = '';
        any.textContent = '— Any collection —';
        select.appendChild(any);
        this.collections
          .filter((c) => {
            if (!q) return true;
            return ((c.title || '') + ' ' + (c.slug || '')).toLowerCase().includes(q);
          })
          .forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.slug;
            opt.textContent = (c.title || c.slug) + (c.title && c.title !== c.slug ? ` (${c.slug})` : '');
            if (value === c.slug) opt.selected = true;
            select.appendChild(opt);
          });
        if (value && !Array.from(select.options).some((o) => o.value === value)) {
          const orphan = document.createElement('option');
          orphan.value = value;
          orphan.textContent = value + ' (missing)';
          orphan.selected = true;
          select.appendChild(orphan);
        }
      };

      renderOptions('');
      search.addEventListener('input', () => renderOptions(search.value));
      select.addEventListener('change', () => {
        this._setConfig(node, field, select.value);
        // Refresh inspector so sub-collection options update.
        this._renderInspector(this.inspectorEl);
        this._refreshNodeSummaryDom(node);
      });

      wrap.appendChild(search);
      wrap.appendChild(select);
      return wrap;
    }

    _buildSubCollectionPicker(node, field, value) {
      const wrap = document.createElement('div');
      wrap.className = 'smart-picker';
      const collectionSlug = (node.config || {}).collection || '';
      const col = this.collections.find((c) => c.slug === collectionSlug);
      const subs = col?.sub_collections || [];

      const select = document.createElement('select');
      select.className = 'input';
      const any = document.createElement('option');
      any.value = '';
      any.textContent = collectionSlug ? '— Any sub-collection —' : '— Select a collection first —';
      select.appendChild(any);

      subs.forEach((sc) => {
        const opt = document.createElement('option');
        opt.value = sc.slug || sc;
        opt.textContent = sc.title || sc.slug || sc;
        if (value === opt.value) opt.selected = true;
        select.appendChild(opt);
      });

      if (!collectionSlug) select.disabled = true;
      select.addEventListener('change', () => this._setConfig(node, field, select.value));
      wrap.appendChild(select);
      return wrap;
    }

    _setConfig(node, field, value, rerenderSummary) {
      if (!node.config) node.config = {};
      node.config[field] = value;
      this.onChange();
      if (rerenderSummary === false) {
        this._refreshNodeSummaryDom(node);
        return;
      }
      this._refreshNodeSummaryDom(node);
      // Soft re-validate badge without full inspector wipe when typing.
      const el = this.container?.querySelector(`[data-node-id="${node.id}"]`);
      if (el) {
        el.classList.toggle('needs-config', this._missingRequired(node).length > 0);
      }
    }

    _refreshNodeSummaryDom(node) {
      const el = this.container?.querySelector(`[data-node-id="${node.id}"]`);
      if (!el) return;
      const box = el.querySelector('.canvas-node-summary');
      if (!box) return;
      box.innerHTML = this._nodeSummary(node)
        .map((line) => `<div class="canvas-node-summary-line">${escapeHtml(line)}</div>`)
        .join('');
      const missing = this._missingRequired(node);
      el.classList.toggle('needs-config', missing.length > 0);
      let badge = el.querySelector('.canvas-node-badge');
      if (missing.length && !badge) {
        badge = document.createElement('span');
        badge.className = 'canvas-node-badge';
        badge.title = 'Missing required settings';
        badge.textContent = 'Setup';
        el.querySelector('.canvas-node-header')?.insertBefore(badge, el.querySelector('.canvas-node-remove'));
      } else if (!missing.length && badge) {
        badge.remove();
      }
    }

    _blockMeta(kind, type) {
      const map = {
        trigger: this.blocks.triggers || {},
        condition: this.blocks.conditions || {},
        action: this.blocks.actions || {}
      };
      return map[kind]?.[type] || { name: type, schema: {} };
    }

    _startDrag(e, node) {
      if (e.button !== 0) return;
      const startX = e.clientX;
      const startY = e.clientY;
      const origX = node.x || 0;
      const origY = node.y || 0;
      let moved = false;

      const onMove = (ev) => {
        moved = true;
        node.x = Math.max(0, origX + (ev.clientX - startX));
        node.y = Math.max(0, origY + (ev.clientY - startY));
        const el = this.container.querySelector(`[data-node-id="${node.id}"]`);
        if (el) {
          el.style.left = node.x + 'px';
          el.style.top = node.y + 'px';
        }
        this._drawEdges(this.container.querySelector('.node-canvas-edges'));
      };

      const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        if (moved) this.onChange();
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    }
  }

  function truncate(str, max) {
    const s = String(str || '');
    if (s.length <= max) return s;
    return s.slice(0, max - 1) + '…';
  }

  function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  }

  function escapeAttr(str) {
    if (str == null) return '';
    return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  global.AutomationNodeEditor = AutomationNodeEditor;
})(typeof window !== 'undefined' ? window : globalThis);
