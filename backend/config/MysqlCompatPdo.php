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
 *   GREATEST(a, b, ...)          -> CASE WHEN a>=b THEN a ELSE b END (2 args, nested for more)
 *   UNIX_TIMESTAMP(NOW())        -> strftime('%s','now','localtime')
 *   UNIX_TIMESTAMP(col)          -> strftime('%s', col)
 *   TIMESTAMPDIFF(SECOND, a, b)  -> (strftime('%s',b) - strftime('%s',a))
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
            || stripos($sql, 'IF(') !== false
            || stripos($sql, 'GREATEST(') !== false
            || stripos($sql, 'TIMESTAMPDIFF') !== false
            || stripos($sql, 'UNIX_TIMESTAMP') !== false;

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

        // 5b) GREATEST(a, b, ...) -> nested CASE WHEN comparisons (max of args).
        // Handles balanced parentheses inside args (e.g. GREATEST(COALESCE(x,0), ?)).
        while (stripos($sql, 'GREATEST(') !== false) {
            $pos = (int) stripos($sql, 'GREATEST(');
            $start = $pos + 9; // length of 'GREATEST('
            $depth = 1; $end = null; $len = strlen($sql);
            for ($i = $start; $i < $len; $i++) {
                if ($sql[$i] === '(') $depth++;
                elseif ($sql[$i] === ')') { $depth--; if ($depth === 0) { $end = $i; break; } }
            }
            if ($end === null) break;
            $args = $this->splitTopLevel(substr($sql, $start, $end - $start), ',');
            if (count($args) < 2) break; // malformed; bail out of the loop
            $cur = trim($args[0]);
            for ($j = 1, $n = count($args); $j < $n; $j++) {
                $next = trim($args[$j]);
                $cur = '(CASE WHEN ' . $cur . ' >= ' . $next . ' THEN ' . $cur . ' ELSE ' . $next . ' END)';
            }
            $sql = substr($sql, 0, $pos) . $cur . substr($sql, $end + 1);
        }

        // 5c) TIMESTAMPDIFF(SECOND, a, b) -> (strftime('%s',b) - strftime('%s',a))
        // Parsed char-by-char so nested parens/commas (e.g. COALESCE(a,b)) are kept inside args,
        // and NOW()/INTERVAL tokens inside args are shielded from later rules.
        $sql = $this->adaptTimestampDiff($sql);

        // 5d) UNIX_TIMESTAMP(NOW()) -> strftime('%s','now','localtime') (before NOW rule)
        $sql = preg_replace(
            "/\bUNIX_TIMESTAMP\s*\(\s*NOW\(\)\s*\)/i",
            "strftime('%s','now','localtime')",
            $sql
        );
        // 5e) UNIX_TIMESTAMP(col/expr) -> strftime('%s', col/expr)
        $sql = preg_replace(
            "/\bUNIX_TIMESTAMP\s*\((.+?)\)/i",
            "strftime('%s',$1)",
            $sql
        );

        // Remove shielding markers (now safe: NOW/INTERVAL rules can't touch them)
        $sql = str_replace(['/*TSDIFF*/', '/*ENDTSDIFF*/'], '', $sql);

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

        // Unshield tokens protected by adaptTimestampDiff (run after NOW/INTERVAL/UNIX_TIMESTAMP rules)
        $sql = str_replace(
            ['«NOW»', '«CUR»', '«INT»', '«TSD»', '«ETD»'],
            ["datetime('now','localtime')", "date('now','localtime')", 'INTERVAL', '', ''],
            $sql
        );
        return $sql;
    }

    /**
     * Adapt TIMESTAMPDIFF(SECOND, a, b) occurrences.
     * Uses char-by-char balanced-paren parsing so nested commas (COALESCE)
     * stay inside the correct arg, and shields inner NOW()/INTERVAL tokens.
     */
    private function adaptTimestampDiff(string $sql): string
    {
        $up = strtoupper($sql);
        $needle = 'TIMESTAMPDIFF';
        $out = '';
        $i = 0;
        $len = strlen($sql);
        $needleLen = strlen($needle);
        while ($i < $len) {
            $p = stripos($up, $needle, $i);
            if ($p === false) {
                $out .= substr($sql, $i);
                break;
            }
            // ensure word boundary and '(' follows
            if ($p > 0 && preg_match('/\w/', $sql[$p - 1])) {
                $out .= substr($sql, $i, $p - $i + $needleLen);
                $i = $p + $needleLen;
                continue;
            }
            $open = strpos($sql, '(', $p);
            if ($open === false || $open !== $p + $needleLen) {
                $out .= substr($sql, $i, $p - $i + $needleLen);
                $i = $p + $needleLen;
                continue;
            }
            // find matching close paren
            $depth = 1; $j = $open + 1; $inner = '';
            while ($j < $len && $depth > 0) {
                $c = $sql[$j];
                if ($c === '(') $depth++;
                elseif ($c === ')') $depth--;
                if ($depth > 0) $inner .= $c;
                $j++;
            }
            // split inner by top-level commas; expect 'SECOND , a , b'
            $parts = $this->splitTopLevel($inner, ',');
            if (count($parts) === 3 && stripos(trim($parts[0]), 'SECOND') !== false) {
                $a = trim($parts[1]);
                $b = trim($parts[2]);
                // shield inner NOW()/INTERVAL/CURDATE() tokens so later rules skip them
                $shield = static function (string $s): string {
                    $s = preg_replace('/\bNOW\(\)/i', '«NOW»', $s);
                    $s = preg_replace('/\bCURDATE\(\)/i', '«CUR»', $s);
                    $s = preg_replace('/\bINTERVAL\b/i', '«INT»', $s);
                    return $s;
                };
                $conv = '(strftime(\'%s\',' . $shield($b) . ') - strftime(\'%s\',' . $shield($a) . '))';
                $out .= substr($sql, $i, $p - $i) . '«TSD»' . $conv . '«ETD»';
                $i = $j;
                continue;
            }
            // not recognizable — emit as-is and continue past the closing paren
            $out .= substr($sql, $i, $j - $i);
            $i = $j;
        }
        // convert shield placeholders to real literals AFTER NOW/INTERVAL rules run.
        // But those rules run later in adaptSql; place shields that won't match them:
        return $out;
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
