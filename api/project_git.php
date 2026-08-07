<?php
/**
 * Admin API for project/ git sync (P59-M3).
 *
 * Only the files under project/ are synced. Credentials live encrypted in
 * settings/project_git.json and never reach the browser or the repository.
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/project_site.php';
require_once __DIR__ . '/../core/project_ssr.php';
require_once __DIR__ . '/../core/project_git.php';
require_once __DIR__ . '/../core/project_git_settings.php';

set_cors_headers('admin');
haru_secure_session_start();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

$action = trim((string) ($_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? 'status'));

function birkly_project_git_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function birkly_project_git_error(string $message, int $httpCode = 200): void
{
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    birkly_project_git_json(['status' => 'error', 'message' => $message]);
}

/**
 * Combined capability + connection + repository state for the Git section.
 *
 * @return array<string, mixed>
 */
function birkly_project_git_state(): array
{
    $capability = ProjectGit::capability();
    $config = birkly_project_git_public_config();

    $state = [
        'capability' => $capability,
        'config' => $config,
        'repository' => null,
    ];

    if (!$capability['available']) {
        return $state;
    }

    try {
        $git = birkly_project_git_engine();
        $state['repository'] = $git->status((string) $config['branch']);
    } catch (ProjectGitException $e) {
        $state['repository'] = null;
        $state['error'] = $e->getMessage();
    }

    return $state;
}

/**
 * @return array{git: ProjectGit, branch: string}
 */
function birkly_project_git_require_connection(): array
{
    $config = birkly_project_git_public_config();
    if (!$config['configured']) {
        birkly_project_git_error('Connect a repository first.');
    }

    $stored = birkly_project_git_read();
    $branch = ProjectGit::normalizeBranch((string) ($stored['branch'] ?? ProjectGit::DEFAULT_BRANCH));

    $git = birkly_project_git_engine();
    if (!$git->isInitialized()) {
        $git->initialize($branch);
    }
    $git->setRemote((string) ($stored['remote_url'] ?? ''));

    return ['git' => $git, 'branch' => $branch];
}

if ($action === 'capability') {
    requireAuth('read_settings');
    birkly_project_git_json([
        'status' => 'ok',
        'data' => ['capability' => ProjectGit::capability()],
    ]);
}

if ($action === 'status') {
    requireAuth('read_settings');
    birkly_project_git_json(['status' => 'ok', 'data' => birkly_project_git_state()]);
}

requireAuth('manage_settings');

$csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');
if (!validate_csrf_token($csrf)) {
    log_security_event('permission_denied', ['action' => 'project_git_' . $action, 'reason' => 'invalid CSRF']);
    birkly_project_git_error('Invalid CSRF token');
}

$capability = ProjectGit::capability();
if (!$capability['available']) {
    birkly_project_git_error(match ($capability['reason']) {
        'git_missing' => 'git is not installed on this server. Install git and reload this page.',
        'proc_open_disabled' => 'This server blocks PHP from running external programs (proc_open is disabled), so git sync cannot run.',
        'project_not_writable' => 'The project/ folder is not writable by the web server.',
        default => 'Git sync is not available on this server.',
    });
}

if ($action === 'connect') {
    $provider = birkly_project_git_normalize_provider((string) ($input['provider'] ?? 'generic'));
    $authMode = birkly_project_git_normalize_auth_mode((string) ($input['auth_mode'] ?? 'none'));
    $token = trim((string) ($input['token'] ?? ''));
    $sshKey = trim((string) ($input['ssh_key'] ?? ''));
    $username = trim((string) ($input['auth_username'] ?? ''));

    try {
        $remoteUrl = ProjectGit::validateRemoteUrl((string) ($input['remote_url'] ?? ''));
        $branch = ProjectGit::normalizeBranch((string) ($input['branch'] ?? ProjectGit::DEFAULT_BRANCH));
    } catch (ProjectGitException $e) {
        birkly_project_git_error($e->getMessage());
        exit;
    }

    $existing = birkly_project_git_read();
    if ($authMode === 'token' && $token === '' && empty($existing['token_encrypted'])) {
        birkly_project_git_error('A personal access token is required for token authentication.');
    }
    if ($authMode === 'ssh' && $sshKey === '' && empty($existing['ssh_key_encrypted'])) {
        birkly_project_git_error('An SSH private key is required for SSH authentication.');
    }

    $patch = array_merge(
        birkly_project_git_credential_patch($authMode, $token, $sshKey, $username),
        [
            'provider' => $provider,
            'remote_url' => $remoteUrl,
            'branch' => $branch,
            'connected_at' => date('c'),
            'connected_by' => birkly_project_git_current_user(),
        ]
    );

    if (!birkly_project_git_write($patch)) {
        birkly_project_git_error('Could not save git settings');
    }

    try {
        $git = birkly_project_git_engine();
        $git->initialize($branch);
        $git->setRemote($remoteUrl);
        $git->testConnection();
    } catch (ProjectGitException $e) {
        birkly_project_git_json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'data' => birkly_project_git_state(),
        ]);
    }

    log_security_event('project_git_connected', [
        'provider' => $provider,
        'remote' => ProjectGit::redactUrl($remoteUrl),
        'branch' => $branch,
        'auth_mode' => $authMode,
        'source' => 'admin',
    ]);

    birkly_project_git_json([
        'status' => 'ok',
        'message' => 'Connected to the remote repository',
        'data' => birkly_project_git_state(),
    ]);
}

if ($action === 'test') {
    $config = birkly_project_git_public_config();
    if (!$config['configured']) {
        birkly_project_git_error('Connect a repository first.');
    }

    try {
        $git = birkly_project_git_engine();
        if (!$git->isInitialized()) {
            $git->initialize((string) $config['branch']);
        }
        $stored = birkly_project_git_read();
        $git->setRemote((string) ($stored['remote_url'] ?? ''));
        $git->testConnection();
    } catch (ProjectGitException $e) {
        birkly_project_git_error($e->getMessage());
    }

    birkly_project_git_json([
        'status' => 'ok',
        'message' => 'Connection works',
        'data' => birkly_project_git_state(),
    ]);
}

if ($action === 'disconnect') {
    try {
        $git = birkly_project_git_engine();
        $git->removeRemote();
    } catch (ProjectGitException $e) {
        // Clearing credentials must succeed even when the worktree is broken.
    }

    birkly_project_git_delete();

    log_security_event('project_git_disconnected', ['source' => 'admin']);

    birkly_project_git_json([
        'status' => 'ok',
        'message' => 'Disconnected. Credentials and the remote have been cleared; local git history is kept.',
        'data' => birkly_project_git_state(),
    ]);
}

if ($action === 'push') {
    ['git' => $git, 'branch' => $branch] = birkly_project_git_require_connection();
    $message = trim((string) ($input['message'] ?? ''));

    try {
        $git->stageAllowlisted();
        $sha = $git->commit($message);
        $git->fetch();
        $repository = $git->status($branch);

        $conflict = birkly_project_git_conflict($repository, $git->pullPreview($branch), $branch);
        if ($conflict !== null) {
            birkly_project_git_json([
                'status' => 'ok',
                'message' => $conflict['message'],
                'data' => array_merge(birkly_project_git_state(), ['conflict' => $conflict]),
            ]);
        }

        if ($sha === null && (int) $repository['ahead'] === 0) {
            birkly_project_git_json([
                'status' => 'ok',
                'message' => 'Nothing to push — the remote already matches project/.',
                'data' => birkly_project_git_state(),
            ]);
        }

        $git->push($branch);
    } catch (ProjectGitException $e) {
        birkly_project_git_error($e->getMessage());
        exit;
    }

    $commit = $git->lastCommit();
    birkly_project_git_record_event('last_push', [
        'sha' => $commit['sha'] ?? null,
        'branch' => $branch,
    ]);

    log_security_event('project_git_pushed', [
        'branch' => $branch,
        'sha' => $commit['sha'] ?? null,
        'source' => 'admin',
    ]);

    birkly_project_git_json([
        'status' => 'ok',
        'message' => 'Pushed project files to ' . $branch . '.',
        'data' => birkly_project_git_state(),
    ]);
}

if ($action === 'pull_preview') {
    ['git' => $git, 'branch' => $branch] = birkly_project_git_require_connection();

    try {
        $git->fetch();
        $repository = $git->status($branch);
        $incoming = $git->pullPreview($branch);
    } catch (ProjectGitException $e) {
        birkly_project_git_error($e->getMessage());
        exit;
    }

    birkly_project_git_json([
        'status' => 'ok',
        'data' => array_merge(birkly_project_git_state(), [
            'incoming' => $incoming,
            'conflict' => birkly_project_git_conflict($repository, $incoming, $branch),
        ]),
    ]);
}

if ($action === 'pull_apply') {
    ['git' => $git, 'branch' => $branch] = birkly_project_git_require_connection();

    try {
        $git->fetch();
        $repository = $git->status($branch);
        $incoming = $git->pullPreview($branch);

        $conflict = birkly_project_git_conflict($repository, $incoming, $branch);
        if ($conflict !== null) {
            birkly_project_git_json([
                'status' => 'ok',
                'message' => $conflict['message'],
                'data' => array_merge(birkly_project_git_state(), ['conflict' => $conflict]),
            ]);
        }

        if ((int) $repository['behind'] === 0) {
            birkly_project_git_json([
                'status' => 'ok',
                'message' => 'Already up to date with ' . $branch . '.',
                'data' => birkly_project_git_state(),
            ]);
        }

        $git->pullApply($branch);
    } catch (ProjectGitException $e) {
        birkly_project_git_error($e->getMessage());
        exit;
    }

    birkly_project_ssr_invalidate_all();

    $commit = $git->lastCommit();
    birkly_project_git_record_event('last_pull', [
        'sha' => $commit['sha'] ?? null,
        'branch' => $branch,
        'files' => count($incoming),
    ]);

    log_security_event('project_git_pulled', [
        'branch' => $branch,
        'sha' => $commit['sha'] ?? null,
        'files' => count($incoming),
        'source' => 'admin',
    ]);

    birkly_project_git_json([
        'status' => 'ok',
        'message' => 'Pulled ' . count($incoming) . ' file' . (count($incoming) === 1 ? '' : 's') . ' from ' . $branch . '.',
        'data' => birkly_project_git_state(),
    ]);
}

if ($action === 'resolve_conflict') {
    $strategy = (string) ($input['strategy'] ?? '');
    if (!in_array($strategy, ['keep_birkly', 'keep_remote'], true)) {
        birkly_project_git_error('Choose Keep Birkly or Keep remote.');
    }

    ['git' => $git, 'branch' => $branch] = birkly_project_git_require_connection();
    $message = trim((string) ($input['message'] ?? ''));

    try {
        $git->fetch();
        $incoming = $git->pullPreview($branch);
        $git->resolveConflict($strategy, $branch, $message);

        // Keep Birkly means the remote must end up matching project/, so the
        // resolution is only complete once it is pushed.
        if ($strategy === 'keep_birkly') {
            $git->push($branch);
        }
    } catch (ProjectGitException $e) {
        birkly_project_git_error($e->getMessage());
        exit;
    }

    if ($strategy === 'keep_remote') {
        birkly_project_ssr_invalidate_all();
    }

    $commit = $git->lastCommit();
    birkly_project_git_record_event($strategy === 'keep_birkly' ? 'last_push' : 'last_pull', [
        'sha' => $commit['sha'] ?? null,
        'branch' => $branch,
        'resolved' => $strategy,
    ]);

    log_security_event('project_git_conflict_resolved', [
        'strategy' => $strategy,
        'branch' => $branch,
        'sha' => $commit['sha'] ?? null,
        'files' => count($incoming),
        'source' => 'admin',
    ]);

    birkly_project_git_json([
        'status' => 'ok',
        'message' => $strategy === 'keep_birkly'
            ? 'Kept the Birkly project files and pushed them to ' . $branch . '.'
            : 'Replaced project/ with the remote version of ' . $branch . '.',
        'data' => birkly_project_git_state(),
    ]);
}

birkly_project_git_error('Invalid action');
