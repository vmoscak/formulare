<?php
/**
 * Cesta nováčika — onboarding checklist pre nových poradcov v tíme (Deň 1 /
 * Týždeň 1 / Mesiac 1), s odkazmi rovno na konkrétne nástroje appky.
 *
 * Prístup: owner (spravuje osnovu, priraďuje/odoberá nováčikov, vidí
 * a upravuje všetko) ALEBO poradca, ktorému owner priradil onboarding
 * (onboarding_started_at IS NOT NULL) — ten vidí len checklist a odškrtáva
 * si vlastný postup, bez možnosti niečo pridať/upraviť/zmazať.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
$isOwner = $me && !empty($me['is_owner']);
$isOnboarding = $me && !empty($me['onboarding_started_at']);
if (!$me || (!$isOwner && !$isOnboarding)) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isOwner && isset($_POST['add'])) {
        $phase = trim((string)($_POST['phase'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        if ($phase !== '' && $title !== '') {
            $maxSort = (int)db()->query('SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps')->fetchColumn();
            db()->prepare('INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$phase, $title, $description, $linkUrl !== '' ? $linkUrl : null, $maxSort + 1]);
        }
    } elseif ($isOwner && isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $phase = trim((string)($_POST['phase'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        if ($id && $phase !== '' && $title !== '') {
            db()->prepare('UPDATE formulare_onboarding_steps SET phase = ?, title = ?, description = ?, link_url = ? WHERE id = ?')
                ->execute([$phase, $title, $description, $linkUrl !== '' ? $linkUrl : null, $id]);
        }
    } elseif ($isOwner && isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_onboarding_progress WHERE step_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM formulare_onboarding_steps WHERE id = ?')->execute([$id]);
    } elseif ($isOwner && isset($_POST['assign_advisor_id'])) {
        $id = (int)$_POST['assign_advisor_id'];
        if ($id) {
            db()->prepare('UPDATE formulare_advisors SET onboarding_started_at = ? WHERE id = ? AND is_owner = 0')
                ->execute([date('Y-m-d H:i:s'), $id]);
        }
    } elseif ($isOwner && isset($_POST['unassign_advisor_id'])) {
        $id = (int)$_POST['unassign_advisor_id'];
        if ($id) {
            db()->prepare('UPDATE formulare_advisors SET onboarding_started_at = NULL WHERE id = ?')->execute([$id]);
            db()->prepare('DELETE FROM formulare_onboarding_progress WHERE advisor_id = ?')->execute([$id]);
        }
    }
    header('Location: /cesta-novacika.php');
    exit;
}

$steps = db()->query('SELECT * FROM formulare_onboarding_steps ORDER BY sort_order, id')->fetchAll();

$doneStepIds = [];
if ($steps) {
    $prog = db()->prepare('SELECT step_id FROM formulare_onboarding_progress WHERE advisor_id = ?');
    $prog->execute([$advisorId]);
    $doneStepIds = array_map('intval', array_column($prog->fetchAll(), 'step_id'));
}

// Zoskupenie podľa fázy, v poradí prvého výskytu (rešpektuje sort_order).
$phases = [];
foreach ($steps as $s) {
    $phases[$s['phase']][] = $s;
}

$totalSteps = count($steps);
$doneCount = count($doneStepIds);
$pct = $totalSteps > 0 ? round($doneCount / $totalSteps * 100) : 0;

// Pre ownera: zoznam ostatných aktívnych poradcov (na priradenie/odobratie)
// spolu s ich vlastným postupom, ak už majú onboarding spustený.
$teamAdvisors = [];
if ($isOwner) {
    $teamAdvisors = db()->query(
        "SELECT id, name, color, onboarding_started_at FROM formulare_advisors WHERE is_owner = 0 AND active = 1 ORDER BY name"
    )->fetchAll();
    if ($teamAdvisors && $totalSteps > 0) {
        $progAll = db()->query('SELECT advisor_id, COUNT(*) AS c FROM formulare_onboarding_progress GROUP BY advisor_id')->fetchAll();
        $progByAdvisor = array_column($progAll, 'c', 'advisor_id');
        foreach ($teamAdvisors as &$ta) {
            $ta['doneCount'] = (int)($progByAdvisor[$ta['id']] ?? 0);
        }
        unset($ta);
    }
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Cesta nováčika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=27">
<style>
  .ob-progress-card{display:flex; align-items:center; gap:18px;}
  .ob-progress-num{font-size:26px; font-weight:800; color:var(--accent); flex-shrink:0;}
  .ob-progress-bar{flex:1; height:10px; border-radius:999px; background:var(--desk); overflow:hidden;}
  .ob-progress-fill{height:100%; background:var(--accent); border-radius:999px; transition:width .3s ease;}
  .ob-phase-title{font-size:13px; font-weight:700; color:var(--ink); text-transform:uppercase; letter-spacing:.04em; margin:22px 0 10px;}
  .ob-phase-title:first-child{margin-top:0;}
  .ob-step{display:flex; align-items:flex-start; gap:12px; padding:12px 4px; border-bottom:1px solid var(--border);}
  .ob-step:last-child{border-bottom:none;}
  .ob-step input[type=checkbox]{width:19px; height:19px; margin-top:1px; accent-color:var(--accent); cursor:pointer; flex-shrink:0;}
  .ob-step-body{flex:1; min-width:0;}
  .ob-step-title{font-size:14px; font-weight:600; color:var(--ink);}
  .ob-step.done .ob-step-title{color:var(--muted); text-decoration:line-through;}
  .ob-step-desc{font-size:12.5px; color:var(--muted); line-height:1.5; margin-top:2px;}
  .ob-step-actions{display:flex; align-items:center; gap:6px; flex-shrink:0;}
  .ob-add-form{display:flex; flex-direction:column; gap:10px;}
  .ob-add-row{display:grid; grid-template-columns:1fr 2fr; gap:10px;}
  .ob-team-row{display:flex; align-items:center; gap:12px; padding:12px 4px; border-bottom:1px solid var(--border);}
  .ob-team-row:last-child{border-bottom:none;}
  .ob-team-ini{width:34px; height:34px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12.5px; font-weight:600; flex-shrink:0;}
  .ob-team-body{flex:1; min-width:0;}
  .ob-team-name{font-size:13.5px; font-weight:600; color:var(--ink);}
  .ob-team-status{font-size:12px; color:var(--muted); margin-top:1px;}
  @media(max-width:720px){ .ob-add-row{grid-template-columns:1fr;} }
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Cesta nováčika</h1>
    <p><?= $isOwner ? 'Onboarding checklist pre nových poradcov · spravuješ osnovu a priraďuješ nováčikov' : 'Tvoj postup pri zaučovaní — odškrtávaj, ako napredúvaš' ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card ob-progress-card">
    <div class="ob-progress-num"><?= $doneCount ?>/<?= $totalSteps ?></div>
    <div style="flex:1;">
      <div class="ob-progress-bar"><div class="ob-progress-fill" id="obFill" style="width:<?= $pct ?>%;"></div></div>
      <div style="font-size:12px; color:var(--muted); margin-top:6px;" id="obPctLabel"><?= $pct ?> % dokončené</div>
    </div>
  </div>

  <?php if ($isOwner): ?>
  <div class="card">
    <h3>Priradiť nováčikovi</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Priradený poradca uvidí Cestu nováčika vo svojej ľavej lište aj pripomienku na Domov — vidí len checklist, osnovu upravuješ výhradne ty.
    </p>
    <?php if (!$teamAdvisors): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <span class="es-title">Zatiaľ žiadni ďalší poradcovia</span>
        <span class="es-sub">Pridaj poradcu v Admin sekcii, potom mu sem vieš priradiť onboarding.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($teamAdvisors as $ta): $assigned = !empty($ta['onboarding_started_at']); ?>
    <div class="ob-team-row">
      <span class="ob-team-ini" style="background:<?= h($ta['color']) ?>;"><?= h(advisorInitials($ta['name'])) ?></span>
      <div class="ob-team-body">
        <div class="ob-team-name"><?= h($ta['name']) ?></div>
        <div class="ob-team-status">
          <?php if ($assigned): ?>
            Priradené · <?= (int)($ta['doneCount'] ?? 0) ?>/<?= $totalSteps ?> dokončené
          <?php else: ?>
            Zatiaľ nepriradené
          <?php endif; ?>
        </div>
      </div>
      <form method="post" style="margin:0;">
        <input type="hidden" name="<?= $assigned ? 'unassign_advisor_id' : 'assign_advisor_id' ?>" value="<?= (int)$ta['id'] ?>">
        <button type="submit" class="toggle-btn"><?= $assigned ? 'Odobrať' : 'Priradiť' ?></button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h3>Osnova</h3>
    <?php if (!$phases): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span class="es-title">Zatiaľ žiadne kroky</span>
        <span class="es-sub"><?= $isOwner ? 'Pridaj prvý krok osnovy nižšie.' : 'Owner sem zatiaľ nič nepridal.' ?></span>
      </div>
    <?php endif; ?>
    <?php foreach ($phases as $phaseName => $phaseSteps): ?>
      <div class="ob-phase-title"><?= h($phaseName) ?></div>
      <?php foreach ($phaseSteps as $s): $isDone = in_array((int)$s['id'], $doneStepIds, true); ?>
      <div class="ob-step<?= $isDone ? ' done' : '' ?>" id="ob-step-<?= (int)$s['id'] ?>" data-step-id="<?= (int)$s['id'] ?>">
        <input type="checkbox" <?= $isDone ? 'checked' : '' ?> data-toggle-step="<?= (int)$s['id'] ?>">
        <div class="ob-step-body">
          <div class="ob-step-title"><?= h($s['title']) ?></div>
          <?php if ($s['description']): ?><div class="ob-step-desc"><?= h($s['description']) ?></div><?php endif; ?>
        </div>
        <div class="ob-step-actions">
          <?php if ($s['link_url']): ?><a class="toggle-btn" href="<?= h($s['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
          <?php if ($isOwner): ?>
          <button type="button" class="toggle-btn" onclick="obEdit(<?= (int)$s['id'] ?>)">Upraviť</button>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento krok?');">
            <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="toggle-btn">Zmazať</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isOwner): ?>
      <form method="post" class="kb-edit" id="ob-edit-<?= (int)$s['id'] ?>" style="display:none; margin-bottom:12px;">
        <input type="hidden" name="edit_id" value="<?= (int)$s['id'] ?>">
        <input type="text" name="phase" value="<?= h($s['phase']) ?>" placeholder="Fáza (napr. Deň 1)" required>
        <input type="text" name="title" value="<?= h($s['title']) ?>" placeholder="Názov kroku" required>
        <textarea name="description" rows="2" placeholder="Popis (nepovinné)"><?= h($s['description']) ?></textarea>
        <input type="text" name="link_url" value="<?= h((string)$s['link_url']) ?>" placeholder="Odkaz (nepovinné, napr. /financna-medzera/)">
        <div style="display:flex; gap:8px;">
          <button type="submit" class="pillbtn solid">Uložiť</button>
          <button type="button" class="pillbtn" onclick="obCancel(<?= (int)$s['id'] ?>)">Zrušiť</button>
        </div>
      </form>
      <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($isOwner): ?>
  <div class="card">
    <h3>Pridať krok</h3>
    <form method="post" class="ob-add-form">
      <input type="hidden" name="add" value="1">
      <div class="ob-add-row">
        <input type="text" name="phase" placeholder="Fáza (napr. Deň 1, Týždeň 1, Mesiac 1)" required>
        <input type="text" name="title" placeholder="Názov kroku" required>
      </div>
      <textarea name="description" rows="2" placeholder="Popis (nepovinné)"></textarea>
      <input type="text" name="link_url" placeholder="Odkaz (nepovinné, napr. /financna-medzera/)">
      <button type="submit" class="pillbtn solid" style="align-self:start; width:max-content;">Pridať krok</button>
    </form>
  </div>
  <?php endif; ?>

</main>
<script>
function obEdit(id) {
  document.getElementById('ob-step-' + id).style.display = 'none';
  document.getElementById('ob-edit-' + id).style.display = 'flex';
  document.getElementById('ob-edit-' + id).style.flexDirection = 'column';
  document.getElementById('ob-edit-' + id).style.gap = '10px';
}
function obCancel(id) {
  document.getElementById('ob-step-' + id).style.display = 'flex';
  document.getElementById('ob-edit-' + id).style.display = 'none';
}

var OB_TOTAL = <?= $totalSteps ?>;
document.addEventListener('change', function(e){
  if (!e.target.matches('input[type=checkbox][data-toggle-step]')) return;
  var stepId = +e.target.dataset.toggleStep;
  var done = e.target.checked;
  var row = document.getElementById('ob-step-' + stepId);
  row.classList.toggle('done', done);

  fetch('/api/onboarding-toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ stepId: stepId, done: done })
  }).catch(function(){});

  var doneNow = document.querySelectorAll('input[data-toggle-step]:checked').length;
  var pct = OB_TOTAL > 0 ? Math.round(doneNow / OB_TOTAL * 100) : 0;
  document.getElementById('obFill').style.width = pct + '%';
  document.getElementById('obPctLabel').textContent = pct + ' % dokončené';
  document.querySelector('.ob-progress-num').textContent = doneNow + '/' + OB_TOTAL;
});
</script>
<script src="/assets/shell.js?v=19"></script>
</body></html>
