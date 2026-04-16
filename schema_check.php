<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/data/stream_stats.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== PRAGMA quick_check ===\n";
foreach ($pdo->query("PRAGMA quick_check") as $r) {
    echo $r[0] . "\n";
}

echo "\n=== TEST: SELECT COUNT(*) FROM stream_hits ===\n";
$count = $pdo->query("SELECT COUNT(*) FROM stream_hits")->fetchColumn();
echo "Total: $count\n";

echo "\n=== TEST: hitsByDay sample ===\n";
$rows = $pdo->query("
    SELECT strftime('%Y-%m-%d', created_at) AS day, format, COUNT(*) as total
    FROM stream_hits
    WHERE date(created_at) BETWEEN '2026-03-01' AND '2026-03-07'
    GROUP BY day, format
    ORDER BY day ASC
    LIMIT 5
");
foreach ($rows as $r) {
    echo "{$r['day']} - {$r['format']}: {$r['total']}\n";
}
