<?php
/**
 * Project git connection settings (settings/project_git.json) — P59-M3.
 *
 * Credentials are encrypted at rest and never written into project/, so they
 * can never be committed or served with the site.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/secret_box.php';
require_once __DIR__ . '/project_git.php';

const BIRKLY_PROJECT_GIT_SETTINGS_FILE = 'project_git.json';

/** @var list<string> */
const BIRKLY_PROJECT_GIT_PROVIDERS = [
    'github',
    'gitlab',
    'bitbucket',
    'codeberg',
    'gitea',
    'forgejo',
    'sourcehut',
    'buzz',
    'generic',
];

function birkly_project_git_storage(): Storage
{
    return new Storage(birkly_project_root() . '/settings');
}

/**
 * Raw stored record, including encrypted fields.
 *
 * @return array<string, mixed>
 */
function birkly_project_git_read(): array
{
    $data = birkly_project_git_storage()->read(BIRKLY_PROJECT_GIT_SETTINGS_FILE);
    return is_array($data) ? $data : [];
}

/**
 * @param array<string, mixed> $patch
 */
function birkly_project_git_write(array $patch): bool
{
    $existing = birkly_project_git_read();
    $updated = array_merge($existing, $patch);
    $updated['updated_at'] = date('c');

    return birkly_project_git_storage()->write(BIRKLY_PROJECT_GIT_SETTINGS_FILE, $updated);
}

function birkly_project_git_delete(): bool
{
    return birkly_project_git_storage()->write(BIRKLY_PROJECT_GIT_SETTINGS_FILE, []);
}

/**
 * Connection settings safe to send to the browser — no secrets, only whether
 * they exist and a short hint so the user can recognise the stored token.
 *
 * @return array<string, mixed>
 */
function birkly_project_git_public_config(): array
{
    $record = birkly_project_git_read();
    $remote = (string) ($record['remote_url'] ?? '');

    return [
        'configured' => $remote !== '',
        'provider' => birkly_project_git_normalize_provider((string) ($record['provider'] ?? 'generic')),
        'remote_url' => $remote === '' ? '' : ProjectGit::redactUrl($remote),
        'branch' => (string) ($record['branch'] ?? ProjectGit::DEFAULT_BRANCH),
        'auth_mode' => birkly_project_git_normalize_auth_mode((string) ($record['auth_mode'] ?? 'none')),
        'auth_username' => (string) ($record['auth_username'] ?? ''),
        'has_token' => !empty($record['token_encrypted']),
        'has_ssh_key' => !empty($record['ssh_key_encrypted']),
        'has_nostr_key' => !empty($record['nostr_key_encrypted']),
        'token_hint' => (string) ($record['token_hint'] ?? ''),
        'nostr_hint' => (string) ($record['nostr_hint'] ?? ''),
        'connected_at' => $record['connected_at'] ?? null,
        'connected_by' => $record['connected_by'] ?? null,
        'last_push' => $record['last_push'] ?? null,
        'last_pull' => $record['last_pull'] ?? null,
    ];
}

/**
 * Human labels + connect hints for first-class providers.
 *
 * @return array<string, array{label: string, group: string, placeholder: string, help: string, default_auth: string, default_username: string}>
 */
function birkly_project_git_provider_meta(): array
{
    return [
        'github' => [
            'label' => 'GitHub',
            'group' => 'Popular',
            'placeholder' => 'https://github.com/your-org/your-site.git',
            'help' => 'Use a fine-grained or classic personal access token with repository contents read/write.',
            'default_auth' => 'token',
            'default_username' => 'git',
        ],
        'gitlab' => [
            'label' => 'GitLab',
            'group' => 'Popular',
            'placeholder' => 'https://gitlab.com/your-group/your-site.git',
            'help' => 'Works with gitlab.com or self-hosted GitLab. Use a project or personal access token (api or write_repository).',
            'default_auth' => 'token',
            'default_username' => 'oauth2',
        ],
        'bitbucket' => [
            'label' => 'Bitbucket',
            'group' => 'Popular',
            'placeholder' => 'https://bitbucket.org/your-workspace/your-site.git',
            'help' => 'Use an Atlassian app password or repository access token. Username is usually your Bitbucket username (or x-token-auth for access tokens).',
            'default_auth' => 'token',
            'default_username' => 'x-token-auth',
        ],
        'codeberg' => [
            'label' => 'Codeberg',
            'group' => 'Open source & European',
            'placeholder' => 'https://codeberg.org/your-org/your-site.git',
            'help' => 'European, free Forgejo hosting. Create an access token under Settings → Applications.',
            'default_auth' => 'token',
            'default_username' => 'git',
        ],
        'gitea' => [
            'label' => 'Gitea',
            'group' => 'Open source & European',
            'placeholder' => 'https://gitea.example.com/your-org/your-site.git',
            'help' => 'Self-hosted Gitea. Use an access token with write:repository scope.',
            'default_auth' => 'token',
            'default_username' => 'git',
        ],
        'forgejo' => [
            'label' => 'Forgejo',
            'group' => 'Open source & European',
            'placeholder' => 'https://forgejo.example.com/your-org/your-site.git',
            'help' => 'Self-hosted Forgejo (Codeberg’s soft-fork lineage). Token auth matches Gitea.',
            'default_auth' => 'token',
            'default_username' => 'git',
        ],
        'sourcehut' => [
            'label' => 'SourceHut',
            'group' => 'Open source & European',
            'placeholder' => 'https://git.sr.ht/~you/your-site',
            'help' => 'Open-source git hosting (sr.ht). Use an OAuth2 personal access token as the password.',
            'default_auth' => 'token',
            'default_username' => 'git',
        ],
        'buzz' => [
            'label' => 'Buzz',
            'group' => 'AI-native',
            'placeholder' => 'https://your-relay.example/git/<owner-pubkey>/<repo>',
            'help' => 'Block’s open-source Slack/GitHub alternative. Uses standard git Smart HTTP on the relay; auth is usually Nostr (NIP-98) via git-credential-nostr on this server.',
            'default_auth' => 'nostr',
            'default_username' => '',
        ],
        'generic' => [
            'label' => 'Other git remote',
            'group' => 'Other',
            'placeholder' => 'https://git.example.com/your-org/your-site.git',
            'help' => 'Any HTTPS or SSH remote that speaks standard git. Content, media, and plugins are never included.',
            'default_auth' => 'token',
            'default_username' => 'birkly',
        ],
    ];
}

function birkly_project_git_normalize_provider(string $provider): string
{
    $provider = strtolower(trim($provider));
    return in_array($provider, BIRKLY_PROJECT_GIT_PROVIDERS, true) ? $provider : 'generic';
}

function birkly_project_git_normalize_auth_mode(string $mode): string
{
    $mode = strtolower(trim($mode));
    return in_array($mode, ['none', 'token', 'ssh', 'nostr'], true) ? $mode : 'none';
}

function birkly_project_git_nostr_helper_available(): bool
{
    $path = ProjectGit::findExecutable('git-credential-nostr');
    return is_string($path) && $path !== '';
}

/**
 * Decrypted credentials for the engine. Never log or return this.
 *
 * @return array<string, mixed>
 */
function birkly_project_git_credentials(): array
{
    $record = birkly_project_git_read();
    $mode = birkly_project_git_normalize_auth_mode((string) ($record['auth_mode'] ?? 'none'));

    if ($mode === 'token' && !empty($record['token_encrypted'])) {
        $token = birkly_decrypt_secret((string) $record['token_encrypted']);
        return [
            'mode' => 'token',
            'token' => is_string($token) ? $token : '',
            'username' => (string) ($record['auth_username'] ?? 'birkly'),
        ];
    }

    if ($mode === 'ssh' && !empty($record['ssh_key_encrypted'])) {
        $key = birkly_decrypt_secret((string) $record['ssh_key_encrypted']);
        return [
            'mode' => 'ssh',
            'ssh_key' => is_string($key) ? $key : '',
        ];
    }

    if ($mode === 'nostr' && !empty($record['nostr_key_encrypted'])) {
        $key = birkly_decrypt_secret((string) $record['nostr_key_encrypted']);
        return [
            'mode' => 'nostr',
            'nostr_key' => is_string($key) ? $key : '',
        ];
    }

    return ['mode' => 'none'];
}

/**
 * Store a token/SSH key encrypted. Passing an empty value keeps whatever is
 * already stored, so the UI can save without re-typing the secret.
 *
 * @return array<string, mixed> Patch to merge into the record
 */
function birkly_project_git_credential_patch(
    string $authMode,
    string $token,
    string $sshKey,
    string $username,
    string $nostrKey = ''
): array {
    $mode = birkly_project_git_normalize_auth_mode($authMode);
    $patch = ['auth_mode' => $mode];

    if ($mode === 'token') {
        $patch['auth_username'] = $username !== '' ? $username : 'birkly';
        $patch['ssh_key_encrypted'] = null;
        $patch['nostr_key_encrypted'] = null;
        $patch['nostr_hint'] = '';
        if ($token !== '') {
            $patch['token_encrypted'] = birkly_encrypt_secret($token);
            $patch['token_hint'] = birkly_project_git_secret_hint($token);
        }
        return $patch;
    }

    if ($mode === 'ssh') {
        $patch['token_encrypted'] = null;
        $patch['token_hint'] = '';
        $patch['nostr_key_encrypted'] = null;
        $patch['nostr_hint'] = '';
        if ($sshKey !== '') {
            $patch['ssh_key_encrypted'] = birkly_encrypt_secret($sshKey);
        }
        return $patch;
    }

    if ($mode === 'nostr') {
        $patch['token_encrypted'] = null;
        $patch['token_hint'] = '';
        $patch['ssh_key_encrypted'] = null;
        $patch['auth_username'] = '';
        if ($nostrKey !== '') {
            $patch['nostr_key_encrypted'] = birkly_encrypt_secret($nostrKey);
            $patch['nostr_hint'] = birkly_project_git_secret_hint($nostrKey);
        }
        return $patch;
    }

    $patch['token_encrypted'] = null;
    $patch['token_hint'] = '';
    $patch['ssh_key_encrypted'] = null;
    $patch['nostr_key_encrypted'] = null;
    $patch['nostr_hint'] = '';
    return $patch;
}

function birkly_project_git_secret_hint(string $secret): string
{
    $secret = trim($secret);
    if (strlen($secret) < 4) {
        return '••••';
    }
    return '••••' . substr($secret, -4);
}

/**
 * Build an engine instance wired to the stored credentials and identity.
 */
function birkly_project_git_engine(?string $basePath = null): ProjectGit
{
    $git = new ProjectGit($basePath);
    $git->setAuth(birkly_project_git_credentials());

    $name = 'Birkly';
    $email = 'birkly@localhost';
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
        if (!empty($user['name'])) {
            $name = (string) $user['name'];
        }
        if (!empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $email = (string) $user['email'];
        }
    }
    $git->setIdentity($name, $email);

    return $git;
}

function birkly_project_git_current_user(): string
{
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
        $user = $_SESSION['user'];
        foreach (['name', 'email', 'id'] as $key) {
            if (!empty($user[$key]) && is_string($user[$key])) {
                return $user[$key];
            }
        }
    }
    return 'system';
}

/**
 * Detect a situation where applying one side would silently discard the other.
 *
 * @param array<string, mixed> $repository Result of ProjectGit::status()
 * @param list<array{path: string, state: string}> $incoming Result of ProjectGit::pullPreview()
 * @return array<string, mixed>|null Null when it is safe to push or fast-forward
 */
function birkly_project_git_conflict(array $repository, array $incoming, string $branch): ?array
{
    $unmerged = $repository['conflicts'] ?? [];
    $dirty = (int) ($repository['dirty'] ?? 0);
    $ahead = (int) ($repository['ahead'] ?? 0);
    $behind = (int) ($repository['behind'] ?? 0);

    $reason = null;
    if ($unmerged !== []) {
        $reason = 'unmerged';
    } elseif ($behind > 0 && $dirty > 0) {
        $reason = 'both_changed';
    } elseif ($behind > 0 && $ahead > 0) {
        $reason = 'diverged';
    }

    if ($reason === null) {
        return null;
    }

    $localPaths = array_column($repository['changes'] ?? [], 'path');
    $remotePaths = array_column($incoming, 'path');

    return [
        'reason' => $reason,
        'branch' => $branch,
        'message' => match ($reason) {
            'unmerged' => 'A previous merge is unfinished. Choose which side to keep.',
            'diverged' => 'Birkly and the remote both have new commits on ' . $branch . '. Choose which side to keep.',
            default => 'Both Birkly and the remote changed ' . $branch . '. Choose which side to keep.',
        },
        'local_files' => $localPaths,
        'remote_files' => $remotePaths,
        'overlapping_files' => array_values(array_intersect($localPaths, $remotePaths)),
        'unmerged_files' => $unmerged,
    ];
}

/**
 * @param array<string, mixed> $details
 */
function birkly_project_git_record_event(string $key, array $details): void
{
    birkly_project_git_write([
        $key => array_merge([
            'at' => date('c'),
            'by' => birkly_project_git_current_user(),
        ], $details),
    ]);
}
