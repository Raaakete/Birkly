<?php
/**
 * P2-M4 — collection groups load/save helpers.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/collection_groups.php';

test_reset_failures();

$tmp = sys_get_temp_dir() . '/birkly-groups-' . uniqid('', true) . '.json';
$sample = [
    ['id' => 'g1', 'name' => 'Main', 'collections' => ['blog', 'about']],
    ['id' => 'ungrouped', 'name' => 'Ungrouped', 'collections' => ['contact']],
];

test_assert('save groups', birkly_save_collection_groups($sample, $tmp));
test_assert('load groups count', count(birkly_load_collection_groups($tmp)) === 2);
test_assert('load preserves slug order', birkly_load_collection_groups($tmp)[0]['collections'][0] === 'blog');
test_assert('missing file returns empty array', birkly_load_collection_groups($tmp . '.missing') === []);

@unlink($tmp);

exit(test_finish('collection_groups_test'));
