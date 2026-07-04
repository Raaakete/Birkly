<?php
/**
 * Shared SQL storage logic for MySQL and PostgreSQL drivers.
 */

require_once __DIR__ . '/StorageInterface.php';

abstract class AbstractSqlStorage implements StorageInterface
{
    protected $pdo;
    protected $prefix;
    protected $tableName;

    public function __construct(array $config)
    {
        $this->prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($config['prefix'] ?? 'birkly_'));
        if ($this->prefix === '') {
            $this->prefix = 'birkly_';
        }
        $this->tableName = $this->prefix . 'storage';

        $this->pdo = new PDO(
            $this->buildDsn($config),
            (string) ($config['username'] ?? $config['user'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $this->ensureSchema();
    }

    abstract protected function buildDsn(array $config): string;

    abstract protected function driverName(): string;

    protected function ensureSchema(): void
    {
        $sql = $this->schemaSql();
        $this->pdo->exec($sql);
    }

    abstract protected function schemaSql(): string;

    public static function testConnection(array $config): array
    {
        try {
            $instance = new static($config);
            $instance->pdo->query('SELECT 1');
            return ['ok' => true, 'message' => 'Connection successful'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function read($path)
    {
        $path = $this->normalizePath($path);
        $stmt = $this->pdo->prepare("SELECT content FROM {$this->tableName} WHERE path = ?");
        $stmt->execute([$path]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $decoded = json_decode($row['content'], true);
        return is_array($decoded) ? $decoded : null;
    }

    public function write($path, $data)
    {
        $path = $this->normalizePath($path);
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        $driver = $this->driverName();
        if ($driver === 'postgresql') {
            $sql = "INSERT INTO {$this->tableName} (path, content, updated_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (path) DO UPDATE SET content = EXCLUDED.content, updated_at = CURRENT_TIMESTAMP";
        } else {
            $sql = "INSERT INTO {$this->tableName} (path, content, updated_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = CURRENT_TIMESTAMP";
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$path, $json]);
    }

    public function delete($path)
    {
        $path = $this->normalizePath($path);
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE path = ?");
        return $stmt->execute([$path]);
    }

    public function list($path)
    {
        $path = $this->normalizePath($path);
        $prefix = $path === '' ? '' : $path . '/';
        $stmt = $this->pdo->prepare("SELECT path FROM {$this->tableName} WHERE path LIKE ? ESCAPE '\\\\'");
        $stmt->execute([$prefix . '%']);
        $rows = $stmt->fetchAll();
        $items = [];

        foreach ($rows as $row) {
            $relative = substr($row['path'], strlen($prefix));
            if ($relative === false || $relative === '') {
                continue;
            }
            $segment = explode('/', $relative, 2)[0];
            if ($segment !== '' && !in_array($segment, $items, true)) {
                $items[] = $segment;
            }
        }

        sort($items);
        return $items;
    }

    public function exists($path)
    {
        $path = $this->normalizePath($path);
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->tableName} WHERE path = ? LIMIT 1");
        $stmt->execute([$path]);
        return (bool) $stmt->fetchColumn();
    }

    public function query($collection, $filters = [], $options = [])
    {
        $prefix = 'collections/' . $collection . '/entries/';
        $stmt = $this->pdo->prepare("SELECT content FROM {$this->tableName} WHERE path LIKE ?");
        $stmt->execute([$prefix . '%']);
        $entries = [];

        while ($row = $stmt->fetch()) {
            $entry = json_decode($row['content'], true);
            if (!is_array($entry)) {
                continue;
            }

            $matches = true;
            foreach ($filters as $field => $filter) {
                $operator = '=';
                $value = $filter;

                if (is_array($filter) && isset($filter['operator'], $filter['value'])) {
                    $operator = $filter['operator'];
                    $value = $filter['value'];
                }

                $fieldValue = $entry[$field] ?? null;

                switch ($operator) {
                    case '=':
                        $matches = ($fieldValue === $value);
                        break;
                    case '!=':
                        $matches = ($fieldValue !== $value);
                        break;
                    case '>':
                        $matches = ($fieldValue > $value);
                        break;
                    case '>=':
                        $matches = ($fieldValue >= $value);
                        break;
                    case '<':
                        $matches = ($fieldValue < $value);
                        break;
                    case '<=':
                        $matches = ($fieldValue <= $value);
                        break;
                    case 'contains':
                        $matches = (is_string($fieldValue) && strpos($fieldValue, $value) !== false);
                        break;
                    case 'in':
                        $matches = (is_array($value) && in_array($fieldValue, $value, true));
                        break;
                    default:
                        $matches = ($fieldValue === $value);
                }

                if (!$matches) {
                    break;
                }
            }

            if ($matches) {
                $entries[] = $entry;
            }
        }

        if (isset($options['sort'])) {
            $sortField = $options['sort'];
            $sortOrder = $options['order'] ?? 'asc';
            usort($entries, function ($a, $b) use ($sortField, $sortOrder) {
                $aVal = $a[$sortField] ?? '';
                $bVal = $b[$sortField] ?? '';
                $result = $aVal <=> $bVal;
                return $sortOrder === 'desc' ? -$result : $result;
            });
        }

        if (isset($options['offset'])) {
            $entries = array_slice($entries, (int) $options['offset']);
        }
        if (isset($options['limit'])) {
            $entries = array_slice($entries, 0, (int) $options['limit']);
        }

        return $entries;
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollback()
    {
        return $this->pdo->rollBack();
    }

    public function getDriverName()
    {
        return $this->driverName();
    }

    public function getMetadata()
    {
        return [
            'driver' => $this->driverName(),
            'version' => '1.0.0',
            'capabilities' => [
                'transactions' => true,
                'queries' => true,
                'indexing' => false,
            ],
            'table' => $this->tableName,
        ];
    }

    protected function normalizePath($path): string
    {
        return trim(str_replace('\\', '/', (string) $path), '/');
    }
}
