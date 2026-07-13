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
    $stmt = db()->prepare('SELECT name, color, disabled_tools, is_admin, is_owner, onboarding_started_at FROM formulare_advisors WHERE id = ? AND active = 1');
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

// Skratky na zvyšné časti appky (mimo troch hlavných záložiek) — najmä pre
// mobil, kde ľavá lišta je skrytá za hamburger menu. Moje dokumenty vidí
// každý, ostatné len admin/owner presne podľa rovnakých pravidiel ako v
// ľavej lište (assets/shell.js).
$extraHubs = [
    ['label' => 'Moje dokumenty', 'subtitle' => 'História vygenerovaných PDF a odoslaných klientských odkazov.',
     'ico' => 'folder', 'color' => '#059669', 'href' => '/moje-dokumenty.php', 'tag' => null],
    ['label' => 'Tímový kalendár', 'subtitle' => 'Udalosti a úlohy pre celý tím, farebne priradené konkrétnym kolegom.',
     'ico' => 'calendar', 'color' => '#2563eb', 'href' => '/tim-kalendar.php', 'tag' => null],
];
if (!empty($me['is_admin'])) {
    $extraHubs[] = ['label' => 'Admin', 'subtitle' => 'Správa poradcov, PIN kódov a zapínanie/vypínanie nástrojov.',
        'ico' => 'shield', 'color' => '#7c3aed', 'href' => '/admin.php', 'tag' => 'Admin'];
}
if (!empty($me['is_owner'])) {
    $extraHubs[] = ['label' => 'Nábor', 'subtitle' => 'Register agentov NBS — vyhľadávanie a mapa podľa kraja/okresu.',
        'ico' => 'users', 'color' => '#d97706', 'href' => '/nabor.php', 'tag' => 'Len pre teba'];
    $extraHubs[] = ['label' => 'Znalostná báza', 'subtitle' => 'Interné FAQ a rýchle texty na kopírovanie jedným klikom.',
        'ico' => 'book', 'color' => '#0d9488', 'href' => '/znalostna-baza.php', 'tag' => 'Len pre teba'];
    $extraHubs[] = ['label' => 'Novinky', 'subtitle' => 'Editor noviniek zobrazovaných na tejto stránke.',
        'ico' => 'megaphone', 'color' => '#ea580c', 'href' => '/novinky.php', 'tag' => 'Len pre teba'];
    $extraHubs[] = ['label' => 'Refinančný Radar', 'subtitle' => 'Ručne udržiavaný prehľad hypotekárnych sadzieb podľa banky a fixácie.',
        'ico' => 'euro', 'color' => '#475569', 'href' => '/refinancny-radar.php', 'tag' => 'Len pre teba'];
    $extraHubs[] = ['label' => 'Oplatí sa mi refinancovať?', 'subtitle' => 'Break-even prepočet — mesačná úspora novej sadzby vs. náklady na prechod.',
        'ico' => 'chart', 'color' => '#0891b2', 'href' => '/oplati-sa-refinancovat.php', 'tag' => 'Len pre teba'];
    // Zatiaľ len pre teba, kým sa overí užitočnosť — potom presunúť medzi
    // nepodmienené karty (viditeľné pre každého poradcu).
    $extraHubs[] = ['label' => 'Copy-Paste zóna', 'subtitle' => 'Tvoje osobné rýchle texty na kopírovanie jedným klikom.',
        'ico' => 'clipboard', 'color' => '#0e7490', 'href' => '/copy-paste.php', 'tag' => 'Len pre teba'];
    $extraHubs[] = ['label' => 'Cesta nováčika', 'subtitle' => 'Onboarding checklist pre nových poradcov — Deň 1 / Týždeň 1 / Mesiac 1.',
        'ico' => 'target', 'color' => '#be185d', 'href' => '/cesta-novacika.php', 'tag' => 'Len pre teba'];
    $extraHubs[] = ['label' => 'Tímový prehľad', 'subtitle' => 'Kto z tímu ktoré nástroje používa — pre lepšiu podporu nováčikov.',
        'ico' => 'trending', 'color' => '#65a30d', 'href' => '/tim-prehlad.php', 'tag' => 'Len pre teba'];
}

$news = [];
try {
    $news = db()->query('SELECT * FROM formulare_news ORDER BY important DESC, created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

$newsPalette = ['#4f46e5', '#059669', '#0d9488', '#7c3aed', '#0284c7', '#d97706'];

// Osobné míľniky — súkromný pokrok podľa počtu vygenerovaných dokumentov,
// ZÁMERNE bez porovnávania s kolegami (žiadny rebríček, len vlastný pokrok
// oproti sebe samému). Pozri NAPADY.md "Odložené" — pôvodná "Sieň slávy" bola
// zámerne odložená kvôli súťaživosti, toto je jej nekonkurenčná náhrada.
$DOC_MILESTONES = [
    1 => 'Prvý krok', 5 => 'Rozbieha sa to', 10 => 'Slušný štart',
    25 => 'Sebaistota rastie', 50 => 'Skúsený pár rúk', 100 => 'Stovka!', 250 => 'Majster remesla',
];
$myDocCount = 0;
try {
    $c = db()->prepare('SELECT COUNT(*) FROM formulare_generated_documents WHERE advisor_id = ?');
    $c->execute([$curAdvisorId]);
    $myDocCount = (int)$c->fetchColumn();
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

$reachedMilestone = null;
$nextMilestone = null;
foreach ($DOC_MILESTONES as $threshold => $label) {
    if ($myDocCount >= $threshold) { $reachedMilestone = ['threshold' => $threshold, 'label' => $label]; }
    elseif ($nextMilestone === null) { $nextMilestone = ['threshold' => $threshold, 'label' => $label]; }
}
$milestonePct = $nextMilestone
    ? round($myDocCount / $nextMilestone['threshold'] * 100)
    : 100;

// Ak ti owner priradil onboarding, ukáž nápadnú pripomienku hore na Domov,
// nech vieš, že máš s Cestou nováčika pracovať — s vlastným postupom.
$onboarding = null;
if (!empty($me['onboarding_started_at'])) {
    try {
        $totalObSteps = (int)db()->query('SELECT COUNT(*) FROM formulare_onboarding_steps')->fetchColumn();
        $doneObStmt = db()->prepare('SELECT COUNT(*) FROM formulare_onboarding_progress WHERE advisor_id = ?');
        $doneObStmt->execute([$curAdvisorId]);
        $doneObSteps = (int)$doneObStmt->fetchColumn();
        $onboarding = [
            'total' => $totalObSteps, 'done' => $doneObSteps,
            'pct' => $totalObSteps > 0 ? round($doneObSteps / $totalObSteps * 100) : 0,
        ];
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }
}

// Náhľad najbližších udalostí z Tímového kalendára — viditeľný pre celý tím.
// Udalosť môže byť priradená viacerým kolegom naraz (napr. obchodníci + owner).
$upcomingEvents = [];
try {
    $today = date('Y-m-d');
    $evStmt = db()->prepare('SELECT * FROM formulare_team_events WHERE event_date >= ? ORDER BY event_date ASC LIMIT 4');
    $evStmt->execute([$today]);
    $upcomingEvents = $evStmt->fetchAll();
    if ($upcomingEvents) {
        $eventIds = array_column($upcomingEvents, 'id');
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $asStmt = db()->prepare(
            "SELECT ea.event_id, a.name, a.color FROM formulare_team_event_assignees ea
             JOIN formulare_advisors a ON a.id = ea.advisor_id WHERE ea.event_id IN ($placeholders)"
        );
        $asStmt->execute($eventIds);
        $assigneesByEvent = [];
        foreach ($asStmt->fetchAll() as $row) { $assigneesByEvent[$row['event_id']][] = $row; }
        foreach ($upcomingEvents as &$ev) { $ev['assignees'] = $assigneesByEvent[$ev['id']] ?? []; }
        unset($ev);
    }
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }
$EVT_SK_MONTHS_SHORT = ['', 'JAN', 'FEB', 'MAR', 'APR', 'MÁJ', 'JÚN', 'JÚL', 'AUG', 'SEP', 'OKT', 'NOV', 'DEC'];
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<title>Portál — Domov</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=21">
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

  <?php if ($onboarding): ?>
  <div class="section">
    <a class="onboarding-banner" href="/cesta-novacika.php">
      <span class="ob-banner-ic">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.3"/></svg>
      </span>
      <div class="ob-banner-body">
        <div class="ob-banner-title">Tvoja Cesta nováčika · <?= (int)$onboarding['done'] ?>/<?= (int)$onboarding['total'] ?> dokončené</div>
        <div class="ob-banner-bar-track"><div class="ob-banner-bar-fill" style="width:<?= (int)$onboarding['pct'] ?>%;"></div></div>
      </div>
      <span class="ob-banner-go">Pokračovať
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
  </div>
  <?php endif; ?>

  <?php if ($myDocCount > 0): ?>
  <div class="section">
    <div class="milestone-card">
      <div class="ms-num"><?= $myDocCount ?></div>
      <div class="ms-body">
        <div class="ms-label"><?= h($reachedMilestone['label'] ?? 'Tvoj pokrok') ?> · <?= $myDocCount ?> vygenerovaných dokumentov</div>
        <?php if ($nextMilestone): ?>
        <div class="ms-bar-track"><div class="ms-bar-fill" style="width:<?= min(100, $milestonePct) ?>%;"></div></div>
        <div class="ms-sub">Ešte <?= $nextMilestone['threshold'] - $myDocCount ?> do ďalšieho míľnika („<?= h($nextMilestone['label']) ?>“)</div>
        <?php else: ?>
        <div class="ms-sub">Dosiahol/-la si najvyšší míľnik — pekná práca!</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($upcomingEvents): ?>
  <div class="section">
    <div class="card">
      <div class="section-head-inline">
        <h3>Najbližšie udalosti</h3>
        <a class="pillbtn" href="/tim-kalendar.php">Otvoriť kalendár</a>
      </div>
      <?php foreach ($upcomingEvents as $e): $ts = strtotime($e['event_date']); $assignees = $e['assignees'] ?? []; ?>
      <div class="tew-row">
        <div class="tew-badge"><span class="d"><?= (int)date('j', $ts) ?></span><span class="m"><?= $EVT_SK_MONTHS_SHORT[(int)date('n', $ts)] ?></span></div>
        <div class="tew-body">
          <div class="tew-title"><?= h($e['title']) ?></div>
          <?php if ($assignees): ?><div class="tew-who"><?= h(implode(', ', array_column($assignees, 'name'))) ?></div><?php endif; ?>
        </div>
        <div class="tew-avatars">
          <?php if (!$assignees): ?>
          <span class="tew-avatar" style="background:#94a3b8;" title="Celý tím">⚑</span>
          <?php else: foreach (array_slice($assignees, 0, 3) as $a): ?>
          <span class="tew-avatar" style="background:<?= h($a['color']) ?>;" title="<?= h($a['name']) ?>">
            <?= h(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?>
          </span>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

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

  <?php if ($extraHubs): ?>
  <div class="section">
    <div class="section-head"><h3>Ďalšie skratky</h3></div>
    <div class="hub-grid">
      <?php foreach ($extraHubs as $eh): ?>
      <a class="hub-card" href="<?= h($eh['href']) ?>" style="--hub-color:<?= h($eh['color']) ?>;">
        <span class="hub-ic"><?= toolIco($eh['ico']) ?></span>
        <div class="hub-body">
          <h4><?= h($eh['label']) ?></h4>
          <p><?= h($eh['subtitle']) ?></p>
        </div>
        <div class="hub-foot">
          <span class="hub-count"><?php if ($eh['tag']): ?><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= h($eh['tag']) ?><?php endif; ?></span>
          <span class="hub-go">Otvoriť
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</main>

<script src="/assets/shell.js?v=17"></script>
</body></html>
