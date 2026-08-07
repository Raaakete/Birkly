<?php
/**
 * Git sync engine for the project/ workspace (P59-M2).
 *
 * PHP orchestrates the system `git` binary. Only allowlisted files under
 * project/ are ever staged — CMS content, media, users, settings and plugins
 * stay out of the repository. No flags reach git from the UI: every command is
 * assembled here from a fixed argument list.
 */

declare(strict_types=1);

require_once __DIR__ . '/project_site.php';
require_once __DIR__ . '/project_site_filesystem.php';

class ProjectGitException extends RuntimeException
{
}

final class ProjectGit
{
    public const DEFAULT_BRANCH = 'main';

    /** git learned GIT_CONFIG_COUNT/KEY/VALUE in 2.31 — used to keep tokens off the command line. */
    private const ENV_CONFIG_MIN_VERSION = '2.31.0';

    private string $root;

    private ProjectSiteFileSystem $fs;

    /** @var array<string, mixed> */
    private array $auth = ['mode' => 'none'];

    /** @var array{name: string, email: string} */
    private array $identity = ['name' => 'Birkly', 'email' => 'birkly@localhost'];

    private ?string $tempHome = null;

    private ?string $tempKey = null;

    /** @var list<string> Values that must never appear in returned output. */
    private array $secrets = [];

    private static ?string $version = null;

    public function __construct(?string $basePath = null)
    {
        birkly_ensure_public_site_path();
        $base = $basePath ?? birkly_public_site_path();
        if (!is_dir($base) && !@mkdir($base, 0755, true)) {
            throw new ProjectGitException('Project directory is not available');
        }
        $real = realpath($base);
        if ($real === false) {
            throw new ProjectGitException('Project directory could not be resolved');
        }
        $this->root = $real;
        $this->fs = new ProjectSiteFileSystem($real);
    }

    public function __destruct()
    {
        $this->cleanupTempFiles();
    }

    public function rootPath(): string
    {
        return $this->root;
    }

    public function gitDir(): string
    {
        return $this->root . '/.git';
    }

    // ---------------------------------------------------------------------
    // Capability
    // ---------------------------------------------------------------------

    /**
     * @return array{available: bool, version: ?string, writable: bool, reason: ?string}
     */
    public static function capability(?string $basePath = null): array
    {
        if (!self::canSpawnProcesses()) {
            return [
                'available' => false,
                'version' => null,
                'writable' => false,
                'reason' => 'proc_open_disabled',
            ];
        }

        $version = self::gitVersion();
        if ($version === null) {
            return [
                'available' => false,
                'version' => null,
                'writable' => false,
                'reason' => 'git_missing',
            ];
        }

        $path = $basePath ?? birkly_public_site_path();
        $writable = is_dir($path) ? is_writable($path) : is_writable(dirname($path));

        return [
            'available' => $writable,
            'version' => $version,
            'writable' => $writable,
            'reason' => $writable ? null : 'project_not_writable',
            'nostr_helper' => self::findExecutable('git-credential-nostr') !== null,
            'providers' => array_keys(function_exists('birkly_project_git_provider_meta')
                ? birkly_project_git_provider_meta()
                : ['github' => true, 'gitlab' => true, 'generic' => true]),
        ];
    }

    /**
     * Resolve an executable on PATH (used for git-credential-nostr / Buzz).
     */
    public static function findExecutable(string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            return null;
        }

        $pathEnv = (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
        foreach (explode(PATH_SEPARATOR, $pathEnv) as $dir) {
            $dir = rtrim($dir, "/\\");
            if ($dir === '') {
                continue;
            }
            $candidate = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private static function canSpawnProcesses(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    public static function gitVersion(): ?string
    {
        if (self::$version !== null) {
            return self::$version === '' ? null : self::$version;
        }

        self::$version = '';
        if (!self::canSpawnProcesses()) {
            return null;
        }

        $result = self::exec(['git', '--version'], sys_get_temp_dir(), []);
        if ($result['exit'] !== 0) {
            return null;
        }
        if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $result['stdout'], $m) !== 1) {
            return null;
        }

        self::$version = $m[1];
        return self::$version;
    }

    // ---------------------------------------------------------------------
    // Credentials
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $auth mode: none|token|ssh|nostr (+ token / username / ssh_key / nostr_key)
     */
    public function setAuth(array $auth): void
    {
        $mode = (string) ($auth['mode'] ?? 'none');
        if (!in_array($mode, ['none', 'token', 'ssh', 'nostr'], true)) {
            $mode = 'none';
        }

        $this->auth = ['mode' => $mode];
        if ($mode === 'token') {
            $token = (string) ($auth['token'] ?? '');
            $this->auth['token'] = $token;
            $this->auth['username'] = (string) ($auth['username'] ?? 'birkly');
            if ($token !== '') {
                $this->secrets[] = $token;
            }
        } elseif ($mode === 'ssh') {
            $this->auth['ssh_key'] = (string) ($auth['ssh_key'] ?? '');
        } elseif ($mode === 'nostr') {
            $nostrKey = trim((string) ($auth['nostr_key'] ?? ''));
            $this->auth['nostr_key'] = $nostrKey;
            if ($nostrKey !== '') {
                $this->secrets[] = $nostrKey;
            }
        }
    }

    public function setIdentity(string $name, string $email): void
    {
        $name = trim($name);
        $email = trim($email);
        $this->identity = [
            'name' => $name !== '' ? $name : 'Birkly',
            'email' => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'birkly@localhost',
        ];
    }

    // ---------------------------------------------------------------------
    // Repository lifecycle
    // ---------------------------------------------------------------------

    public function isInitialized(): bool
    {
        return is_dir($this->gitDir());
    }

    public function initialize(string $branch = self::DEFAULT_BRANCH): void
    {
        $branch = self::normalizeBranch($branch);
        if (!$this->isInitialized()) {
            $this->mustRun(['init', '--quiet']);
        }
        // `git init -b` is 2.28+; setting HEAD directly works on every version.
        // Only safe before the first commit — afterwards it would move the
        // branch pointer without touching the worktree.
        if (!$this->refExists('HEAD')) {
            $this->run(['symbolic-ref', 'HEAD', 'refs/heads/' . $branch]);
        }
        $this->writeGitignore();
    }

    public function writeGitignore(): bool
    {
        $path = $this->root . '/.gitignore';
        if (is_file($path)) {
            return true;
        }
        return @file_put_contents($path, self::gitignoreTemplate()) !== false;
    }

    public static function gitignoreTemplate(): string
    {
        return <<<'GITIGNORE'
# Managed by Birkly — this repository holds the project/ website workspace only.
# CMS content, media, users, settings and plugins live outside project/ and are
# never synced. The rules below keep the repo clean if it is used elsewhere.

# Server-side code is never served from project/
*.php
*.phtml
*.phar

# Secrets and environment
.env
.env.*
*.pem
*.key
*.p12
config.php

# CMS data — belongs to the installation, not the repository
/content/
/media/
/users/
/settings/
/logs/
/cache/
/data/
/backups/

# OS and editor junk
.DS_Store
Thumbs.db
*.swp
*~
.idea/
.vscode/

GITIGNORE;
    }

    public function setRemote(string $url): void
    {
        $url = self::validateRemoteUrl($url);
        if ($this->remoteUrl() === null) {
            $this->mustRun(['remote', 'add', 'origin', $url]);
            return;
        }
        $this->mustRun(['remote', 'set-url', 'origin', $url]);
    }

    public function removeRemote(): void
    {
        if ($this->remoteUrl() !== null) {
            $this->run(['remote', 'remove', 'origin']);
        }
    }

    public function remoteUrl(): ?string
    {
        if (!$this->isInitialized()) {
            return null;
        }
        $result = $this->run(['remote', 'get-url', 'origin']);
        if ($result['exit'] !== 0) {
            return null;
        }
        $url = trim($result['stdout']);
        return $url === '' ? null : $url;
    }

    /**
     * Reject anything that is not a plain HTTP(S) or SSH remote so no git
     * transport helper (ext::, file://, …) can be driven from the UI.
     */
    public static function validateRemoteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new ProjectGitException('Remote URL is required');
        }
        if (strlen($url) > 2048 || preg_match('/[\s\x00-\x1f]/', $url) === 1) {
            throw new ProjectGitException('Remote URL contains invalid characters');
        }
        if (str_starts_with($url, '-')) {
            throw new ProjectGitException('Remote URL is not valid');
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        if (preg_match('#^ssh://[^/]+/#i', $url) === 1) {
            return $url;
        }
        // scp-like syntax: git@github.com:owner/repo.git
        if (preg_match('#^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+:[^:]+$#', $url) === 1) {
            return $url;
        }
        // Local path remotes are allowed so installs can sync to a mounted bare repo.
        if (str_starts_with($url, '/') || str_starts_with($url, 'file://')) {
            return $url;
        }

        throw new ProjectGitException('Remote URL must be an HTTPS or SSH git URL');
    }

    public static function normalizeBranch(string $branch): string
    {
        $branch = trim($branch);
        if ($branch === '') {
            return self::DEFAULT_BRANCH;
        }
        if (preg_match('/^[A-Za-z0-9._\/-]{1,255}$/', $branch) !== 1) {
            throw new ProjectGitException('Branch name contains invalid characters');
        }
        if (str_starts_with($branch, '-') || str_contains($branch, '..')) {
            throw new ProjectGitException('Branch name is not valid');
        }
        return $branch;
    }

    // ---------------------------------------------------------------------
    // Status
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function status(string $branch = self::DEFAULT_BRANCH): array
    {
        $branch = self::normalizeBranch($branch);
        $initialized = $this->isInitialized();
        $remote = $initialized ? $this->remoteUrl() : null;

        $status = [
            'initialized' => $initialized,
            'connected' => $remote !== null,
            'remote_url' => $remote !== null ? self::redactUrl($remote) : null,
            'branch' => $initialized ? ($this->currentBranch() ?? $branch) : $branch,
            'ahead' => 0,
            'behind' => 0,
            'dirty' => 0,
            'changes' => [],
            'conflicts' => [],
            'has_remote_branch' => false,
            'last_commit' => null,
        ];

        if (!$initialized) {
            return $status;
        }

        $changes = $this->allowlistedChanges();
        $status['changes'] = $changes;
        $status['dirty'] = count($changes);
        $status['conflicts'] = $this->unmergedPaths();
        $status['last_commit'] = $this->lastCommit();

        $remoteRef = 'refs/remotes/origin/' . $branch;
        if ($this->refExists($remoteRef)) {
            $status['has_remote_branch'] = true;
            [$behind, $ahead] = $this->aheadBehind($remoteRef);
            $status['behind'] = $behind;
            $status['ahead'] = $ahead;
        } elseif ($this->refExists('HEAD')) {
            $status['ahead'] = $this->commitCount('HEAD');
        }

        return $status;
    }

    public function currentBranch(): ?string
    {
        $result = $this->run(['symbolic-ref', '--short', 'HEAD']);
        if ($result['exit'] !== 0) {
            return null;
        }
        $branch = trim($result['stdout']);
        return $branch === '' ? null : $branch;
    }

    /**
     * Working-tree changes limited to files Birkly is allowed to sync.
     *
     * @return list<array{path: string, state: string}>
     */
    public function allowlistedChanges(): array
    {
        $result = $this->run(['status', '--porcelain=v1', '--untracked-files=all', '-z']);
        if ($result['exit'] !== 0) {
            return [];
        }

        $records = explode("\0", $result['stdout']);
        $changes = [];
        for ($i = 0; $i < count($records); $i++) {
            $record = $records[$i];
            if (strlen($record) < 4) {
                continue;
            }
            $code = substr($record, 0, 2);
            $path = substr($record, 3);
            // Renames/copies carry the source path in the next NUL record.
            if ($code[0] === 'R' || $code[0] === 'C') {
                $i++;
            }
            if (!$this->isSyncablePath($path)) {
                continue;
            }
            $changes[] = ['path' => $path, 'state' => self::describeStatusCode($code)];
        }

        usort($changes, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        return $changes;
    }

    /** @return list<string> */
    public function unmergedPaths(): array
    {
        $result = $this->run(['diff', '--name-only', '--diff-filter=U']);
        if ($result['exit'] !== 0) {
            return [];
        }
        $paths = array_values(array_filter(array_map('trim', explode("\n", $result['stdout']))));
        sort($paths);
        return $paths;
    }

    public function mergeInProgress(): bool
    {
        return is_file($this->gitDir() . '/MERGE_HEAD');
    }

    /**
     * @return array{sha: string, message: string, date: string}|null
     */
    public function lastCommit(): ?array
    {
        $result = $this->run(['log', '-1', '--pretty=format:%H%x1f%s%x1f%cI']);
        if ($result['exit'] !== 0 || trim($result['stdout']) === '') {
            return null;
        }
        $parts = explode("\x1f", trim($result['stdout']));
        if (count($parts) < 3) {
            return null;
        }
        return [
            'sha' => substr($parts[0], 0, 12),
            'message' => $parts[1],
            'date' => $parts[2],
        ];
    }

    // ---------------------------------------------------------------------
    // Staging + commit
    // ---------------------------------------------------------------------

    /**
     * Stage every allowlisted file, plus removals for files git tracks that
     * Birkly may no longer sync (deleted, or now a blocked type).
     *
     * @return int Number of staged paths
     */
    public function stageAllowlisted(): int
    {
        $allowed = $this->syncablePaths();
        $staged = 0;

        foreach (array_chunk($allowed, 200) as $chunk) {
            $args = array_merge(['add', '--'], $chunk);
            $this->mustRun($args);
            $staged += count($chunk);
        }

        foreach ($this->trackedPaths() as $tracked) {
            if (in_array($tracked, $allowed, true)) {
                continue;
            }
            $this->run(['rm', '--cached', '--quiet', '--ignore-unmatch', '--', $tracked]);
            $staged++;
        }

        return $staged;
    }

    /** @return list<string> */
    public function syncablePaths(): array
    {
        $paths = $this->fs->listAllFilePaths();
        if (is_file($this->root . '/.gitignore')) {
            $paths[] = '.gitignore';
        }
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values(array_unique($paths));
    }

    /** @return list<string> */
    public function trackedPaths(): array
    {
        $result = $this->run(['ls-files', '-z']);
        if ($result['exit'] !== 0) {
            return [];
        }
        return array_values(array_filter(explode("\0", $result['stdout']), static fn(string $p): bool => $p !== ''));
    }

    public function hasStagedChanges(): bool
    {
        if (!$this->refExists('HEAD')) {
            $result = $this->run(['diff', '--cached', '--name-only']);
            return trim($result['stdout']) !== '';
        }
        return $this->run(['diff', '--cached', '--quiet'])['exit'] !== 0;
    }

    public function commit(string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            $message = 'Update project files from Birkly';
        }
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000);
        }

        if (!$this->hasStagedChanges()) {
            return null;
        }

        $this->mustRun([
            '-c', 'user.name=' . $this->identity['name'],
            '-c', 'user.email=' . $this->identity['email'],
            'commit', '--quiet', '--no-verify', '-m', $message,
        ]);

        $commit = $this->lastCommit();
        return $commit['sha'] ?? null;
    }

    // ---------------------------------------------------------------------
    // Network operations
    // ---------------------------------------------------------------------

    public function testConnection(): void
    {
        $url = $this->remoteUrl();
        if ($url === null) {
            throw new ProjectGitException('No remote configured');
        }
        $result = $this->runNetwork(['ls-remote', '--heads', 'origin']);
        if ($result['exit'] !== 0) {
            throw new ProjectGitException($this->friendlyNetworkError($result['stderr']));
        }
    }

    public function fetch(): void
    {
        if ($this->remoteUrl() === null) {
            throw new ProjectGitException('No remote configured');
        }
        $result = $this->runNetwork(['fetch', '--prune', '--quiet', 'origin']);
        if ($result['exit'] !== 0) {
            throw new ProjectGitException($this->friendlyNetworkError($result['stderr']));
        }
    }

    public function push(string $branch): void
    {
        $branch = self::normalizeBranch($branch);
        if ($this->remoteUrl() === null) {
            throw new ProjectGitException('No remote configured');
        }
        if (!$this->refExists('HEAD')) {
            throw new ProjectGitException('Nothing to push — no commits yet');
        }

        $result = $this->runNetwork(['push', '--quiet', 'origin', 'HEAD:refs/heads/' . $branch]);
        if ($result['exit'] !== 0) {
            throw new ProjectGitException($this->friendlyNetworkError($result['stderr']));
        }
    }

    /**
     * Files that would change locally if the remote branch were applied.
     *
     * @return list<array{path: string, state: string}>
     */
    public function pullPreview(string $branch): array
    {
        $branch = self::normalizeBranch($branch);
        $remoteRef = 'refs/remotes/origin/' . $branch;
        if (!$this->refExists($remoteRef)) {
            return [];
        }

        if (!$this->refExists('HEAD')) {
            $result = $this->run(['ls-tree', '-r', '--name-only', '-z', $remoteRef]);
            $paths = array_filter(explode("\0", $result['stdout']), static fn(string $p): bool => $p !== '');
            return array_values(array_map(
                static fn(string $p): array => ['path' => $p, 'state' => 'added'],
                $paths
            ));
        }

        $result = $this->run(['diff', '--name-status', '-z', 'HEAD', $remoteRef]);
        $records = array_values(array_filter(explode("\0", $result['stdout']), static fn(string $p): bool => $p !== ''));

        $changes = [];
        for ($i = 0; $i + 1 < count($records); $i += 2) {
            $code = $records[$i];
            $path = $records[$i + 1];
            $changes[] = ['path' => $path, 'state' => self::describeStatusCode(substr($code, 0, 1) . ' ')];
        }
        return $changes;
    }

    /**
     * Fast-forward the worktree onto the remote branch.
     */
    public function pullApply(string $branch): void
    {
        $branch = self::normalizeBranch($branch);
        $remoteRef = 'refs/remotes/origin/' . $branch;
        if (!$this->refExists($remoteRef)) {
            throw new ProjectGitException('Remote branch ' . $branch . ' does not exist yet');
        }

        if (!$this->refExists('HEAD')) {
            $this->adoptRemoteBranch($branch, $remoteRef);
            return;
        }

        $result = $this->run(['merge', '--ff-only', '--quiet', $remoteRef]);
        if ($result['exit'] !== 0) {
            throw new ProjectGitException('Cannot fast-forward — local and remote have diverged');
        }
    }

    /**
     * Resolve a both-changed situation by picking one side wholesale.
     *
     * keep_birkly — local project/ wins; the merge is recorded so the next
     *               push is a clean fast-forward for the remote.
     * keep_remote — the remote branch replaces project/ entirely.
     */
    public function resolveConflict(string $strategy, string $branch, string $message = ''): void
    {
        if (!in_array($strategy, ['keep_birkly', 'keep_remote'], true)) {
            throw new ProjectGitException('Unknown conflict strategy');
        }

        $branch = self::normalizeBranch($branch);
        $remoteRef = 'refs/remotes/origin/' . $branch;

        if ($this->mergeInProgress()) {
            $this->run(['merge', '--abort']);
        }

        if (!$this->refExists($remoteRef)) {
            throw new ProjectGitException('Remote branch ' . $branch . ' does not exist yet');
        }

        if ($strategy === 'keep_remote') {
            $this->mustRun(['reset', '--hard', '--quiet', $remoteRef]);
            $this->removeUntrackedSyncableFiles();
            return;
        }

        $this->stageAllowlisted();
        $this->commit($message !== '' ? $message : 'Keep Birkly project files');

        if (!$this->refExists('HEAD')) {
            $this->adoptRemoteBranch($branch, $remoteRef);
            return;
        }

        $merge = $this->run([
            '-c', 'user.name=' . $this->identity['name'],
            '-c', 'user.email=' . $this->identity['email'],
            'merge', '--no-verify', '--allow-unrelated-histories', '-X', 'ours',
            '-m', 'Merge remote into Birkly project (keep Birkly)', $remoteRef,
        ]);

        if ($merge['exit'] === 0) {
            return;
        }

        // Add/add or binary conflicts still need an explicit side.
        $unmerged = $this->unmergedPaths();
        if ($unmerged === []) {
            throw new ProjectGitException('Merge failed: ' . $this->sanitize($merge['stderr']));
        }
        foreach ($unmerged as $path) {
            $this->run(['checkout', '--ours', '--', $path]);
            $this->run(['add', '--', $path]);
        }
        $this->mustRun([
            '-c', 'user.name=' . $this->identity['name'],
            '-c', 'user.email=' . $this->identity['email'],
            'commit', '--quiet', '--no-verify', '-m', 'Merge remote into Birkly project (keep Birkly)',
        ]);
    }

    /**
     * Point an empty repository at the remote branch. `git checkout` refuses
     * when untracked files (a freshly written .gitignore, an existing
     * index.html) would be overwritten, so reset the worktree instead — pull
     * is remote-wins by design, and callers block on a dirty tree first.
     */
    private function adoptRemoteBranch(string $branch, string $remoteRef): void
    {
        $this->mustRun(['update-ref', 'refs/heads/' . $branch, $remoteRef]);
        $this->mustRun(['symbolic-ref', 'HEAD', 'refs/heads/' . $branch]);
        $this->mustRun(['reset', '--hard', '--quiet', 'HEAD']);
    }

    /**
     * Drop untracked files Birkly would sync, so "keep remote" leaves no
     * local-only pages behind. Non-syncable files are never touched.
     */
    private function removeUntrackedSyncableFiles(): void
    {
        $tracked = $this->trackedPaths();
        foreach ($this->syncablePaths() as $path) {
            if (in_array($path, $tracked, true)) {
                continue;
            }
            $full = $this->root . '/' . $path;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * A deleted file no longer shows up in the filesystem allowlist, so this
     * applies the same extension/dot-segment rules the sandbox uses instead of
     * asking the filesystem.
     */
    private function isSyncablePath(string $path): bool
    {
        if ($path === '.gitignore') {
            return true;
        }
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return false;
            }
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $this->fs->allowedExtensions(), true);
    }

    private function refExists(string $ref): bool
    {
        return $this->run(['rev-parse', '--verify', '--quiet', $ref . '^{commit}'])['exit'] === 0;
    }

    /** @return array{0: int, 1: int} behind, ahead */
    private function aheadBehind(string $remoteRef): array
    {
        if (!$this->refExists('HEAD')) {
            return [$this->commitCount($remoteRef), 0];
        }
        $result = $this->run(['rev-list', '--left-right', '--count', $remoteRef . '...HEAD']);
        if ($result['exit'] !== 0) {
            return [0, 0];
        }
        $parts = preg_split('/\s+/', trim($result['stdout'])) ?: [];
        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    private function commitCount(string $ref): int
    {
        $result = $this->run(['rev-list', '--count', $ref]);
        return $result['exit'] === 0 ? (int) trim($result['stdout']) : 0;
    }

    private static function describeStatusCode(string $code): string
    {
        if (trim($code) === '??') {
            $primary = '?';
        } else {
            $primary = trim(substr($code, 0, 1));
            if ($primary === '') {
                $primary = trim(substr($code, 1, 1));
            }
        }

        return match ($primary) {
            '?', 'A' => 'added',
            'D' => 'deleted',
            'R' => 'renamed',
            'C' => 'copied',
            'U' => 'conflicted',
            default => 'modified',
        };
    }

    /**
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function run(array $args): array
    {
        return $this->execute($args, false);
    }

    /**
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runNetwork(array $args): array
    {
        return $this->execute($args, true);
    }

    /**
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function mustRun(array $args): array
    {
        $result = $this->execute($args, false);
        if ($result['exit'] !== 0) {
            throw new ProjectGitException('git ' . $args[0] . ' failed: ' . $this->sanitize($result['stderr']));
        }
        return $result;
    }

    /**
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function execute(array $args, bool $withAuth): array
    {
        if (self::gitVersion() === null) {
            throw new ProjectGitException('git is not available on this server');
        }

        $env = $this->baseEnv();
        $configArgs = [];

        if ($withAuth) {
            $config = $this->authConfig();
            if ($config !== [] && self::supportsEnvConfig()) {
                $env['GIT_CONFIG_COUNT'] = (string) count($config);
                $index = 0;
                foreach ($config as $key => $value) {
                    $env['GIT_CONFIG_KEY_' . $index] = $key;
                    $env['GIT_CONFIG_VALUE_' . $index] = $value;
                    $index++;
                }
            } else {
                foreach ($config as $key => $value) {
                    $configArgs[] = '-c';
                    $configArgs[] = $key . '=' . $value;
                }
            }

            $sshCommand = $this->sshCommand();
            if ($sshCommand !== null) {
                $env['GIT_SSH_COMMAND'] = $sshCommand;
            }

            $this->applyNostrAuth($env, $configArgs);
        }

        $result = self::exec(array_merge(['git'], $configArgs, $args), $this->root, $env);
        $result['stdout'] = $this->sanitize($result['stdout']);
        $result['stderr'] = $this->sanitize($result['stderr']);
        return $result;
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private static function exec(array $command, string $cwd, array $env): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, $cwd, $env === [] ? null : $env);
        if (!is_resource($process)) {
            return ['exit' => 127, 'stdout' => '', 'stderr' => 'Could not start git'];
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * @return array<string, string>
     */
    private function baseEnv(): array
    {
        return [
            'PATH' => (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'),
            'HOME' => $this->tempHome(),
            'LANG' => 'C',
            'LC_ALL' => 'C',
            // Never let git block a web request on an interactive prompt.
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_ASKPASS' => 'echo',
            'SSH_ASKPASS' => 'echo',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_ATTR_NOSYSTEM' => '1',
            'GIT_FLUSH' => '1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authConfig(): array
    {
        if (($this->auth['mode'] ?? 'none') !== 'token') {
            return [];
        }
        $token = (string) ($this->auth['token'] ?? '');
        if ($token === '') {
            return [];
        }
        $url = $this->remoteUrl();
        if ($url === null || preg_match('#^https?://#i', $url) !== 1) {
            return [];
        }

        $username = (string) ($this->auth['username'] ?? 'birkly');
        if ($username === '') {
            $username = 'birkly';
        }

        // Scope the header to this remote so the token cannot leak to a
        // redirect target on another host.
        $key = 'http.' . self::authConfigScope($url) . '.extraheader';
        return [$key => 'Authorization: Basic ' . base64_encode($username . ':' . $token)];
    }

    private static function authConfigScope(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }
        $scope = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $scope .= ':' . $parts['port'];
        }
        return $scope . '/';
    }

    /**
     * Buzz (and other Nostr forges) authenticate Smart HTTP via NIP-98 using
     * git-credential-nostr. We only enable this when the helper is installed.
     *
     * @param array<string, string> $env
     * @param list<string> $configArgs
     */
    private function applyNostrAuth(array &$env, array &$configArgs): void
    {
        if (($this->auth['mode'] ?? 'none') !== 'nostr') {
            return;
        }

        $helper = self::findExecutable('git-credential-nostr');
        if ($helper === null) {
            throw new ProjectGitException(
                'Buzz/Nostr auth needs git-credential-nostr on this server’s PATH. Install the Buzz credential helper, or switch to SSH/token if your remote supports it.'
            );
        }

        $nostrKey = (string) ($this->auth['nostr_key'] ?? '');
        if ($nostrKey === '') {
            throw new ProjectGitException('A Nostr private key (nsec or hex) is required for Buzz authentication.');
        }

        $env['NOSTR_PRIVATE_KEY'] = $nostrKey;

        $url = $this->remoteUrl();
        $parts = is_string($url) ? parse_url($url) : false;
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? 'https') : 'https';
        if ($host === '') {
            throw new ProjectGitException('Buzz remotes must be HTTPS URLs like https://relay.example/git/<owner>/<repo>.');
        }

        $helperOrigin = $scheme . '://' . $host;
        $config = [
            'credential.helper' => '',
            'credential.useHttpPath' => 'true',
            'credential.' . $helperOrigin . '.helper' => $helper,
        ];

        if (self::supportsEnvConfig()) {
            $start = isset($env['GIT_CONFIG_COUNT']) ? (int) $env['GIT_CONFIG_COUNT'] : 0;
            $env['GIT_CONFIG_COUNT'] = (string) ($start + count($config));
            $index = $start;
            foreach ($config as $key => $value) {
                $env['GIT_CONFIG_KEY_' . $index] = $key;
                $env['GIT_CONFIG_VALUE_' . $index] = $value;
                $index++;
            }
        } else {
            foreach ($config as $key => $value) {
                $configArgs[] = '-c';
                $configArgs[] = $key . '=' . $value;
            }
        }
    }

    private function sshCommand(): ?string
    {
        if (($this->auth['mode'] ?? 'none') !== 'ssh') {
            return null;
        }
        $key = (string) ($this->auth['ssh_key'] ?? '');
        if (trim($key) === '') {
            return null;
        }

        if ($this->tempKey === null) {
            $path = $this->tempHome() . '/id_project';
            if (@file_put_contents($path, rtrim($key, "\r\n") . "\n") === false) {
                throw new ProjectGitException('Could not stage SSH key for git');
            }
            @chmod($path, 0600);
            $this->tempKey = $path;
        }

        return 'ssh -i ' . escapeshellarg($this->tempKey)
            . ' -o IdentitiesOnly=yes -o BatchMode=yes'
            . ' -o StrictHostKeyChecking=accept-new'
            . ' -o UserKnownHostsFile=' . escapeshellarg($this->tempHome() . '/known_hosts');
    }

    private function tempHome(): string
    {
        if ($this->tempHome === null) {
            $dir = sys_get_temp_dir() . '/birkly_git_' . bin2hex(random_bytes(6));
            if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new ProjectGitException('Could not create a private git home directory');
            }
            @chmod($dir, 0700);
            $this->tempHome = $dir;
        }
        return $this->tempHome;
    }

    private function cleanupTempFiles(): void
    {
        if ($this->tempKey !== null) {
            @unlink($this->tempKey);
            $this->tempKey = null;
        }
        if ($this->tempHome !== null) {
            foreach (glob($this->tempHome . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempHome);
            $this->tempHome = null;
        }
    }

    private static function supportsEnvConfig(): bool
    {
        $version = self::gitVersion();
        return $version !== null && version_compare($version, self::ENV_CONFIG_MIN_VERSION, '>=');
    }

    /**
     * Strip credentials from anything that may reach a log, the UI, or disk.
     */
    private function sanitize(string $text): string
    {
        foreach ($this->secrets as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, '***', $text);
            }
        }
        return (string) preg_replace('#(://)[^/@\s]+:[^/@\s]+@#', '$1***@', $text);
    }

    public static function redactUrl(string $url): string
    {
        return (string) preg_replace('#(://)[^/@\s]+(?::[^/@\s]+)?@#', '$1***@', $url);
    }

    private function friendlyNetworkError(string $stderr): string
    {
        $text = trim($this->sanitize($stderr));
        $lower = strtolower($text);

        if (str_contains($lower, 'authentication failed') || str_contains($lower, 'invalid username or password')
            || str_contains($lower, '403') || str_contains($lower, 'permission denied (publickey)')) {
            return 'Authentication failed — check the token or SSH key and its repository permissions.';
        }
        if (str_contains($lower, 'could not resolve host') || str_contains($lower, 'connection refused')
            || str_contains($lower, 'network is unreachable') || str_contains($lower, 'timed out')) {
            return 'Could not reach the remote — check the URL and this server’s network access.';
        }
        if (str_contains($lower, 'repository not found') || str_contains($lower, 'does not appear to be a git repository')) {
            return 'Repository not found at that URL.';
        }
        if (str_contains($lower, 'non-fast-forward') || str_contains($lower, 'fetch first')) {
            return 'The remote has newer commits — pull before pushing.';
        }

        return $text !== '' ? $text : 'git command failed';
    }
}
