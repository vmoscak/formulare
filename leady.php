<?php
/**
 * Dopyty — malý CRM priamo v Portáli: Leady + Rezervácie stretnutí, náhrada
 * za starú evidenciu v admin.vmfin.sk (Finančný svet tam už len presmerúva
 * sem, vmfin_bookings/vmfin_meetings tiež mizne). Výhradne pre poradcu
 * s is_owner=1, rovnaká zásada ako nabor-kandidati.php.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

const LD_STATUSES = [
    'novy'         => ['Nový', 'neutral'],
    'kontaktovany' => ['Kontaktovaný', 'warn'],
    'konvertovany' => ['Konvertovaný', 'ok'],
    'zamietnuty'   => ['Zamietnutý', 'bad'],
];
const LD_SOURCES = [
    'manual'       => 'Manuálne',
    'web_formular' => 'Web formulár',
    'odporucanie'  => 'Odporúčanie',
    'social'       => 'Sociálne siete',
    'ine'          => 'Iné',
];
const BK_STATUSES = [
    'pending'   => ['Čaká', 'warn'],
    'proposed'  => ['Navrhnuté', 'warn'],
    'confirmed' => ['Potvrdené', 'ok'],
    'cancelled' => ['Zrušené', 'bad'],
];

$tab = ($_GET['tab'] ?? 'leady') === 'rezervacie' ? 'rezervacie' : 'leady';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) { http_response_code(403); exit('Neplatný CSRF token — obnov stránku a skús to znova.'); }
    if (isset($_POST['add'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $source = array_key_exists($_POST['source'] ?? '', LD_SOURCES) ? $_POST['source'] : 'manual';
        $message = trim((string)($_POST['message'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($name !== '') {
            db()->prepare('INSERT INTO formulare_leads (name, phone, email, source, message, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$name, $phone, $email, $source, $message, $note, $advisorId]);
        }
        header('Location: /leady.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit;
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $source = array_key_exists($_POST['source'] ?? '', LD_SOURCES) ? $_POST['source'] : 'manual';
        $status = array_key_exists($_POST['status'] ?? '', LD_STATUSES) ? $_POST['status'] : 'novy';
        $message = trim((string)($_POST['message'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        if ($id && $name !== '') {
            db()->prepare('UPDATE formulare_leads SET name = ?, phone = ?, email = ?, source = ?, status = ?, message = ?, note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$name, $phone, $email, $source, $status, $message, $note, $id]);
        }
        header('Location: /leady.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit;
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_leads WHERE id = ?')->execute([$id]);
        header('Location: /leady.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit;
    } elseif (isset($_POST['convert_id'])) {
        $id = (int)$_POST['convert_id'];
        $stmt = db()->prepare('SELECT * FROM formulare_leads WHERE id = ?');
        $stmt->execute([$id]);
        $lead = $stmt->fetch();
        if ($lead) {
            db()->prepare('UPDATE formulare_leads SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute(['konvertovany', $id]);
            $qs = http_build_query(['newName' => $lead['name'], 'newPhone' => $lead['phone'], 'newEmail' => $lead['email']]);
            header('Location: /financna-analyza/?' . $qs);
            exit;
        }
        header('Location: /leady.php');
        exit;
    } elseif (isset($_POST['booking_confirm_id']) || isset($_POST['booking_propose_id'])) {
        $isPropose = isset($_POST['booking_propose_id']);
        $id = (int)($isPropose ? $_POST['booking_propose_id'] : $_POST['booking_confirm_id']);
        $confirmedDate = trim((string)($_POST['confirmed_date'] ?? ''));
        $confirmedTime = trim((string)($_POST['confirmed_time'] ?? ''));
        $adminNote = trim((string)($_POST['admin_note'] ?? ''));
        $stmt = db()->prepare('SELECT * FROM formulare_bookings WHERE id = ?');
        $stmt->execute([$id]);
        $booking = $stmt->fetch();
        if ($booking && $confirmedDate !== '' && $confirmedTime !== '') {
            $status = $isPropose ? 'proposed' : 'confirmed';
            db()->prepare('UPDATE formulare_bookings SET status = ?, confirmed_date = ?, confirmed_time = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$status, $confirmedDate, $confirmedTime, $adminNote, $id]);
            $name = (string)$booking['name']; $email = (string)$booking['email']; $topic = (string)$booking['topic'];
            if ($status === 'confirmed') {
                sendPortalMail($email, '✅ Váš termín konzultácie bol potvrdený | VMfin',
                    "Dobrý deň, $name,\n\nVáš termín konzultácie k téme $topic bol potvrdený na $confirmedDate o $confirmedTime.\n\nS pozdravom,\nVMfin");
            } else {
                $confirmLink = 'https://portal.vmfin.sk/booking-confirm.php?token=' . urlencode((string)$booking['token']);
                sendPortalMail($email, '🔄 Návrh nového termínu konzultácie | VMfin',
                    "Dobrý deň, $name,\n\nnavrhujem nový termín konzultácie: $confirmedDate o $confirmedTime.\n\nAk vám termín vyhovuje, potvrďte ho jedným klikom:\n$confirmLink\n\nAk vám termín nevyhovuje, odpovedzte na tento email alebo mi zavolajte a dohodneme iný.\n\nS pozdravom,\nVMfin");
            }
        }
        header('Location: /leady.php?tab=rezervacie');
        exit;
    } elseif (isset($_POST['booking_cancel_id'])) {
        $id = (int)$_POST['booking_cancel_id'];
        $stmt = db()->prepare('SELECT * FROM formulare_bookings WHERE id = ?');
        $stmt->execute([$id]);
        $booking = $stmt->fetch();
        if ($booking) {
            db()->prepare('UPDATE formulare_bookings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute(['cancelled', $id]);
            sendPortalMail((string)$booking['email'], '❌ Konzultácia bola zrušená | VMfin',
                "Dobrý deň, {$booking['name']},\n\nvaša rezervácia konzultácie bola zrušená.\nAk chcete, navrhnite nový termín odpoveďou na tento email.\n\nS pozdravom,\nVMfin");
        }
        header('Location: /leady.php?tab=rezervacie');
        exit;
    }
}

$fStatus = trim((string)($_GET['status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($fStatus !== '' && array_key_exists($fStatus, LD_STATUSES)) {
    $where[] = 'status = ?';
    $params[] = $fStatus;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmt = db()->prepare("SELECT * FROM formulare_leads $whereSql ORDER BY updated_at DESC, id DESC");
$stmt->execute($params);
$leads = $stmt->fetchAll();

$statusCounts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM formulare_leads GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int)$r['c'];
}
$totalCount = array_sum($statusCounts);

$bkStatusCounts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM formulare_bookings GROUP BY status') as $r) {
    $bkStatusCounts[$r['status']] = (int)$r['c'];
}
$bkTotalCount = array_sum($bkStatusCounts);
$bkPendingCount = $bkStatusCounts['pending'] ?? 0;

$bkFStatus = trim((string)($_GET['bstatus'] ?? ''));
$bookings = [];
if ($tab === 'rezervacie') {
    $bwhere = [];
    $bparams = [];
    if ($bkFStatus !== '' && array_key_exists($bkFStatus, BK_STATUSES)) {
        $bwhere[] = 'status = ?';
        $bparams[] = $bkFStatus;
    }
    $bwhereSql = $bwhere ? ('WHERE ' . implode(' AND ', $bwhere)) : '';
    $stmt = db()->prepare("SELECT * FROM formulare_bookings $bwhereSql ORDER BY created_at DESC, id DESC");
    $stmt->execute($bparams);
    $bookings = $stmt->fetchAll();
}

function ldQs(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Dopyty</title>
<link rel="stylesheet" href="<?= asset('fonts.css') ?>">
<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('panel.css') ?>">
<style>
  .ld-status{display:inline-flex; align-items:center; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;}
  .ld-status.neutral{background:var(--desk); color:var(--muted);}
  .ld-status.warn{background:var(--amber-soft); color:var(--amber);}
  .ld-status.ok{background:var(--good-soft); color:var(--good);}
  .ld-status.bad{background:var(--rose-soft); color:var(--rose);}
  .ld-row{display:flex; align-items:flex-start; gap:12px; padding:14px 4px; border-bottom:1px solid var(--border);}
  .ld-row:last-child{border-bottom:none;}
  .ld-main{flex:1; min-width:0;}
  .ld-name-line{display:flex; align-items:center; gap:8px; flex-wrap:wrap;}
  .ld-name{font-size:14px; font-weight:700; color:var(--ink);}
  .ld-meta{font-size:12.5px; color:var(--muted); margin-top:3px;}
  .ld-message{font-size:12.5px; color:var(--ink-2); margin-top:6px; line-height:1.5; white-space:pre-wrap;}
  .ld-note{font-size:12.5px; color:var(--muted); margin-top:4px; line-height:1.5; white-space:pre-wrap; font-style:italic;}
  .ld-actions{display:flex; align-items:center; gap:6px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; max-width:220px;}
  .ld-edit-form{display:none; flex-direction:column; gap:10px; margin-bottom:12px;}
  .ld-add-row{display:grid; grid-template-columns:2fr 1fr 1fr; gap:10px;}
  .ld-add-row2{display:grid; grid-template-columns:1fr 1fr; gap:10px;}
  @media(max-width:720px){ .ld-add-row,.ld-add-row2{grid-template-columns:1fr;} .ld-actions{max-width:none;} }
  .ld-stats{display:flex; align-items:center; gap:22px; flex-wrap:wrap;}
  .ld-stat-num{font-size:20px; font-weight:700; color:var(--ink);}
  .ld-stat-label{font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em;}
  .ld-filter-row{display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px;}
  .ld-search{flex:1; min-width:220px; display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--desk);}
  .ld-search input{flex:1; border:none; background:transparent; font-size:13.5px; color:var(--ink); outline:none;}
  .ld-search svg{flex-shrink:0; color:var(--muted);}
  .dp-tabs{display:flex; gap:8px; margin-bottom:14px;}
  .dp-tab{padding:9px 16px; border-radius:999px; border:1px solid var(--border); background:var(--paper); color:var(--ink-2); font-weight:600; font-size:13.5px; text-decoration:none;}
  .dp-tab.active{background:var(--accent); color:#fff; border-color:var(--accent);}
  .bk-term{font-weight:700; color:var(--ink);}
  .bk-alt{color:var(--muted); font-size:12.5px;}
  .bk-inline-form{display:none; flex-direction:column; gap:8px; margin-top:10px; padding-top:10px; border-top:1px dashed var(--border);}
  .bk-inline-row{display:grid; grid-template-columns:1fr 1fr; gap:8px;}
  @media(max-width:720px){ .bk-inline-row{grid-template-columns:1fr;} }
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Dopyty</h1>
    <p>Leady a rezervácie stretnutí — viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="dp-tabs">
    <a class="dp-tab<?= $tab === 'leady' ? ' active' : '' ?>" href="/leady.php">Leady (<?= $totalCount ?>)</a>
    <a class="dp-tab<?= $tab === 'rezervacie' ? ' active' : '' ?>" href="/leady.php?tab=rezervacie">Rezervácie (<?= $bkTotalCount ?><?= $bkPendingCount > 0 ? ', ' . $bkPendingCount . ' čaká' : '' ?>)</a>
  </div>

  <?php if ($tab === 'leady'): ?>

  <div class="card ld-stats">
    <div>
      <div class="ld-stat-label">Celkovo</div>
      <div class="ld-stat-num"><?= $totalCount ?></div>
    </div>
    <div>
      <div class="ld-stat-label">Nových</div>
      <div class="ld-stat-num"><?= $statusCounts['novy'] ?? 0 ?></div>
    </div>
    <div>
      <div class="ld-stat-label">Kontaktovaných</div>
      <div class="ld-stat-num"><?= $statusCounts['kontaktovany'] ?? 0 ?></div>
    </div>
    <div>
      <div class="ld-stat-label">Konvertovaných</div>
      <div class="ld-stat-num" style="color:var(--good);"><?= $statusCounts['konvertovany'] ?? 0 ?></div>
    </div>
  </div>

  <div class="card">
    <h3>Hľadať a filtrovať</h3>
    <form method="get" class="ld-search" style="margin-bottom:12px;">
      <?php if ($fStatus !== ''): ?><input type="hidden" name="status" value="<?= h($fStatus) ?>"><?php endif; ?>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Hľadať podľa mena, telefónu alebo e-mailu…">
      <button type="submit" class="toggle-btn">Hľadať</button>
      <?php if ($q !== ''): ?><a class="toggle-btn" href="<?= ldQs(['q' => '']) ?>">✕</a><?php endif; ?>
    </form>
    <div class="ld-filter-row">
      <a class="pillbtn<?= $fStatus === '' ? ' solid' : '' ?>" href="<?= ldQs(['status' => '']) ?>">Všetci (<?= $totalCount ?>)</a>
      <?php foreach (LD_STATUSES as $key => $meta): ?>
      <a class="pillbtn<?= $fStatus === $key ? ' solid' : '' ?>" href="<?= ldQs(['status' => $key]) ?>"><?= h($meta[0]) ?> (<?= $statusCounts[$key] ?? 0 ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h3>Zoznam leadov</h3>
    <?php if (!$leads): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
        <span class="es-title">Zatiaľ žiadne leady</span>
        <span class="es-sub">Pridaj prvý dopyt nižšie.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($leads as $l): $st = LD_STATUSES[$l['status']] ?? ['—', 'neutral']; ?>
    <div class="ld-row" id="ld-row-<?= (int)$l['id'] ?>">
      <div class="ld-main">
        <div class="ld-name-line">
          <span class="ld-name"><?= h($l['name']) ?></span>
          <span class="ld-status <?= h($st[1]) ?>"><?= h($st[0]) ?></span>
        </div>
        <div class="ld-meta">
          <?= h(LD_SOURCES[$l['source']] ?? '') ?>
          <?php if ($l['phone']): ?> · <?= h($l['phone']) ?><?php endif; ?>
          <?php if ($l['email']): ?> · <?= h($l['email']) ?><?php endif; ?>
          · <?= h(date('j. n. Y', strtotime((string)$l['created_at']))) ?>
        </div>
        <?php if ($l['message']): ?><div class="ld-message"><?= h($l['message']) ?></div><?php endif; ?>
        <?php if ($l['note']): ?><div class="ld-note">Poznámka: <?= h($l['note']) ?></div><?php endif; ?>
      </div>
      <div class="ld-actions">
        <?php if ($l['status'] !== 'konvertovany'): ?>
        <form method="post" style="margin:0;">
          <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="convert_id" value="<?= (int)$l['id'] ?>">
          <button type="submit" class="pillbtn solid">Previesť na klienta →</button>
        </form>
        <?php endif; ?>
        <button type="button" class="toggle-btn" onclick="ldEdit(<?= (int)$l['id'] ?>)">Upraviť</button>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento lead?');">
          <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="delete_id" value="<?= (int)$l['id'] ?>">
          <button type="submit" class="toggle-btn">Zmazať</button>
        </form>
      </div>
    </div>
    <form method="post" class="ld-edit-form" id="ld-edit-<?= (int)$l['id'] ?>">
      <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="edit_id" value="<?= (int)$l['id'] ?>">
      <div class="ld-add-row">
        <input type="text" name="name" value="<?= h($l['name']) ?>" placeholder="Meno" required>
        <input type="text" name="phone" value="<?= h($l['phone']) ?>" placeholder="Telefón">
        <input type="text" name="email" value="<?= h($l['email']) ?>" placeholder="E-mail">
      </div>
      <div class="ld-add-row2">
        <select name="source">
          <?php foreach (LD_SOURCES as $key => $label): ?>
          <option value="<?= h($key) ?>" <?= $l['source'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <?php foreach (LD_STATUSES as $key => $meta): ?>
          <option value="<?= h($key) ?>" <?= $l['status'] === $key ? 'selected' : '' ?>><?= h($meta[0]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <textarea name="message" rows="2" placeholder="Správa od leada (nepovinné)"><?= h($l['message']) ?></textarea>
      <textarea name="note" rows="2" placeholder="Tvoja poznámka (nepovinné)"><?= h($l['note']) ?></textarea>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <button type="button" class="pillbtn" onclick="ldCancel(<?= (int)$l['id'] ?>)">Zrušiť</button>
      </div>
    </form>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Pridať lead</h3>
    <form method="post" style="display:flex; flex-direction:column; gap:10px;">
      <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="add" value="1">
      <div class="ld-add-row">
        <input type="text" name="name" placeholder="Meno" required>
        <input type="text" name="phone" placeholder="Telefón">
        <input type="text" name="email" placeholder="E-mail">
      </div>
      <select name="source" style="max-width:220px;">
        <?php foreach (LD_SOURCES as $key => $label): ?>
        <option value="<?= h($key) ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <textarea name="message" rows="2" placeholder="Správa od leada (nepovinné)"></textarea>
      <textarea name="note" rows="2" placeholder="Poznámka (nepovinné)"></textarea>
      <button type="submit" class="pillbtn solid" style="align-self:start; width:max-content;">Pridať lead</button>
    </form>
  </div>

  <?php else /* rezervacie */: ?>

  <div class="card ld-stats">
    <div>
      <div class="ld-stat-label">Celkovo</div>
      <div class="ld-stat-num"><?= $bkTotalCount ?></div>
    </div>
    <?php foreach (BK_STATUSES as $key => $meta): ?>
    <div>
      <div class="ld-stat-label"><?= h($meta[0]) ?></div>
      <div class="ld-stat-num"><?= $bkStatusCounts[$key] ?? 0 ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Filtrovať podľa stavu</h3>
    <div class="ld-filter-row">
      <a class="pillbtn<?= $bkFStatus === '' ? ' solid' : '' ?>" href="<?= ldQs(['bstatus' => '']) ?>">Všetky (<?= $bkTotalCount ?>)</a>
      <?php foreach (BK_STATUSES as $key => $meta): ?>
      <a class="pillbtn<?= $bkFStatus === $key ? ' solid' : '' ?>" href="<?= ldQs(['bstatus' => $key]) ?>"><?= h($meta[0]) ?> (<?= $bkStatusCounts[$key] ?? 0 ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h3>Zoznam rezervácií</h3>
    <?php if (!$bookings): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span class="es-title">Zatiaľ žiadne rezervácie</span>
        <span class="es-sub">Nové žiadosti z rezervačného formulára na vmfin.sk sa objavia tu.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($bookings as $b): $bst = BK_STATUSES[$b['status']] ?? ['—', 'neutral']; ?>
    <div class="ld-row">
      <div class="ld-main">
        <div class="ld-name-line">
          <span class="ld-name"><?= h($b['name']) ?></span>
          <span class="ld-status <?= h($bst[1]) ?>"><?= h($bst[0]) ?></span>
        </div>
        <div class="ld-meta">
          <?= h($b['topic']) ?> · <?= $b['meeting_type'] === 'osobne' ? 'Osobne' : 'Online' ?>
          <?php if ($b['phone']): ?> · <?= h($b['phone']) ?><?php endif; ?>
          <?php if ($b['email']): ?> · <?= h($b['email']) ?><?php endif; ?>
        </div>
        <div class="ld-meta">
          Preferovaný termín: <span class="bk-term"><?= h(date('j. n. Y', strtotime((string)$b['preferred_date']))) ?> o <?= h($b['preferred_time']) ?></span>
          <?php if ($b['alt_date']): ?><br><span class="bk-alt">Alternatíva: <?= h(date('j. n. Y', strtotime((string)$b['alt_date']))) ?> o <?= h($b['alt_time'] ?? '') ?></span><?php endif; ?>
          <?php if ($b['status'] === 'proposed' || $b['status'] === 'confirmed'): ?>
            <?php if ($b['confirmed_date']): ?><br><strong>Dohodnutý termín: <?= h(date('j. n. Y', strtotime((string)$b['confirmed_date']))) ?> o <?= h($b['confirmed_time'] ?? '') ?></strong><?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($b['message']): ?><div class="ld-message"><?= h($b['message']) ?></div><?php endif; ?>
        <?php if ($b['admin_note']): ?><div class="ld-note">Poznámka: <?= h($b['admin_note']) ?></div><?php endif; ?>

        <?php if (in_array($b['status'], ['pending', 'proposed'], true)): ?>
        <div class="bk-inline-form" id="bk-confirm-<?= (int)$b['id'] ?>">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="booking_confirm_id" value="<?= (int)$b['id'] ?>">
            <div class="bk-inline-row">
              <input type="date" name="confirmed_date" value="<?= h((string)($b['confirmed_date'] ?: $b['preferred_date'])) ?>" required>
              <input type="time" name="confirmed_time" value="<?= h((string)($b['confirmed_time'] ?: $b['preferred_time'])) ?>" required>
            </div>
            <textarea name="admin_note" rows="2" placeholder="Poznámka (nepovinné)"><?= h((string)$b['admin_note']) ?></textarea>
            <div style="display:flex; gap:8px;">
              <button type="submit" class="pillbtn solid">Potvrdiť a odoslať e-mail</button>
              <button type="button" class="pillbtn" onclick="bkToggle(<?= (int)$b['id'] ?>,'confirm')">Zrušiť</button>
            </div>
          </form>
        </div>
        <?php endif; ?>
        <?php if ($b['status'] === 'pending'): ?>
        <div class="bk-inline-form" id="bk-propose-<?= (int)$b['id'] ?>">
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="booking_propose_id" value="<?= (int)$b['id'] ?>">
            <div class="bk-inline-row">
              <input type="date" name="confirmed_date" value="<?= h((string)($b['alt_date'] ?: '')) ?>" required>
              <input type="time" name="confirmed_time" value="<?= h((string)($b['alt_time'] ?: '')) ?>" required>
            </div>
            <textarea name="admin_note" rows="2" placeholder="Poznámka (nepovinné)"></textarea>
            <div style="display:flex; gap:8px;">
              <button type="submit" class="pillbtn solid">Navrhnúť a odoslať e-mail</button>
              <button type="button" class="pillbtn" onclick="bkToggle(<?= (int)$b['id'] ?>,'propose')">Zrušiť</button>
            </div>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <div class="ld-actions">
        <?php if (in_array($b['status'], ['pending', 'proposed'], true)): ?>
        <button type="button" class="toggle-btn" onclick="bkToggle(<?= (int)$b['id'] ?>,'confirm')">Potvrdiť</button>
        <?php endif; ?>
        <?php if ($b['status'] === 'pending'): ?>
        <button type="button" class="toggle-btn" onclick="bkToggle(<?= (int)$b['id'] ?>,'propose')">Navrhnúť termín</button>
        <?php endif; ?>
        <?php if (in_array($b['status'], ['pending', 'confirmed'], true)): ?>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zrušiť túto rezerváciu?');">
          <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="booking_cancel_id" value="<?= (int)$b['id'] ?>">
          <button type="submit" class="toggle-btn">Zrušiť</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>

</main>
<script>
function ldEdit(id) {
  document.getElementById('ld-row-' + id).style.display = 'none';
  document.getElementById('ld-edit-' + id).style.display = 'flex';
}
function ldCancel(id) {
  document.getElementById('ld-row-' + id).style.display = 'flex';
  document.getElementById('ld-edit-' + id).style.display = 'none';
}
function bkToggle(id, kind) {
  var el = document.getElementById('bk-' + kind + '-' + id);
  if (!el) return;
  el.style.display = el.style.display === 'flex' ? 'none' : 'flex';
}
</script>
<script src="<?= asset('toast.js') ?>"></script>
<script src="<?= asset('shell.js') ?>"></script>
</body></html>
