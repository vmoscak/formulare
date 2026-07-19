<?php
/**
 * Tímový prehľad — pre teba ako manažéra: kto z tímu ktoré nástroje reálne
 * používa, na základe už existujúceho logovania vygenerovaných dokumentov
 * (formulare_generated_documents). Cieľ: vidieť, kto sa zasekol alebo dlho
 * nič negeneroval, bez toho, aby si sa musel každého pýtať. Prístup
 * VÝHRADNE pre is_owner=1 (rovnaký vzor ako nabor.php/znalostna-baza.php).
 * Žiadne nové osobné údaje klientov sa tu nezhromažďujú — len agregované
 * počty nad dátami, ktoré appka beztak už zbiera.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools-registry.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

$totalToolsAvailable = 0;
foreach ($TOOL_CATEGORIES as $cat) { $totalToolsAvailable += count($cat['tools']); }

$rows = db()->query(
    'SELECT a.id, a.name, a.color, a.created_at AS joined_at,
            COUNT(d.id) AS total_docs, COUNT(DISTINCT d.tool) AS tools_used, MAX(d.generated_at) AS last_active
     FROM formulare_advisors a
     LEFT JOIN formulare_generated_documents d ON d.advisor_id = a.id
     WHERE a.active = 1
     GROUP BY a.id, a.name, a.color, a.created_at
     ORDER BY total_docs ASC, a.name ASC'
)->fetchAll();

$now = new DateTimeImmutable();
function daysAgo(?string $dt, DateTimeImmutable $now): ?int {
    if (!$dt) return null;
    try { $d = new DateTimeImmutable($dt); } catch (Throwable $e) { return null; }
    return (int)$now->diff($d)->days;
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Tímový prehľad</title>
<link rel="stylesheet" href="<?= asset('fonts.css') ?>">
<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('panel.css') ?>">
<style>
  .tp-who{display:flex; align-items:center; gap:10px;}
  .tp-ini{width:32px; height:32px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; flex-shrink:0;}
  .tp-bar-track{width:100px; height:8px; border-radius:999px; background:var(--desk); overflow:hidden; display:inline-block; vertical-align:middle; margin-right:8px;}
  .tp-bar-fill{height:100%; background:var(--accent); border-radius:999px;}
  .tp-status{display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:700; padding:3px 9px; border-radius:999px;}
  .tp-status.ok{background:var(--good-soft,#ecfdf5); color:var(--good,#059669);}
  .tp-status.warn{background:var(--amber-soft,#fffbeb); color:var(--amber,#d97706);}
  .tp-status.bad{background:var(--err-soft,#fff1f2); color:var(--err,#e11d48);}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Tímový prehľad</h1>
    <p>Kto z tímu ktoré nástroje používa · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Aktivita poradcov · <?= count($rows) ?></h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Zoradené od najmenej aktívnych — nikto sa nezhromažďuje mimo toho, čo appka beztak už loguje pri generovaní PDF.
    </p>
    <table>
      <tr><th>Poradca</th><th>Vyskúšané nástroje</th><th>Dokumenty spolu</th><th>Posledná aktivita</th><th>Stav</th></tr>
      <?php foreach ($rows as $r):
        $ago = daysAgo($r['last_active'], $now);
        $toolsUsed = (int)$r['tools_used'];
        $toolsPct = $totalToolsAvailable > 0 ? min(100, round($toolsUsed / $totalToolsAvailable * 100)) : 0;
        if ($ago === null) { $status = ['bad', 'Zatiaľ nič negeneroval/-a']; }
        elseif ($ago > 14) { $status = ['warn', 'Neaktívny/-a ' . $ago . ' dní']; }
        else { $status = ['ok', 'Aktívny/-a']; }
      ?>
      <tr>
        <td data-label="Poradca">
          <span class="tp-who">
            <span class="tp-ini" style="background:<?= h($r['color']) ?>;"><?= h(advisorInitials($r['name'])) ?></span>
            <span class="strong"><?= h($r['name']) ?></span>
          </span>
        </td>
        <td data-label="Vyskúšané nástroje">
          <span class="tp-bar-track"><span class="tp-bar-fill" style="width:<?= $toolsPct ?>%;"></span></span>
          <?= $toolsUsed ?>/<?= $totalToolsAvailable ?>
        </td>
        <td data-label="Dokumenty spolu"><?= (int)$r['total_docs'] ?></td>
        <td class="date" data-label="Posledná aktivita"><?= $ago === null ? '—' : ($ago === 0 ? 'dnes' : ('pred ' . $ago . ' dňami')) ?></td>
        <td data-label="Stav"><span class="tp-status <?= $status[0] ?>"><?= h($status[1]) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

</main>
<script src="<?= asset('shell.js') ?>"></script>
</body></html>
