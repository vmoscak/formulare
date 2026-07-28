<?php
/**
 * Aktuálne postupy (predtým "Znalostná báza", tabuľka aj URL ostávajú
 * formulare_knowledge_base / znalostna-baza.php — premenované len viditeľne,
 * nech to poradcovia reálne používajú). Krátke trvalé postupy/FAQ. Čítanie
 * majú všetci aktívni poradcovia (zdieľaný tímový obsah) — pridávať,
 * upravovať a mazať záznamy môže len owner (is_owner=1), rovnako ako v
 * nabor.php. Teraz aj v spodnom plávajúcom doku (assets/shell.js), predtým
 * bola zámerne mimo navigácie a nikto ju nepoužíval.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }
$isOwner = !empty($me['is_owner']);

// Pevný zoznam kategórií (nie voľný text) — nech sa dá podľa nich naozaj
// filtrovať a farebne rozlíšiť, bez rizika, že si každý napíše niečo trochu
// inak ("Auto" vs. "auto poistenie"). Farby preberajú tú istú paletu ako
// plávajúci dok a karty nástrojov na Domov.
$KB_CATEGORIES = [
    'zivotne'   => ['label' => 'Životné poistenie',        'c1' => '#10b981', 'c2' => '#059669'],
    'auto'      => ['label' => 'Poistenie auta',            'c1' => '#38bdf8', 'c2' => '#0284c7'],
    'majetok'   => ['label' => 'Majetok a domácnosť',       'c1' => '#fbbf24', 'c2' => '#d97706'],
    'skody'     => ['label' => 'Škody a reklamácie',        'c1' => '#fb7185', 'c2' => '#e11d48'],
    'zmluvy'    => ['label' => 'Zmluvy a administratíva',   'c1' => '#4f46e5', 'c2' => '#4338ca'],
    'vseobecne' => ['label' => 'Všeobecné',                 'c1' => '#a78bfa', 'c2' => '#7c3aed'],
];
function kbCategory(array $cats, ?string $key): array {
    return $cats[$key] ?? end($cats);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    if (!csrfCheck()) { http_response_code(403); exit('Neplatný CSRF token — obnov stránku a skús to znova.'); }
    $category = (string)($_POST['category'] ?? 'vseobecne');
    if (!isset($KB_CATEGORIES[$category])) { $category = 'vseobecne'; }
    if (isset($_POST['add'])) {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if ($title !== '' && $body !== '') {
            db()->prepare('INSERT INTO formulare_knowledge_base (title, body, category, advisor_id, advisor_name) VALUES (?, ?, ?, ?, ?)')
                ->execute([$title, $body, $category, $advisorId, $me['name']]);
        }
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if ($id && $title !== '' && $body !== '') {
            db()->prepare("UPDATE formulare_knowledge_base SET title = ?, body = ?, category = ?, updated_at = ? WHERE id = ?")
                ->execute([$title, $body, $category, date('Y-m-d H:i:s'), $id]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_knowledge_base WHERE id = ?')->execute([$id]);
    }
    header('Location: /znalostna-baza.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$cat = (string)($_GET['cat'] ?? '');
if ($cat !== '' && !isset($KB_CATEGORIES[$cat])) { $cat = ''; }
$entries = [];
try {
    $where = [];
    $params = [];
    if ($q !== '') { $where[] = '(title LIKE ? OR body LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
    if ($cat !== '') { $where[] = 'category = ?'; $params[] = $cat; }
    $sql = 'SELECT * FROM formulare_knowledge_base' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY title';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();
} catch (Throwable $e) { /* tabuľka/stĺpec category ešte nemusí existovať — spusti sql/046 */ }
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Aktuálne postupy a informácie</title>
<link rel="stylesheet" href="<?= asset('fonts.css') ?>">
<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('panel.css') ?>">
<style>
  /* Pridávanie/úprava/mazanie je aj pre ownera schované, kým si v ľavej lište
     nezapne admin režim (assets/shell.js, localStorage 'adminView') — bežne
     má vidieť presne to isté, čo ostatní poradcovia. Skutočné oprávnenie
     (POST akcie) je stále gate-nuté server-side na is_owner, toto je len
     viditeľnosť ovládacích prvkov. */
  .owner-only{display:none;}
  body.admin-view-on .owner-only{display:revert;}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Aktuálne postupy a informácie</h1>
    <p>Krátke návody a postupy · zdieľané pre celý tím</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Hľadať</h3>
    <form method="get" class="filter-form">
      <div class="f-field" style="min-width:280px;">
        <label>Text</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="napr. výpoveď, ČSOB, reklamácia...">
      </div>
      <div class="f-field" style="min-width:200px;">
        <label>Kategória</label>
        <select name="cat">
          <option value="">Všetky kategórie</option>
          <?php foreach ($KB_CATEGORIES as $ck => $cv): ?>
          <option value="<?= h($ck) ?>" <?= $cat === $ck ? 'selected' : '' ?>><?= h($cv['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="f-field" style="min-width:0;">
        <button type="submit" class="pillbtn solid">Hľadať</button>
      </div>
      <?php if ($q || $cat): ?>
      <div class="f-field" style="min-width:0;">
        <a class="pillbtn" href="/znalostna-baza.php">Zrušiť</a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($isOwner): ?>
  <div class="card owner-only">
    <h3>Pridať nový záznam</h3>
    <form method="post" class="kb-form">
      <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="add" value="1">
      <input type="text" name="title" placeholder="Názov (napr. „ČSOB – čo pýtať pri zaseknutej likvidácii“)" required>
      <select name="category">
        <?php foreach ($KB_CATEGORIES as $ck => $cv): ?>
        <option value="<?= h($ck) ?>" <?= $ck === 'vseobecne' ? 'selected' : '' ?>><?= h($cv['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <textarea name="body" rows="4" placeholder="Text, ktorý sa dá skopírovať a poslať/vložiť..." required></textarea>
      <button type="submit" class="pillbtn solid" style="align-self:flex-start;">Pridať</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="card">
    <h3>Záznamy · <?= count($entries) ?></h3>
    <div class="kb-list">
      <?php foreach ($entries as $e): $ecat = kbCategory($KB_CATEGORIES, $e['category'] ?? null); ?>
      <div class="kb-item" id="kb-<?= (int)$e['id'] ?>" style="--kb-c:<?= h($ecat['c2']) ?>; --kb-c1:<?= h($ecat['c1']) ?>; --kb-c2:<?= h($ecat['c2']) ?>;">
        <div class="kb-view">
          <div class="kb-head">
            <div>
              <span class="kb-cat-badge"><?= h($ecat['label']) ?></span>
              <h4><?= h($e['title']) ?></h4>
            </div>
            <div class="kb-actions">
              <button type="button" class="toggle-btn" onclick="kbCopy(<?= (int)$e['id'] ?>)">Kopírovať</button>
              <?php if ($isOwner): ?>
              <button type="button" class="toggle-btn owner-only" onclick="kbEdit(<?= (int)$e['id'] ?>)">Upraviť</button>
              <form method="post" class="owner-only" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento záznam?');">
                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="delete_id" value="<?= (int)$e['id'] ?>">
                <button type="submit" class="toggle-btn">Zmazať</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <p class="kb-body" data-raw="<?= h($e['body']) ?>"><?= nl2br(h($e['body'])) ?></p>
          <button type="button" class="kb-more" onclick="kbToggleMore(this)">Zobraziť celé</button>
          <div class="kb-meta">Pridal <?= h($e['advisor_name']) ?> · <span class="date"><?= h($e['created_at']) ?></span></div>
        </div>
        <?php if ($isOwner): ?>
        <form method="post" class="kb-edit" style="display:none;">
          <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="edit_id" value="<?= (int)$e['id'] ?>">
          <input type="text" name="title" value="<?= h($e['title']) ?>" required>
          <select name="category">
            <?php foreach ($KB_CATEGORIES as $ck => $cv): ?>
            <option value="<?= h($ck) ?>" <?= ($e['category'] ?? 'vseobecne') === $ck ? 'selected' : '' ?>><?= h($cv['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <textarea name="body" rows="4" required><?= h($e['body']) ?></textarea>
          <div style="display:flex; gap:8px;">
            <button type="submit" class="pillbtn solid">Uložiť</button>
            <button type="button" class="pillbtn" onclick="kbCancel(<?= (int)$e['id'] ?>)">Zrušiť</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$entries): ?><div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span class="es-title">Žiadne výsledky<?= $q ? ' pre „' . h($q) . '“' : '' ?></span>
        <span class="es-sub"><?= $q ? 'Skús iné slovo.' : 'Zatiaľ tu nič nie je.' ?><?php if ($isOwner): ?><span class="owner-only"> <?= $q ? 'Alebo pridaj nový záznam nižšie.' : 'Pridaj prvý záznam nižšie.' ?></span><?php endif; ?></span>
      </div><?php endif; ?>
    </div>
  </div>

</main>
<script>
function kbEdit(id) {
  var item = document.getElementById('kb-' + id);
  item.querySelector('.kb-view').style.display = 'none';
  item.querySelector('.kb-edit').style.display = 'flex';
}
function kbCancel(id) {
  var item = document.getElementById('kb-' + id);
  item.querySelector('.kb-view').style.display = 'block';
  item.querySelector('.kb-edit').style.display = 'none';
}
function kbCopy(id) {
  var item = document.getElementById('kb-' + id);
  var text = item.querySelector('.kb-body').dataset.raw;
  navigator.clipboard.writeText(text).catch(function () {});
}
// "Zobraziť celé" — text je skrátený na pár riadkov (CSS max-height), bez
// posuvnej lišty vnútri karty; kliknutím sa odkryje celý. Tlačidlo sa ukáže
// len pri záznamoch, kde sa text naozaj neveľme celý (porovnanie skutočnej
// a zobrazenej výšky).
function kbToggleMore(btn) {
  var body = btn.previousElementSibling;
  var expanded = body.classList.toggle('expanded');
  btn.textContent = expanded ? 'Skryť' : 'Zobraziť celé';
}
(function () {
  document.querySelectorAll('.kb-body').forEach(function (el) {
    if (el.scrollHeight > el.clientHeight + 2) {
      el.classList.add('truncated');
      var btn = el.nextElementSibling;
      if (btn && btn.classList.contains('kb-more')) btn.classList.add('show');
    }
  });
})();
// Rovnaký prepínač ako admin režim v ľavej lište (assets/shell.js) — kým je
// vypnutý, aj owner vidí presne to isté, čo bežný poradca (len čítanie).
(function () {
  try {
    if (localStorage.getItem('adminView') === '1') {
      document.body.classList.add('admin-view-on');
    }
  } catch (e) {}
})();
</script>
<script src="<?= asset('shell.js') ?>"></script>
</body></html>
