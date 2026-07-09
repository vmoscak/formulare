<?php
/**
 * Domov / Úvod — prvá záložka po prihlásení. Ukazuje novinky (presunuté
 * sem z index.php, kde boli predtým) a veľké karty na 3 sekcie appky
 * (Nástroje / Formuláre / Pomôcky) — v inom dizajne než farebné karty
 * jednotlivých nástrojov na ich vlastných stránkach.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools-registry.php';

$curAdvisorId = curAdvisorId();
if (!$curAdvisorId) { header('Location: /'); exit; }

try {
    $stmt = db()->prepare('SELECT name, color, disabled_tools FROM formulare_advisors WHERE id = ? AND active = 1');
    $stmt->execute([$curAdvisorId]);
    $me = $stmt->fetch();
} catch (Throwable $e) { $me = null; }
if (!$me) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

$disabledSlugs = [];
if (!empty($me['disabled_tools'])) {
    $decoded = json_decode($me['disabled_tools'], true);
    if (is_array($decoded)) $disabledSlugs = $decoded;
}

// Počet viditeľných nástrojov v každej skupine (rovnaké pravidlo ako inc-tools-page.php).
$groupCounts = array_fill_keys(array_keys($TOOL_GROUPS), 0);
foreach ($TOOL_CATEGORIES as $cat) {
    $group = $cat['group'] ?? 'nastroje';
    foreach ($cat['tools'] as $t) {
        if (!in_array(toolSlug($t['href']), $disabledSlugs, true)) $groupCounts[$group]++;
    }
}

$hubMeta = [
    'nastroje'  => ['ico' => 'chart', 'color' => '#4f46e5', 'href' => '/nastroje.php'],
    'formulare' => ['ico' => 'file-x', 'color' => '#0284c7', 'href' => '/formulare.php'],
    'pomocky'   => ['ico' => 'message', 'color' => '#e11d48', 'href' => '/pomocky.php'],
];

$news = [];
try {
    $news = db()->query('SELECT * FROM formulare_news ORDER BY important DESC, created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

$newsPalette = ['#4f46e5', '#059669', '#0d9488', '#7c3aed', '#0284c7', '#d97706'];
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Formuláre — Domov</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=9">
</head><body class="home-page">
<div class="home-bg" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
<header class="topbar">
  <div class="tb-title">
    <h1>Ahoj, <?= h(explode(' ', $me['name'])[0]) ?></h1>
    <p>Prehľad noviniek a rýchly vstup do appky</p>
  </div>
  <div class="tb-actions">
    <span class="who">
      <span class="ini" style="background:<?= h($me['color']) ?>;"><?= h(advisorInitials($me['name'])) ?></span>
      <b><?= h($me['name']) ?></b>
    </span>
  </div>
</header>

<main class="content">

  <?php if ($news): ?>
  <div class="section">
    <div class="section-head"><h3>Novinky</h3></div>
    <div class="news-wrap">
      <?php $idx = 0; foreach ($news as $n): ?>
      <?php
      if ($n['important']) { $accent = '#e11d48'; }
      else { $accent = $newsPalette[$idx % count($newsPalette)]; $idx++; }
      ?>
      <div class="news-item<?= $n['important'] ? ' important' : '' ?>" style="--news-accent:<?= $accent ?>; animation-delay:<?= .06 + $idx * .07 ?>s;">
        <?php if ($n['important']): ?><span class="news-badge">Dôležité</span><?php endif; ?>
        <div>
          <h4><?= h($n['title']) ?></h4>
          <p><?= h($n['body']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="section">
    <div class="section-head"><h3>Kam chceš ísť?</h3></div>
    <div class="hub-grid">
      <?php foreach ($TOOL_GROUPS as $key => $meta): ?>
      <a class="hub-card" href="<?= h($hubMeta[$key]['href']) ?>" style="--hub-color:<?= h($hubMeta[$key]['color']) ?>;">
        <span class="hub-ic"><?= toolIco($hubMeta[$key]['ico']) ?></span>
        <div class="hub-body">
          <h4><?= h($meta['label']) ?></h4>
          <p><?= h($meta['subtitle']) ?></p>
        </div>
        <div class="hub-foot">
          <span class="hub-count"><?= (int)$groupCounts[$key] ?> nástrojov</span>
          <span class="hub-go">Otvoriť
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

</main>

<script src="/assets/shell.js?v=6"></script>
</body></html>
