<?php
/**
 * Plugin Migration System for Birkly
 * 
 * Handles plugin data migrations when plugins are updated.
 */

require_once __DIR__ . '/../storage/StorageFactory.php';

/**
 * Register a migration for a plugin
 * 
 * @param string $plugin_id Plugin ID
 * @param string $version Version this migration applies to
 * @param callable $migration Migration function
 * @return bool True on success
 */
function register_plugin_migration($plugin_id, $version, $migration) {
    global $haru_plugin_migrations;
    
    if (!isset($haru_plugin_migrations)) {
        $haru_plugin_migrations = [];
    }
    
    if (!isset($haru_plugin_migrations[$plugin_id])) {
        $haru_plugin_migrations[$plugin_id] = [];
    }
    
    $haru_plugin_migrations[$plugin_id][$version] = $migration;
    
    return true;
}

/**
 * Run migrations for a plugin
 * 
 * @param string $plugin_id Plugin ID
 * @param string $from_version Current version
 * @param string $to_version Target version
 * @return array Result with 'success' (bool) and 'messages' (array)
 */
function run_plugin_migrations($plugin_id, $from_version, $to_version) {
    global $haru_plugin_migrations;
    
    $messages = [];
    $success = true;
    
    if (!isset($haru_plugin_migrations[$plugin_id])) {
        return ['success' => true, 'messages' => ['No migrations registered']];
    }
    
    $migrations = $haru_plugin_migrations[$plugin_id] ?? [];
    
    // Sort migrations by version
    uksort($migrations, 'version_compare');
    
    // Run migrations between from_version and to_version
    foreach ($migrations as $migration_version => $migration) {
        if (version_compare($migration_version, $from_version, '>') && 
            version_compare($migration_version, $to_version, '<=')) {
            
            try {
                $messages[] = "Running migration to version {$migration_version}";
                
                // Execute migration
                if (is_callable($migration)) {
                    $result = call_user_func($migration);
                    if ($result === false) {
                        $success = false;
                        $messages[] = "Migration to version {$migration_version} failed";
                        break;
                    }
                }
                
                // Record migration
                record_plugin_migration($plugin_id, $migration_version);
                $messages[] = "Migration to version {$migration_version} completed";
                
            } catch (Exception $e) {
                $success = false;
                $messages[] = "Migration to version {$migration_version} error: " . $e->getMessage();
                break;
            }
        }
    }
    
    return [
        'success' => $success,
        'messages' => $messages
    ];
}

/**
 * Record that a migration has been run
 * 
 * @param string $plugin_id Plugin ID
 * @param string $version Version migrated to
 * @return bool True on success
 */
function record_plugin_migration($plugin_id, $version) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $migrations = $storage->read('plugin_migrations.json') ?? [];
    
    if (!isset($migrations[$plugin_id])) {
        $migrations[$plugin_id] = [];
    }
    
    $migrations[$plugin_id][] = [
        'version' => $version,
        'migrated_at' => date('c'),
        'migrated_by' => $_SESSION['user']['id'] ?? 'system'
    ];
    
    return $storage->write('plugin_migrations.json', $migrations);
}

/**
 * Get migration history for a plugin
 * 
 * @param string $plugin_id Plugin ID
 * @return array Migration history
 */
function get_plugin_migration_history($plugin_id) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $migrations = $storage->read('plugin_migrations.json') ?? [];
    
    return $migrations[$plugin_id] ?? [];
}

/**
 * Get last migrated version for a plugin
 * 
 * @param string $plugin_id Plugin ID
 * @return string|null Last migrated version or null
 */
function get_plugin_last_migrated_version($plugin_id) {
    $history = get_plugin_migration_history($plugin_id);
    
    if (empty($history)) {
        return null;
    }
    
    // Get latest migration
    usort($history, function($a, $b) {
        return version_compare($b['version'], $a['version']);
    });
    
    return $history[0]['version'] ?? null;
}

/**
 * Check if plugin needs migrations
 * 
 * @param string $plugin_id Plugin ID
 * @param string $current_version Current plugin version
 * @return bool True if migrations are needed
 */
function plugin_needs_migration($plugin_id, $current_version) {
    $last_migrated = get_plugin_last_migrated_version($plugin_id);
    
    if ($last_migrated === null) {
        return true; // Never migrated
    }
    
    return version_compare($current_version, $last_migrated, '>');
}
