<?php
/**
 * A PDO-compatible wrapper for Turso HTTP client.
 * Inherits SQL adaptation logic from MysqlCompatPdo.
 */

declare(strict_types=1);

require_once __DIR__ . '/TursoClient.php';
require_once __DIR__ . '/MysqlCompatPdo.php';

class TursoPdo
{
    private TursoClient $client;
    private array $options;
    private ?string $lastInsertId = null;
    private bool $inTransaction = false;

    public function __construct(string $url, string $token, array $options = [])
    {
        $this->client = new TursoClient($url, $token);
        $this->options = $options;
    }

    public function prepare(string $query, array $options = []): TursoStatement
    {
        $sql = $this->adaptSql($query);
        return new TursoStatement($this, $this->client, $sql);
    }

    public function exec(string $statement): int
    {
        $sql = $this->adaptSql($statement);
        $result = $this->client->execute($sql);
        $this->lastInsertId = isset($result['last_insert_rowid']) ? (string)$result['last_insert_rowid'] : null;
        return (int)($result['rows_affected'] ?? 0);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): TursoStatement
    {
        $sql = $this->adaptSql($query);
        $stmt = new TursoStatement($this, $this->client, $sql);
        $stmt->execute();
        return $stmt;
    }

    public function fetchColumn(string $query, array $params = [], int $column = 0): mixed
    {
        $stmt = $this->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn($column);
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->lastInsertId ?? "0";
    }

    public function setLastInsertId(?string $id): void
    {
        $this->lastInsertId = $id;
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction) return false;
        $this->exec('BEGIN TRANSACTION');
        $this->inTransaction = true;
        return true;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) return false;
        $this->exec('COMMIT');
        $this->inTransaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        if (!$this->inTransaction) return false;
        $this->exec('ROLLBACK');
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        $this->options[$attribute] = $value;
        return true;
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->options[$attribute] ?? null;
    }

    private function adaptSql(string $sql): string
    {
        // Use the adaptation logic from MysqlCompatPdo
        static $adapter = null;
        if ($adapter === null) {
            $adapter = new class('sqlite::memory:') extends MysqlCompatPdo {
                public function adapt(string $sql): string {
                    $reflection = new ReflectionClass('MysqlCompatPdo');
                    $method = $reflection->getMethod('adaptSql');
                    $method->setAccessible(true);
                    return $method->invoke($this, $sql);
                }
            };
        }
        return $adapter->adapt($sql);
    }
}

class TursoStatement
{
    private TursoPdo $pdo;
    private TursoClient $client;
    private string $sql;
    private array $params = [];
    private ?array $result = null;
    private int $cursor = 0;

    public function __construct(TursoPdo $pdo, TursoClient $client, string $sql)
    {
        $this->pdo = $pdo;
        $this->client = $client;
        $this->sql = $sql;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->params[$param] = $value;
        return true;
    }

    public function bindParam(string|int $param, mixed &$variable, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        $this->params[$param] = &$variable;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $this->params = array_merge($this->params, $params);
        }
        try {
            $this->result = $this->client->execute($this->sql, $this->params);
            $this->cursor = 0;
            if (isset($this->result['last_insert_rowid'])) {
                $this->pdo->setLastInsertId((string)$this->result['last_insert_rowid']);
            }
            return true;
        } catch (Exception $e) {
            error_log("Turso Execution Error: " . $e->getMessage());
            return false;
        }
    }

    public function fetch(int $mode = PDO::FETCH_ASSOC): mixed
    {
        if (!$this->result || !isset($this->result['results']['rows'])) {
            return false;
        }

        $rows = $this->result['results']['rows'];
        $cols = $this->result['results']['columns'];

        if ($this->cursor >= count($rows)) {
            return false;
        }

        $row = $rows[$this->cursor++];
        
        if ($mode === PDO::FETCH_ASSOC) {
            $mapped = [];
            foreach ($cols as $i => $name) {
                $mapped[$name] = $row[$i];
            }
            return $mapped;
        } elseif ($mode === PDO::FETCH_NUM) {
            return $row;
        } elseif ($mode === PDO::FETCH_BOTH) {
            $mapped = [];
            foreach ($cols as $i => $name) {
                $mapped[$name] = $row[$i];
                $mapped[$i] = $row[$i];
            }
            return $mapped;
        }

        return false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch(PDO::FETCH_NUM);
        if ($row === false) return false;
        return $row[$column] ?? null;
    }

    public function fetchAll(int $mode = PDO::FETCH_ASSOC): array
    {
        $all = [];
        while ($row = $this->fetch($mode)) {
            $all[] = $row;
        }
        return $all;
    }

    public function rowCount(): int
    {
        return (int)($this->result['rows_affected'] ?? 0);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    public function errorInfo(): array
    {
        return ['00000', null, null];
    }
}
