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
        'name' => $t['name'], 'desc' => $t['desc'], 'href' => $t['href'], 'slug' => $slug,
        'group' => $TOOL_GROUPS[$t['group']]['label'] ?? '',
        'fav' => in_array($slug, $favoriteSlugs, true),
    ];
}

// Odporúčané nástroje na rýchly štart, kým si poradca nevytvorí vlastné
// Obľúbené — ručne vybraný prierez naprieč skupinami, nie automatika.
$RECOMMENDED_SLUGS = ['wizard-poistenie', 'splnomocnenie', 'checklisty-skody', 'prvych-30-dni'];
$recommendedTools = [];
$recommendedTitle = 'Odporúčané na štart';
if (!$favoriteTools) {
    // Ak už má poradca reálnu históriu generovania, odporúčania nahradí tým,
    // čo naozaj najviac používa — statický zoznam ostáva len pre úplný
    // začiatok, kým appku ešte nepoužil.
    $usageCounts = [];
    try {
        $uStmt = db()->prepare(
            'SELECT tool, COUNT(*) c FROM formulare_generated_documents
             WHERE advisor_id = ? GROUP BY tool ORDER BY c DESC, MAX(generated_at) DESC LIMIT 6'
        );
        $uStmt->execute([$curAdvisorId]);
        $usageCounts = $uStmt->fetchAll();
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

    if ($usageCounts) {
        $recommendedTitle = 'Najčastejšie používané';
        foreach ($usageCounts as $u) {
            $slug = $u['tool'];
            if (isset($allToolsFlat[$slug])) $recommendedTools[] = $allToolsFlat[$slug] + ['slug' => $slug];
        }
    }
    if (!$recommendedTools) {
        foreach ($RECOMMENDED_SLUGS as $slug) {
            if (isset($allToolsFlat[$slug])) $recommendedTools[] = $allToolsFlat[$slug] + ['slug' => $slug];
        }
    }
}

$news = [];
try {
    $news = db()->query('SELECT * FROM formulare_news ORDER BY important DESC, created_at DESC LIMIT 5')->fetchAll();
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

$newsPalette = ['#4f46e5', '#059669', '#0d9488', '#7c3aed', '#0284c7', '#d97706'];

// Ak ti owner priradil onboarding, ukáž nápadnú pripomienku hore na Domov —
// Cesta nováčika je koncept "Mapa cesty a odmeny": postup je automatický
// podľa uplynutého času od nástupu, nie podľa odškrtnutých krokov.
$onboarding = null;
if (!empty($me['onboarding_started_at'])) {
    try {
        $totalDurationDays = (int)db()->query('SELECT COALESCE(SUM(duration_days), 0) FROM formulare_onboarding_phases WHERE is_ongoing = 0')->fetchColumn();
        $elapsedDays = max(0, (int)floor((time() - strtotime($me['onboarding_started_at'])) / 86400));
        $onboarding = [
            'day' => $elapsedDays + 1,
            'pct' => $totalDurationDays > 0 ? min(100, round($elapsedDays / $totalDurationDays * 100)) : 0,
        ];
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }
}

// "Pokračuj, kde si skončil" — posledný vygenerovaný dokument poradcu za
// posledné 2 týždne. Otvára sa rovnakým mechanizmom ako v Mojich dokumentoch
// (?loadDoc=id načíta uložené dáta a rovno vygeneruje PDF).
$lastDoc = null;
try {
    $ldStmt = db()->prepare(
        "SELECT id, tool, client_label, generated_at FROM formulare_generated_documents
         WHERE advisor_id = ? ORDER BY generated_at DESC LIMIT 1"
    );
    $ldStmt->execute([$curAdvisorId]);
    $ld = $ldStmt->fetch();
    if ($ld && strtotime($ld['generated_at']) > time() - 14 * 86400) $lastDoc = $ld;
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

// Vyplnené klientske odkazy, ktoré poradca ešte nevidel (claimed_at sa
// nastaví pri otvorení Mojich dokumentov) — nech sa o vyplnení dozvie
// hneď na Domove a nemusí nikam preklikávať naslepo.
$newSubmitted = 0;
try {
    $nsStmt = db()->prepare(
        "SELECT COUNT(*) FROM formulare_client_links
         WHERE advisor_id = ? AND status = 'submitted' AND claimed_at IS NULL"
    );
    $nsStmt->execute([$curAdvisorId]);
    $newSubmitted = (int)$nsStmt->fetchColumn();
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

/** "1 klientsky odkaz bol vyplnený" / "3 odkazy boli vyplnené" / "5 odkazov bolo vyplnených" */
function submittedPhrase(int $n): string {
    if ($n === 1) return "1 klientsky odkaz bol vyplnený";
    if ($n >= 2 && $n <= 4) return "$n klientske odkazy boli vyplnené";
    return "$n klientskych odkazov bolo vyplnených";
}

/** Ľudský relatívny čas po slovensky ("pred chvíľou", "včera", "pred 3 dňami"). */
function timeAgoSk(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 3600) return 'pred chvíľou';
    if ($diff < 86400) return 'dnes';
    if ($diff < 2 * 86400) return 'včera';
    $days = (int)floor($diff / 86400);
    return "pred $days dňami";
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
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<link rel="manifest" href="/assets/manifest.json">
<meta name="theme-color" content="#4f46e5">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Portál">
<title>Portál — Domov</title>
<link rel="stylesheet" href="<?= asset('fonts.css') ?>">
<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('panel.css') ?>">
<style>
  .fav-draggable{cursor:grab;}
  .fav-draggable.is-dragging{opacity:.4;}
  .fav-drag-handle{position:absolute; top:10px; left:10px; z-index:3; width:26px; height:26px; border-radius:50%;
    background:rgba(255,255,255,.88); box-shadow:0 2px 6px rgba(0,0,0,.12); display:flex; align-items:center; justify-content:center;
    font-size:14px; color:var(--muted); line-height:1; pointer-events:none;}
</style>
</head><body class="home-page">
<div class="home-bg" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
<header class="topbar">
  <div class="tb-title">
    <h1>Ahoj, <?= h(explode(' ', $me['name'])[0]) ?></h1>
    <p><?= $news ? 'Prehľad noviniek a rýchly vstup do appky' : 'Rýchly vstup do appky a tvoje obľúbené nástroje' ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/moje-dokumenty.php">Moje dokumenty</a>
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
        <div class="ob-banner-title">Tvoja Cesta nováčika · Deň <?= (int)$onboarding['day'] ?></div>
        <div class="ob-banner-bar-track"><div class="ob-banner-bar-fill" style="width:<?= (int)$onboarding['pct'] ?>%;"></div></div>
      </div>
      <span class="ob-banner-go">Pokračovať
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
  </div>
  <?php endif; ?>

  <?php if ($newSubmitted > 0 || $lastDoc): ?>
  <div class="section dom-quick-banners">
    <?php if ($newSubmitted > 0): ?>
    <a class="dom-banner dom-banner-submitted" href="/moje-dokumenty.php">
      <span class="dom-banner-ic">📥</span>
      <span class="dom-banner-text"><b><?= submittedPhrase($newSubmitted) ?></b> — pozri si odpovede klientov</span>
      <span class="dom-banner-go">Otvoriť
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
    <?php endif; ?>
    <?php if ($lastDoc): ?>
    <a class="dom-banner" href="/<?= h(rawurlencode($lastDoc['tool'])) ?>/index.html?loadDoc=<?= (int)$lastDoc['id'] ?>" target="_blank">
      <span class="dom-banner-ic">🕘</span>
      <span class="dom-banner-text">Naposledy: <b><?= h(toolLabel($lastDoc['tool'])) ?></b> — <?= h($lastDoc['client_label']) ?> · <?= timeAgoSk($lastDoc['generated_at']) ?></span>
      <span class="dom-banner-go">Otvoriť
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
    <?php endif; ?>
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
        <kbd class="dom-search-kbd">/</kbd>
      </div>

      <div id="domSearchResults" class="search-results" hidden></div>

      <div id="domFavSection">
        <div class="section-head"><h3>Obľúbené</h3></div>
        <?php if ($favoriteTools): ?>
        <div class="tool-grid" id="domFavGrid">
          <?php foreach ($favoriteTools as $t): ?>
          <div class="tool-card-wrap fav-draggable" draggable="true" data-slug="<?= h($t['slug']) ?>">
            <span class="fav-drag-handle" title="Presunúť" aria-hidden="true">⠿</span>
            <button type="button" class="fav-star is-fav" data-slug="<?= h($t['slug']) ?>" onclick="bzUnfavorite(this)" title="Odobrať z obľúbených" aria-label="Odobrať z obľúbených">
              <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </button>
            <a class="tool-card c-<?= h($t['color']) ?>" href="<?= h($t['href']) ?>" draggable="false">
              <span class="ic"><?= toolIco($t['ico']) ?></span>
              <h4><?= h($t['name']) ?></h4>
              <p><?= h($t['desc']) ?></p>
              <span class="go">Otvoriť
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </span>
              <?php if (!empty($t['client'])): ?><span class="tool-client-cta" onclick="event.preventDefault();event.stopPropagation();window.location='<?= h($t['href']) ?>?poslat=1';">✉ Poslať klientovi</span><?php endif; ?>
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
        <?php if ($recommendedTools): ?>
        <div id="domRecommended">
          <div class="section-head"><h3><?= h($recommendedTitle) ?></h3></div>
          <div class="tool-grid">
            <?php foreach ($recommendedTools as $t): ?>
            <div class="tool-card-wrap">
              <button type="button" class="fav-star" data-slug="<?= h($t['slug']) ?>" onclick="bzToggleFav(this)" title="Obľúbené" aria-label="Obľúbené">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              </button>
              <a class="tool-card c-<?= h($t['color']) ?>" href="<?= h($t['href']) ?>">
                <span class="ic"><?= toolIco($t['ico']) ?></span>
                <h4><?= h($t['name']) ?></h4>
                <p><?= h($t['desc']) ?></p>
                <span class="go">Otvoriť
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
                <?php if (!empty($t['client'])): ?><span class="tool-client-cta" onclick="event.preventDefault();event.stopPropagation();window.location='<?= h($t['href']) ?>?poslat=1';">✉ Poslať klientovi</span><?php endif; ?>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
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

// Diakritika sa pri rýchlom písaní často vynecháva ("vypoved" má nájsť aj
// "Výpoveď") — porovnávame aj triedime podľa tejto zjednodušenej formy.
var DOM_DIACRITIC_RE = /[\u0300-\u036f]/g;
function domNormalize(s) {
  return (s || '').toString().normalize('NFD').replace(DOM_DIACRITIC_RE, '').toLowerCase();
}

// Zvýrazní nájdenú zhodu v pôvodnom (neupravenom) texte — normalizácia
// diakritiky cez NFD zachováva dĺžku reťazca, takže index z normalizovanej
// verzie sedí aj v origináli.
function domHighlight(original, normQuery) {
  var normOriginal = domNormalize(original);
  var i = normOriginal.indexOf(normQuery);
  if (i === -1) return domEscape(original);
  return domEscape(original.slice(0, i)) + '<mark>' + domEscape(original.slice(i, i + normQuery.length)) + '</mark>' + domEscape(original.slice(i + normQuery.length));
}

(function () {
  var input = document.getElementById('domSearchInput');
  var results = document.getElementById('domSearchResults');
  var favSection = document.getElementById('domFavSection');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = domNormalize(input.value.trim());
    if (!q) {
      results.hidden = true;
      favSection.style.display = '';
      return;
    }
    favSection.style.display = 'none';
    results.hidden = false;
    var matches = DOM_ALL_TOOLS.filter(function (t) {
      return domNormalize(t.name).indexOf(q) !== -1 || domNormalize(t.desc).indexOf(q) !== -1;
    });
    if (!matches.length) {
      results.innerHTML = '<div class="empty-state"><span class="es-title">Nič sa nenašlo</span><span class="es-sub">Skús iné slovo.</span></div>';
      return;
    }
    results.innerHTML = matches.map(function (t) {
      return '<div class="search-result-item">' +
        '<button type="button" class="fav-star' + (t.fav ? ' is-fav' : '') + '" data-slug="' + domEscape(t.slug) + '" onclick="bzToggleFav(this)" title="Obľúbené" aria-label="Obľúbené">' +
        '<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></button>' +
        '<a class="sri-link" href="' + t.href + '">' +
        '<span class="sri-group">' + domEscape(t.group) + '</span>' +
        '<b>' + domHighlight(t.name, q) + '</b>' +
        '<span class="sri-desc">' + domHighlight(t.desc, q) + '</span></a></div>';
    }).join('');
  });

  // "/" alebo Ctrl+K odkiaľkoľvek na Domove skočí do vyhľadávania — bežná
  // skratka (napr. GitHub, Slack), nech sa netreba naň klikať myšou.
  document.addEventListener('keydown', function (e) {
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    var typing = tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable;
    if (e.key === '/' && !typing) {
      e.preventDefault();
      input.focus();
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      input.focus();
      input.select();
    }
  });
})();

function bzToggleFav(btn) {
  var slug = btn.dataset.slug;
  btn.classList.toggle('is-fav');
  fetch('/api/toggle-favorite.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ slug: slug })
  }).catch(function () { btn.classList.toggle('is-fav'); });
}

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

// Vlastné poradie Obľúbených myšou (HTML5 drag & drop, len desktop —
// dotykové zariadenia poradie naďalej menia len cez pridanie/odobratie
// hviezdičky). Poradie sa ukladá do rovnakého stĺpca ako favorite_tools,
// len sa prehodí — endpoint nepridáva ani neuberá žiadny nástroj.
(function () {
  var grid = document.getElementById('domFavGrid');
  if (!grid) return;
  var draggedEl = null;

  grid.addEventListener('dragstart', function (e) {
    var wrap = e.target.closest('.tool-card-wrap');
    if (!wrap) return;
    draggedEl = wrap;
    wrap.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', wrap.dataset.slug || ''); } catch (err) {}
  });

  grid.addEventListener('dragover', function (e) {
    if (!draggedEl) return;
    e.preventDefault();
    var target = e.target.closest('.tool-card-wrap');
    if (!target || target === draggedEl) return;
    var box = target.getBoundingClientRect();
    var before = (e.clientX - box.left) < box.width / 2;
    grid.insertBefore(draggedEl, before ? target : target.nextSibling);
  });

  grid.addEventListener('dragend', function () {
    if (draggedEl) draggedEl.classList.remove('is-dragging');
    draggedEl = null;
    var slugs = Array.prototype.map.call(grid.querySelectorAll('.tool-card-wrap'), function (w) { return w.dataset.slug; });
    fetch('/api/reorder-favorites.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slugs: slugs })
    }).catch(function () {});
  });
})();
</script>
<script src="<?= asset('shell.js') ?>"></script>
</body></html>
