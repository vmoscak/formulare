<?php
/**
 * Evidencia kandidátov na nábor — vlastný zoznam ľudí, ktorých si oslovil/-a
 * ty alebo ktorí oslovili teba, nezávislý od registra NBS/mapy (nabor.php).
 * Kandidát nemusí byť vôbec v tom datasete — táto evidencia je čisto tvoja
 * poznámka/pipeline, nie prepojená s formulare_registry_entities.
 *
 * Prístup VÝHRADNE pre poradcu s is_owner=1 (rovnaká zásada ako nabor.php).
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

const RK_STATUSES = [
    'novy'       => ['Nový kontakt', 'neutral'],
    'oslovene'   => ['Oslovený/á', 'warn'],
    'zaujem'     => ['Prejavil/a záujem', 'accent'],
    'stretnutie' => ['Dohodnuté stretnutie', 'accent'],
    'pripojil'   => ['Pripojil/a sa', 'ok'],
    'odmietol'   => ['Odmietol/a', 'bad'],
    'stratil'    => ['Stratený kontakt', 'neutral'],
];
const RK_INITIATORS = [
    'ja'  => 'Oslovil/-a som ja',
    'oni' => 'Oslovil/-a ma on/ona',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $initiator = array_key_exists($_POST['initiator'] ?? '', RK_INITIATORS) ? $_POST['initiator'] : 'ja';
        $status = array_key_exists($_POST['status'] ?? '', RK_STATUSES) ? $_POST['status'] : 'novy';
        $note = trim((string)($_POST['note'] ?? ''));
        $contactDate = trim((string)($_POST['contact_date'] ?? '')) ?: null;
        if ($name !== '') {
            db()->prepare('INSERT INTO formulare_recruit_candidates (name, phone, email, initiator, status, note, contact_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$name, $phone, $email, $initiator, $status, $note, $contactDate, $advisorId]);
        }
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $initiator = array_key_exists($_POST['initiator'] ?? '', RK_INITIATORS) ? $_POST['initiator'] : 'ja';
        $status = array_key_exists($_POST['status'] ?? '', RK_STATUSES) ? $_POST['status'] : 'novy';
        $note = trim((string)($_POST['note'] ?? ''));
        $contactDate = trim((string)($_POST['contact_date'] ?? '')) ?: null;
        if ($id && $name !== '') {
            db()->prepare('UPDATE formulare_recruit_candidates SET name = ?, phone = ?, email = ?, initiator = ?, status = ?, note = ?, contact_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$name, $phone, $email, $initiator, $status, $note, $contactDate, $id]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_recruit_candidates WHERE id = ?')->execute([$id]);
    }
    header('Location: /nabor-kandidati.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

// Stavy, v ktorých je kandidát ešte "v jednaní" — mimo nich (pripojil/odmietol/
// stratil) sa zaspatosť kontaktu už nerieši, vec je uzavretá.
const RK_ACTIVE_STATUSES = ['novy', 'oslovene', 'zaujem', 'stretnutie'];
const RK_STALE_DAYS = 14;

$fStatus = trim((string)($_GET['status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$fStale = isset($_GET['stale']) && $_GET['stale'] === '1';
$view = ($_GET['view'] ?? 'list') === 'kanban' ? 'kanban' : 'list';

$where = [];
$params = [];
if ($fStatus !== '' && array_key_exists($fStatus, RK_STATUSES)) {
    $where[] = 'status = ?';
    $params[] = $fStatus;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%';
}
$staleThreshold = date('Y-m-d', strtotime('-' . RK_STALE_DAYS . ' days'));
if ($fStale) {
    $where[] = '(' . implode(' OR ', array_fill(0, count(RK_ACTIVE_STATUSES), 'status = ?')) . ')';
    foreach (RK_ACTIVE_STATUSES as $s) { $params[] = $s; }
    $where[] = '(contact_date IS NULL OR contact_date < ?)';
    $params[] = $staleThreshold;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = db()->prepare("SELECT * FROM formulare_recruit_candidates $whereSql ORDER BY updated_at DESC, id DESC");
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Zaspatosť sa počíta per riadok (nie v SQL vyššie, keď stale filter nie je
// aktívny) — nech sa dá zobraziť badge aj mimo filtra "Bez pohybu".
foreach ($candidates as &$c) {
    $isActiveStatus = in_array($c['status'], RK_ACTIVE_STATUSES, true);
    $c['is_stale'] = $isActiveStatus && (!$c['contact_date'] || $c['contact_date'] < $staleThreshold);
}
unset($c);

$statusCounts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM formulare_recruit_candidates GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int)$r['c'];
}
$totalCount = array_sum($statusCounts);
$activeCount = ($statusCounts['novy'] ?? 0) + ($statusCounts['oslovene'] ?? 0) + ($statusCounts['zaujem'] ?? 0) + ($statusCounts['stretnutie'] ?? 0);
$joinedCount = $statusCounts['pripojil'] ?? 0;

$staleCount = 0;
try {
    $stStmt = db()->prepare(
        'SELECT COUNT(*) FROM formulare_recruit_candidates WHERE (' . implode(' OR ', array_fill(0, count(RK_ACTIVE_STATUSES), 'status = ?')) . ') AND (contact_date IS NULL OR contact_date < ?)'
    );
    $stStmt->execute(array_merge(RK_ACTIVE_STATUSES, [$staleThreshold]));
    $staleCount = (int)$stStmt->fetchColumn();
} catch (Throwable $e) { /* nič */ }

function rkQs(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Kandidáti na nábor</title>
<link rel="stylesheet" href="/assets/fonts.css">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=28">
<style>
  .rk-status{display:inline-flex; align-items:center; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;}
  .rk-status.neutral{background:var(--desk); color:var(--muted);}
  .rk-status.warn{background:var(--amber-soft); color:var(--amber);}
  .rk-status.accent{background:var(--accent-soft); color:var(--accent);}
  .rk-status.ok{background:var(--good-soft); color:var(--good);}
  .rk-status.bad{background:var(--rose-soft); color:var(--rose);}
  .rk-row{display:flex; align-items:flex-start; gap:12px; padding:14px 4px; border-bottom:1px solid var(--border);}
  .rk-row:last-child{border-bottom:none;}
  .rk-main{flex:1; min-width:0;}
  .rk-name-line{display:flex; align-items:center; gap:8px; flex-wrap:wrap;}
  .rk-name{font-size:14px; font-weight:700; color:var(--ink);}
  .rk-meta{font-size:12.5px; color:var(--muted); margin-top:3px;}
  .rk-note{font-size:12.5px; color:var(--ink-2); margin-top:6px; line-height:1.5; white-space:pre-wrap;}
  .rk-actions{display:flex; align-items:center; gap:6px; flex-shrink:0;}
  .rk-edit-form{display:none; flex-direction:column; gap:10px; margin-bottom:12px;}
  .rk-add-row{display:grid; grid-template-columns:2fr 1fr 1fr; gap:10px;}
  .rk-add-row2{display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;}
  @media(max-width:720px){ .rk-add-row,.rk-add-row2{grid-template-columns:1fr;} }
  .rk-stats{display:flex; align-items:center; gap:22px; flex-wrap:wrap;}
  .rk-stat-num{font-size:20px; font-weight:700; color:var(--ink);}
  .rk-stat-label{font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;}
  .rk-stale-badge{display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:700; color:var(--amber); background:var(--amber-soft); padding:2px 8px; border-radius:999px; margin-left:8px; white-space:nowrap;}
  .rk-filter-row{display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;}
  .rk-search{flex:1; min-width:220px; display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--desk);}
  .rk-search input{flex:1; border:none; background:transparent; font-size:13.5px; color:var(--ink); outline:none;}
  .rk-search svg{flex-shrink:0; color:var(--muted);}
  .rk-view-toggle{display:flex; gap:6px; flex-shrink:0;}
  .rk-kanban{display:flex; gap:12px; overflow-x:auto; padding-bottom:6px;}
  .rk-kanban-col{flex:0 0 250px; background:var(--desk); border-radius:var(--radius-md); padding:10px; display:flex; flex-direction:column; gap:8px;}
  .rk-kanban-col-head{display:flex; align-items:center; justify-content:space-between; padding:2px 4px 6px; border-bottom:1px solid var(--border);}
  .rk-kanban-col-title{font-size:12px; font-weight:700; color:var(--ink-2);}
  .rk-kanban-col-count{font-size:11px; font-weight:700; color:var(--muted);}
  .rk-kanban-card{background:var(--paper); border:1px solid var(--border); border-radius:var(--radius-md); padding:10px; display:flex; flex-direction:column; gap:5px;}
  .rk-kanban-name{font-size:13px; font-weight:700; color:var(--ink);}
  .rk-kanban-meta{font-size:11.5px; color:var(--muted); line-height:1.4;}
  .rk-kanban-note{font-size:11.5px; color:var(--ink-2); line-height:1.4; white-space:pre-wrap;}
  .rk-kanban-move{width:100%; font-size:11.5px; padding:5px 6px; border-radius:6px; border:1px solid var(--border); background:var(--desk); color:var(--ink-2); margin-top:2px;}
  .rk-kanban-empty{font-size:11.5px; color:var(--muted); text-align:center; padding:10px 4px;}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Kandidáti na nábor</h1>
    <p>Ľudia, ktorých si oslovil/-a ty alebo ktorí oslovili teba — nezávisle od registra/mapy · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nabor.php">Register a mapa (NBS) →</a>
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card rk-stats">
    <div>
      <div class="rk-stat-label">Celkovo evidovaných</div>
      <div class="rk-stat-num"><?= $totalCount ?></div>
    </div>
    <div>
      <div class="rk-stat-label">Aktívne v jednaní</div>
      <div class="rk-stat-num"><?= $activeCount ?></div>
    </div>
    <div>
      <div class="rk-stat-label">Pripojili sa</div>
      <div class="rk-stat-num" style="color:var(--good);"><?= $joinedCount ?></div>
    </div>
    <?php if ($staleCount > 0): ?>
    <div>
      <div class="rk-stat-label">Bez pohybu &gt; <?= RK_STALE_DAYS ?> dní</div>
      <div class="rk-stat-num" style="color:var(--amber);"><?= $staleCount ?></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Hľadať a filtrovať</h3>
    <form method="get" class="rk-search" style="margin-bottom:12px;">
      <?php if ($fStatus !== ''): ?><input type="hidden" name="status" value="<?= h($fStatus) ?>"><?php endif; ?>
      <?php if ($fStale): ?><input type="hidden" name="stale" value="1"><?php endif; ?>
      <?php if ($view === 'kanban'): ?><input type="hidden" name="view" value="kanban"><?php endif; ?>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Hľadať podľa mena, telefónu alebo e-mailu…">
      <button type="submit" class="toggle-btn">Hľadať</button>
      <?php if ($q !== ''): ?><a class="toggle-btn" href="<?= rkQs(['q' => '']) ?>">✕</a><?php endif; ?>
    </form>
    <div class="rk-filter-row">
      <a class="pillbtn<?= $fStatus === '' && !$fStale ? ' solid' : '' ?>" href="<?= rkQs(['status' => '', 'stale' => '']) ?>">Všetci (<?= $totalCount ?>)</a>
      <?php foreach (RK_STATUSES as $key => $meta): ?>
      <a class="pillbtn<?= $fStatus === $key ? ' solid' : '' ?>" href="<?= rkQs(['status' => $key, 'stale' => '']) ?>"><?= h($meta[0]) ?> (<?= $statusCounts[$key] ?? 0 ?>)</a>
      <?php endforeach; ?>
      <?php if ($staleCount > 0): ?>
      <a class="pillbtn<?= $fStale ? ' solid' : '' ?>" href="<?= rkQs(['stale' => $fStale ? '' : '1', 'status' => '']) ?>">⚠️ Bez pohybu (<?= $staleCount ?>)</a>
      <?php endif; ?>
      <div class="rk-view-toggle" style="margin-left:auto;">
        <a class="pillbtn<?= $view === 'list' ? ' solid' : '' ?>" href="<?= rkQs(['view' => '']) ?>">☰ Zoznam</a>
        <a class="pillbtn<?= $view === 'kanban' ? ' solid' : '' ?>" href="<?= rkQs(['view' => 'kanban']) ?>">▦ Kanban</a>
      </div>
    </div>
  </div>

  <?php if ($view === 'kanban'): ?>
  <div class="card">
    <h3>Kanban podľa stavu</h3>
    <div class="rk-kanban">
      <?php foreach (RK_STATUSES as $key => $meta): $colCandidates = array_values(array_filter($candidates, fn($c) => $c['status'] === $key)); ?>
      <div class="rk-kanban-col">
        <div class="rk-kanban-col-head">
          <span class="rk-kanban-col-title"><?= h($meta[0]) ?></span>
          <span class="rk-kanban-col-count"><?= count($colCandidates) ?></span>
        </div>
        <?php if (!$colCandidates): ?>
        <div class="rk-kanban-empty">—</div>
        <?php endif; ?>
        <?php foreach ($colCandidates as $c): ?>
        <div class="rk-kanban-card" id="rk-kcard-<?= (int)$c['id'] ?>">
          <div class="rk-kanban-name"><?= h($c['name']) ?><?php if ($c['is_stale']): ?><span class="rk-stale-badge">⚠️ bez pohybu</span><?php endif; ?></div>
          <div class="rk-kanban-meta">
            <?= h(RK_INITIATORS[$c['initiator']] ?? '') ?>
            <?php if ($c['phone']): ?><br><?= h($c['phone']) ?><?php endif; ?>
            <?php if ($c['contact_date']): ?><br>posledný kontakt <?= h(date('j. n. Y', strtotime($c['contact_date']))) ?><?php endif; ?>
          </div>
          <?php if ($c['note']): ?><div class="rk-kanban-note"><?= h($c['note']) ?></div><?php endif; ?>
          <select class="rk-kanban-move" data-id="<?= (int)$c['id'] ?>" onchange="rkMoveCard(this)">
            <?php foreach (RK_STATUSES as $mKey => $mMeta): ?>
            <option value="<?= h($mKey) ?>" <?= $mKey === $key ? 'selected' : '' ?>><?= h($mMeta[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <h3>Zoznam kandidátov</h3>
    <?php if (!$candidates): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
        <span class="es-title">Zatiaľ žiadni kandidáti</span>
        <span class="es-sub">Pridaj prvého kandidáta nižšie — nemusí byť z mapy, môže to byť ktokoľvek, koho si oslovil/-a alebo kto oslovil teba.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($candidates as $c): $st = RK_STATUSES[$c['status']] ?? ['—', 'neutral']; ?>
    <div class="rk-row" id="rk-row-<?= (int)$c['id'] ?>">
      <div class="rk-main">
        <div class="rk-name-line">
          <span class="rk-name"><?= h($c['name']) ?></span>
          <span class="rk-status <?= h($st[1]) ?>"><?= h($st[0]) ?></span>
          <?php if ($c['is_stale']): ?><span class="rk-stale-badge">⚠️ bez pohybu</span><?php endif; ?>
        </div>
        <div class="rk-meta">
          <?= h(RK_INITIATORS[$c['initiator']] ?? '') ?>
          <?php if ($c['phone']): ?> · <?= h($c['phone']) ?><?php endif; ?>
          <?php if ($c['email']): ?> · <?= h($c['email']) ?><?php endif; ?>
          <?php if ($c['contact_date']): ?> · posledný kontakt <?= h(date('j. n. Y', strtotime($c['contact_date']))) ?><?php endif; ?>
        </div>
        <?php if ($c['note']): ?><div class="rk-note"><?= h($c['note']) ?></div><?php endif; ?>
      </div>
      <div class="rk-actions">
        <button type="button" class="toggle-btn" onclick="rkEdit(<?= (int)$c['id'] ?>)">Upraviť</button>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tohto kandidáta?');">
          <input type="hidden" name="delete_id" value="<?= (int)$c['id'] ?>">
          <button type="submit" class="toggle-btn">Zmazať</button>
        </form>
      </div>
    </div>
    <form method="post" class="rk-edit-form" id="rk-edit-<?= (int)$c['id'] ?>">
      <input type="hidden" name="edit_id" value="<?= (int)$c['id'] ?>">
      <div class="rk-add-row">
        <input type="text" name="name" value="<?= h($c['name']) ?>" placeholder="Meno a priezvisko" required>
        <input type="text" name="phone" value="<?= h($c['phone']) ?>" placeholder="Telefón">
        <input type="text" name="email" value="<?= h($c['email']) ?>" placeholder="E-mail">
      </div>
      <div class="rk-add-row2">
        <select name="initiator">
          <?php foreach (RK_INITIATORS as $key => $label): ?>
          <option value="<?= h($key) ?>" <?= $c['initiator'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <?php foreach (RK_STATUSES as $key => $meta): ?>
          <option value="<?= h($key) ?>" <?= $c['status'] === $key ? 'selected' : '' ?>><?= h($meta[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="contact_date" value="<?= h((string)$c['contact_date']) ?>">
      </div>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné)"><?= h($c['note']) ?></textarea>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <button type="button" class="pillbtn" onclick="rkCancel(<?= (int)$c['id'] ?>)">Zrušiť</button>
      </div>
    </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h3>Pridať kandidáta</h3>
    <form method="post" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="add" value="1">
      <div class="rk-add-row">
        <input type="text" name="name" placeholder="Meno a priezvisko" required>
        <input type="text" name="phone" placeholder="Telefón">
        <input type="text" name="email" placeholder="E-mail">
      </div>
      <div class="rk-add-row2">
        <select name="initiator">
          <?php foreach (RK_INITIATORS as $key => $label): ?>
          <option value="<?= h($key) ?>"><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <?php foreach (RK_STATUSES as $key => $meta): ?>
          <option value="<?= h($key) ?>" <?= $key === 'novy' ? 'selected' : '' ?>><?= h($meta[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" name="contact_date" value="<?= h(date('Y-m-d')) ?>">
      </div>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné) — odkiaľ ho poznáš, o čom ste sa rozprávali..."></textarea>
      <button type="submit" class="pillbtn solid" style="align-self:start; width:max-content;">Pridať kandidáta</button>
    </form>
  </div>

</main>
<script>
function rkEdit(id) {
  document.getElementById('rk-row-' + id).style.display = 'none';
  document.getElementById('rk-edit-' + id).style.display = 'flex';
}
function rkCancel(id) {
  document.getElementById('rk-row-' + id).style.display = 'flex';
  document.getElementById('rk-edit-' + id).style.display = 'none';
}
function rkMoveCard(sel) {
  var id = +sel.dataset.id;
  var status = sel.value;
  var card = document.getElementById('rk-kcard-' + id);
  sel.disabled = true;
  fetch('/api/nabor-candidate-status.php', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: id, status: status })
  }).then(function (r) { return r.json(); }).then(function (data) {
    if (!data.ok) throw new Error();
    if (window.showToast) showToast('Stav kandidáta zmenený.', 'success');
    location.reload();
  }).catch(function () {
    sel.disabled = false;
    if (window.showToast) showToast('Nepodarilo sa zmeniť stav.', 'error');
  });
  if (card) card.style.opacity = '.5';
}
</script>
<script src="/assets/toast.js"></script>
<script src="/assets/shell.js?v=21"></script>
</body></html>
