<?php
/**
 * Domov / Úvod — prvá záložka po prihlásení. Ukazuje novinky (presunuté
 * sem z index.php, kde boli predtým), vyhľadávanie naprieč nástrojmi a
 * osobné Obľúbené (hviezdička nastavená v Nástrojoch/Formulároch/Pomôckach) —
 * plná navigácia na všetky skupiny ostáva v ľavej lište (assets/shell.js).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools-registry.php';

$curAdvisorId = curAdvisorId();
if (!$curAdvisorId) { header('Location: /'); exit; }

try {
    $stmt = db()->prepare('SELECT name, color, disabled_tools, favorite_tools, is_admin, is_owner, onboarding_started_at FROM formulare_advisors WHERE id = ? AND active = 1');
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

$favoriteSlugs = [];
if (!empty($me['favorite_tools'])) {
    $decodedFav = json_decode($me['favorite_tools'], true);
    if (is_array($decodedFav)) $favoriteSlugs = $decodedFav;
}

// Plochý zoznam všetkých nástrojov (na vyhľadávanie aj obľúbené) — vynecháva
// adminom vypnuté, drží si aj skupinu kvôli farbe/ikone karty.
$allToolsFlat = [];
foreach ($TOOL_CATEGORIES as $cat) {
    $group = $cat['group'] ?? 'nastroje';
    foreach ($cat['tools'] as $t) {
        $slug = toolSlug($t['href']);
        if (in_array($slug, $disabledSlugs, true)) continue;
        $allToolsFlat[$slug] = $t + ['group' => $group];
    }
}

// Obľúbené v poradí, v akom si ich poradca pridal (nie abecedne/podľa registra).
$favoriteTools = [];
foreach ($favoriteSlugs as $slug) {
    if (isset($allToolsFlat[$slug])) $favoriteTools[] = $allToolsFlat[$slug] + ['slug' => $slug];
}

// Dáta pre klientské vyhľadávanie (JS filtruje bez ďalšieho requestu na server).
$searchData = [];
foreach ($allToolsFlat as $slug => $t) {
    $searchData[] = [
        'name' => $t['name'], 'desc' => $t['desc'], 'href' => $t['href'],
        'group' => $TOOL_GROUPS[$t['group']]['label'] ?? '',
    ];
}


$news = [];
try {
    $news = db()->query('SELECT * FROM formulare_news ORDER BY important DESC, created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

$newsPalette = ['#4f46e5', '#059669', '#0d9488', '#7c3aed', '#0284c7', '#d97706'];

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
<link rel="stylesheet" href="/assets/panel.css?v=27">
</head><body class="home-page">
<div class="home-bg" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
<header class="topbar">
  <div class="tb-title">
    <h1>Ahoj, <?= h(explode(' ', $me['name'])[0]) ?></h1>
    <p>Prehľad noviniek a rýchly vstup do appky</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/moje-dokumenty.php">Moje dokumenty</a>
    <a class="pillbtn amber" href="/budgetove-zlavy/">% Budgetové zľavy</a>
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

  <div class="domov-layout<?= $upcomingEvents ? ' has-sidebar' : '' ?>">
    <div class="domov-main">
      <div class="dom-search-wrap">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" id="domSearchInput" placeholder="Hľadaj nástroj podľa názvu alebo popisu…" autocomplete="off">
      </div>

      <div id="domSearchResults" class="search-results" hidden></div>

      <div id="domFavSection">
        <div class="section-head"><h3>Obľúbené</h3></div>
        <?php if ($favoriteTools): ?>
        <div class="tool-grid" id="domFavGrid">
          <?php foreach ($favoriteTools as $t): ?>
          <div class="tool-card-wrap">
            <button type="button" class="fav-star is-fav" data-slug="<?= h($t['slug']) ?>" onclick="bzUnfavorite(this)" title="Odobrať z obľúbených" aria-label="Odobrať z obľúbených">
              <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </button>
            <a class="tool-card c-<?= h($t['color']) ?>" href="<?= h($t['href']) ?>">
              <span class="ic"><?= toolIco($t['ico']) ?></span>
              <h4><?= h($t['name']) ?></h4>
              <p><?= h($t['desc']) ?></p>
              <span class="go">Otvoriť
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </span>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="empty-state" id="domFavEmpty" <?= $favoriteTools ? 'hidden' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <span class="es-title">Zatiaľ žiadne obľúbené</span>
          <span class="es-sub">Klikni na hviezdičku pri nástroji v Nástrojoch, Formulároch alebo Pomôckach — nech sa ti tu zobrazí to, čo naozaj používaš.</span>
          <div class="fav-empty-cta">
            <a class="pillbtn" href="/nastroje.php">Nástroje</a>
            <a class="pillbtn" href="/formulare.php">Formuláre</a>
            <a class="pillbtn" href="/pomocky.php">Pomôcky</a>
          </div>
        </div>
      </div>
    </div>

    <?php if ($upcomingEvents): ?>
    <aside class="domov-sidebar">
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
    </aside>
    <?php endif; ?>
  </div>

</main>

<script>
var DOM_ALL_TOOLS = <?= json_encode($searchData, JSON_UNESCAPED_UNICODE) ?>;

function domEscape(s) {
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

(function () {
  var input = document.getElementById('domSearchInput');
  var results = document.getElementById('domSearchResults');
  var favSection = document.getElementById('domFavSection');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    if (!q) {
      results.hidden = true;
      favSection.style.display = '';
      return;
    }
    favSection.style.display = 'none';
    results.hidden = false;
    var matches = DOM_ALL_TOOLS.filter(function (t) {
      return t.name.toLowerCase().indexOf(q) !== -1 || t.desc.toLowerCase().indexOf(q) !== -1;
    });
    if (!matches.length) {
      results.innerHTML = '<div class="empty-state"><span class="es-title">Nič sa nenašlo</span><span class="es-sub">Skús iné slovo.</span></div>';
      return;
    }
    results.innerHTML = matches.map(function (t) {
      return '<a class="search-result-item" href="' + t.href + '">' +
        '<span class="sri-group">' + domEscape(t.group) + '</span>' +
        '<b>' + domEscape(t.name) + '</b>' +
        '<span class="sri-desc">' + domEscape(t.desc) + '</span></a>';
    }).join('');
  });
})();

function bzUnfavorite(btn) {
  var slug = btn.dataset.slug;
  var wrap = btn.closest('.tool-card-wrap');
  fetch('/api/toggle-favorite.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ slug: slug })
  }).then(function () {
    if (wrap) wrap.remove();
    var grid = document.getElementById('domFavGrid');
    if (grid && !grid.querySelector('.tool-card-wrap')) {
      grid.remove();
      var empty = document.getElementById('domFavEmpty');
      if (empty) empty.hidden = false;
    }
  });
}
</script>
<script src="/assets/shell.js?v=20"></script>
</body></html>
