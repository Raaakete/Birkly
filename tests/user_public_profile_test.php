<?php
/**
 * Entry editor author labels come from the user helper, not the template.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
        return;
    }
    echo "OK: $label\n";
}

require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/birkly_users.php';

$template = (string) file_get_contents($root . '/admin/templates/entry-editor.html');
assert_true('entry editor does not define getUserInfo', !str_contains($template, 'function getUserInfo'));
assert_true('entry editor does not read users/*.json', !str_contains($template, "users/' . \$userId"));
assert_true('entry editor asks for the public profile', str_contains($template, 'birkly_user_public_profile'));

$sandbox = sys_get_temp_dir() . '/birkly-user-profile-' . getmypid();
if (is_dir($sandbox)) {
    foreach (glob($sandbox . '/users/*') ?: [] as $file) {
        unlink($file);
    }
    @rmdir($sandbox . '/users');
    @rmdir($sandbox);
}
mkdir($sandbox . '/users', 0755, true);

$GLOBALS['__birkly_project_root'] = $sandbox;

$id = 'abc123';
file_put_contents($sandbox . '/users/' . $id . '.json', json_encode([
    'id' => $id,
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'password' => 'secret-hash',
]));

$profile = birkly_user_public_profile($id);
assert_true('profile name is loaded', ($profile['name'] ?? '') === 'Ada Lovelace');
assert_true('profile email is loaded', ($profile['email'] ?? '') === 'ada@example.com');
assert_true('profile omits the password', !isset($profile['password']));
assert_true('missing id returns null', birkly_user_public_profile('missing') === null);
assert_true('blank id returns null', birkly_user_public_profile('  ') === null);

unset($GLOBALS['__birkly_project_root']);
unlink($sandbox . '/users/' . $id . '.json');
rmdir($sandbox . '/users');
rmdir($sandbox);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " failure(s)\n");
    exit(1);
}

echo "user_public_profile_test: OK\n";
