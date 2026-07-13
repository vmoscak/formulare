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

$fStatus = trim((string)($_GET['status'] ?? ''));
$where = '';
$params = [];
if ($fStatus !== '' && array_key_exists($fStatus, RK_STATUSES)) {
    $where = 'WHERE status = ?';
    $params[] = $fStatus;
}
$stmt = db()->prepare("SELECT * FROM formulare_recruit_candidates $where ORDER BY updated_at DESC, id DESC");
$stmt->execute($params);
$candidates = $stmt->fetchAll();

$statusCounts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM formulare_recruit_candidates GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int)$r['c'];
}
$totalCount = array_sum($statusCounts);
$activeCount = ($statusCounts['novy'] ?? 0) + ($statusCounts['oslovene'] ?? 0) + ($statusCounts['zaujem'] ?? 0) + ($statusCounts['stretnutie'] ?? 0);
$joinedCount = $statusCounts['pripojil'] ?? 0;
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Kandidáti na nábor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=24">
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
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Kandidáti na nábor</h1>
    <p>Ľudia, ktorých si oslovil/-a ty alebo ktorí oslovili teba — nezávisle od registra/mapy · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nabor.php">← Náborová zóna (register a mapa)</a>
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
  </div>

  <div class="card">
    <h3>Filtrovať podľa stavu</h3>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <a class="pillbtn<?= $fStatus === '' ? ' solid' : '' ?>" href="/nabor-kandidati.php">Všetci (<?= $totalCount ?>)</a>
      <?php foreach (RK_STATUSES as $key => $meta): ?>
      <a class="pillbtn<?= $fStatus === $key ? ' solid' : '' ?>" href="/nabor-kandidati.php?status=<?= urlencode($key) ?>"><?= h($meta[0]) ?> (<?= $statusCounts[$key] ?? 0 ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>

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
</script>
<script src="/assets/shell.js?v=18"></script>
</body></html>
