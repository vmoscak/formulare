<?php
/**
 * Vlastná história poradcu — jeho vygenerované dokumenty a klientske odkazy.
 * Na rozdiel od admin.php (majiteľ, vidí všetkých) tu každý poradca vidí
 * len svoje vlastné záznamy (filter podľa cur_advisor cookie).
 */
require_once __DIR__ . '/db.php';

$advisorId = isset($_COOKIE['cur_advisor']) ? (int)$_COOKIE['cur_advisor'] : 0;
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
<style>
  :root{ --accent:<?= h($me['color']) ?>; --accent-soft:#eaf1fc; --ink:#1d2536; --muted:#8b94a3; --border:#e2e6ee; --bg:#eef1f6; }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--bg); color:var(--ink); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; padding:24px; }
  .wrap{ max-width:1100px; margin:0 auto; display:flex; flex-direction:column; gap:24px; }
  h1{
    font-size:22px; margin:0;
    opacity:0; transform:translateY(10px);
    animation:riseIn .5s cubic-bezier(.22,1,.36,1) forwards;
  }
  .back{ font-size:13px; color:var(--accent); text-decoration:none; transition:opacity .2s ease; }
  .back:hover{ opacity:.7; }
  .card{
    background:#fff; border:1px solid var(--border); border-radius:16px; padding:22px 24px;
    opacity:0; transform:translateY(16px);
    animation:riseIn .5s cubic-bezier(.22,1,.36,1) forwards;
  }
  .card:nth-of-type(1){ animation-delay:.05s; }
  .card:nth-of-type(2){ animation-delay:.12s; }
  @keyframes riseIn{ to{ opacity:1; transform:translateY(0); } }
  @media(prefers-reduced-motion:reduce){ h1,.card{ animation:none; opacity:1; transform:none; } }
  .card h2{ font-size:15px; margin:0 0 14px; }
  table{ width:100%; border-collapse:collapse; font-size:13px; }
  th{ text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted);
      border-bottom:1px solid var(--border); padding:8px 10px; }
  td{ padding:8px 10px; border-bottom:1px solid #f0f1f5; vertical-align:top; }
  tbody tr{ transition:background-color .18s ease; }
  tbody tr:hover{ background-color:#f7f9fc; }
  .pill{ display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; }
  .pill.submitted{ background:#e5f7e5; color:#0ca30c; }
  .pill.pending{ background:#fdf3e5; color:#c98500; }
  .toggle-btn{
    padding:5px 10px; border:1.5px solid var(--border); border-radius:8px; background:#fff; font-size:12px; cursor:pointer;
    transition:border-color .18s ease, transform .18s ease;
  }
  .toggle-btn:hover{ border-color:var(--accent); transform:translateY(-1px); }
  @media (max-width:720px){ table{ display:block; overflow-x:auto; } }
</style>
</head><body>
<div class="wrap">
  <div><a href="/nastroje.php" class="back">← Späť na nástroje</a></div>
  <h1>Moje dokumenty</h1>

  <div class="card">
    <h2>Vygenerované dokumenty (posledných 200)</h2>
    <table>
      <tr><th>Klient</th><th>Nástroj</th><th>Zdroj</th><th>Kedy</th><th></th></tr>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><?= h($d['client_label']) ?></td>
        <td><?= h($d['tool']) ?></td>
        <td><?= $d['source'] === 'client' ? 'klient' : 'poradca' ?></td>
        <td><?= h($d['generated_at']) ?></td>
        <td>
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
        <td><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$links): ?><tr><td colspan="4" style="color:var(--muted);">Zatiaľ žiadne odkazy.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
</body></html>
