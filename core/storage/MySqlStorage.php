<?php
/**
 * MySQL/MariaDB storage driver for Birkly.
 */

require_once __DIR__ . '/AbstractSqlStorage.php';

class MySqlStorage extends AbstractSqlStorage
{
    protected function buildDsn(array $config): string
    {
        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? $config['name'] ?? 'birkly');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    protected function driverName(): string
    {
        return 'mysql';
    }

    protected function pdoDriverOptions(array $config): array
    {
        $timeout = (int) ($config['connect_timeout'] ?? 5);
        if ($timeout <= 0) {
            $timeout = 5;
        }

        return defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')
            ? [PDO::MYSQL_ATTR_CONNECT_TIMEOUT => $timeout]
            : [];
    }

    protected function schemaSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            path VARCHAR(512) NOT NULL PRIMARY KEY,
            content LONGTEXT NOT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
}
