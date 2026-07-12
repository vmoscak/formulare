<?php
/**
 * Refinančný Radar — ručne udržiavaný prehľad aktuálnych hypotekárnych
 * sadzieb podľa banky a fixácie. Žiadne automatické sťahovanie (vedomé
 * rozhodnutie — pri číslach pre klienta sa nedá spoliehať na niečo, čo sa
 * môže potichu pokaziť). Prístup VÝHRADNE pre is_owner=1 — rieši len
 * hypotéky sám, ostatní poradcovia to nepotrebujú (rovnaký vzor ako
 * nabor.php/znalostna-baza.php).
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $bank = trim((string)($_POST['bank'] ?? ''));
        $fixation = trim((string)($_POST['fixation'] ?? ''));
        $rate = (string)($_POST['rate'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));
        if ($bank !== '' && $fixation !== '' && is_numeric($rate)) {
            db()->prepare('INSERT INTO formulare_refi_rates (bank, fixation, rate, note) VALUES (?, ?, ?, ?)')
                ->execute([$bank, $fixation, (float)$rate, $note]);
        }
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $bank = trim((string)($_POST['bank'] ?? ''));
        $fixation = trim((string)($_POST['fixation'] ?? ''));
        $rate = (string)($_POST['rate'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));
        if ($id && $bank !== '' && $fixation !== '' && is_numeric($rate)) {
            db()->prepare('UPDATE formulare_refi_rates SET bank = ?, fixation = ?, rate = ?, note = ?, updated_at = ? WHERE id = ?')
                ->execute([$bank, $fixation, (float)$rate, $note, date('Y-m-d H:i:s'), $id]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_refi_rates WHERE id = ?')->execute([$id]);
    }
    header('Location: /refinancny-radar.php');
    exit;
}

$rates = [];
try {
    $rates = db()->query('SELECT * FROM formulare_refi_rates ORDER BY fixation, rate ASC')->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }

const REFI_STALE_DAYS = 45;
function refiDaysOld(string $updatedAt): int {
    $diff = time() - strtotime($updatedAt);
    return (int)floor($diff / 86400);
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Refinančný Radar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=13">
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Refinančný Radar</h1>
    <p>Ručne udržiavaný prehľad sadzieb · žiadne automatické sťahovanie · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Pridať / aktualizovať sadzbu</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Žiadne dáta sa nesťahujú automaticky — zadávaš len ty, keď si sadzbu overíš. Pri každom zázname vidno, ako dávno bol aktualizovaný.
    </p>
    <form method="post" class="add-form">
      <input type="hidden" name="add" value="1">
      <input name="bank" placeholder="Banka (napr. VÚB)" required>
      <input name="fixation" placeholder="Fixácia (napr. 3 roky)" required>
      <input name="rate" type="number" step="0.01" min="0" max="20" placeholder="Sadzba % (napr. 3.45)" required>
      <input name="note" placeholder="Poznámka (voliteľné)">
      <button type="submit">Pridať</button>
    </form>
  </div>

  <div class="card">
    <h3>Sadzby · <?= count($rates) ?></h3>
    <table>
      <tr><th>Banka</th><th>Fixácia</th><th>Sadzba</th><th>Poznámka</th><th>Aktualizované</th><th></th></tr>
      <?php foreach ($rates as $r): $days = refiDaysOld($r['updated_at']); $stale = $days > REFI_STALE_DAYS; ?>
      <tr id="view-<?= (int)$r['id'] ?>">
        <td data-label="Banka"><b class="strong"><?= h($r['bank']) ?></b></td>
        <td data-label="Fixácia"><?= h($r['fixation']) ?></td>
        <td data-label="Sadzba"><?= number_format((float)$r['rate'], 2, ',', ' ') ?> %</td>
        <td data-label="Poznámka"><?= h($r['note']) ?: '—' ?></td>
        <td data-label="Aktualizované">
          <span class="pill <?= $stale ? 'pending' : 'submitted' ?>" title="<?= h($r['updated_at']) ?>">
            <?= $stale ? "Neaktualizované $days dní" : "Pred $days dňami" ?>
          </span>
        </td>
        <td style="display:flex; gap:6px;">
          <button type="button" class="toggle-btn" onclick="editRate(<?= (int)$r['id'] ?>)">Upraviť</button>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento záznam?');">
            <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="toggle-btn">Zmazať</button>
          </form>
        </td>
      </tr>
      <tr id="edit-<?= (int)$r['id'] ?>" style="display:none;">
        <td colspan="6">
          <form method="post" class="add-form" style="display:flex; flex-wrap:wrap; gap:10px;">
            <input type="hidden" name="edit_id" value="<?= (int)$r['id'] ?>">
            <input name="bank" value="<?= h($r['bank']) ?>" placeholder="Banka" required>
            <input name="fixation" value="<?= h($r['fixation']) ?>" placeholder="Fixácia" required>
            <input name="rate" type="number" step="0.01" min="0" max="20" value="<?= h($r['rate']) ?>" placeholder="Sadzba %" required>
            <input name="note" value="<?= h($r['note']) ?>" placeholder="Poznámka">
            <button type="submit">Uložiť</button>
            <button type="button" class="toggle-btn" onclick="cancelEdit(<?= (int)$r['id'] ?>)">Zrušiť</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rates): ?><tr><td colspan="6" style="color:var(--muted);">Zatiaľ žiadne sadzby — pridaj prvú vyššie.</td></tr><?php endif; ?>
    </table>
  </div>

</main>
<script>
function editRate(id){
  document.getElementById('view-'+id).style.display = 'none';
  document.getElementById('edit-'+id).style.display = '';
}
function cancelEdit(id){
  document.getElementById('edit-'+id).style.display = 'none';
  document.getElementById('view-'+id).style.display = '';
}
</script>
<script src="/assets/shell.js?v=9"></script>
</body></html>
