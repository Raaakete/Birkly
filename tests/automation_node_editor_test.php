<?php
/**
 * P7-M6: Automation node editor serializer smoke tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Automation node editor tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/automation/includes/node_serializer.php';

$sample = [
    'id' => 'auto_test',
    'name' => 'Notify on publish',
    'enabled' => true,
    'stop_on_failure' => false,
    'trigger' => [
        'type' => 'status_changed',
        'config' => ['to' => 'published', 'collection' => 'blog'],
    ],
    'conditions' => [
        ['type' => 'field_match', 'config' => ['field' => 'title', 'operator' => 'not_empty']],
    ],
    'actions' => [
        [
            'type' => 'email',
            'config' => [
                'to' => 'admin@example.com',
                'subject' => 'Published',
                'body' => 'Entry published',
            ],
        ],
        [
            'type' => 'webhook',
            'config' => ['url' => 'https://example.com/hook', 'method' => 'POST'],
        ],
    ],
];

$canvas = automation_canvas_from_workflow($sample);
test_assert('canvas has trigger node', count(array_filter($canvas['nodes'], fn($n) => ($n['kind'] ?? '') === 'trigger')) === 1);
test_assert('canvas has condition node', count(array_filter($canvas['nodes'], fn($n) => ($n['kind'] ?? '') === 'condition')) === 1);
test_assert('canvas has two action nodes', count(array_filter($canvas['nodes'], fn($n) => ($n['kind'] ?? '') === 'action')) === 2);
test_assert('canvas edges connect chain', count($canvas['edges']) === 3);

$roundtrip = automation_workflow_roundtrip($sample);
test_assert('round-trip preserves trigger type', ($roundtrip['trigger']['type'] ?? '') === 'status_changed');
test_assert('round-trip preserves trigger config', ($roundtrip['trigger']['config']['to'] ?? '') === 'published');
test_assert('round-trip preserves condition count', count($roundtrip['conditions']) === 1);
test_assert('round-trip preserves action count', count($roundtrip['actions']) === 2);
test_assert('round-trip preserves first action type', ($roundtrip['actions'][0]['type'] ?? '') === 'email');
test_assert('round-trip preserves webhook config', ($roundtrip['actions'][1]['config']['url'] ?? '') === 'https://example.com/hook');

$errors = automation_validate_canvas($canvas);
test_assert('valid sample canvas passes validation', $errors === []);

$invalid = [
    'nodes' => [
        ['id' => 'a', 'kind' => 'action', 'type' => 'email', 'config' => []],
    ],
    'edges' => [],
];
$invalidErrors = automation_validate_canvas($invalid);
test_assert('missing trigger fails validation', count($invalidErrors) > 0);

$cycleCanvas = [
    'nodes' => [
        ['id' => 't', 'kind' => 'trigger', 'type' => 'entry_created', 'config' => []],
        ['id' => 'a', 'kind' => 'action', 'type' => 'email', 'config' => []],
        ['id' => 'b', 'kind' => 'action', 'type' => 'log', 'config' => []],
    ],
    'edges' => [
        ['from' => 't', 'to' => 'a'],
        ['from' => 'a', 'to' => 'b'],
        ['from' => 'b', 'to' => 'a'],
    ],
];
test_assert('cycle detected', automation_canvas_has_cycle($cycleCanvas['nodes'], $cycleCanvas['edges']) === true);
test_assert('cycle canvas fails validation', count(automation_validate_canvas($cycleCanvas)) > 0);

$serializerJs = file_get_contents($root . '/plugins/automation/admin/automation_node_serializer.js');
$editorJs = file_get_contents($root . '/plugins/automation/admin/automation_node_editor.js');
$adminHtml = file_get_contents($root . '/plugins/automation/admin/automation.html');

test_assert('node serializer JS exists', is_file($root . '/plugins/automation/admin/automation_node_serializer.js'));
test_assert('node editor JS exists', is_file($root . '/plugins/automation/admin/automation_node_editor.js'));
test_assert('serializer exports canvasFromWorkflow', strpos($serializerJs, 'canvasFromWorkflow') !== false);
test_assert('serializer defines palette sections', strpos($serializerJs, 'PALETTE_SECTIONS') !== false);
test_assert('editor exports AutomationNodeEditor', strpos($editorJs, 'AutomationNodeEditor') !== false);
test_assert('admin HTML has form/canvas toggle', strpos($adminHtml, 'setAutomationEditorMode') !== false);
test_assert('admin HTML loads node editor scripts', strpos($adminHtml, 'automation_node_editor.js') !== false);

exit(test_finish('automation_node_editor_test'));
