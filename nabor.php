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

// -- štatistiky --
$totalCount = 0;
$lastImportAt = null;
try {
    $totalCount = (int)db()->query('SELECT COUNT(*) c FROM formulare_registry_entities')->fetch()['c'];
    $row = db()->query('SELECT MAX(imported_at) m FROM formulare_registry_entities')->fetch();
    $lastImportAt = $row['m'] ?? null;
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }

// -- fasety pre filter dropdowny --
$facets = ['categories' => [], 'sectors' => [], 'parent_names' => [], 'dataset_updated' => null];
if (is_file(REGISTRY_FACETS_FILE)) {
    $decoded = json_decode((string)file_get_contents(REGISTRY_FACETS_FILE), true);
    if (is_array($decoded)) $facets = array_merge($facets, $decoded);
}

// -- vyhľadávanie / filter (GET, aby sa dalo odkázať/zdieľať) --
$q = trim((string)($_GET['q'] ?? ''));
$fCategories = array_values(array_filter(array_map('trim', (array)($_GET['cat'] ?? []))));
$fSector = trim((string)($_GET['sector'] ?? ''));
$fParent = trim((string)($_GET['parent'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where = [];
$params = [];
if ($q !== '') { $where[] = '(name LIKE ? OR ico LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
if ($fCategories) {
    $where[] = '(' . implode(' OR ', array_fill(0, count($fCategories), 'categories LIKE ?')) . ')';
    foreach ($fCategories as $c) { $params[] = '%"' . $c . '"%'; }
}
if ($fSector !== '') { $where[] = 'sectors LIKE ?'; $params[] = '%"' . $fSector . '"%'; }
if ($fParent !== '') { $where[] = 'parent_names LIKE ?'; $params[] = '%"' . $fParent . '"%'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$results = [];
$resultCount = 0;
try {
    $countStmt = db()->prepare("SELECT COUNT(*) c FROM formulare_registry_entities $whereSql");
    $countStmt->execute($params);
    $resultCount = (int)$countStmt->fetch()['c'];

    $offset = ($page - 1) * $perPage;
    $listStmt = db()->prepare("SELECT ico, name, address, city, categories, sectors, parent_names, raw_json
        FROM formulare_registry_entities $whereSql ORDER BY name LIMIT $perPage OFFSET $offset");
    $listStmt->execute($params);
    $results = $listStmt->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }

$totalPages = max(1, (int)ceil($resultCount / $perPage));

function pillList($jsonText, string $cls = 'tag'): string {
    $items = json_decode((string)$jsonText, true);
    if (!is_array($items) || !$items) return '<span class="na">—</span>';
    return implode('', array_map(fn($i) => '<span class="' . $cls . '">' . h($i) . '</span>', $items));
}

/** Kategórie s dátumom "od" (registrácia) — čítané priamo z raw_json licencií. */
function categoryPills($rawJsonText): string {
    $licenses = json_decode((string)$rawJsonText, true);
    if (!is_array($licenses) || !$licenses) return '<span class="na">—</span>';
    $out = [];
    foreach ($licenses as $item) {
        if (!is_array($item) || empty($item['scope'])) continue;
        $since = !empty($item['valid_from']) ? '<span class="since">od ' . h($item['valid_from']) . '</span>' : '';
        $out[] = '<span class="tag accent">' . h($item['scope']) . $since . '</span>';
    }
    if (!$out) return '<span class="na">—</span>';
    return implode('', $out);
}

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
<link rel="stylesheet" href="/assets/panel.css?v=3">
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Náborová zóna</h1>
    <p>Register inštitúcií a finančných agentov (NBS) · viditeľné len tebe</p>
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
    <h3>Hľadať a filtrovať</h3>
    <form method="get" class="filter-form">
      <div class="f-field" style="min-width:220px;">
        <label>Meno / IČO</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="napr. Allianz alebo IČO">
      </div>
      <div class="f-field" style="min-width:260px;">
        <label>Kategória <span style="font-weight:400; text-transform:none; letter-spacing:0;">(môžeš vybrať viac)</span></label>
        <div class="chk-group">
          <?php foreach ($facets['categories'] as $c): ?>
          <label class="chk"><input type="checkbox" name="cat[]" value="<?= h($c) ?>" <?= in_array($c, $fCategories, true) ? 'checked' : '' ?>><?= h($c) ?></label>
          <?php endforeach; ?>
        </div>
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
      <div class="f-field" style="min-width:0;">
        <button type="submit" class="pillbtn solid">Filtrovať</button>
      </div>
      <?php if ($q || $fCategories || $fSector || $fParent): ?>
      <div class="f-field" style="min-width:0;">
        <a class="pillbtn" href="/nabor.php">Zrušiť filter</a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <h3>Výsledky · <?= number_format($resultCount, 0, ',', ' ') ?><?= $resultCount === 1 ? ' záznam' : ($resultCount >= 2 && $resultCount <= 4 ? ' záznamy' : ' záznamov') ?></h3>
    <table class="registry-table">
      <colgroup>
        <col class="c-ico"><col class="c-name"><col class="c-address">
        <col class="c-cat"><col class="c-sector"><col class="c-parent">
      </colgroup>
      <tr><th>IČO</th><th>Názov</th><th>Adresa</th><th>Kategórie</th><th>Sektory</th><th>Registrovaný pod</th></tr>
      <?php foreach ($results as $r): ?>
      <tr>
        <td class="mono"><?= h($r['ico']) ?></td>
        <td><span class="strong"><?= h($r['name']) ?></span></td>
        <td><?= h($r['city'] ?: $r['address']) ?></td>
        <td><?= categoryPills($r['raw_json']) ?></td>
        <td><?= pillList($r['sectors']) ?></td>
        <td><?= pillList($r['parent_names']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$results): ?><tr><td colspan="6" class="empty">Žiadne záznamy — over import, prípadne uprav filter.</td></tr><?php endif; ?>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pager">
      <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= qs(['page' => $page - 1]) ?>">← Predošlá</a>
      <span>Strana <?= $page ?> / <?= $totalPages ?></span>
      <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= qs(['page' => $page + 1]) ?>">Ďalšia →</a>
    </div>
    <?php endif; ?>
  </div>

</main>
<script src="/assets/shell.js?v=3"></script>
</body></html>
