<?php
/**
 * Tímový kalendár — spoločný zoznam dôležitých termínov a dátumov (napr.
 * uzávierky, školenia, tímové stretnutia). Vidí každý prihlásený poradca,
 * pridávať/upravovať/mazať smie výhradne owner (rovnaký vzor ako
 * novinky.php — editor obsahu pre všetkých, edituje len jeden človek).
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($me['is_owner'])) { header('Location: /tim-kalendar.php'); exit; }
    if (isset($_POST['add'])) {
        $eventDate = trim((string)($_POST['event_date'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($eventDate !== '' && $title !== '') {
            db()->prepare('INSERT INTO formulare_team_events (event_date, title, note, created_by) VALUES (?, ?, ?, ?)')
                ->execute([$eventDate, $title, $note, $advisorId]);
        }
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $eventDate = trim((string)($_POST['event_date'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($id && $eventDate !== '' && $title !== '') {
            db()->prepare('UPDATE formulare_team_events SET event_date = ?, title = ?, note = ? WHERE id = ?')
                ->execute([$eventDate, $title, $note, $id]);
        }
    } elseif (isset($_POST['delete_id'])) {
        db()->prepare('DELETE FROM formulare_team_events WHERE id = ?')->execute([(int)$_POST['delete_id']]);
    }
    header('Location: /tim-kalendar.php');
    exit;
}

$today = date('Y-m-d');
$upcoming = db()->prepare('SELECT * FROM formulare_team_events WHERE event_date >= ? ORDER BY event_date ASC');
$upcoming->execute([$today]);
$upcoming = $upcoming->fetchAll();

$past = db()->prepare('SELECT * FROM formulare_team_events WHERE event_date < ? ORDER BY event_date DESC LIMIT 15');
$past->execute([$today]);
$past = $past->fetchAll();

$SK_MONTHS_SHORT = ['', 'JAN', 'FEB', 'MAR', 'APR', 'MÁJ', 'JÚN', 'JÚL', 'AUG', 'SEP', 'OKT', 'NOV', 'DEC'];
function eventBadge(string $dateStr, array $months): array {
    $ts = strtotime($dateStr);
    return [(int)date('j', $ts), $months[(int)date('n', $ts)]];
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Tímový kalendár</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=18">
<style>
  .tk-event{display:flex; align-items:flex-start; gap:14px; padding:13px 4px; border-bottom:1px solid var(--border);}
  .tk-event:last-child{border-bottom:none;}
  .tk-badge{width:52px; height:52px; border-radius:var(--radius-md); background:var(--accent-soft); color:var(--accent);
    display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0;}
  .tk-badge .d{font-size:17px; font-weight:800; line-height:1.1;}
  .tk-badge .m{font-size:9.5px; font-weight:700; letter-spacing:.04em;}
  .tk-past .tk-badge{background:var(--desk); color:var(--muted);}
  .tk-body{flex:1; min-width:0;}
  .tk-title{font-size:14px; font-weight:600; color:var(--ink);}
  .tk-note{font-size:12.5px; color:var(--muted); line-height:1.5; margin-top:2px;}
  .tk-actions{display:flex; gap:6px; flex-shrink:0;}
  .tk-add-form{display:grid; grid-template-columns:160px 1fr; gap:10px;}
  .tk-add-form textarea{grid-column:1 / -1;}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Tímový kalendár</h1>
    <p>Dôležité termíny a dátumy pre celý tím</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Nadchádzajúce termíny · <?= count($upcoming) ?></h3>
    <?php if (!$upcoming): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span class="es-title">Zatiaľ žiadne termíny</span>
        <span class="es-sub"><?= !empty($me['is_owner']) ? 'Pridaj prvý termín nižšie.' : 'Owner sem zatiaľ nič nepridal.' ?></span>
      </div>
    <?php endif; ?>
    <?php foreach ($upcoming as $e): [$d, $m] = eventBadge($e['event_date'], $SK_MONTHS_SHORT); ?>
    <div class="tk-event" id="tk-event-<?= (int)$e['id'] ?>">
      <div class="tk-badge"><span class="d"><?= $d ?></span><span class="m"><?= $m ?></span></div>
      <div class="tk-body">
        <div class="tk-title"><?= h($e['title']) ?></div>
        <?php if ($e['note']): ?><div class="tk-note"><?= nl2br(h($e['note'])) ?></div><?php endif; ?>
      </div>
      <?php if (!empty($me['is_owner'])): ?>
      <div class="tk-actions">
        <button type="button" class="toggle-btn" onclick="tkEdit(<?= (int)$e['id'] ?>)">Upraviť</button>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento termín?');">
          <input type="hidden" name="delete_id" value="<?= (int)$e['id'] ?>">
          <button type="submit" class="toggle-btn">Zmazať</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($me['is_owner'])): ?>
    <form method="post" class="kb-edit" id="tk-edit-<?= (int)$e['id'] ?>" style="display:none; margin-bottom:12px; flex-direction:column; gap:10px;">
      <input type="hidden" name="edit_id" value="<?= (int)$e['id'] ?>">
      <input type="date" name="event_date" value="<?= h($e['event_date']) ?>" required>
      <input type="text" name="title" value="<?= h($e['title']) ?>" placeholder="Názov termínu" required>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné)"><?= h($e['note']) ?></textarea>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <button type="button" class="pillbtn" onclick="tkCancel(<?= (int)$e['id'] ?>)">Zrušiť</button>
      </div>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($past): ?>
  <div class="card">
    <h3>Uplynulé termíny</h3>
    <?php foreach ($past as $e): [$d, $m] = eventBadge($e['event_date'], $SK_MONTHS_SHORT); ?>
    <div class="tk-event tk-past" id="tk-event-<?= (int)$e['id'] ?>">
      <div class="tk-badge"><span class="d"><?= $d ?></span><span class="m"><?= $m ?></span></div>
      <div class="tk-body">
        <div class="tk-title"><?= h($e['title']) ?></div>
        <?php if ($e['note']): ?><div class="tk-note"><?= nl2br(h($e['note'])) ?></div><?php endif; ?>
      </div>
      <?php if (!empty($me['is_owner'])): ?>
      <div class="tk-actions">
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento termín?');">
          <input type="hidden" name="delete_id" value="<?= (int)$e['id'] ?>">
          <button type="submit" class="toggle-btn">Zmazať</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($me['is_owner'])): ?>
  <div class="card">
    <h3>Pridať termín</h3>
    <form method="post" class="tk-add-form">
      <input type="hidden" name="add" value="1">
      <input type="date" name="event_date" required>
      <input type="text" name="title" placeholder="Názov termínu (napr. Uzávierka provízií)" required>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné)"></textarea>
      <button type="submit" class="pillbtn solid" style="width:max-content;">Pridať termín</button>
    </form>
  </div>
  <?php endif; ?>

</main>
<script>
function tkEdit(id) {
  document.getElementById('tk-event-' + id).style.display = 'none';
  document.getElementById('tk-edit-' + id).style.display = 'flex';
}
function tkCancel(id) {
  document.getElementById('tk-event-' + id).style.display = 'flex';
  document.getElementById('tk-edit-' + id).style.display = 'none';
}
</script>
<script src="/assets/shell.js?v=16"></script>
</body></html>
