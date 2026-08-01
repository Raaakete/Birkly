/**
 * Automation node canvas ↔ workflow JSON serializer (browser).
 * Supports persisted graph with branch edges (true/false/default).
 */
(function (global) {
  'use strict';

  function normalizeEdge(edge) {
    if (!edge || !edge.from || !edge.to) return null;
    const branch = edge.branch === 'true' || edge.branch === 'false' ? edge.branch : 'default';
    return { from: String(edge.from), to: String(edge.to), branch };
  }

  function normalizeCanvas(canvas) {
    const nodes = Array.isArray(canvas?.nodes) ? canvas.nodes.map((n) => ({
      id: String(n.id || ''),
      kind: n.kind === 'condition' || n.kind === 'action' || n.kind === 'trigger' ? n.kind : 'action',
      type: String(n.type || ''),
      config: n.config && typeof n.config === 'object' ? { ...n.config } : {},
      x: Number.isFinite(n.x) ? n.x : 40,
      y: Number.isFinite(n.y) ? n.y : 40
    })).filter((n) => n.id) : [];

    const edges = Array.isArray(canvas?.edges)
      ? canvas.edges.map(normalizeEdge).filter(Boolean)
      : [];

    return { nodes, edges };
  }

  function canvasFromWorkflow(automation) {
    if (automation && automation.graph && Array.isArray(automation.graph.nodes) && automation.graph.nodes.length) {
      return normalizeCanvas(automation.graph);
    }

    const nodes = [];
    const edges = [];
    let y = 80;
    let prevId = null;
    let index = 0;

    const trigger = automation?.trigger || {};
    if (trigger.type) {
      const id = 'node_trigger';
      nodes.push({
        id,
        kind: 'trigger',
        type: trigger.type,
        config: trigger.config || {},
        x: 80,
        y
      });
      prevId = id;
      y += 150;
      index++;
    }

    (automation?.conditions || []).forEach((condition) => {
      if (!condition.type) return;
      const id = 'node_cond_' + index;
      nodes.push({
        id,
        kind: 'condition',
        type: condition.type,
        config: condition.config || {},
        x: 80,
        y
      });
      if (prevId) edges.push({ from: prevId, to: id, branch: 'default' });
      prevId = id;
      y += 150;
      index++;
    });

    (automation?.actions || []).forEach((action) => {
      if (!action.type) return;
      const id = 'node_action_' + index;
      nodes.push({
        id,
        kind: 'action',
        type: action.type,
        config: action.config || {},
        x: 80,
        y
      });
      if (prevId) {
        const prev = nodes.find((n) => n.id === prevId);
        const branch = prev && prev.kind === 'condition' ? 'true' : 'default';
        edges.push({ from: prevId, to: id, branch });
      }
      prevId = id;
      y += 150;
      index++;
    });

    return { nodes, edges };
  }

  function hasCycle(nodes, edges) {
    const adj = {};
    nodes.forEach((n) => { adj[n.id] = []; });
    edges.forEach((e) => {
      if (adj[e.from]) adj[e.from].push(e.to);
    });

    const visited = {};
    const stack = {};

    function dfs(id) {
      if (stack[id]) return true;
      if (visited[id]) return false;
      visited[id] = true;
      stack[id] = true;
      for (const next of adj[id] || []) {
        if (dfs(next)) return true;
      }
      delete stack[id];
      return false;
    }

    return Object.keys(adj).some((id) => dfs(id));
  }

  function outgoing(edges, fromId, branch) {
    return edges.filter((e) => {
      if (e.from !== fromId) return false;
      if (!branch) return true;
      return e.branch === branch;
    });
  }

  function topologicalOrder(nodes, edges) {
    if (!nodes.length) return [];

    const byId = {};
    nodes.forEach((n) => { byId[n.id] = n; });

    const start = nodes.find((n) => n.kind === 'trigger')?.id
      || nodes.find((n) => !edges.some((e) => e.to === n.id))?.id;

    const ordered = [];
    const visited = {};
    const queue = start ? [start] : [];

    while (queue.length) {
      const id = queue.shift();
      if (!id || visited[id] || !byId[id]) continue;
      visited[id] = true;
      ordered.push(byId[id]);
      outgoing(edges, id).forEach((e) => {
        if (!visited[e.to]) queue.push(e.to);
      });
    }

    nodes.forEach((n) => {
      if (!visited[n.id] && n.type) ordered.push(n);
    });

    return ordered;
  }

  /**
   * Flatten graph into legacy trigger/conditions/actions for backward compatibility.
   * Primary path follows default/true branches.
   */
  function workflowFromCanvas(canvas) {
    const normalized = normalizeCanvas(canvas);
    const nodes = normalized.nodes;
    const edges = normalized.edges;

    if (!nodes.length) {
      return { trigger: { type: '', config: {} }, conditions: [], actions: [], graph: normalized };
    }

    const byId = {};
    nodes.forEach((n) => { byId[n.id] = n; });

    const triggerNode = nodes.find((n) => n.kind === 'trigger' && n.type);
    const trigger = triggerNode
      ? { type: triggerNode.type, config: triggerNode.config || {} }
      : { type: '', config: {} };

    const conditions = [];
    const actions = [];
    const visited = {};
    let current = triggerNode?.id || null;

    while (current && !visited[current] && byId[current]) {
      visited[current] = true;
      const node = byId[current];
      if (node.kind === 'condition' && node.type) {
        conditions.push({ type: node.type, config: node.config || {} });
        const trueEdge = outgoing(edges, current, 'true')[0]
          || outgoing(edges, current, 'default')[0];
        current = trueEdge ? trueEdge.to : null;
        continue;
      }
      if (node.kind === 'action' && node.type) {
        actions.push({ type: node.type, config: node.config || {} });
      }
      const next = outgoing(edges, current, 'default')[0]
        || outgoing(edges, current, 'true')[0]
        || outgoing(edges, current)[0];
      current = next ? next.to : null;
    }

    // Include unreachable typed nodes so form editor still sees them.
    nodes.forEach((n) => {
      if (visited[n.id] || !n.type) return;
      if (n.kind === 'condition') conditions.push({ type: n.type, config: n.config || {} });
      if (n.kind === 'action') actions.push({ type: n.type, config: n.config || {} });
    });

    return { trigger, conditions, actions, graph: normalized };
  }

  function validateCanvas(canvas) {
    const errors = [];
    const normalized = normalizeCanvas(canvas);
    const nodes = normalized.nodes;
    const edges = normalized.edges;

    const triggers = nodes.filter((n) => n.kind === 'trigger' && n.type);
    const actions = nodes.filter((n) => n.kind === 'action' && n.type);

    if (triggers.length === 0) errors.push('Workflow requires exactly one trigger node.');
    else if (triggers.length > 1) errors.push('Workflow allows only one trigger node.');
    if (actions.length === 0) errors.push('Workflow requires at least one action node.');
    if (hasCycle(nodes, edges)) errors.push('Workflow graph must be acyclic.');

    const ids = new Set(nodes.map((n) => n.id));
    edges.forEach((e) => {
      if (!ids.has(e.from) || !ids.has(e.to)) {
        errors.push('Connection references a missing node.');
      }
    });

    nodes.filter((n) => n.kind === 'condition').forEach((n) => {
      const trueCount = outgoing(edges, n.id, 'true').length;
      const falseCount = outgoing(edges, n.id, 'false').length;
      if (trueCount > 1) errors.push(`Condition "${n.type || n.id}" has more than one If-true connection.`);
      if (falseCount > 1) errors.push(`Condition "${n.type || n.id}" has more than one Else connection.`);
    });

    nodes.filter((n) => n.kind !== 'condition').forEach((n) => {
      const defaults = outgoing(edges, n.id, 'default').concat(outgoing(edges, n.id, 'true'));
      // Allow one primary out; multiple would be ambiguous for non-branch nodes.
      const uniqueTargets = new Set(defaults.map((e) => e.to));
      if (uniqueTargets.size > 1 && n.kind !== 'trigger') {
        // Trigger may only have one out too
      }
      if (outgoing(edges, n.id).filter((e) => e.branch === 'default').length > 1) {
        errors.push(`Node "${n.type || n.id}" has multiple default outgoing connections.`);
      }
    });

    return errors;
  }

  function workflowRoundtrip(automation) {
    return workflowFromCanvas(canvasFromWorkflow(automation));
  }

  const PALETTE_SECTIONS = [
    {
      id: 'content',
      label: 'Content',
      match(kind, blockId, meta) {
        return meta.category === 'Content' || meta.category === 'Sub-Content';
      }
    },
    {
      id: 'system',
      label: 'System',
      match(kind, blockId, meta) {
        return meta.category === 'System';
      }
    },
    {
      id: 'communication',
      label: 'Communication',
      match(kind, blockId, meta) {
        return meta.category === 'Communication' || meta.category === 'Utility';
      }
    },
    {
      id: 'commerce',
      label: 'Commerce',
      match(kind, blockId, meta) {
        return meta.category === 'Commerce';
      }
    },
    {
      id: 'events',
      label: 'Events',
      match(kind, blockId, meta) {
        return meta.category === 'Events';
      }
    },
    {
      id: 'user',
      label: 'User',
      match(kind, blockId, meta) {
        return meta.category === 'User' || /^user_/.test(blockId);
      }
    },
    {
      id: 'webhooks',
      label: 'Webhooks',
      match(kind, blockId) {
        if (kind === 'trigger' && blockId === 'webhook_inbound') return true;
        return kind === 'action' && ['webhook', 'slack', 'teams'].includes(blockId);
      }
    }
  ];

  global.AutomationNodeSerializer = {
    canvasFromWorkflow,
    workflowFromCanvas,
    validateCanvas,
    workflowRoundtrip,
    topologicalOrder,
    normalizeCanvas,
    outgoing,
    PALETTE_SECTIONS
  };
})(typeof window !== 'undefined' ? window : globalThis);
