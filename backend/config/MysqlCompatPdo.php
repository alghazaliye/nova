<?php
/**
 * PDO subclass that transparently adapts MySQL-specific SQL to SQLite,
 * so the rest of the codebase keeps using PDO prepare/exec unchanged.
 *
 * Handled adaptations (SQLite only):
 *   NOW()                        -> datetime('now','localtime')
 *   CURDATE()                    -> date('now','localtime')
 *   DATE_SUB(NOW(), INTERVAL n DAY) -> datetime('now','localtime','-n days')
 *   NOW() - INTERVAL n HOUR        -> datetime('now','localtime','-n hours')
 *   INTERVAL n DAY/HOUR            -> handled within DATE_SUB expressions
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
        if ($this->isSqlite && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            unset($options[PDO::MYSQL_ATTR_INIT_COMMAND]);
        }
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

    public function query(?string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $sql = $this->adaptSql((string)$query);
        if ($fetchMode !== null && $fetchModeArgs !== []) {
            return parent::query($sql, $fetchMode, ...$fetchModeArgs);
        }
        if ($fetchMode !== null) {
            return parent::query($sql, $fetchMode);
        }
        return parent::query($sql);
    }

    private function adaptSql(string $sql): string
    {
        if (!$this->isSqlite) {
            return $sql;
        }
        $needsWork = stripos($sql, 'ON DUPLICATE') !== false
            || stripos($sql, 'INSERT IGNORE') !== false
            || stripos($sql, 'NOW()') !== false
            || stripos($sql, 'CURDATE()') !== false
            || stripos($sql, 'INTERVAL') !== false
            || stripos($sql, 'IF(') !== false;

        // IF(cond, a, b) -> CASE WHEN cond THEN a ELSE b END (MySQL only)
        $sql = preg_replace_callback(
            '/\bIF\s*\((.+?)\s*,\s*(.+?)\s*,\s*(.+?)\s*\)/is',
            static fn ($m) => '(CASE WHEN ' . $m[1] . ' THEN ' . $m[2] . ' ELSE ' . $m[3] . ' END)',
            $sql
        );
        if (!$needsWork) {
            return $sql;
        }

        // 1) DATE_SUB(NOW(), INTERVAL n DAY/HOUR) -> SQLite datetime modifier
        $sql = preg_replace_callback(
            '/\bDATE_SUB\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(DAY|HOUR|MINUTE|SECOND)\s*\)/i',
            static fn ($m) => "datetime('now','localtime','" . '-' . $m[1] . ' ' . strtolower($m[2]) . "s')",
            $sql
        );

        // 2) NOW() - INTERVAL n HOUR/DAY -> SQLite datetime modifier
        $sql = preg_replace_callback(
            "/\bNOW\(\)\s*-\s*INTERVAL\s+(\d+)\s+(HOUR|DAY|MINUTE|SECOND)\b/i",
            static fn ($m) => "datetime('now','localtime','" . '-' . $m[1] . ' ' . strtolower($m[2]) . "s')",
            $sql
        );

        // 3) NOW() -> SQLite datetime
        $sql = preg_replace('/\bNOW\(\)/i', "datetime('now','localtime')", $sql);

        // 4) CURDATE() -> SQLite date
        $sql = preg_replace('/\bCURDATE\(\)/i', "date('now','localtime')", $sql);

        // 5) INSERT IGNORE -> INSERT OR IGNORE
        $sql = str_ireplace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $sql);

        // 3) INSERT ... ON DUPLICATE KEY UPDATE ...
        //    Greedy-match the VALUES(...) portion (may contain nested parens),
        //    then consume until "ON DUPLICATE KEY UPDATE", then capture rest.
        if (preg_match(
            '/^(INSERT INTO [`"\w]+\s*(?:\([^)]*\))?\s*VALUES\s*[\s\S]+?)\s+ON DUPLICATE KEY UPDATE\s+(.+?)\s*$/is',
            $sql,
            $m
        )) {
            $insert = $m[1]; // (upsert block unchanged)
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
