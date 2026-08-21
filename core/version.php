<?php
// core/version.php - Birkly Version Management System

class BirklyVersion {
    private static $currentVersion = '1.0.0';
    private static $versionFile = __DIR__ . '/../VERSION';
    // GitHub repository for update checks and downloads
    private static $githubRepo = 'Raaakete/Birkly';
    private static $updateCheckUrl = 'https://api.github.com/repos/Raaakete/Birkly/releases/latest';
    
    /**
     * Get download URL for a specific version
     */
    private static function getDownloadUrl(string $version): string {
        $tag = 'v' . ltrim($version, 'v');
        return 'https://github.com/' . self::$githubRepo . '/archive/refs/tags/' . $tag . '.zip';
    }

    /**
     * Whether a GitHub token is configured for private-repo update checks.
     */
    private static function hasGithubToken(): bool
    {
        return self::resolveGithubToken() !== '';
    }

    /**
     * @return array{data:?array,http_code:int}
     */
    private static function fetchGithubJson(string $url): array
    {
        $headers = self::getGithubHeaders('Birkly/' . self::getCurrentVersion());
        $headerLines = $headers;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($response) || $response === '') {
                return ['data' => null, 'http_code' => $httpCode > 0 ? $httpCode : 0];
            }

            $data = json_decode($response, true);
            return [
                'data' => is_array($data) ? $data : null,
                'http_code' => $httpCode,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headerLines),
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) {
                    $httpCode = (int) $m[1];
                    break;
                }
            }
        }

        if ($response === false) {
            return ['data' => null, 'http_code' => $httpCode];
        }

        $data = json_decode($response, true);
        if (is_array($data) && $httpCode > 0 && !isset($data['status'])) {
            $data['status'] = $httpCode;
        }

        return [
            'data' => is_array($data) ? $data : null,
            'http_code' => $httpCode,
        ];
    }

    private static function isGithubApiError(?array $data): bool
    {
        if (!is_array($data)) {
            return true;
        }

        if (isset($data['message']) && !isset($data['tag_name'])) {
            return true;
        }

        return false;
    }

    private static function isGithubTagList(?array $data): bool
    {
        return is_array($data) && $data !== [] && array_is_list($data);
    }

    private static function githubStatusCode(?array $data, int $httpCode = 0): int
    {
        if (is_array($data) && isset($data['status']) && is_numeric($data['status'])) {
            return (int) $data['status'];
        }

        return $httpCode;
    }

    private static function githubApiErrorMessage(?array $release, int $releaseCode, ?array $tags, int $tagsCode): string
    {
        $status = $releaseCode > 0 ? $releaseCode : $tagsCode;
        if ($status === 0 && is_array($release)) {
            $status = self::githubStatusCode($release);
        }
        if ($status === 0 && is_array($tags)) {
            $status = self::githubStatusCode($tags);
        }

        if (($status === 401 || $status === 403) || ($status === 404 && !self::hasGithubToken())) {
            return 'GitHub requires authentication for this repository. Add a personal access token in settings/security.json as {"github_token":"..."} or set env BIRKLY_GITHUB_TOKEN (repo read scope).';
        }

        if ($status === 404) {
            return 'No published releases or tags found on GitHub. Publish a non-draft release or push a version tag (e.g. v1.0.4).';
        }

        if ($status === 403) {
            return 'GitHub API rate limit exceeded. Add a personal access token in settings/security.json or set env BIRKLY_GITHUB_TOKEN.';
        }

        $message = '';
        if (is_array($release) && isset($release['message'])) {
            $message = (string) $release['message'];
        } elseif (is_array($tags) && isset($tags['message'])) {
            $message = (string) $tags['message'];
        }

        if ($message !== '') {
            return 'GitHub API error: ' . $message;
        }

        return 'Unable to check for updates. Repository may be private or unreachable.';
    }

    /**
     * Resolve latest release metadata (formal release or newest git tag).
     */
    private static function fetchLatestReleaseMeta(): array
    {
        $releaseResponse = self::fetchGithubJson(self::$updateCheckUrl);
        $release = $releaseResponse['data'];
        $releaseCode = $releaseResponse['http_code'];

        if (is_array($release) && isset($release['tag_name']) && !self::isGithubApiError($release)) {
            return $release;
        }

        // Fallback: tags work without a published GitHub Release asset
        $tagsResponse = self::fetchGithubJson('https://api.github.com/repos/' . self::$githubRepo . '/tags');
        $tags = $tagsResponse['data'];
        $tagsCode = $tagsResponse['http_code'];

        if (self::isGithubTagList($tags)) {
            usort($tags, static function ($a, $b) {
                return version_compare(ltrim((string) ($b['name'] ?? '0'), 'v'), ltrim((string) ($a['name'] ?? '0'), 'v'));
            });

            $tagName = (string) ($tags[0]['name'] ?? '');
            if ($tagName !== '') {
                return [
                    'tag_name' => $tagName,
                    'body' => '',
                    'zipball_url' => self::getDownloadUrl(ltrim($tagName, 'v')),
                    'published_at' => '',
                    'prerelease' => false,
                ];
            }
        }

        return [
            'error' => self::githubApiErrorMessage($release, $releaseCode, $tags, $tagsCode),
        ];
    }

    /**
     * Locate CMS root prefix inside a GitHub source archive (e.g. "Birkly-1.0.3/").
     */
    private static function findZipCmsPrefix(ZipArchive $zip): ?string
    {
        if ($zip->locateName('index.php') !== false && $zip->locateName('admin/index.php') !== false) {
            return '';
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || !preg_match('#^([^/]+)/index\.php$#', $name, $m)) {
                continue;
            }
            $prefix = $m[1] . '/';
            if ($zip->locateName($prefix . 'admin/index.php') !== false
                && $zip->locateName($prefix . 'core/version.php') !== false) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * Build common HTTP headers for GitHub requests. Supports private repos via token.
     * Token sources (first found wins):
     *  - env BIRKLY_GITHUB_TOKEN
     *  - settings/security.json key "github_token"
     */
    private static function resolveGithubToken(): string
    {
        $token = getenv('BIRKLY_GITHUB_TOKEN');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $securityFile = __DIR__ . '/../settings/security.json';
        if (file_exists($securityFile)) {
            $sec = json_decode(@file_get_contents($securityFile), true) ?: [];
            if (!empty($sec['github_token']) && is_string($sec['github_token'])) {
                return $sec['github_token'];
            }
        }

        return '';
    }

    private static function getGithubHeaders(string $userAgent): array {
        $headers = [
            'User-Agent: ' . $userAgent,
            'Accept: application/vnd.github+json'
        ];

        $token = self::resolveGithubToken();
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        return $headers;
    }
    
    /**
     * Get current CMS version
     */
    public static function getCurrentVersion(): string {
        if (file_exists(self::$versionFile)) {
            $version = trim(file_get_contents(self::$versionFile));
            if (preg_match('/^\d+\.\d+\.\d+/', $version)) {
                return $version;
            }
        }
        return self::$currentVersion;
    }
    
    /**
     * Check GitHub for available updates.
     */
    private static function checkGithubForUpdates(string $currentVersion): array
    {
        try {
            $release = self::fetchLatestReleaseMeta();

            if (isset($release['error'])) {
                return ['error' => $release['error']];
            }

            $latestVersion = ltrim((string) ($release['tag_name'] ?? ''), 'v');
            if ($latestVersion === '' || !preg_match('/^\d+\.\d+\.\d+/', $latestVersion)) {
                return ['error' => 'Latest GitHub release has an invalid version tag.'];
            }

            $isUpdateAvailable = version_compare($latestVersion, $currentVersion, '>');

            return [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $isUpdateAvailable,
                'release_notes' => $release['body'] ?? '',
                'download_url' => $release['zipball_url'] ?? self::getDownloadUrl($latestVersion),
                'published_at' => $release['published_at'] ?? '',
                'prerelease' => $release['prerelease'] ?? false,
                'source' => 'github',
            ];
        } catch (Exception $e) {
            return ['error' => 'Update check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Whether Ops is the configured update source (no per-site GitHub token needed).
     */
    private static function opsUpdatesEnabled(): bool
    {
        if (!is_file(__DIR__ . '/platform_ops.php')) {
            return false;
        }
        require_once __DIR__ . '/platform_ops.php';
        return birkly_ops_updates_check_url() !== '';
    }

    /**
     * @return array{download_url:string,checksum_sha256:string}|null
     */
    private static function resolveOpsDownloadMeta(string $version): ?array
    {
        if (!self::opsUpdatesEnabled()) {
            return null;
        }

        require_once __DIR__ . '/platform_ops.php';
        $ops = birkly_ops_check_updates(self::getCurrentVersion());
        if (!is_array($ops) || empty($ops['ok'])) {
            return null;
        }

        $latest = (string) ($ops['latest_version'] ?? '');
        if ($latest !== $version) {
            return null;
        }

        $url = trim((string) ($ops['download_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        return [
            'download_url' => $url,
            'checksum_sha256' => strtolower(trim((string) ($ops['checksum_sha256'] ?? ''))),
        ];
    }

    private static function fetchRemoteBytes(string $url, array $extraHeaders = []): ?string
    {
        $headers = array_merge(['User-Agent: Birkly/' . self::getCurrentVersion()], $extraHeaders);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($body) || $code < 200 || $code >= 300) {
                return null;
            }
            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 300,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        return is_string($body) && $body !== '' ? $body : null;
    }

    /**
     * Check for available updates
     */
    public static function checkForUpdates(): array {
        $currentVersion = self::getCurrentVersion();

        if (self::opsUpdatesEnabled()) {
            require_once __DIR__ . '/platform_ops.php';
            $ops = birkly_ops_check_updates($currentVersion);
            if (is_array($ops) && !empty($ops['ok'])) {
                return [
                    'current_version' => (string) ($ops['current_version'] ?? $currentVersion),
                    'latest_version' => (string) ($ops['latest_version'] ?? $currentVersion),
                    'update_available' => !empty($ops['update_available']),
                    'release_notes' => (string) ($ops['release_notes'] ?? ''),
                    'download_url' => (string) ($ops['download_url'] ?? ''),
                    'checksum_sha256' => (string) ($ops['checksum_sha256'] ?? ''),
                    'published_at' => '',
                    'prerelease' => birkly_ops_release_channel() !== 'stable',
                    'source' => 'ops',
                ];
            }

            return ['error' => 'Update check failed. Ops could not be reached — verify network access to your Ops platform.'];
        }

        return self::checkGithubForUpdates($currentVersion);
    }
    
    /**
     * Download and verify update package
     */
    public static function downloadUpdate(string $version): array {
        $tempFile = sys_get_temp_dir() . '/birkly-update-' . $version . '.zip';
        $opsMeta = self::resolveOpsDownloadMeta($version);
        $downloadUrl = $opsMeta['download_url'] ?? self::getDownloadUrl($version);
        $expectedChecksum = $opsMeta['checksum_sha256'] ?? '';
        
        try {
            $extraHeaders = $opsMeta === null ? self::getGithubHeaders('Birkly/' . self::getCurrentVersion()) : [];
            $zipContent = self::fetchRemoteBytes($downloadUrl, $extraHeaders);
            if ($zipContent === null) {
                if ($opsMeta !== null) {
                    return ['error' => 'Failed to download update from Ops. Check that the release artifact is published.'];
                }
                return ['error' => 'Failed to download update. For private repos set BIRKLY_GITHUB_TOKEN or settings/security.json github_token.'];
            }
            
            if (file_put_contents($tempFile, $zipContent) === false) {
                return ['error' => 'Failed to save update file'];
            }

            if ($expectedChecksum !== '') {
                $actual = hash_file('sha256', $tempFile);
                if (!is_string($actual) || !hash_equals($expectedChecksum, strtolower($actual))) {
                    unlink($tempFile);
                    return ['error' => 'Update checksum verification failed'];
                }
            }
            
            if (!self::verifyUpdateIntegrity($tempFile, $version)) {
                unlink($tempFile);
                return ['error' => 'Update integrity verification failed'];
            }
            
            return [
                'success' => true,
                'temp_file' => $tempFile,
                'version' => $version,
                'size' => filesize($tempFile)
            ];
            
        } catch (Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            return ['error' => 'Download failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify update package integrity
     */
    private static function verifyUpdateIntegrity(string $filePath, string $version): bool {
        $fileSize = filesize($filePath);
        // Reject empty/corrupt downloads; GitHub archives vary in size
        if ($fileSize < 10 * 1024 || $fileSize > 100 * 1024 * 1024) {
            return false;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            return false;
        }

        $prefix = self::findZipCmsPrefix($zip);
        if ($prefix === null) {
            $zip->close();
            return false;
        }
        
        $requiredFiles = [
            'index.php',
            'admin/index.php',
            'core/version.php',
        ];
        
        foreach ($requiredFiles as $file) {
            if ($zip->locateName($prefix . $file) === false) {
                $zip->close();
                return false;
            }
        }
        
        $zip->close();
        return true;
    }
    
    /**
     * Install update package
     */
    public static function installUpdate(string $tempFile, string $version): array {
        $backupDir = __DIR__ . '/../backups/pre-update-' . date('Y-m-d-H-i-s');
        $cmsRoot = dirname(__DIR__);
        
        try {
            // Create backup
            if (!self::createBackup($cmsRoot, $backupDir)) {
                return ['error' => 'Failed to create backup'];
            }
            
            // Extract update
            $zip = new ZipArchive();
            if ($zip->open($tempFile) !== TRUE) {
                return ['error' => 'Failed to open update package'];
            }
            
            // GitHub zipball extracts to a subdirectory (Birkly-1.0.3/)
            $prefix = self::findZipCmsPrefix($zip);
            if ($prefix === null) {
                $zip->close();
                return ['error' => 'CMS files not found in update package'];
            }

            $tempExtractDir = sys_get_temp_dir() . '/birkly-extract-' . uniqid();
            if (!$zip->extractTo($tempExtractDir)) {
                $zip->close();
                return ['error' => 'Failed to extract update'];
            }
            $zip->close();

            $actualCmsRoot = $prefix === '' ? $tempExtractDir : $tempExtractDir . '/' . rtrim($prefix, '/');
            if (!file_exists($actualCmsRoot . '/index.php')) {
                return ['error' => 'CMS files not found in update package'];
            }
            
            // Move files to CMS root (excluding user/site data — same policy as release manifest)
            $excludeFiles = [
                'users/',
                'content/',
                'media/',
                'logs/',
                'settings/',
                'backups/',
                'project/',
                '.env',
                'config.php'
            ];
            
            if (!self::moveUpdateFiles($actualCmsRoot, $cmsRoot, $excludeFiles)) {
                // Restore backup on failure
                self::restoreBackup($backupDir, $cmsRoot);
                return ['error' => 'Failed to install update files'];
            }
            
            // Update version file
            file_put_contents(self::$versionFile, $version);
            
            // Clean up
            self::cleanupTempFiles($tempFile, $tempExtractDir);
            
            return [
                'success' => true,
                'version' => $version,
                'backup_location' => $backupDir
            ];
            
        } catch (Exception $e) {
            // Restore backup on any error
            if (is_dir($backupDir)) {
                self::restoreBackup($backupDir, $cmsRoot);
            }
            return ['error' => 'Installation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create backup before update
     */
    private static function createBackup(string $sourceDir, string $backupDir): bool {
        if (!is_dir($sourceDir)) {
            return false;
        }
        
        // Create backup directory
        if (!mkdir($backupDir, 0755, true)) {
            return false;
        }
        
        // Copy core files (exclude user data)
        $coreFiles = [
            'admin/',
            'api/',
            'core/',
            'fieldtypes/',
            'plugins/',
            'project/',
            'index.php',
            'VERSION'
        ];
        
        foreach ($coreFiles as $file) {
            $sourcePath = $sourceDir . '/' . $file;
            $backupPath = $backupDir . '/' . $file;
            
            if (is_file($sourcePath)) {
                if (!copy($sourcePath, $backupPath)) {
                    return false;
                }
            } elseif (is_dir($sourcePath)) {
                if (!self::copyDirectory($sourcePath, $backupPath)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Copy directory recursively
     */
    private static function copyDirectory(string $source, string $destination): bool {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!mkdir($destination, 0755, true)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!mkdir($targetPath, 0755, true)) {
                    return false;
                }
            } else {
                if (!copy($item, $targetPath)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Move update files to CMS root
     */
    private static function moveUpdateFiles(string $sourceDir, string $targetDir, array $excludeFiles): bool {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = $iterator->getSubPathName();
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
            
            // Skip excluded files
            $shouldExclude = false;
            foreach ($excludeFiles as $exclude) {
                if (strpos($relativePath, $exclude) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) {
                continue;
            }
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    if (!mkdir($targetPath, 0755, true)) {
                        return false;
                    }
                }
            } else {
                // Ensure target directory exists
                $targetDirPath = dirname($targetPath);
                if (!is_dir($targetDirPath)) {
                    if (!mkdir($targetDirPath, 0755, true)) {
                        return false;
                    }
                }
                
                if (!copy($item, $targetPath)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Restore backup
     */
    private static function restoreBackup(string $backupDir, string $targetDir): bool {
        return self::copyDirectory($backupDir, $targetDir);
    }
    
    /**
     * Clean up temporary files
     */
    private static function cleanupTempFiles(string $tempFile, string $tempExtractDir): void {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        if (is_dir($tempExtractDir)) {
            self::removeDirectory($tempExtractDir);
        }
    }
    
    /**
     * Remove directory recursively
     */
    private static function removeDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item);
            } else {
                unlink($item);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * @internal Used by tests to validate update ZIP layout.
     */
    public static function validateUpdatePackageForTest(string $filePath, string $version): bool
    {
        return self::verifyUpdateIntegrity($filePath, $version);
    }

    /**
     * @internal Used by tests to validate GitHub API error handling.
     */
    public static function githubApiErrorMessageForTest(?array $release, int $releaseCode, ?array $tags, int $tagsCode, bool $hasToken): string
    {
        $original = getenv('BIRKLY_GITHUB_TOKEN');
        if ($hasToken) {
            putenv('BIRKLY_GITHUB_TOKEN=test-token');
        } else {
            putenv('BIRKLY_GITHUB_TOKEN');
        }

        try {
            return self::githubApiErrorMessage($release, $releaseCode, $tags, $tagsCode);
        } finally {
            if ($original === false) {
                putenv('BIRKLY_GITHUB_TOKEN');
            } else {
                putenv('BIRKLY_GITHUB_TOKEN=' . $original);
            }
        }
    }

    /**
     * @internal Used by tests to validate GitHub tag list detection.
     */
    public static function isGithubTagListForTest(?array $data): bool
    {
        return self::isGithubTagList($data);
    }
    
    /**
     * Get update status for admin panel
     */
    public static function getUpdateStatus(): array {
        $updateInfo = self::checkForUpdates();
        
        if (isset($updateInfo['error'])) {
            return [
                'status' => 'error',
                'message' => $updateInfo['error']
            ];
        }
        
        return [
            'status' => 'ok',
            'current_version' => $updateInfo['current_version'],
            'latest_version' => $updateInfo['latest_version'],
            'update_available' => $updateInfo['update_available'],
            'release_notes' => $updateInfo['release_notes'],
            'published_at' => $updateInfo['published_at'],
            'prerelease' => $updateInfo['prerelease']
        ];
    }
}
?>
