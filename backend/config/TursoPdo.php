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

    public function query(string $query): TursoStatement
    {
        $sql = $this->adaptSql($query);
        $stmt = new TursoStatement($this, $this->client, $sql);
        $stmt->execute();
        return $stmt;
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->lastInsertId ?? "0";
    }

    public function setLastInsertId(?string $id): void
    {
        $this->lastInsertId = $id;
    }

    private function adaptSql(string $sql): string
    {
        // Use the adaptation logic from MysqlCompatPdo
        $adapter = new class('sqlite::memory:') extends MysqlCompatPdo {
            public function adapt(string $sql): string {
                $reflection = new ReflectionClass('MysqlCompatPdo');
                $method = $reflection->getMethod('adaptSql');
                $method->setAccessible(true);
                return $method->invoke($this, $sql);
            }
        };
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

    public function execute(array $params = []): bool
    {
        $this->params = array_merge($this->params, $params);
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
        $mapped = [];
        foreach ($cols as $i => $name) {
            $mapped[$name] = $row[$i];
        }

        return $mapped;
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
}
