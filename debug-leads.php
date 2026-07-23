<?php
/**
 * Dočasná diagnostická stránka — priamy dump obsahu formulare_leads,
 * aby sa dalo overiť, či lead z leads-intake.php skutočne pristál v DB,
 * alebo sa problém deje až pri zobrazovaní v leady.php (napr. cache/CDN).
 * Zmazať po vyriešení. Len owner.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

header('Content-Type: text/plain; charset=utf-8');
echo "Čas servera: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $count = (int)db()->query('SELECT COUNT(*) FROM formulare_leads')->fetchColumn();
    echo "Počet riadkov vo formulare_leads: $count\n\n";
    $rows = db()->query('SELECT * FROM formulare_leads ORDER BY id DESC LIMIT 20')->fetchAll();
    foreach ($rows as $r) {
        echo "---\n";
        foreach ($r as $k => $v) {
            if (is_int($k)) continue;
            echo "$k: " . var_export($v, true) . "\n";
        }
    }
} catch (Throwable $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
