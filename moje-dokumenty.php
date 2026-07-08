<?php
/**
 * Vlastná história poradcu — jeho vygenerované dokumenty a klientske odkazy.
 * Na rozdiel od admin.php (majiteľ, vidí všetkých) tu každý poradca vidí
 * len svoje vlastné záznamy (filter podľa cur_advisor cookie).
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
if (!$advisorId) { header('Location: /'); exit; }

$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    db()->prepare('DELETE FROM formulare_generated_documents WHERE id = ? AND advisor_id = ?')->execute([$id, $advisorId]);
    header('Location: /moje-dokumenty.php');
    exit;
}

$docs = db()->prepare(
    'SELECT * FROM formulare_generated_documents WHERE advisor_id = ? ORDER BY generated_at DESC LIMIT 200'
);
$docs->execute([$advisorId]);
$docs = $docs->fetchAll();

$links = db()->prepare(
    'SELECT * FROM formulare_client_links WHERE advisor_id = ? ORDER BY created_at DESC LIMIT 200'
);
$links->execute([$advisorId]);
$links = $links->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Moje dokumenty</title>
<link rel="stylesheet" href="/assets/panel.css?v=1">
</head><body>
<div class="wrap">

  <div class="topbar">
    <div class="mark">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
      </svg>
    </div>
    <span class="wordmark">Formuláre</span>
    <a href="/nastroje.php" class="back">← Späť na nástroje</a>
  </div>

  <div>
    <div class="kicker">Archív poradcu · <?= h($me['name']) ?></div>
    <h1>Moje dokumenty</h1>
  </div>

  <div class="card">
    <h2>Vygenerované dokumenty · posledných 200</h2>
    <table>
      <tr><th>Klient</th><th>Nástroj</th><th>Zdroj</th><th>Kedy</th><th></th></tr>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><?= h($d['client_label']) ?></td>
        <td><?= h(toolLabel($d['tool'])) ?></td>
        <td><?= $d['source'] === 'client' ? 'klient' : 'poradca' ?></td>
        <td class="date"><?= h($d['generated_at']) ?></td>
        <td style="display:flex; gap:6px;">
          <a class="toggle-btn" href="/<?= rawurlencode($d['tool']) ?>/index.html?loadDoc=<?= (int)$d['id'] ?>" target="_blank">PDF</a>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento dokument?');">
            <input type="hidden" name="delete_id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="toggle-btn">Zmazať</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$docs): ?><tr><td colspan="5" style="color:var(--muted);">Zatiaľ žiadne dokumenty.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>Klientske odkazy</h2>
    <table>
      <tr><th>Klient</th><th>Nástroj</th><th>Stav</th><th>Vytvorené</th></tr>
      <?php foreach ($links as $l): ?>
      <tr>
        <td><?= h($l['client_label']) ?></td>
        <td><?= h($l['tool']) ?></td>
        <td><span class="pill <?= $l['status'] ?>"><?= $l['status']==='submitted' ? 'Vyplnené' : 'Čaká' ?></span></td>
        <td class="date"><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$links): ?><tr><td colspan="4" style="color:var(--muted);">Zatiaľ žiadne odkazy.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
</body></html>
