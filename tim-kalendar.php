<?php
/**
 * Tímový kalendár — klasický mesačný kalendár s udalosťami/úlohami, ktoré
 * owner priraďuje konkrétnym kolegom (farebne podľa ich farby, rovnako ako
 * iniciálky inde v appke) alebo necháva pre celý tím (sivá bodka). Vidí
 * každý prihlásený poradca, pridávať/upravovať/mazať smie výhradne owner.
 * Kompaktný náhľad najbližších udalostí je aj na Domov (uvod.php).
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }
$isOwner = !empty($me['is_owner']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}
$SK_MONTHS = ['', 'január','február','marec','apríl','máj','jún','júl','august','september','október','november','december'];
$SK_DOW = ['Po','Ut','St','Št','Pi','So','Ne'];
$SK_DOW_LONG = ['', 'Pondelok','Utorok','Streda','Štvrtok','Piatok','Sobota','Nedeľa'];

function backUrl(string $month, ?string $day): string {
    $qs = ['month' => $month];
    if ($day) $qs['day'] = $day;
    return '/tim-kalendar.php?' . http_build_query($qs);
}

$monthParam = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$selectedDay = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['day'] ?? '')) ? $_GET['day'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backMonth = (string)($_POST['_month'] ?? $monthParam);
    $backDay = (string)($_POST['_day'] ?? '') ?: null;
    if ($isOwner) {
        if (isset($_POST['add'])) {
            $eventDate = trim((string)($_POST['event_date'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            $assignedId = (int)($_POST['assigned_advisor_id'] ?? 0);
            if ($eventDate !== '' && $title !== '') {
                db()->prepare('INSERT INTO formulare_team_events (event_date, title, note, assigned_advisor_id, created_by) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$eventDate, $title, $note, $assignedId ?: null, $advisorId]);
                $backMonth = substr($eventDate, 0, 7);
                $backDay = $eventDate;
            }
        } elseif (isset($_POST['edit_id'])) {
            $id = (int)$_POST['edit_id'];
            $eventDate = trim((string)($_POST['event_date'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));
            $assignedId = (int)($_POST['assigned_advisor_id'] ?? 0);
            if ($id && $eventDate !== '' && $title !== '') {
                db()->prepare('UPDATE formulare_team_events SET event_date = ?, title = ?, note = ?, assigned_advisor_id = ? WHERE id = ?')
                    ->execute([$eventDate, $title, $note, $assignedId ?: null, $id]);
                $backMonth = substr($eventDate, 0, 7);
                $backDay = $eventDate;
            }
        } elseif (isset($_POST['delete_id'])) {
            db()->prepare('DELETE FROM formulare_team_events WHERE id = ?')->execute([(int)$_POST['delete_id']]);
        }
    }
    header('Location: ' . backUrl($backMonth, $backDay));
    exit;
}

$monthStart = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
if (!$monthStart) { $monthStart = new DateTime('first day of this month'); $monthParam = $monthStart->format('Y-m'); }
$monthStart->setTime(0, 0, 0);
$monthLabel = $SK_MONTHS[(int)$monthStart->format('n')] . ' ' . $monthStart->format('Y');
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');

$gridStart = clone $monthStart;
$dow = (int)$gridStart->format('N');
$gridStart->modify('-' . ($dow - 1) . ' days');
$gridDays = [];
$cursor = clone $gridStart;
for ($i = 0; $i < 42; $i++) { $gridDays[] = clone $cursor; $cursor->modify('+1 day'); }
$rangeStart = $gridDays[0]->format('Y-m-d');
$rangeEnd = end($gridDays)->format('Y-m-d');

$evStmt = db()->prepare('SELECT * FROM formulare_team_events WHERE event_date BETWEEN ? AND ? ORDER BY event_date, id');
$evStmt->execute([$rangeStart, $rangeEnd]);
$eventsByDate = [];
foreach ($evStmt->fetchAll() as $e) { $eventsByDate[$e['event_date']][] = $e; }

$advisorsById = [];
$assignableAdvisors = [];
foreach (db()->query('SELECT id, name, color FROM formulare_advisors WHERE active = 1 ORDER BY name')->fetchAll() as $a) {
    $advisorsById[$a['id']] = $a;
    $assignableAdvisors[] = $a;
}
$UNASSIGNED_COLOR = '#94a3b8';

$today = date('Y-m-d');
$selectedEvents = $selectedDay ? ($eventsByDate[$selectedDay] ?? []) : [];
$editEventId = (int)($_GET['edit'] ?? 0);
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Tímový kalendár</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=20">
<style>
  .cal-nav{display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;}
  .cal-nav h3{margin:0; font-size:16px; text-transform:capitalize;}
  .cal-grid{display:grid; grid-template-columns:repeat(7,1fr); gap:4px;}
  .cal-dow{font-size:11px; font-weight:700; color:var(--muted); text-align:center; padding:4px 0; text-transform:uppercase; letter-spacing:.03em;}
  .cal-day{
    position:relative; aspect-ratio:1/1; border-radius:var(--radius-md); border:1px solid var(--border); background:var(--paper);
    display:flex; flex-direction:column; align-items:center; padding:6px 2px 4px; text-decoration:none; color:var(--ink);
    transition:border-color .15s, background .15s;
  }
  .cal-day:hover{border-color:var(--accent); background:var(--accent-soft);}
  .cal-day.other{opacity:.35;}
  .cal-day.today{border-color:var(--accent); border-width:2px;}
  .cal-day.selected{background:var(--accent); border-color:var(--accent);}
  .cal-day.selected .cal-day-num{color:#fff;}
  .cal-day-num{font-size:12.5px; font-weight:700;}
  .cal-day-dots{display:flex; flex-wrap:wrap; gap:2px; justify-content:center; margin-top:4px; max-width:100%;}
  .cal-dot{width:6px; height:6px; border-radius:50%; flex-shrink:0;}
  .cal-legend{display:flex; flex-wrap:wrap; gap:12px; margin-top:16px; padding-top:14px; border-top:1px solid var(--border);}
  .cal-legend-item{display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted);}
  .cal-legend-dot{width:9px; height:9px; border-radius:50%; flex-shrink:0;}
  .tk-event{display:flex; align-items:flex-start; gap:12px; padding:12px 4px; border-bottom:1px solid var(--border);}
  .tk-event:last-child{border-bottom:none;}
  .tk-avatar{width:32px; height:32px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:11.5px; font-weight:600; flex-shrink:0;}
  .tk-body{flex:1; min-width:0;}
  .tk-title{font-size:14px; font-weight:600; color:var(--ink);}
  .tk-who{font-size:11.5px; color:var(--muted); margin-top:1px;}
  .tk-note{font-size:12.5px; color:var(--muted); line-height:1.5; margin-top:4px;}
  .tk-actions{display:flex; gap:6px; flex-shrink:0;}
  .tk-add-form{display:flex; flex-direction:column; gap:10px;}
  .tk-add-row{display:grid; grid-template-columns:160px 1fr 1fr; gap:10px;}
  @media(max-width:720px){ .tk-add-row{grid-template-columns:1fr;} .cal-day-num{font-size:11px;} }
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Tímový kalendár</h1>
    <p>Dôležité termíny a úlohy pre celý tím<?= $isOwner ? '' : ' · farba = kolega, ktorého sa to týka' ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <div class="cal-nav">
      <a class="pillbtn" href="?month=<?= $prevMonth ?>">← Predch.</a>
      <h3><?= h($monthLabel) ?></h3>
      <a class="pillbtn" href="?month=<?= $nextMonth ?>">Nasl. →</a>
    </div>
    <div class="cal-grid">
      <?php foreach ($SK_DOW as $d): ?><div class="cal-dow"><?= $d ?></div><?php endforeach; ?>
      <?php foreach ($gridDays as $d):
        $dStr = $d->format('Y-m-d');
        $cls = [];
        if ($d->format('Y-m') !== $monthParam) $cls[] = 'other';
        if ($dStr === $today) $cls[] = 'today';
        if ($dStr === $selectedDay) $cls[] = 'selected';
        $dayEvents = $eventsByDate[$dStr] ?? [];
      ?>
      <a class="cal-day <?= implode(' ', $cls) ?>" href="?month=<?= $monthParam ?>&day=<?= $dStr ?>">
        <span class="cal-day-num"><?= (int)$d->format('j') ?></span>
        <?php if ($dayEvents): ?>
        <div class="cal-day-dots">
          <?php foreach (array_slice($dayEvents, 0, 6) as $ev):
            $col = ($ev['assigned_advisor_id'] && isset($advisorsById[$ev['assigned_advisor_id']])) ? $advisorsById[$ev['assigned_advisor_id']]['color'] : $UNASSIGNED_COLOR;
          ?>
          <span class="cal-dot" style="background:<?= h($col) ?>;" title="<?= h($ev['title']) ?>"></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="cal-legend">
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:<?= $UNASSIGNED_COLOR ?>;"></span>Celý tím</span>
      <?php foreach ($assignableAdvisors as $a): ?>
      <span class="cal-legend-item"><span class="cal-legend-dot" style="background:<?= h($a['color']) ?>;"></span><?= h($a['name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($selectedDay): ?>
  <div class="card">
    <h3><?= $SK_DOW_LONG[(int)(new DateTime($selectedDay))->format('N')] ?>, <?= (int)(new DateTime($selectedDay))->format('j') ?>. <?= $SK_MONTHS[(int)(new DateTime($selectedDay))->format('n')] ?> <?= (new DateTime($selectedDay))->format('Y') ?></h3>
    <?php if (!$selectedEvents): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span class="es-title">Žiadne udalosti tento deň</span>
        <span class="es-sub"><?= $isOwner ? 'Pridaj udalosť nižšie.' : 'Owner sem zatiaľ nič nepridal.' ?></span>
      </div>
    <?php endif; ?>
    <?php foreach ($selectedEvents as $e):
      $assignee = $e['assigned_advisor_id'] ? ($advisorsById[$e['assigned_advisor_id']] ?? null) : null;
      $col = $assignee ? $assignee['color'] : $UNASSIGNED_COLOR;
    ?>
    <?php if ($editEventId === (int)$e['id']): ?>
    <form method="post" class="kb-edit" style="margin-bottom:12px;">
      <input type="hidden" name="edit_id" value="<?= (int)$e['id'] ?>">
      <input type="hidden" name="_month" value="<?= h($monthParam) ?>">
      <input type="hidden" name="_day" value="<?= h($selectedDay) ?>">
      <div class="tk-add-row">
        <input type="date" name="event_date" value="<?= h($e['event_date']) ?>" required>
        <input type="text" name="title" value="<?= h($e['title']) ?>" placeholder="Názov" required>
        <select name="assigned_advisor_id">
          <option value="0">Celý tím</option>
          <?php foreach ($assignableAdvisors as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= $e['assigned_advisor_id'] == $a['id'] ? 'selected' : '' ?>><?= h($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné)"><?= h($e['note']) ?></textarea>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <a class="pillbtn" href="<?= backUrl($monthParam, $selectedDay) ?>">Zrušiť</a>
      </div>
    </form>
    <?php else: ?>
    <div class="tk-event">
      <span class="tk-avatar" style="background:<?= h($col) ?>;"><?= $assignee ? h(advisorInitials($assignee['name'])) : '⚑' ?></span>
      <div class="tk-body">
        <div class="tk-title"><?= h($e['title']) ?></div>
        <div class="tk-who"><?= $assignee ? h($assignee['name']) : 'Celý tím' ?></div>
        <?php if ($e['note']): ?><div class="tk-note"><?= nl2br(h($e['note'])) ?></div><?php endif; ?>
      </div>
      <?php if ($isOwner): ?>
      <div class="tk-actions">
        <a class="toggle-btn" href="?month=<?= $monthParam ?>&day=<?= $selectedDay ?>&edit=<?= (int)$e['id'] ?>">Upraviť</a>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať túto udalosť?');">
          <input type="hidden" name="delete_id" value="<?= (int)$e['id'] ?>">
          <input type="hidden" name="_month" value="<?= h($monthParam) ?>">
          <input type="hidden" name="_day" value="<?= h($selectedDay) ?>">
          <button type="submit" class="toggle-btn">Zmazať</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($isOwner): ?>
  <div class="card">
    <h3>Pridať udalosť</h3>
    <form method="post" class="tk-add-form">
      <input type="hidden" name="add" value="1">
      <input type="hidden" name="_month" value="<?= h($monthParam) ?>">
      <input type="hidden" name="_day" value="<?= h((string)$selectedDay) ?>">
      <div class="tk-add-row">
        <input type="date" name="event_date" value="<?= h($selectedDay ?: $today) ?>" required>
        <input type="text" name="title" placeholder="Názov (napr. Uzávierka provízií)" required>
        <select name="assigned_advisor_id">
          <option value="0">Celý tím</option>
          <?php foreach ($assignableAdvisors as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné)"></textarea>
      <button type="submit" class="pillbtn solid" style="align-self:start; width:max-content;">Pridať udalosť</button>
    </form>
  </div>
  <?php endif; ?>

</main>
<script src="/assets/shell.js?v=17"></script>
</body></html>
