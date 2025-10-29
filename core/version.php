<?php
// core/version.php - HaruCMS Version Management System

class HaruVersion {
    private static $currentVersion = '1.0.0';
    private static $versionFile = __DIR__ . '/../VERSION';
    private static $updateCheckUrl = 'https://api.github.com/repos/Raaakete/haru/releases/latest';
    private static $downloadBaseUrl = 'https://cdn.jsdelivr.net/gh/Raaakete/haru@';
    
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
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'HaruCMS/' . $currentVersion
                ]
            ]);
            
            $response = file_get_contents(self::$updateCheckUrl, false, $context);
            if (!$response) {
                return ['error' => 'Unable to check for updates'];
            }
            
            $release = json_decode($response, true);
            if (!$release || !isset($release['tag_name'])) {
                return ['error' => 'Invalid release data'];
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
        $downloadUrl = self::$downloadBaseUrl . $version . '/harucms.zip';
        $tempFile = sys_get_temp_dir() . '/harucms-update-' . $version . '.zip';
        
        try {
            // Download the update
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300, // 5 minutes for large downloads
                    'user_agent' => 'HaruCMS/' . self::getCurrentVersion()
                ]
            ]);
            
            $zipContent = file_get_contents($downloadUrl, false, $context);
            if (!$zipContent) {
                return ['error' => 'Failed to download update'];
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
        // Check file size (minimum 1MB, maximum 100MB)
        $fileSize = filesize($filePath);
        if ($fileSize < 1024 * 1024 || $fileSize > 100 * 1024 * 1024) {
            return false;
        }
        
        // Verify it's a valid ZIP file
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            return false;
        }
        
        // Check for required files
        $requiredFiles = [
            'index.php',
            'admin/index.php',
            'api/index.php',
            'core/version.php'
        ];
        
        foreach ($requiredFiles as $file) {
            if ($zip->locateName($file) === false) {
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
            
            // Extract to temporary directory first
            $tempExtractDir = sys_get_temp_dir() . '/harucms-extract-' . uniqid();
            if (!$zip->extractTo($tempExtractDir)) {
                $zip->close();
                return ['error' => 'Failed to extract update'];
            }
            $zip->close();
            
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
            
            if (!self::moveUpdateFiles($tempExtractDir, $cmsRoot, $excludeFiles)) {
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
            'render_page.php',
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
