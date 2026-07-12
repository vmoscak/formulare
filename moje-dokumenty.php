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
function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Moje dokumenty</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=17">
</head><body>

<header class="topbar">
  <div class="tb-title">
    <h1>Moje dokumenty</h1>
    <p>Archív poradcu · <?= h($me['name']) ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
    <span class="who">
      <span class="ini" style="background:<?= h($me['color']) ?>;"><?= h(advisorInitials($me['name'])) ?></span>
      <b><?= h($me['name']) ?></b>
    </span>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Vygenerované dokumenty · posledných 200</h3>
    <table>
      <tr><th>Klient</th><th>Nástroj</th><th>Zdroj</th><th>Kedy</th><th></th></tr>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><span class="strong"><?= h($d['client_label']) ?></span></td>
        <td><?= h(toolLabel($d['tool'])) ?></td>
        <td><?= $d['source'] === 'client' ? 'klient' : 'poradca' ?></td>
        <td class="date"><?= h($d['generated_at']) ?></td>
        <td style="display:flex; gap:6px; justify-content:flex-end;">
          <a class="toggle-btn" href="/<?= rawurlencode($d['tool']) ?>/index.html?loadDoc=<?= (int)$d['id'] ?>" target="_blank">PDF</a>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento dokument?');">
            <input type="hidden" name="delete_id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="toggle-btn">Zmazať</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$docs): ?><tr><td colspan="5"><div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/></svg>
        <span class="es-title">Zatiaľ žiadne dokumenty</span>
        <span class="es-sub">Vygenerované PDF sa tu objavia automaticky, hneď ako si nejaké stiahneš z niektorého nástroja.</span>
      </div></td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h3>Klientske odkazy</h3>
    <table>
      <tr><th>Klient</th><th>Nástroj</th><th>Stav</th><th>Vytvorené</th></tr>
      <?php foreach ($links as $l): ?>
      <tr>
        <td><span class="strong"><?= h($l['client_label']) ?></span></td>
        <td><?= h(toolLabel($l['tool'])) ?></td>
        <td><span class="pill <?= h($l['status']) ?>"><?= $l['status']==='submitted' ? 'Vyplnené' : 'Čaká' ?></span></td>
        <td class="date"><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$links): ?><tr><td colspan="4"><div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        <span class="es-title">Zatiaľ žiadne odkazy</span>
        <span class="es-sub">Klientske odkazy vytvorené z niektorého nástroja sa tu objavia spolu s ich stavom (čaká/vyplnené).</span>
      </div></td></tr><?php endif; ?>
    </table>
  </div>

</main>

<script src="/assets/shell.js?v=16"></script>
</body></html>
