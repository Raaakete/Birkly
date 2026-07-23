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
     * Fetch JSON from GitHub with auth headers when configured.
     */
    private static function fetchGithubJson(string $url): ?array
    {
        $headers = self::getGithubHeaders('Birkly/' . self::getCurrentVersion());
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Resolve latest release metadata (formal release or newest git tag).
     */
    private static function fetchLatestReleaseMeta(): array
    {
        $release = self::fetchGithubJson(self::$updateCheckUrl);
        if ($release && isset($release['tag_name']) && !isset($release['message'])) {
            return $release;
        }

        // Fallback: tags work without a published GitHub Release asset
        $tags = self::fetchGithubJson('https://api.github.com/repos/' . self::$githubRepo . '/tags');
        if (!$tags || !is_array($tags) || $tags === []) {
            if (is_array($release) && isset($release['message'])) {
                if (($release['status'] ?? null) == 401 || ($release['status'] ?? null) == 403) {
                    return ['error' => 'GitHub private repo detected. Add a token in settings/security.json as {"github_token":"..."} or set env BIRKLY_GITHUB_TOKEN.'];
                }
                if (($release['status'] ?? null) == 404) {
                    return ['error' => 'No releases or tags found on GitHub. Publish a release or push a version tag (e.g. v1.0.4).'];
                }
                return ['error' => 'GitHub API error: ' . ($release['message'] ?? 'Unknown error')];
            }
            return ['error' => 'Unable to check for updates. Repository may be private or unreachable.'];
        }

        usort($tags, static function ($a, $b) {
            return version_compare(ltrim((string) ($b['name'] ?? '0'), 'v'), ltrim((string) ($a['name'] ?? '0'), 'v'));
        });

        $tagName = (string) ($tags[0]['name'] ?? '');
        if ($tagName === '') {
            return ['error' => 'No valid version tags found on GitHub.'];
        }

        return [
            'tag_name' => $tagName,
            'body' => '',
            'zipball_url' => self::getDownloadUrl(ltrim($tagName, 'v')),
            'published_at' => '',
            'prerelease' => false,
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
    private static function getGithubHeaders(string $userAgent): array {
        $headers = [
            'User-Agent: ' . $userAgent,
            'Accept: application/vnd.github+json'
        ];

        $token = getenv('BIRKLY_GITHUB_TOKEN');
        if (!$token) {
            $securityFile = __DIR__ . '/../settings/security.json';
            if (file_exists($securityFile)) {
                $sec = json_decode(@file_get_contents($securityFile), true) ?: [];
                if (!empty($sec['github_token']) && is_string($sec['github_token'])) {
                    $token = $sec['github_token'];
                }
            }
        }

        if ($token) {
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
     * Check for available updates
     */
    public static function checkForUpdates(): array {
        $currentVersion = self::getCurrentVersion();

        if (is_file(__DIR__ . '/platform_ops.php')) {
            require_once __DIR__ . '/platform_ops.php';
            $ops = birkly_ops_check_updates($currentVersion);
            if (is_array($ops)) {
                return [
                    'current_version' => (string) ($ops['current_version'] ?? $currentVersion),
                    'latest_version' => (string) ($ops['latest_version'] ?? $currentVersion),
                    'update_available' => !empty($ops['update_available']),
                    'release_notes' => (string) ($ops['release_notes'] ?? ''),
                    'download_url' => (string) ($ops['download_url'] ?? ''),
                    'published_at' => '',
                    'prerelease' => birkly_ops_release_channel() !== 'stable',
                    'source' => 'ops',
                ];
            }
        }
        
        try {
            $release = self::fetchLatestReleaseMeta();
            
            if (isset($release['error'])) {
                return ['error' => $release['error']];
            }
            
            $latestVersion = ltrim($release['tag_name'], 'v');
            $isUpdateAvailable = version_compare($latestVersion, $currentVersion, '>');
            
            return [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $isUpdateAvailable,
                'release_notes' => $release['body'] ?? '',
                'download_url' => $release['zipball_url'] ?? '',
                'published_at' => $release['published_at'] ?? '',
                'prerelease' => $release['prerelease'] ?? false
            ];
            
        } catch (Exception $e) {
            return ['error' => 'Update check failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Download and verify update package
     */
    public static function downloadUpdate(string $version): array {
        $downloadUrl = self::getDownloadUrl($version);
        $tempFile = sys_get_temp_dir() . '/birkly-update-' . $version . '.zip';
        
        try {
            // Download the update
            $headers = self::getGithubHeaders('Birkly/' . self::getCurrentVersion());
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers)
                ]
            ]);
            
            $zipContent = @file_get_contents($downloadUrl, false, $context);
            if (!$zipContent) {
                return ['error' => 'Failed to download update. For private repos set BIRKLY_GITHUB_TOKEN or settings/security.json github_token.'];
            }
            
            // Save to temporary file
            if (file_put_contents($tempFile, $zipContent) === false) {
                return ['error' => 'Failed to save update file'];
            }
            
            // Verify file integrity
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
            
            // Move files to CMS root (excluding sensitive files)
            $excludeFiles = [
                'users/',
                'content/',
                'media/',
                'logs/',
                'settings/',
                'backups/',
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
            'templates/',
            'index.php',
            'archive/legacy-templating/render_page.php',
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
