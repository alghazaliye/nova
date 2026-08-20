<?php
// Unit tests for MysqlCompatPdo SQL adaptations (SQLite mode)
require_once __DIR__ . '/../backend/config/MysqlCompatPdo.php';

// Build a pdo that is sqlite but we only need adaptSql — make a wrapper subclass
class CompatTester extends MysqlCompatPdo
{
    public function adaptPublic(string $sql): string
    {
        $ref = new ReflectionMethod(MysqlCompatPdo::class, 'adaptSql');
        $ref->setAccessible(true);
        return $ref->invoke($this, $sql);
    }
    public function connect(): void
    {
        // force isSqlite = true
    }
}

$pdo = new CompatTester('sqlite::memory:');

$cases = [
    // expected substring | input
    ["CASE WHEN COALESCE(last_read_message_id, 0) >= ? THEN COALESCE(last_read_message_id, 0) ELSE ? END",
     "UPDATE conversation_members SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)"],
    ["(strftime('%s',datetime('now','localtime')) - strftime('%s',COALESCE(updated_at, created_at)))",
     "AND TIMESTAMPDIFF(SECOND, COALESCE(updated_at, created_at), NOW()) > disappear_after"],
    ["(strftime('%s',datetime('now','localtime')) - strftime('%s',started_at)) * 1000",
     "duration = TIMESTAMPDIFF(SECOND, started_at, NOW()) * 1000"],
    ["strftime('%s',datetime('now','localtime')) - strftime('%s',created_at)",
     "SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(created_at) AS age_seconds FROM messages WHERE id = ?"],
    ["datetime('now','localtime','-1 days')",
     "WHERE created_at > NOW() - INTERVAL 1 DAY"],
    ["INSERT OR IGNORE INTO",
     "INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (1,2)"],
];

$pass = 0; $fail = 0;
foreach ($cases as [$expected, $input]) {
    $out = $pdo->adaptPublic($input);
    if (stripos($out, $expected) !== false) {
        echo "PASS: $input\n";
        echo "     -> " . substr($out, 0, 160) . "\n";
        $pass++;
    } else {
        echo "FAIL: $input\n";
        echo "     expected: $expected\n     got     : $out\n";
        $fail++;
    }
}

// End-to-end SQLite execution test: actually prepare GREATEST + TIMESTAMPDIFF queries
$mem = new PDO('sqlite::memory:');
$mem->exec('CREATE TABLE t1 (id INTEGER PRIMARY KEY, v INTEGER, created_at TEXT)');
$mem->exec("INSERT INTO t1 VALUES (1, 5, datetime('now','localtime','-1 hour'))");

$sql = "UPDATE t1 SET v = GREATEST(COALESCE(v,0), 10) WHERE id = 1";
$adapted = $pdo->adaptPublic($sql);
$mem->exec($adapted);
$got = $mem->query("SELECT v FROM t1 WHERE id=1")->fetchColumn();
echo ($got == 10) ? "PASS: GREATEST exec -> v=$got\n" : "FAIL: GREATEST exec -> v=$got (expected 10)\n";
if ($got == 10) $pass++; else $fail++;

$sql2 = "SELECT (strftime('%s',datetime('now','localtime')) - strftime('%s',created_at))/3600 AS hours FROM t1 WHERE id=1";
$row = $mem->query($sql2)->fetch(PDO::FETCH_ASSOC);
$h = (float)$row['hours'];
echo ($h >= 0.99 && $h <= 1.02) ? "PASS: TIMESTAMPDIFF-style strftime exec -> hours=$h\n" : "FAIL: hours=$h\n";
if ($h >= 0.99 && $h <= 1.02) $pass++; else $fail++;

echo "\nTOTAL: " . ($pass + $fail) . " | PASS: $pass | FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
