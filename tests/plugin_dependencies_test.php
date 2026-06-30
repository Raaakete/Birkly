<?php
/**
 * P5.2-M0: Plugin dependency check uses $birkly_plugins registry.
 * Run: php tests/plugin_dependencies_test.php
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Plugin dependencies tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/plugins.php';

load_plugins();

global $birkly_plugins;
test_assert('user_management in plugin registry', isset($birkly_plugins['user_management']));

$errors = check_plugin_dependencies('user_management');
$notFound = in_array("Plugin 'user_management' not found", $errors, true);
test_assert('check_plugin_dependencies finds user_management', !$notFound, implode('; ', $errors));

$validation = validate_plugin_activation('user_management');
$validationNotFound = in_array("Plugin 'user_management' not found", $validation['errors'], true);
test_assert('validate_plugin_activation finds user_management', !$validationNotFound, implode('; ', $validation['errors']));

exit(test_finish('plugin_dependencies_test'));
