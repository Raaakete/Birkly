<?php
/**
 * Storage migration helpers — flat-file to SQL backend.
 */

require_once __DIR__ . '/StorageFactory.php';
require_once __DIR__ . '/FlatFileStorage.php';

class StorageMigration
{
    public static function migrateFlatFileToStorage(FlatFileStorage $source, StorageInterface $target): array
    {
        $basePath = rtrim($source->basePath, '/');
        if (!is_dir($basePath)) {
            return ['ok' => false, 'message' => 'Source content directory not found', 'migrated' => 0];
        }

        $files = self::collectJsonFiles($basePath, $basePath);
        $migrated = 0;
        $errors = [];

        $target->beginTransaction();
        try {
            foreach ($files as $relativePath) {
                $fullPath = $basePath . '/' . $relativePath;
                $content = file_get_contents($fullPath);
                $data = json_decode($content, true);
                if (!is_array($data)) {
                    $errors[] = $relativePath;
                    continue;
                }
                if (!$target->write($relativePath, $data)) {
                    $errors[] = $relativePath;
                    continue;
                }
                $migrated++;
            }
            $target->commit();
        } catch (Throwable $e) {
            $target->rollback();
            return ['ok' => false, 'message' => $e->getMessage(), 'migrated' => $migrated];
        }

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => 'Migration completed with errors for ' . count($errors) . ' file(s)',
                'migrated' => $migrated,
                'errors' => array_slice($errors, 0, 10),
            ];
        }

        return ['ok' => true, 'message' => 'Migration completed successfully', 'migrated' => $migrated];
    }

    private static function collectJsonFiles(string $dir, string $basePath): array
    {
        $results = [];
        $items = scandir($dir);
        if ($items === false) {
            return $results;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . '/' . $item;
            if (is_dir($full)) {
                $results = array_merge($results, self::collectJsonFiles($full, $basePath));
                continue;
            }
            if (substr($item, -5) !== '.json') {
                continue;
            }
            $relative = ltrim(substr($full, strlen($basePath)), '/');
            $results[] = $relative;
        }

        return $results;
    }
}
