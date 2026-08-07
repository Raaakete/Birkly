<?php
/**
 * Project git engine (P59-M2) — exercised against a local bare repository so
 * the suite never needs network access.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/project_site.php';
require_once dirname(__DIR__) . '/core/project_site_filesystem.php';
require_once dirname(__DIR__) . '/core/project_git.php';

$failures = 0;
$skipped = false;

function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo '  ' . $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

// ---------------------------------------------------------------------------
// Capability
// ---------------------------------------------------------------------------

$tmp = sys_get_temp_dir() . '/birkly_git_test_' . bin2hex(random_bytes(4));
@mkdir($tmp . '/a/project', 0755, true);
@mkdir($tmp . '/b/project', 0755, true);
@mkdir($tmp . '/bare', 0755, true);

echo 'capability' . PHP_EOL;
$capability = ProjectGit::capability($tmp . '/a/project');
assert_true('capability reports a git version', is_string($capability['version']) && $capability['version'] !== '');
assert_true('capability available in a writable project', $capability['available'] === true);

if (!$capability['available']) {
    echo PHP_EOL . 'project_git_test: SKIP (git unavailable)' . PHP_EOL;
    rrmdir($tmp);
    exit(0);
}

// ---------------------------------------------------------------------------
// Remote URL / branch validation — no arbitrary git transports from the UI
// ---------------------------------------------------------------------------

echo PHP_EOL . 'input validation' . PHP_EOL;
$rejected = 0;
foreach ([
    'ext::sh -c whoami',
    '--upload-pack=touch /tmp/pwned',
    "https://example.com/repo.git\nrm -rf /",
    'not a url',
] as $badUrl) {
    try {
        ProjectGit::validateRemoteUrl($badUrl);
    } catch (ProjectGitException $e) {
        $rejected++;
    }
}
assert_true('rejects non-git-transport remote URLs', $rejected === 4);
assert_true('accepts https remote', ProjectGit::validateRemoteUrl('https://github.com/o/r.git') === 'https://github.com/o/r.git');
assert_true('accepts scp-style ssh remote', ProjectGit::validateRemoteUrl('git@github.com:o/r.git') === 'git@github.com:o/r.git');

$branchRejected = 0;
foreach (['--force', 'a b', 'feat/..\\evil'] as $badBranch) {
    try {
        ProjectGit::normalizeBranch($badBranch);
    } catch (ProjectGitException $e) {
        $branchRejected++;
    }
}
assert_true('rejects unsafe branch names', $branchRejected === 3);
assert_true('empty branch falls back to main', ProjectGit::normalizeBranch('') === 'main');
assert_true(
    'credentials are redacted from remote URLs',
    ProjectGit::redactUrl('https://user:ghp_secret@github.com/o/r.git') === 'https://***@github.com/o/r.git'
);

// ---------------------------------------------------------------------------
// Init + connect to a local bare repo
// ---------------------------------------------------------------------------

echo PHP_EOL . 'init and connect' . PHP_EOL;
$bare = $tmp . '/bare/project.git';
exec('git init --bare --quiet ' . escapeshellarg($bare) . ' 2>&1', $out, $code);
assert_true('bare repo created', $code === 0 && is_dir($bare));

$projectA = $tmp . '/a/project';
$git = new ProjectGit($projectA);
$git->setIdentity('Birkly Test', 'test@birkly.local');
$git->initialize('main');

assert_true('repository initialized', $git->isInitialized());
assert_true('gitignore written on init', is_file($projectA . '/.gitignore'));
assert_true('gitignore blocks php', str_contains((string) file_get_contents($projectA . '/.gitignore'), '*.php'));
assert_true('gitignore blocks cms content', str_contains((string) file_get_contents($projectA . '/.gitignore'), '/content/'));

$git->setRemote($bare);
assert_true('remote recorded', $git->remoteUrl() === $bare);

$status = $git->status('main');
assert_true('status reports connected', $status['connected'] === true);
assert_true('status reports branch', $status['branch'] === 'main');
assert_true('status has no remote branch yet', $status['has_remote_branch'] === false);

// ---------------------------------------------------------------------------
// Commit + push allowlisted files only
// ---------------------------------------------------------------------------

echo PHP_EOL . 'commit and push' . PHP_EOL;
$fsA = new ProjectSiteFileSystem($projectA);
$fsA->writeFile('index.html', '<h1>Birkly</h1>');
$fsA->writeFile('css/style.css', 'body{color:#222}');
// Written straight to disk: the sandbox refuses these, and git must too.
file_put_contents($projectA . '/secret.php', '<?php echo getenv("SECRET");');
@mkdir($projectA . '/.private', 0755, true);
file_put_contents($projectA . '/.private/token.txt', 'ghp_supersecret');

$changes = $git->allowlistedChanges();
$changed = array_column($changes, 'path');
assert_true('status lists allowlisted html', in_array('index.html', $changed, true));
assert_true('status lists allowlisted css', in_array('css/style.css', $changed, true));
assert_true('status hides php files', !in_array('secret.php', $changed, true));
assert_true('status hides dot directories', !in_array('.private/token.txt', $changed, true));

$git->stageAllowlisted();
$sha = $git->commit('Initial project files');
assert_true('commit produced a sha', is_string($sha) && $sha !== '');

$tracked = $git->trackedPaths();
assert_true('tracked includes index.html', in_array('index.html', $tracked, true));
assert_true('tracked includes css/style.css', in_array('css/style.css', $tracked, true));
assert_true('tracked includes .gitignore', in_array('.gitignore', $tracked, true));
assert_true('php file never tracked', !in_array('secret.php', $tracked, true));
assert_true('dot directory never tracked', !in_array('.private/token.txt', $tracked, true));

$git->push('main');
exec('git --git-dir=' . escapeshellarg($bare) . ' ls-tree -r --name-only main 2>&1', $remoteFiles, $lsCode);
assert_true('push reached the bare repo', $lsCode === 0);
assert_true('remote has index.html', in_array('index.html', $remoteFiles, true));
assert_true('remote has no php file', !in_array('secret.php', $remoteFiles, true));
assert_true('remote has no dot directory file', !in_array('.private/token.txt', $remoteFiles, true));

$git->fetch();
$status = $git->status('main');
assert_true('after push nothing is ahead', $status['ahead'] === 0);
assert_true('after push nothing is behind', $status['behind'] === 0);
assert_true('after push the tree is clean', $status['dirty'] === 0);
assert_true('last commit is reported', is_array($status['last_commit']) && $status['last_commit']['message'] === 'Initial project files');

// ---------------------------------------------------------------------------
// Pull into a second worktree
// ---------------------------------------------------------------------------

echo PHP_EOL . 'pull' . PHP_EOL;
$projectB = $tmp . '/b/project';
$gitB = new ProjectGit($projectB);
$gitB->setIdentity('Birkly Test B', 'test-b@birkly.local');
$gitB->initialize('main');
$gitB->setRemote($bare);
$gitB->fetch();

$preview = $gitB->pullPreview('main');
$previewPaths = array_column($preview, 'path');
assert_true('preview lists incoming index.html', in_array('index.html', $previewPaths, true));
assert_true('preview lists incoming stylesheet', in_array('css/style.css', $previewPaths, true));

$gitB->pullApply('main');
assert_true('pull wrote index.html', is_file($projectB . '/index.html'));
assert_true('pull wrote css/style.css', is_file($projectB . '/css/style.css'));
assert_true('pull content matches', trim((string) file_get_contents($projectB . '/index.html')) === '<h1>Birkly</h1>');
assert_true('pull did not create php files', !is_file($projectB . '/secret.php'));

// Round trip: B edits, pushes; A pulls.
$fsB = new ProjectSiteFileSystem($projectB);
$fsB->writeFile('about.html', '<p>About</p>');
$gitB->stageAllowlisted();
$gitB->commit('Add about page');
$gitB->push('main');

$git->fetch();
$status = $git->status('main');
assert_true('A sees one incoming commit', $status['behind'] === 1);
$git->pullApply('main');
assert_true('A received about.html', is_file($projectA . '/about.html'));

// ---------------------------------------------------------------------------
// Untracking: a file that leaves the allowlist is removed from the repo
// ---------------------------------------------------------------------------

echo PHP_EOL . 'allowlist enforcement on later commits' . PHP_EOL;
unlink($projectA . '/about.html');
$git->stageAllowlisted();
$git->commit('Remove about page');
$git->push('main');
$remoteFiles = [];
exec('git --git-dir=' . escapeshellarg($bare) . ' ls-tree -r --name-only main 2>&1', $remoteFiles);
assert_true('deleted file removed from remote', !in_array('about.html', $remoteFiles, true));
assert_true('remaining files still tracked', in_array('index.html', $remoteFiles, true));

// ---------------------------------------------------------------------------
// Conflicts — both sides changed the same file
// ---------------------------------------------------------------------------

echo PHP_EOL . 'conflicts' . PHP_EOL;
$gitB->fetch();
$gitB->pullApply('main');
$fsB->writeFile('index.html', '<h1>Remote wins</h1>');
$gitB->stageAllowlisted();
$gitB->commit('Remote edit');
$gitB->push('main');

$fsA->writeFile('index.html', '<h1>Birkly wins</h1>');
$git->fetch();
$status = $git->status('main');
assert_true('conflict: local is dirty', $status['dirty'] === 1);
assert_true('conflict: remote is ahead', $status['behind'] === 1);

$ffFailed = false;
try {
    $git->pullApply('main');
} catch (ProjectGitException $e) {
    $ffFailed = true;
}
assert_true('dirty fast-forward is not silently applied', $ffFailed || file_get_contents($projectA . '/index.html') === '<h1>Birkly wins</h1>');

$git->resolveConflict('keep_birkly', 'main');
assert_true('keep Birkly preserves local content', trim((string) file_get_contents($projectA . '/index.html')) === '<h1>Birkly wins</h1>');
$git->push('main');
exec('git --git-dir=' . escapeshellarg($bare) . ' show main:index.html 2>&1', $remoteIndex, $showCode);
assert_true('keep Birkly result is what the remote now has', $showCode === 0 && trim(implode("\n", $remoteIndex)) === '<h1>Birkly wins</h1>');

// Keep remote on the other worktree.
$fsB->writeFile('index.html', '<h1>Local B draft</h1>');
$fsB->writeFile('scratch.html', '<p>local only</p>');
$gitB->fetch();
$gitB->resolveConflict('keep_remote', 'main');
assert_true('keep remote replaces local content', trim((string) file_get_contents($projectB . '/index.html')) === '<h1>Birkly wins</h1>');
assert_true('keep remote drops local-only files', !is_file($projectB . '/scratch.html'));

// ---------------------------------------------------------------------------
// Sandbox: nothing outside project/ is ever involved
// ---------------------------------------------------------------------------

echo PHP_EOL . 'sandbox' . PHP_EOL;
@mkdir($tmp . '/a/content', 0755, true);
file_put_contents($tmp . '/a/content/entries.json', '{"secret":true}');
file_put_contents($tmp . '/a/config.php', '<?php $db = "prod";');

$git->stageAllowlisted();
$tracked = $git->trackedPaths();
$outside = array_filter($tracked, static fn(string $p): bool => str_starts_with($p, '..') || str_contains($p, 'config.php') || str_contains($p, 'content/'));
assert_true('nothing outside project/ is tracked', $outside === []);
assert_true('git dir lives inside project/', $git->gitDir() === realpath($projectA) . '/.git');

// ---------------------------------------------------------------------------
// HTTP deny for .git
// ---------------------------------------------------------------------------

echo PHP_EOL . 'http deny' . PHP_EOL;
assert_true('denies /project/.git/config', birkly_is_git_metadata_path('/project/.git/config'));
assert_true('denies /.git', birkly_is_git_metadata_path('/.git'));
assert_true('denies nested .git', birkly_is_git_metadata_path('/project/sub/.git/HEAD'));
assert_true('denies percent-encoded .git', birkly_is_git_metadata_path('/project/%2Egit/config'));
assert_true('allows normal project files', !birkly_is_git_metadata_path('/project/index.html'));
assert_true('allows filenames containing git', !birkly_is_git_metadata_path('/project/gitignore.html'));

rrmdir($tmp);

// ---------------------------------------------------------------------------
// Credential storage — encrypted at rest, never echoed back to the browser
// ---------------------------------------------------------------------------

echo PHP_EOL . 'credential storage' . PHP_EOL;
require_once dirname(__DIR__) . '/core/project_git_settings.php';

$settingsFile = dirname(__DIR__) . '/settings/project_git.json';
$settingsBackup = is_file($settingsFile) ? file_get_contents($settingsFile) : null;

try {
    birkly_project_git_delete();
    birkly_project_git_write(array_merge(
        birkly_project_git_credential_patch('token', 'ghp_supersecrettoken1234', '', ''),
        [
            'provider' => 'github',
            'remote_url' => 'https://github.com/acme/site.git',
            'branch' => 'main',
        ]
    ));

    $raw = birkly_project_git_read();
    assert_true('token is not stored in plaintext', !str_contains(json_encode($raw), 'ghp_supersecrettoken1234'));
    assert_true('token ciphertext is stored', !empty($raw['token_encrypted']));

    $credentials = birkly_project_git_credentials();
    assert_true('token decrypts back to the original', ($credentials['token'] ?? '') === 'ghp_supersecrettoken1234');
    assert_true('auth mode round-trips', ($credentials['mode'] ?? '') === 'token');

    $public = birkly_project_git_public_config();
    assert_true('public config reports configured', $public['configured'] === true);
    assert_true('public config never carries the token', !str_contains(json_encode($public), 'ghp_supersecrettoken1234'));
    assert_true('public config exposes a masked hint', $public['token_hint'] === '••••1234');
    assert_true('public config keeps the branch', $public['branch'] === 'main');

    // Saving again without re-typing the token keeps the stored one.
    birkly_project_git_write(birkly_project_git_credential_patch('token', '', '', 'deploy'));
    assert_true('empty token keeps the stored credential', (birkly_project_git_credentials()['token'] ?? '') === 'ghp_supersecrettoken1234');

    // Switching to SSH clears the token.
    birkly_project_git_write(birkly_project_git_credential_patch('ssh', '', "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n", ''));
    $public = birkly_project_git_public_config();
    assert_true('switching to ssh drops the token', $public['has_token'] === false);
    assert_true('ssh key is stored', $public['has_ssh_key'] === true);
    assert_true('ssh key decrypts', str_contains((string) (birkly_project_git_credentials()['ssh_key'] ?? ''), 'BEGIN OPENSSH'));

    birkly_project_git_delete();
    $public = birkly_project_git_public_config();
    assert_true('disconnect clears the remote', $public['configured'] === false);
    assert_true('disconnect clears the token', $public['has_token'] === false);
    assert_true('disconnect clears the ssh key', $public['has_ssh_key'] === false);
} finally {
    if ($settingsBackup !== null) {
        file_put_contents($settingsFile, $settingsBackup);
    } elseif (is_file($settingsFile)) {
        unlink($settingsFile);
    }
}

// ---------------------------------------------------------------------------
// Conflict detection — no silent overwrite in either direction
// ---------------------------------------------------------------------------

echo PHP_EOL . 'conflict detection' . PHP_EOL;

$clean = ['dirty' => 0, 'ahead' => 0, 'behind' => 0, 'conflicts' => [], 'changes' => []];
assert_true('clean tree is not a conflict', birkly_project_git_conflict($clean, [], 'main') === null);

$aheadOnly = array_merge($clean, ['ahead' => 2, 'changes' => [['path' => 'index.html', 'state' => 'modified']], 'dirty' => 1]);
assert_true('local-only changes are not a conflict', birkly_project_git_conflict($aheadOnly, [], 'main') === null);

$behindOnly = array_merge($clean, ['behind' => 3]);
assert_true(
    'remote-only changes are not a conflict',
    birkly_project_git_conflict($behindOnly, [['path' => 'index.html', 'state' => 'modified']], 'main') === null
);

$bothChanged = array_merge($clean, [
    'behind' => 1,
    'dirty' => 2,
    'changes' => [['path' => 'index.html', 'state' => 'modified'], ['path' => 'new.html', 'state' => 'added']],
]);
$conflict = birkly_project_git_conflict($bothChanged, [['path' => 'index.html', 'state' => 'modified']], 'main');
assert_true('both sides changed is a conflict', is_array($conflict));
assert_true('conflict reason is both_changed', ($conflict['reason'] ?? '') === 'both_changed');
assert_true('conflict lists the overlapping file', ($conflict['overlapping_files'] ?? []) === ['index.html']);
assert_true('conflict lists local files', in_array('new.html', $conflict['local_files'] ?? [], true));

$diverged = array_merge($clean, ['ahead' => 1, 'behind' => 1]);
assert_true('diverged history is a conflict', (birkly_project_git_conflict($diverged, [], 'main')['reason'] ?? '') === 'diverged');

$unmerged = array_merge($clean, ['conflicts' => ['index.html']]);
assert_true('unfinished merge is a conflict', (birkly_project_git_conflict($unmerged, [], 'main')['reason'] ?? '') === 'unmerged');

// ---------------------------------------------------------------------------
// Admin API requires a session
// ---------------------------------------------------------------------------

echo PHP_EOL . 'api auth' . PHP_EOL;
$probe = project_git_http_probe(dirname(__DIR__));
if ($probe === null) {
    echo '  unauthenticated rejection: SKIP (could not start php dev server)' . PHP_EOL;
} else {
    assert_true('unauthenticated status → 401', $probe['status'] === 401);
    assert_true('unauthenticated connect → 401', $probe['connect'] === 401);
    assert_true('unauthenticated push → 401', $probe['push'] === 401);
    assert_true('unauthenticated pull_apply → 401', $probe['pull_apply'] === 401);
    assert_true('unauthenticated resolve_conflict → 401', $probe['resolve'] === 401);
    assert_true('.git over HTTP → 403', $probe['git_dir'] === 403);
}

/**
 * @return array<string, int>|null
 */
function project_git_http_probe(string $root): ?array
{
    if (!function_exists('proc_open')) {
        return null;
    }

    $host = '127.0.0.1';
    $port = 8600 + random_int(0, 300);
    $cmd = escapeshellarg(PHP_BINARY ?: 'php') . ' -S ' . $host . ':' . $port . ' ' . escapeshellarg('server-router.php');
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $descriptors, $pipes, $root);
    if (!is_resource($proc)) {
        return null;
    }

    $up = false;
    for ($i = 0; $i < 40; $i++) {
        usleep(100000);
        $conn = @fsockopen($host, $port, $errno, $errstr, 0.2);
        if ($conn) {
            fclose($conn);
            $up = true;
            break;
        }
    }

    $result = null;
    if ($up) {
        $base = 'http://' . $host . ':' . $port;
        $result = [
            'status' => project_git_http_code($base . '/api/project_git.php?action=status', null),
            'connect' => project_git_http_code($base . '/api/project_git.php?action=connect', ['action' => 'connect']),
            'push' => project_git_http_code($base . '/api/project_git.php?action=push', ['action' => 'push']),
            'pull_apply' => project_git_http_code($base . '/api/project_git.php?action=pull_apply', ['action' => 'pull_apply']),
            'resolve' => project_git_http_code($base . '/api/project_git.php?action=resolve_conflict', ['action' => 'resolve_conflict']),
            'git_dir' => project_git_http_code($base . '/project/.git/config', null),
        ];
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_terminate($proc);
    proc_close($proc);

    return $result;
}

/**
 * @param array<string, mixed>|null $body
 */
function project_git_http_code(string $url, ?array $body): int
{
    $http = ['ignore_errors' => true, 'timeout' => 5];
    if ($body !== null) {
        $http['method'] = 'POST';
        $http['header'] = "Content-Type: application/json\r\n";
        $http['content'] = json_encode($body);
    }
    $response = @file_get_contents($url, false, stream_context_create(['http' => $http]));
    if ($response === false) {
        return 0;
    }
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) === 1) {
        return (int) $m[1];
    }
    return 0;
}

echo PHP_EOL . 'project_git_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
