/**
 * Automation node canvas ↔ workflow JSON serializer (browser).
 * Mirrors plugins/automation/includes/node_serializer.php for admin UI.
 */
(function (global) {
  'use strict';

  function canvasFromWorkflow(automation) {
    const nodes = [];
    const edges = [];
    let y = 80;
    let prevId = null;
    let index = 0;

    const trigger = automation.trigger || {};
    if (trigger.type) {
      const id = 'node_trigger';
      nodes.push({
        id,
        kind: 'trigger',
        type: trigger.type,
        config: trigger.config || {},
        x: 120,
        y
      });
      prevId = id;
      y += 140;
      index++;
    }

    (automation.conditions || []).forEach((condition) => {
      if (!condition.type) return;
      const id = 'node_cond_' + index;
      nodes.push({
        id,
        kind: 'condition',
        type: condition.type,
        config: condition.config || {},
        x: 120,
        y
      });
      if (prevId) edges.push({ from: prevId, to: id });
      prevId = id;
      y += 140;
      index++;
    });

    (automation.actions || []).forEach((action) => {
      if (!action.type) return;
      const id = 'node_action_' + index;
      nodes.push({
        id,
        kind: 'action',
        type: action.type,
        config: action.config || {},
        x: 120,
        y
      });
      if (prevId) edges.push({ from: prevId, to: id });
      prevId = id;
      y += 140;
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

  function topologicalOrder(nodes, edges) {
    if (!nodes.length) return [];

    const byId = {};
    nodes.forEach((n) => { byId[n.id] = n; });

    const outgoing = {};
    const inDegree = {};
    nodes.forEach((n) => {
      outgoing[n.id] = [];
      inDegree[n.id] = 0;
    });
    edges.forEach((e) => {
      if (!byId[e.from] || !byId[e.to]) return;
      outgoing[e.from].push(e.to);
      inDegree[e.to] = (inDegree[e.to] || 0) + 1;
    });

    let start = nodes.find((n) => n.kind === 'trigger')?.id;
    if (!start) {
      start = Object.keys(inDegree).find((id) => inDegree[id] === 0);
    }

    const ordered = [];
    const visited = {};
    let current = start;
    while (current && !visited[current] && byId[current]) {
      visited[current] = true;
      ordered.push(byId[current]);
      const next = (outgoing[current] || []).find((c) => !visited[c]);
      current = next || null;
    }

    nodes.forEach((n) => {
      if (!visited[n.id] && n.type) ordered.push(n);
    });

    return ordered;
  }

  function workflowFromCanvas(canvas) {
    const nodes = canvas.nodes || [];
    const edges = canvas.edges || [];

    if (!nodes.length) {
      return { trigger: { type: '', config: {} }, conditions: [], actions: [] };
    }

    const ordered = topologicalOrder(nodes, edges);
    const trigger = { type: '', config: {} };
    const conditions = [];
    const actions = [];

    ordered.forEach((node) => {
      const config = node.config || {};
      if (node.kind === 'trigger' && node.type) {
        trigger.type = node.type;
        trigger.config = config;
      } else if (node.kind === 'condition' && node.type) {
        conditions.push({ type: node.type, config });
      } else if (node.kind === 'action' && node.type) {
        actions.push({ type: node.type, config });
      }
    });

    return { trigger, conditions, actions };
  }

  function validateCanvas(canvas) {
    const errors = [];
    const nodes = canvas.nodes || [];
    const edges = canvas.edges || [];

    const triggers = nodes.filter((n) => n.kind === 'trigger' && n.type);
    const actions = nodes.filter((n) => n.kind === 'action' && n.type);

    if (triggers.length === 0) errors.push('Workflow requires exactly one trigger node.');
    else if (triggers.length > 1) errors.push('Workflow allows only one trigger node.');
    if (actions.length === 0) errors.push('Workflow requires at least one action node.');
    if (hasCycle(nodes, edges)) errors.push('Workflow graph must be acyclic.');

    return errors;
  }

  function workflowRoundtrip(automation) {
    return workflowFromCanvas(canvasFromWorkflow(automation));
  }

  /** Palette section matchers for Commerce, Events, User, Webhooks */
  const PALETTE_SECTIONS = [
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
    PALETTE_SECTIONS
  };
})(typeof window !== 'undefined' ? window : globalThis);
