<?php
/**
 * Automation node canvas ↔ workflow JSON serializer.
 * P7-M6: Visual editor view over the existing automation storage schema.
 *
 * Runtime executor reads trigger/conditions/actions only — canvas metadata is optional.
 */

/**
 * Build a canvas graph from stored automation workflow JSON.
 *
 * @param array $automation Automation record (trigger, conditions, actions)
 * @return array{nodes: array<int, array>, edges: array<int, array>}
 */
function automation_canvas_from_workflow(array $automation): array
{
    $nodes = [];
    $edges = [];
    $y = 80;
    $prevId = null;
    $index = 0;

    $trigger = $automation['trigger'] ?? [];
    if (!empty($trigger['type'])) {
        $id = 'node_trigger';
        $nodes[] = [
            'id' => $id,
            'kind' => 'trigger',
            'type' => (string) $trigger['type'],
            'config' => is_array($trigger['config'] ?? null) ? $trigger['config'] : [],
            'x' => 120,
            'y' => $y,
        ];
        $prevId = $id;
        $y += 140;
        $index++;
    }

    foreach ($automation['conditions'] ?? [] as $condition) {
        if (empty($condition['type'])) {
            continue;
        }
        $id = 'node_cond_' . $index;
        $nodes[] = [
            'id' => $id,
            'kind' => 'condition',
            'type' => (string) $condition['type'],
            'config' => is_array($condition['config'] ?? null) ? $condition['config'] : [],
            'x' => 120,
            'y' => $y,
        ];
        if ($prevId !== null) {
            $edges[] = ['from' => $prevId, 'to' => $id];
        }
        $prevId = $id;
        $y += 140;
        $index++;
    }

    foreach ($automation['actions'] ?? [] as $action) {
        if (empty($action['type'])) {
            continue;
        }
        $id = 'node_action_' . $index;
        $nodes[] = [
            'id' => $id,
            'kind' => 'action',
            'type' => (string) $action['type'],
            'config' => is_array($action['config'] ?? null) ? $action['config'] : [],
            'x' => 120,
            'y' => $y,
        ];
        if ($prevId !== null) {
            $edges[] = ['from' => $prevId, 'to' => $id];
        }
        $prevId = $id;
        $y += 140;
        $index++;
    }

    return ['nodes' => $nodes, 'edges' => $edges];
}

/**
 * Serialize a canvas graph back to workflow JSON (trigger, conditions, actions).
 *
 * @param array $canvas Canvas with nodes[] and edges[]
 * @return array{trigger: array, conditions: array, actions: array}
 */
function automation_workflow_from_canvas(array $canvas): array
{
    $nodes = $canvas['nodes'] ?? [];
    $edges = $canvas['edges'] ?? [];

    if ($nodes === []) {
        return [
            'trigger' => ['type' => '', 'config' => []],
            'conditions' => [],
            'actions' => [],
        ];
    }

    $ordered = automation_canvas_topological_order($nodes, $edges);

    $trigger = ['type' => '', 'config' => []];
    $conditions = [];
    $actions = [];

    foreach ($ordered as $node) {
        $kind = $node['kind'] ?? '';
        $type = $node['type'] ?? '';
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        if ($kind === 'trigger' && $type !== '') {
            $trigger = ['type' => $type, 'config' => $config];
        } elseif ($kind === 'condition' && $type !== '') {
            $conditions[] = ['type' => $type, 'config' => $config];
        } elseif ($kind === 'action' && $type !== '') {
            $actions[] = ['type' => $type, 'config' => $config];
        }
    }

    return [
        'trigger' => $trigger,
        'conditions' => $conditions,
        'actions' => $actions,
    ];
}

/**
 * Validate canvas structure (acyclic flow, required nodes).
 *
 * @return array<int, string> List of error messages (empty = valid)
 */
function automation_validate_canvas(array $canvas): array
{
    $errors = [];
    $nodes = $canvas['nodes'] ?? [];
    $edges = $canvas['edges'] ?? [];

    $triggers = array_values(array_filter($nodes, static function ($n) {
        return ($n['kind'] ?? '') === 'trigger' && ($n['type'] ?? '') !== '';
    }));
    $actions = array_values(array_filter($nodes, static function ($n) {
        return ($n['kind'] ?? '') === 'action' && ($n['type'] ?? '') !== '';
    }));

    if (count($triggers) === 0) {
        $errors[] = 'Workflow requires exactly one trigger node.';
    } elseif (count($triggers) > 1) {
        $errors[] = 'Workflow allows only one trigger node.';
    }
    if (count($actions) === 0) {
        $errors[] = 'Workflow requires at least one action node.';
    }

    if (automation_canvas_has_cycle($nodes, $edges)) {
        $errors[] = 'Workflow graph must be acyclic.';
    }

    return $errors;
}

/**
 * Topological order from trigger through edges (linear chain preferred).
 *
 * @param array<int, array> $nodes
 * @param array<int, array> $edges
 * @return array<int, array>
 */
function automation_canvas_topological_order(array $nodes, array $edges): array
{
    if ($nodes === []) {
        return [];
    }

    $byId = [];
    foreach ($nodes as $node) {
        $byId[$node['id'] ?? ''] = $node;
    }

    $outgoing = [];
    $inDegree = [];
    foreach ($nodes as $node) {
        $id = $node['id'] ?? '';
        $outgoing[$id] = [];
        $inDegree[$id] = 0;
    }
    foreach ($edges as $edge) {
        $from = $edge['from'] ?? '';
        $to = $edge['to'] ?? '';
        if (!isset($byId[$from], $byId[$to])) {
            continue;
        }
        $outgoing[$from][] = $to;
        $inDegree[$to] = ($inDegree[$to] ?? 0) + 1;
    }

    $start = null;
    foreach ($nodes as $node) {
        if (($node['kind'] ?? '') === 'trigger') {
            $start = $node['id'] ?? null;
            break;
        }
    }
    if ($start === null) {
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $start = $id;
                break;
            }
        }
    }

    $ordered = [];
    $visited = [];
    $current = $start;
    while ($current !== null && !isset($visited[$current]) && isset($byId[$current])) {
        $visited[$current] = true;
        $ordered[] = $byId[$current];
        $next = null;
        foreach ($outgoing[$current] ?? [] as $candidate) {
            if (!isset($visited[$candidate])) {
                $next = $candidate;
                break;
            }
        }
        $current = $next;
    }

    foreach ($nodes as $node) {
        $id = $node['id'] ?? '';
        if (!isset($visited[$id]) && ($node['type'] ?? '') !== '') {
            $ordered[] = $node;
        }
    }

    return $ordered;
}

/**
 * @param array<int, array> $nodes
 * @param array<int, array> $edges
 */
function automation_canvas_has_cycle(array $nodes, array $edges): bool
{
    $adj = [];
    foreach ($nodes as $node) {
        $adj[$node['id'] ?? ''] = [];
    }
    foreach ($edges as $edge) {
        $from = $edge['from'] ?? '';
        $to = $edge['to'] ?? '';
        if (isset($adj[$from])) {
            $adj[$from][] = $to;
        }
    }

    $visited = [];
    $stack = [];

    $dfs = static function ($id) use (&$dfs, &$adj, &$visited, &$stack): bool {
        if (isset($stack[$id])) {
            return true;
        }
        if (isset($visited[$id])) {
            return false;
        }
        $visited[$id] = true;
        $stack[$id] = true;
        foreach ($adj[$id] ?? [] as $next) {
            if ($dfs($next)) {
                return true;
            }
        }
        unset($stack[$id]);
        return false;
    };

    foreach (array_keys($adj) as $id) {
        if ($dfs($id)) {
            return true;
        }
    }

    return false;
}

/**
 * Round-trip helper: workflow → canvas → workflow (workflow fields only).
 *
 * @return array{trigger: array, conditions: array, actions: array}
 */
function automation_workflow_roundtrip(array $automation): array
{
    $canvas = automation_canvas_from_workflow($automation);
    return automation_workflow_from_canvas($canvas);
}
