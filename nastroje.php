<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools-registry.php';

// Vyžaduje zvoleného poradcu — bez neho niet čo personalizovať, vráť na výber.
$curAdvisorId = curAdvisorId();
if (!$curAdvisorId) { header('Location: /'); exit; }

try {
    $stmt = db()->prepare('SELECT name, color, disabled_tools FROM formulare_advisors WHERE id = ? AND active = 1');
    $stmt->execute([$curAdvisorId]);
    $me = $stmt->fetch();
} catch (Throwable $e) { $me = null; }
if (!$me) { header('Location: /'); exit; }

function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// Nástroje vypnuté adminom pre tohto poradcu (viď admin.php) — chýbajúci/prázdny
// stĺpec znamená "nič nie je vypnuté", takže nový nástroj automaticky vidí každý.
$disabledSlugs = [];
if (!empty($me['disabled_tools'])) {
    $decoded = json_decode($me['disabled_tools'], true);
    if (is_array($decoded)) $disabledSlugs = $decoded;
}

// Kategórie prefiltrované na to, čo tento poradca reálne smie vidieť —
// prázdne kategórie sa v prehľade vôbec nezobrazia.
$categories = [];
foreach ($TOOL_CATEGORIES as $cat) {
    $visibleTools = array_values(array_filter($cat['tools'], function ($t) use ($disabledSlugs) {
        return !in_array(toolSlug($t['href']), $disabledSlugs, true);
    }));
    if ($visibleTools) $categories[] = ['title' => $cat['title'], 'tools' => $visibleTools];
}
$arrow = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Formuláre — nástroje</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/panel.css?v=4">
</head>
<body>

<header class="topbar">
  <div class="tb-title">
    <h1>Nástroje</h1>
    <p>Vyber nástroj — vyplníš údaje a hotový dokument stiahneš ako PDF.</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/moje-dokumenty.php">Moje dokumenty</a>
    <span class="who">
      <span class="ini" style="background:<?= htmlspecialchars($me['color']) ?>;"><?= htmlspecialchars(advisorInitials($me['name'])) ?></span>
      <b><?= htmlspecialchars($me['name']) ?></b>
    </span>
  </div>
</header>

<main class="content">

  <div class="page-head">
    <div class="kicker">Pracovný pult</div>
    <h2>Dobrý deň, <?= htmlspecialchars(explode(' ', $me['name'])[0]) ?></h2>
    <p>Máš pripravených <?= array_sum(array_map(fn($c) => count($c['tools']), $categories)) ?> nástrojov v <?= count($categories) ?> kategóriách.</p>
  </div>

  <?php foreach ($categories as $cat): ?>
  <div class="section">
    <div class="section-head">
      <h3><?= htmlspecialchars($cat['title']) ?></h3>
      <span class="count"><?= count($cat['tools']) ?></span>
    </div>
    <div class="tool-grid">
      <?php foreach ($cat['tools'] as $t): ?>
      <a class="tool-card c-<?= htmlspecialchars($t['color']) ?><?= !empty($t['hero']) ? ' hero' : '' ?>" href="<?= htmlspecialchars($t['href']) ?>">
        <span class="ic"><?= toolIco($t['ico']) ?></span>
        <h4><?= htmlspecialchars($t['name']) ?></h4>
        <p><?= htmlspecialchars($t['desc']) ?></p>
        <span class="go">Otvoriť <?= $arrow ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="section">
    <div class="section-head">
      <h3>Pripravujeme</h3>
    </div>
    <div class="tool-grid">
      <div class="tool-card soon">
        <span class="ic"><?= toolIco('plus') ?></span>
        <h4>Ďalšie formuláre</h4>
        <p>Postupne pribudnú ďalšie vzory a žiadosti.</p>
        <span class="badge-soon">Čoskoro</span>
      </div>
    </div>
  </div>

</main>

<script src="/assets/shell.js?v=4"></script>
</body>
</html>
