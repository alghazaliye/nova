<?php
/**
 * PDO subclass that transparently adapts MySQL-specific SQL to SQLite,
 * so the rest of the codebase keeps using PDO prepare/exec unchanged.
 *
 * Handled adaptations (SQLite only):
 *   NOW()                        -> datetime('now','localtime')
 *   INSERT IGNORE INTO ...       -> INSERT OR IGNORE INTO ...
 *   INSERT ... ON DUPLICATE KEY UPDATE col=val,...
 *     -> INSERT INTO ... VALUES (...) ON CONFLICT DO UPDATE SET col=val,...
 *       (SQLite 3.24+ upsert; VALUES(col) -> excluded.col)
 *
 * Notes:
 *   - Placeholder count stays identical (MySQL also counts UPDATE placeholders),
 *     so execute() parameter arrays work unchanged.
 */
declare(strict_types=1);

class MysqlCompatPdo extends PDO
{
    private bool $isSqlite = false;

    public function __construct(string $dsn, ?string $user = null, ?string $password = null, array $options = [])
    {
        $this->isSqlite = str_starts_with($dsn, 'sqlite:');
        // Strip MySQL-specific option that SQLite does not understand
        unset($options[PDO::MYSQL_ATTR_INIT_COMMAND]);
        parent::__construct($dsn, $user, $password, $options);
    }

    public function prepare($query, array $options = []): PDOStatement|false
    {
        return parent::prepare($this->adaptSql((string)$query), $options);
    }

    public function exec($statement): int|false
    {
        return parent::exec($this->adaptSql((string)$statement));
    }

    private function adaptSql(string $sql): string
    {
        if (!$this->isSqlite) {
            return $sql;
        }
        $needsWork = stripos($sql, 'ON DUPLICATE') !== false
            || stripos($sql, 'INSERT IGNORE') !== false
            || stripos($sql, 'NOW()') !== false;
        if (!$needsWork) {
            return $sql;
        }

        // 1) NOW() -> SQLite datetime
        $sql = preg_replace('/\bNOW\(\)/i', "datetime('now','localtime')", $sql);

        // 2) INSERT IGNORE -> INSERT OR IGNORE
        $sql = str_ireplace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $sql);

        // 3) INSERT ... ON DUPLICATE KEY UPDATE ...
        //    Greedy-match the VALUES(...) portion (may contain nested parens),
        //    then consume until "ON DUPLICATE KEY UPDATE", then capture rest.
        if (preg_match(
            '/^(INSERT INTO [`"\w]+\s*(?:\([^)]*\))?\s*VALUES\s*[\s\S]+?)\s+ON DUPLICATE KEY UPDATE\s+(.+?)\s*$/is',
            $sql,
            $m
        )) {
            $insert = $m[1];
            $updates = trim($m[2]);

            // Rewrite VALUES(col) references to SQLite's excluded.col
            $setParts = [];
            foreach ($this->splitTopLevel($updates, ',') as $pair) {
                $pair = trim($pair);
                if (preg_match('/^([`"\w]+)\s*=\s*(.+)$/s', $pair, $p)) {
                    $col = trim($p[1], '`"');
                    $val = trim($p[2]);
                    if (preg_match('/^VALUES\(\s*([`"\w]+)\s*\)$/i', $val, $vref)) {
                        $val = 'excluded.' . trim($vref[1], '`"');
                    }
                    $setParts[] = "{$col} = {$val}";
                }
            }

            $sql = $insert;
            if ($setParts !== []) {
                // SQLite 3.24+ upsert; conflict target is inferred from table's
                // unique indexes (present on all upsert-target tables in this schema).
                $sql .= ' ON CONFLICT DO UPDATE SET ' . implode(', ', $setParts);
            }
        }
        return $sql;
    }

    /** Split a string by $sep, respecting parentheses depth. */
    private function splitTopLevel(string $list, string $sep): array
    {
        $parts = []; $depth = 0; $cur = ''; $len = strlen($list); $sepLen = strlen($sep);
        for ($i = 0; $i < $len; $i++) {
            $c = $list[$i];
            if ($c === '(') $depth++;
            elseif ($c === ')') $depth--;
            elseif ($depth === 0 && substr($list, $i, $sepLen) === $sep) {
                $parts[] = $cur; $cur = '';
                $i += $sepLen - 1;
                continue;
            }
            $cur .= $c;
        }
        if (trim($cur) !== '') $parts[] = $cur;
        return $parts;
    }

    private function extractTable(string $insert): string
    {
        if (preg_match('/^INSERT\s+INTO\s+[`"]?(\w+)[`"]?/i', $insert, $m)) {
            return '`' . $m[1] . '`';
        }
        return '';
    }
}
