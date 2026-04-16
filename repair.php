<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/data/stream_stats.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== REINDEX ===\n";
$pdo->exec('REINDEX');
echo "OK\n";

echo "\n=== VACUUM ===\n";
$pdo->exec('VACUUM');
echo "OK\n";

echo "\n=== PRAGMA quick_check ===\n";
foreach ($pdo->query("PRAGMA quick_check") as $r) {
    echo $r[0] . "\n";
}
