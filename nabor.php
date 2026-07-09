<?php
/**
 * Náborová zóna — register inštitúcií a finančných agentov (NBS).
 * Prístup VÝHRADNE pre poradcu s is_owner=1 — nie is_admin (zámerne, aj
 * budúci ďalší admin by túto zónu nemal vidieť, pozri sql/005_advisor_owner.sql).
 *
 * Dáta sa nikdy neposielajú cez appku/chat — veľký JSON export (desiatky MB)
 * sa nahráva priamo cez FTP do data/nbs-register.json (mimo verejnej časti,
 * chránené existujúcou bránou appky aj tak) a tu sa jedným klikom importuje
 * priamo na serveri. Mapa a detailný náhľad licencií sú plánované na fázu 2.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$importMessage = '';
$importError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    try {
        $result = registryImport(REGISTRY_DATA_FILE, REGISTRY_FACETS_FILE);
        $importMessage = 'Naimportovaných ' . number_format($result['count'], 0, ',', ' ') . ' záznamov'
            . ($result['updated'] ? ' (dataset aktualizovaný ' . h($result['updated']) . ')' : '') . '.';
    } catch (Throwable $e) {
        $importError = $e->getMessage();
    }
}

$geocodeMessage = '';
$geocodeDebug = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['geocode_batch'])) {
    try {
        $g = geocodeBatchProcess(50);
        $geocodeMessage = 'Spracovaných ' . $g['processed'] . ' (nájdených ' . $g['found'] . ', nenájdených ' . $g['not_found']
            . ($g['retried'] > 0 ? ', dočasne odmietnutých ' . $g['retried'] . ' — skúsi sa znova' : '') . '), zostáva ' . number_format($g['remaining'], 0, ',', ' ') . '.';
        if (!empty($g['first_error'])) $geocodeDebug = $g['first_error'];
    } catch (Throwable $e) {
        $geocodeMessage = 'Chyba: ' . $e->getMessage();
    }
}

// -- stav geokódovania (presné adresy agentov) --
$geoStats = ['pending' => 0, 'found' => 0, 'not_found' => 0];
try {
    foreach (db()->query("SELECT status, COUNT(*) c FROM formulare_geocode_cache GROUP BY status") as $g) {
        $geoStats[$g['status']] = (int)$g['c'];
    }
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }
$geoTotal = array_sum($geoStats);

// -- štatistiky --
$totalCount = 0;
$lastImportAt = null;
$newSinceLastImport = 0;
try {
    $totalCount = (int)db()->query('SELECT COUNT(*) c FROM formulare_registry_entities')->fetch()['c'];
    $row = db()->query('SELECT MAX(imported_at) m FROM formulare_registry_entities')->fetch();
    $lastImportAt = $row['m'] ?? null;
    // "Nové" = prvýkrát videné práve v poslednom importe (first_seen_at == imported_at).
    $newSinceLastImport = (int)db()->query("SELECT COUNT(*) c FROM formulare_registry_entities WHERE first_seen_at IS NOT NULL AND first_seen_at = imported_at")->fetch()['c'];
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna, alebo pred migráciou first_seen_at */ }

// -- fasety pre filter dropdowny --
$facets = ['categories' => [], 'sectors' => [], 'parent_names' => [], 'regions' => [], 'okresy' => [], 'dataset_updated' => null];
if (is_file(REGISTRY_FACETS_FILE)) {
    $decoded = json_decode((string)file_get_contents(REGISTRY_FACETS_FILE), true);
    if (is_array($decoded)) $facets = array_merge($facets, $decoded);
}

// -- vyhľadávanie / filter (GET, aby sa dalo odkázať/zdieľať) --
$q = trim((string)($_GET['q'] ?? ''));
$fCategories = array_values(array_intersect(array_map('trim', (array)($_GET['cat'] ?? [])), AGENT_CATEGORIES));
$fSector = trim((string)($_GET['sector'] ?? ''));
$fParent = trim((string)($_GET['parent'] ?? ''));
$fRegion = trim((string)($_GET['region'] ?? ''));
$fOkres = trim((string)($_GET['okres'] ?? ''));

// where bez kraja (pre prehľad počtov podľa kraja nižšie — nech vidno rozloženie
// aj pri už zvolenom kraji) a where s krajom (pre samotné výsledky/stránkovanie).
// Náborová zóna je natrvalo obmedzená len na NABOR_ACTIVE_REGIONS (rovnaké
// rozhodnutie ako AGENT_CATEGORIES) — netreba dáta z celého Slovenska.
$activeCategories = $fCategories ?: AGENT_CATEGORIES;
$where = [];
$params = [];
$where[] = '(' . implode(' OR ', array_fill(0, count($activeCategories), 'categories LIKE ?')) . ')';
foreach ($activeCategories as $c) { $params[] = '%"' . $c . '"%'; }
$where[] = '(' . implode(' OR ', array_fill(0, count(NABOR_ACTIVE_REGIONS), 'region = ?')) . ')';
foreach (NABOR_ACTIVE_REGIONS as $r) { $params[] = $r; }
if ($q !== '') { $where[] = '(name LIKE ? OR ico LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
if ($fSector !== '') { $where[] = 'sectors LIKE ?'; $params[] = '%"' . $fSector . '"%'; }
if ($fParent !== '') { $where[] = 'parent_names LIKE ?'; $params[] = '%"' . $fParent . '"%'; }
$whereSqlNoRegion = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$paramsNoRegion = $params;

$regionCounts = [];
try {
    $regStmt = db()->prepare("SELECT COALESCE(region, '— bez PSČ/kraja —') AS region, COUNT(*) c
        FROM formulare_registry_entities $whereSqlNoRegion GROUP BY region ORDER BY c DESC");
    $regStmt->execute($paramsNoRegion);
    $regionCounts = $regStmt->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }

$regionMax = $regionCounts ? max(array_column($regionCounts, 'c')) : 0;

function qs(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Náborová zóna</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=9">
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Náborová zóna</h1>
    <p>Register inštitúcií a finančných agentov (NBS) · len Prešovský a Košický kraj · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Import dát</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Súbor nahraj cez FTP do <code>data/nbs-register.json</code> (mimo verejnej časti appky), potom klikni Importovať.
      Pri každom importe sa dáta kompletne nahradia najnovším súborom.
    </p>

    <?php if ($importMessage): ?><div class="pill submitted" style="margin-bottom:12px; padding:6px 12px;"><?= $importMessage ?></div><?php endif; ?>
    <?php if ($importError): ?><div class="pill pending" style="margin-bottom:12px; padding:6px 12px; background:var(--rose-soft); color:var(--rose); border-color:#fbd0d5;">Chyba importu: <?= h($importError) ?></div><?php endif; ?>

    <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">V databáze</div>
        <div style="font-size:20px; font-weight:700; color:var(--ink);"><?= number_format($totalCount, 0, ',', ' ') ?> záznamov</div>
      </div>
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">Posledný import</div>
        <div style="font-size:13px; color:var(--ink-2);" class="date"><?= $lastImportAt ? h($lastImportAt) : '—' ?></div>
      </div>
      <?php if ($newSinceLastImport > 0): ?>
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">Nové od posledného importu</div>
        <div style="font-size:20px; font-weight:700; color:var(--good);"><?= number_format($newSinceLastImport, 0, ',', ' ') ?></div>
      </div>
      <?php endif; ?>
      <?php if ($facets['dataset_updated']): ?>
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">Dataset aktualizovaný</div>
        <div style="font-size:13px; color:var(--ink-2);" class="date"><?= h($facets['dataset_updated']) ?></div>
      </div>
      <?php endif; ?>
      <form method="post" style="margin-left:auto;">
        <input type="hidden" name="import" value="1">
        <button type="submit" class="pillbtn solid">Importovať / obnoviť dáta</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>Presné geokódovanie adries (poloha na mape)</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Len Prešovský a Košický kraj (LocationIQ, platená služba) — beží automaticky na pozadí cez Plánovač úloh.
      Kým adresa nie je geokódovaná presne, na mape sa zobrazí aspoň približne (stred obce podľa PSČ).
    </p>
    <?php if ($geocodeMessage): ?><div class="pill submitted" style="margin-bottom:12px; padding:6px 12px;"><?= h($geocodeMessage) ?></div><?php endif; ?>
    <?php if ($geocodeDebug): ?><div class="pill pending" style="margin-bottom:12px; padding:6px 12px; background:var(--rose-soft); color:var(--rose); border-color:#fbd0d5; display:block; white-space:normal;">Diagnostika (dočasné): <?= h($geocodeDebug) ?></div><?php endif; ?>
    <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">Presne nájdených</div>
        <div style="font-size:20px; font-weight:700; color:var(--good);"><?= number_format($geoStats['found'], 0, ',', ' ') ?></div>
      </div>
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">Čaká na spracovanie</div>
        <div style="font-size:20px; font-weight:700; color:var(--ink);"><?= number_format($geoStats['pending'], 0, ',', ' ') ?></div>
      </div>
      <div>
        <div style="font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;">Nenájdených</div>
        <div style="font-size:20px; font-weight:700; color:var(--muted);"><?= number_format($geoStats['not_found'], 0, ',', ' ') ?></div>
      </div>
      <form method="post" style="margin-left:auto;">
        <input type="hidden" name="geocode_batch" value="1">
        <button type="submit" class="pillbtn">Spustiť dávku teraz (~50, ručne)</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>Hľadať a filtrovať</h3>
    <form method="get" class="filter-form">
      <div class="f-field" style="min-width:220px;">
        <label>Meno / IČO</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="napr. Allianz alebo IČO">
      </div>
      <div class="f-field">
        <label>Sektor</label>
        <select name="sector">
          <option value="">Všetky</option>
          <?php foreach ($facets['sectors'] as $s): ?>
          <option value="<?= h($s) ?>" <?= $s === $fSector ? 'selected' : '' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f-field" style="min-width:220px;">
        <label>Registrovaný pod (materská spoločnosť)</label>
        <select name="parent">
          <option value="">Všetky</option>
          <?php foreach ($facets['parent_names'] as $p): ?>
          <option value="<?= h($p) ?>" <?= $p === $fParent ? 'selected' : '' ?>><?= h($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f-field">
        <label>Kraj</label>
        <select name="region">
          <option value="">Oba kraje</option>
          <?php foreach (NABOR_ACTIVE_REGIONS as $r): ?>
          <option value="<?= h($r) ?>" <?= $r === $fRegion ? 'selected' : '' ?>><?= h($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f-field">
        <label>Okres</label>
        <select name="okres">
          <option value="">Všetky okresy</option>
          <?php foreach ($facets['okresy'] as $o): ?>
          <option value="<?= h($o) ?>" <?= $o === $fOkres ? 'selected' : '' ?>><?= h($o) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f-field" style="min-width:0;">
        <button type="submit" class="pillbtn solid">Filtrovať</button>
      </div>
      <?php if ($q || $fSector || $fParent || $fRegion || $fOkres): ?>
      <div class="f-field" style="min-width:0;">
        <a class="pillbtn" href="/nabor.php">Zrušiť filter</a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($regionCounts): ?>
  <div class="card">
    <h3>Podľa kraja</h3>
    <div class="kraj-bars">
      <?php foreach ($regionCounts as $rc): ?>
      <a class="kraj-bar<?= $rc['region'] === $fRegion ? ' on' : '' ?>" href="<?= qs(['region' => $rc['region'] === $fRegion ? '' : $rc['region']]) ?>">
        <span class="kraj-name"><?= h($rc['region']) ?></span>
        <span class="kraj-track"><span class="kraj-fill" style="width:<?= $regionMax > 0 ? round($rc['c'] / $regionMax * 100) : 0 ?>%;"></span></span>
        <span class="kraj-count"><?= number_format((int)$rc['c'], 0, ',', ' ') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <h3>Mapa <span id="mapCount" style="font-weight:400; color:var(--muted); font-size:12.5px;"></span></h3>
    <p style="margin:-6px 0 12px; font-size:12.5px; color:var(--muted);">
      Poloha je približná (stred obce podľa PSČ, nie presná ulica). Priblíž sa na konkrétne mesto — pri prekrytých bodoch
      klikni na zhluk pre rozbalenie.
    </p>
    <div style="display:flex; align-items:center; gap:16px; margin:-2px 0 12px; font-size:12px; color:var(--muted);">
      <span style="display:inline-flex; align-items:center; gap:6px;"><span class="nabor-legend-dot" style="background:#4f46e5;"></span> Existujúci</span>
      <span style="display:inline-flex; align-items:center; gap:6px;"><span class="nabor-legend-dot" style="background:#059669;"></span> Nový od posledného importu</span>
    </div>
    <div id="naborMap"></div>
  </div>

</main>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function popupHtml(m) {
    var cats = (m.cats || []).map(function (c) { return '<span class="map-tag">' + esc(c) + '</span>'; }).join('');
    var sectors = (m.sectors || []).map(function (s) { return '<span class="map-tag alt">' + esc(s) + '</span>'; }).join('');
    var parents = (m.parents || []).map(function (p) { return '<span class="map-tag alt">' + esc(p) + '</span>'; }).join('');
    return '<div class="map-pop">' +
      '<b>' + esc(m.name) + '</b>' + (m.is_new ? ' <span class="map-tag new">Nové</span>' : '') +
      '<div class="map-pop-sub">' + esc(m.city || '') + (m.region ? ' · ' + esc(m.region) : '') + ' · IČO ' + esc(m.ico) + '</div>' +
      (cats ? '<div class="map-pop-row">' + cats + '</div>' : '') +
      (sectors ? '<div class="map-pop-row">' + sectors + '</div>' : '') +
      (parents ? '<div class="map-pop-row"><span class="map-pop-label">Pod:</span> ' + parents + '</div>' : '') +
      '</div>';
  }

  var pinIcons = {
    existing: L.divIcon({ className: 'nabor-pin', html: '<span></span>', iconSize: [16, 16], iconAnchor: [8, 8] }),
    fresh: L.divIcon({ className: 'nabor-pin nabor-pin-new', html: '<span></span>', iconSize: [16, 16], iconAnchor: [8, 8] })
  };

  var map = L.map('naborMap').setView([48.7, 19.5], 8);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap prispievatelia'
  }).addTo(map);

  var clusters = L.markerClusterGroup({ chunkedLoading: true, spiderfyOnMaxZoom: true });
  var countEl = document.getElementById('mapCount');

  fetch('/api/nabor-markers.php' + window.location.search, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (rows) {
      countEl.textContent = '· ' + rows.length.toLocaleString('sk-SK') + ' na mape';
      var bounds = [];
      rows.forEach(function (m) {
        var marker = L.marker([m.lat, m.lon], { icon: m.is_new ? pinIcons.fresh : pinIcons.existing });
        marker.bindPopup(popupHtml(m));
        marker.on('mouseover', function () { marker.openPopup(); });
        clusters.addLayer(marker);
        bounds.push([m.lat, m.lon]);
      });
      map.addLayer(clusters);
      if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
    })
    .catch(function () { countEl.textContent = '· chyba načítania'; });
})();
</script>
<script src="/assets/shell.js?v=6"></script>
</body></html>
