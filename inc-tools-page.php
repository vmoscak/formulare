<?php
/**
 * Zdieľaná stránka so zoznamom nástrojov — spoločná pre všetky tri záložky
 * ľavej lišty (Nástroje / Formuláre / Pomôcky). Vstupné súbory (nastroje.php,
 * formulare.php, pomocky.php) len nastavia $GROUP a includnú tento súbor.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools-registry.php';

if (!isset($GROUP) || !isset($TOOL_GROUPS[$GROUP])) { $GROUP = 'nastroje'; }

// Vyžaduje zvoleného poradcu — bez neho niet čo personalizovať, vráť na výber.
$curAdvisorId = curAdvisorId();
if (!$curAdvisorId) { header('Location: /'); exit; }

try {
    $stmt = db()->prepare('SELECT name, color, disabled_tools, favorite_tools FROM formulare_advisors WHERE id = ? AND active = 1');
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

// Obľúbené (hviezdička) — vlastný zoznam poradcu, zobrazuje sa na Domove.
$favoriteSlugs = [];
if (!empty($me['favorite_tools'])) {
    $decodedFav = json_decode($me['favorite_tools'], true);
    if (is_array($decodedFav)) $favoriteSlugs = $decodedFav;
}

// "Flow" banner — 3-krokový reťazec poistnej analýzy klienta (len na záložke
// Nástroje), prepojený odovzdávaním odpovedí cez URL seed medzi krokmi
// (viď wizard-poistenie gapCalcUrl() a financna-medzera doCreateChecklist()).
// Nástroje, ktoré sú v banneri, sa nižšie v bežnej mriežke vynechajú, aby sa
// nezobrazovali dvakrát na tej istej stránke.
$FLOW_STEPS = [
    'wizard-poistenie'  => ['n' => 1, 'blurb' => 'Krátky dotazník – zistí, čo klient potrebuje'],
    'financna-medzera'  => ['n' => 2, 'blurb' => 'Dopočíta presné sumy krytia'],
    'checklist-analyza' => ['n' => 3, 'blurb' => 'Automaticky sa vyplní z krokov 1 a 2'],
];
$flowTools = [];
if ($GROUP === 'nastroje') {
    foreach ($TOOL_CATEGORIES as $cat) {
        foreach ($cat['tools'] as $t) {
            $slug = toolSlug($t['href']);
            if (!isset($FLOW_STEPS[$slug]) || in_array($slug, $disabledSlugs, true)) continue;
            $flowTools[$FLOW_STEPS[$slug]['n']] = $t + ['blurb' => $FLOW_STEPS[$slug]['blurb']];
        }
    }
    ksort($flowTools);
}

// Kategórie prefiltrované na aktuálnu záložku a na to, čo tento poradca reálne
// smie vidieť — prázdne kategórie sa v prehľade vôbec nezobrazia. Nástroje už
// zobrazené vo flow banneri sa tu vynechajú (viď vyššie).
$categories = [];
foreach ($TOOL_CATEGORIES as $cat) {
    if (($cat['group'] ?? 'nastroje') !== $GROUP) continue;
    $visibleTools = array_values(array_filter($cat['tools'], function ($t) use ($disabledSlugs, $FLOW_STEPS, $flowTools) {
        $slug = toolSlug($t['href']);
        if (in_array($slug, $disabledSlugs, true)) return false;
        if ($flowTools && isset($FLOW_STEPS[$slug])) return false;
        return true;
    }));
    if ($visibleTools) $categories[] = ['title' => $cat['title'], 'tools' => $visibleTools];
}
$groupMeta = $TOOL_GROUPS[$GROUP];
$arrow = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Portál — <?= htmlspecialchars($groupMeta['label']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=27">
</head>
<body>

<header class="topbar">
  <div class="tb-title">
    <h1><?= htmlspecialchars($groupMeta['label']) ?></h1>
    <p><?= htmlspecialchars($groupMeta['subtitle']) ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/moje-dokumenty.php">Moje dokumenty</a>
    <a class="pillbtn amber" href="/budgetove-zlavy/">% Budgetové zľavy</a>
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

  <?php if ($flowTools): ?>
  <div class="flow-banner">
    <div class="flow-banner-head">
      <span class="flow-kicker">Odporúčaný postup</span>
      <h3>Poistná analýza klienta — 3 kroky, jeden celok</h3>
      <p>Dotazník zistí, čo klient potrebuje. Kalkulačka dopočíta presné sumy. Checklist sa vyplní automaticky z prvých dvoch krokov — tu je jasné, kde s klientom začať.</p>
    </div>
    <div class="flow-steps">
      <?php $i = 0; foreach ($flowTools as $n => $t): $i++; ?>
      <?php if ($i > 1): ?><span class="flow-arrow"><?= $arrow ?></span><?php endif; ?>
      <a class="tool-card c-<?= htmlspecialchars($t['color']) ?> flow-step" href="<?= htmlspecialchars($t['href']) ?>">
        <span class="flow-num"><?= (int)$n ?></span>
        <span class="ic"><?= toolIco($t['ico']) ?></span>
        <h4><?= htmlspecialchars($t['name']) ?></h4>
        <p><?= htmlspecialchars($t['blurb']) ?></p>
        <span class="go">Otvoriť <?= $arrow ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php foreach ($categories as $cat): ?>
  <div class="section">
    <div class="section-head">
      <h3><?= htmlspecialchars($cat['title']) ?></h3>
      <span class="count"><?= count($cat['tools']) ?></span>
    </div>
    <div class="tool-grid">
      <?php foreach ($cat['tools'] as $t): $tSlug = toolSlug($t['href']); $tIsFav = in_array($tSlug, $favoriteSlugs, true); ?>
      <div class="tool-card-wrap">
        <button type="button" class="fav-star<?= $tIsFav ? ' is-fav' : '' ?>" data-slug="<?= htmlspecialchars($tSlug) ?>" onclick="bzToggleFav(this)" title="Obľúbené" aria-label="Obľúbené">
          <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </button>
        <a class="tool-card c-<?= htmlspecialchars($t['color']) ?>" href="<?= htmlspecialchars($t['href']) ?>">
          <span class="ic"><?= toolIco($t['ico']) ?></span>
          <h4><?= htmlspecialchars($t['name']) ?></h4>
          <p><?= htmlspecialchars($t['desc']) ?></p>
          <span class="go">Otvoriť <?= $arrow ?></span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

</main>

<script>
function bzToggleFav(btn) {
  var slug = btn.dataset.slug;
  btn.classList.toggle('is-fav');
  fetch('/api/toggle-favorite.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ slug: slug })
  }).catch(function () { btn.classList.toggle('is-fav'); });
}
</script>
<script src="/assets/shell.js?v=20"></script>
</body>
</html>
