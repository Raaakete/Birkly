<?php
/**
 * PostgreSQL storage driver for Birkly.
 */

require_once __DIR__ . '/AbstractSqlStorage.php';

class PostgresStorage extends AbstractSqlStorage
{
    protected function buildDsn(array $config): string
    {
        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 5432);
        $database = (string) ($config['database'] ?? $config['name'] ?? 'birkly');

        return "pgsql:host={$host};port={$port};dbname={$database};connect_timeout=5";
    }

    protected function driverName(): string
    {
        return 'postgresql';
    }

    protected function schemaSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            path VARCHAR(512) NOT NULL PRIMARY KEY,
            content TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    }
}
