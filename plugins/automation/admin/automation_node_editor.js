/**
 * Automation node/canvas editor — P7-M6
 * Visual workflow builder over existing automation JSON schema.
 */
(function (global) {
  'use strict';

  const Serializer = global.AutomationNodeSerializer;
  if (!Serializer) return;

  let nodeSeq = 0;

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
      this.canvas = { nodes: [], edges: [] };
      this.selectedNodeId = null;
      this.onChange = options.onChange || function () {};
      this.dragState = null;

      this._buildPalette();
      this._render();
    }

    setBlocks(blocks) {
      this.blocks = blocks || { triggers: {}, conditions: {}, actions: {} };
      this._buildPalette();
    }

    loadFromWorkflow(automation) {
      this.canvas = Serializer.canvasFromWorkflow(automation || {});
      this.selectedNodeId = null;
      this._render();
      this.onChange();
    }

    toWorkflow() {
      return Serializer.workflowFromCanvas(this.canvas);
    }

    validate() {
      return Serializer.validateCanvas(this.canvas);
    }

    exportJson() {
      return JSON.stringify(this.canvas, null, 2);
    }

    importJson(json) {
      const data = typeof json === 'string' ? JSON.parse(json) : json;
      if (!data || !Array.isArray(data.nodes)) {
        throw new Error('Invalid canvas JSON');
      }
      this.canvas = {
        nodes: data.nodes,
        edges: Array.isArray(data.edges) ? data.edges : []
      };
      this.selectedNodeId = null;
      this._render();
      this.onChange();
    }

    _buildPalette() {
      if (!this.paletteEl) return;
      this.paletteEl.innerHTML = '';

      const sections = Serializer.PALETTE_SECTIONS;
      const kinds = [
        { key: 'triggers', kind: 'trigger', label: 'Triggers' },
        { key: 'conditions', kind: 'condition', label: 'Conditions' },
        { key: 'actions', kind: 'action', label: 'Actions' }
      ];

      sections.forEach((section) => {
        const sectionEl = document.createElement('div');
        sectionEl.className = 'node-palette-section';
        sectionEl.dataset.section = section.id;

        const title = document.createElement('h4');
        title.className = 'node-palette-section-title';
        title.textContent = section.label;
        sectionEl.appendChild(title);

        let hasBlocks = false;

        kinds.forEach(({ key, kind }) => {
          const bucket = this.blocks[key] || {};
          Object.keys(bucket).forEach((blockId) => {
            const meta = bucket[blockId];
            if (!section.match(kind, blockId, meta)) return;
            hasBlocks = true;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'node-palette-block';
            btn.dataset.kind = kind;
            btn.dataset.type = blockId;
            btn.title = meta.description || meta.name || blockId;
            btn.innerHTML = `
              <span class="node-palette-block-kind">${kind}</span>
              <span class="node-palette-block-name">${escapeHtml(meta.name || blockId)}</span>
            `;
            btn.addEventListener('click', () => this._addNode(kind, blockId));
            sectionEl.appendChild(btn);
          });
        });

        if (hasBlocks) {
          this.paletteEl.appendChild(sectionEl);
        }
      });

      if (!this.paletteEl.children.length) {
        this.paletteEl.innerHTML = '<p class="form-help">No palette blocks loaded.</p>';
      }
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

      const y = 80 + this.canvas.nodes.length * 140;
      const node = {
        id: nextNodeId(kind),
        kind,
        type,
        config: {},
        x: 120,
        y
      };
      this.canvas.nodes.push(node);

      const last = this.canvas.nodes[this.canvas.nodes.length - 2];
      if (last) {
        this.canvas.edges.push({ from: last.id, to: node.id });
      }

      this.selectedNodeId = node.id;
      this._render();
      this.onChange();
    }

    _removeNode(nodeId) {
      this.canvas.nodes = this.canvas.nodes.filter((n) => n.id !== nodeId);
      this.canvas.edges = this.canvas.edges.filter((e) => e.from !== nodeId && e.to !== nodeId);

      if (this.canvas.nodes.length > 1) {
        this.canvas.edges = [];
        for (let i = 0; i < this.canvas.nodes.length - 1; i++) {
          this.canvas.edges.push({
            from: this.canvas.nodes[i].id,
            to: this.canvas.nodes[i + 1].id
          });
        }
      }

      if (this.selectedNodeId === nodeId) this.selectedNodeId = null;
      this._render();
      this.onChange();
    }

    _render() {
      if (!this.container) return;

      this.container.innerHTML = `
        <svg class="node-canvas-edges" aria-hidden="true"></svg>
        <div class="node-canvas-nodes"></div>
      `;

      const nodesLayer = this.container.querySelector('.node-canvas-nodes');
      const edgesLayer = this.container.querySelector('.node-canvas-edges');
      const inspector = this.inspectorEl;

      this.canvas.nodes.forEach((node) => {
        const meta = this._blockMeta(node.kind, node.type);
        const el = document.createElement('div');
        el.className = 'canvas-node canvas-node-' + node.kind + (this.selectedNodeId === node.id ? ' selected' : '');
        el.dataset.nodeId = node.id;
        el.style.left = (node.x || 0) + 'px';
        el.style.top = (node.y || 0) + 'px';
        el.innerHTML = `
          <div class="canvas-node-header">
            <span class="canvas-node-kind">${node.kind}</span>
            <button type="button" class="canvas-node-remove" title="Remove" aria-label="Remove node">&times;</button>
          </div>
          <div class="canvas-node-title">${escapeHtml(meta.name || node.type || 'Untitled')}</div>
          <div class="canvas-node-type">${escapeHtml(node.type)}</div>
        `;

        el.addEventListener('mousedown', (e) => {
          if (e.target.closest('.canvas-node-remove')) return;
          this._startDrag(e, node);
        });
        el.addEventListener('click', (e) => {
          if (e.target.closest('.canvas-node-remove')) return;
          this.selectedNodeId = node.id;
          this._render();
        });
        el.querySelector('.canvas-node-remove').addEventListener('click', (e) => {
          e.stopPropagation();
          this._removeNode(node.id);
        });

        nodesLayer.appendChild(el);
      });

      this._drawEdges(edgesLayer);
      this._renderInspector(inspector);
    }

    _drawEdges(svg) {
      if (!svg) return;
      const rect = this.container.getBoundingClientRect();
      svg.setAttribute('width', String(Math.max(rect.width, 600)));
      svg.setAttribute('height', String(Math.max(rect.height, 400)));

      const paths = this.canvas.edges.map((edge) => {
        const fromEl = this.container.querySelector(`[data-node-id="${edge.from}"]`);
        const toEl = this.container.querySelector(`[data-node-id="${edge.to}"]`);
        if (!fromEl || !toEl) return '';

        const cr = this.container.getBoundingClientRect();
        const fr = fromEl.getBoundingClientRect();
        const tr = toEl.getBoundingClientRect();

        const x1 = fr.left - cr.left + fr.width / 2;
        const y1 = fr.bottom - cr.top;
        const x2 = tr.left - cr.left + tr.width / 2;
        const y2 = tr.top - cr.top;
        const midY = (y1 + y2) / 2;

        return `<path class="canvas-edge" d="M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}" />`;
      }).join('');

      svg.innerHTML = paths;
    }

    _renderInspector(inspector) {
      if (!inspector) return;
      const node = this.canvas.nodes.find((n) => n.id === this.selectedNodeId);
      if (!node) {
        inspector.innerHTML = '<p class="form-help">Select a node to edit its configuration.</p>';
        return;
      }

      const meta = this._blockMeta(node.kind, node.type);
      const schema = meta.schema || {};
      const fields = Object.keys(schema);

      let fieldsHtml = '';
      if (fields.length === 0) {
        fieldsHtml = '<p class="form-help">No configuration required.</p>';
      } else {
        fieldsHtml = fields.map((field) => {
          const fs = schema[field];
          const val = (node.config || {})[field] ?? '';
          const label = escapeHtml(fs.label || field);
          if (fs.type === 'textarea') {
            return `
              <div class="form-group">
                <label>${label}</label>
                <textarea class="input node-inspector-field" data-field="${escapeAttr(field)}" rows="3">${escapeHtml(val)}</textarea>
              </div>`;
          }
          if (fs.type === 'select' && Array.isArray(fs.options)) {
            const opts = fs.options.map((o) => {
              const v = o.value ?? o;
              const selected = val === v ? ' selected' : '';
              return `<option value="${escapeAttr(v)}"${selected}>${escapeHtml(o.label || v)}</option>`;
            }).join('');
            return `
              <div class="form-group">
                <label>${label}</label>
                <select class="input node-inspector-field" data-field="${escapeAttr(field)}">
                  <option value="">—</option>${opts}
                </select>
              </div>`;
          }
          return `
            <div class="form-group">
              <label>${label}</label>
              <input type="text" class="input node-inspector-field" data-field="${escapeAttr(field)}" value="${escapeAttr(val)}">
            </div>`;
        }).join('');
      }

      inspector.innerHTML = `
        <h4 class="node-inspector-title">${escapeHtml(meta.name || node.type)}</h4>
        <p class="form-help">${escapeHtml(meta.description || '')}</p>
        ${fieldsHtml}
      `;

      inspector.querySelectorAll('.node-inspector-field').forEach((input) => {
        const field = input.dataset.field;
        const handler = () => {
          if (!node.config) node.config = {};
          node.config[field] = input.value;
          this.onChange();
        };
        input.addEventListener('input', handler);
        input.addEventListener('change', handler);
      });
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

      const onMove = (ev) => {
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
      };

      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', onUp);
    }
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
