<?php
/**
 * Prehľad pre majiteľa — zoznam poradcov, história dokumentov naprieč
 * všetkými poradcami, prehľad klientskych odkazov. Prístup: gate cookie
 * (rieši .htaccess) + cur_advisor musí patriť poradcovi s is_admin=1.
 */
require_once __DIR__ . '/db.php';

$advisorId = isset($_COOKIE['cur_advisor']) ? (int)$_COOKIE['cur_advisor'] : 0;
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_admin = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

// --- akcie: pridanie / deaktivácia poradcu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_name'])) {
        $name = trim((string)$_POST['add_name']);
        $org = trim((string)($_POST['add_org'] ?? ''));
        $email = trim((string)($_POST['add_email'] ?? ''));
        $phone = trim((string)($_POST['add_phone'] ?? ''));
        if ($name !== '' && $email !== '') {
            $stmt = db()->prepare('INSERT INTO formulare_advisors (name, org, email, phone) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $org, $email, $phone]);
        }
    } elseif (isset($_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        db()->prepare('UPDATE formulare_advisors SET active = 1 - active WHERE id = ?')->execute([$id]);
    } elseif (isset($_POST['color_id'])) {
        $id = (int)$_POST['color_id'];
        $color = (string)($_POST['color'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            db()->prepare('UPDATE formulare_advisors SET color = ? WHERE id = ?')->execute([$color, $id]);
        }
    }
    header('Location: /admin.php');
    exit;
}

$advisors = db()->query('SELECT * FROM formulare_advisors ORDER BY active DESC, name')->fetchAll();
$docs = db()->query(
    'SELECT d.*, a.name AS advisor_name FROM formulare_generated_documents d
     JOIN formulare_advisors a ON a.id = d.advisor_id ORDER BY d.generated_at DESC LIMIT 200'
)->fetchAll();
$links = db()->query(
    'SELECT l.*, a.name AS advisor_name FROM formulare_client_links l
     JOIN formulare_advisors a ON a.id = l.advisor_id ORDER BY l.created_at DESC LIMIT 200'
)->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Prehľad pre majiteľa</title>
<style>
  :root{ --accent:#1f5fd1; --accent-soft:#eaf1fc; --ink:#1d2536; --muted:#8b94a3; --border:#e2e6ee; --bg:#eef1f6; }
  *{box-sizing:border-box;}
  body{ margin:0; background:var(--bg); color:var(--ink); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; padding:24px; }
  .wrap{ max-width:1100px; margin:0 auto; display:flex; flex-direction:column; gap:24px; }
  h1{ font-size:22px; margin:0; }
  .back{ font-size:13px; color:var(--accent); text-decoration:none; }
  .card{ background:#fff; border:1px solid var(--border); border-radius:16px; padding:22px 24px; }
  .card h2{ font-size:15px; margin:0 0 14px; }
  table{ width:100%; border-collapse:collapse; font-size:13px; }
  th{ text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted);
      border-bottom:1px solid var(--border); padding:8px 10px; }
  td{ padding:8px 10px; border-bottom:1px solid #f0f1f5; vertical-align:top; }
  tr.inactive td{ color:var(--muted); text-decoration:line-through; }
  .pill{ display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:700; }
  .pill.submitted{ background:#e5f7e5; color:#0ca30c; }
  .pill.pending{ background:#fdf3e5; color:#c98500; }
  .add-form{ display:grid; grid-template-columns:repeat(4,1fr) auto; gap:10px; margin-top:14px; }
  .add-form input{ padding:9px 11px; border:1.5px solid var(--border); border-radius:10px; font-size:13px; }
  .add-form button{ padding:9px 16px; border:none; border-radius:10px; background:var(--accent); color:#fff; font-weight:700; cursor:pointer; }
  .toggle-btn{ padding:5px 10px; border:1.5px solid var(--border); border-radius:8px; background:#fff; font-size:12px; cursor:pointer; }
  .color-form{ display:flex; align-items:center; gap:6px; margin:0; }
  .color-form input[type=color]{ width:30px; height:30px; padding:0; border:1.5px solid var(--border); border-radius:8px; cursor:pointer; background:#fff; }
  .color-form button{ padding:5px 8px; border:1.5px solid var(--border); border-radius:8px; background:#fff; font-size:11px; cursor:pointer; }
  @media (max-width:720px){ .add-form{ grid-template-columns:1fr; } table{ display:block; overflow-x:auto; } }
</style>
</head><body>
<div class="wrap">
  <div><a href="/" class="back">← Späť na formuláre</a></div>
  <h1>Prehľad pre majiteľa</h1>

  <div class="card">
    <h2>Poradcovia</h2>
    <table>
      <tr><th>Farba</th><th>Meno</th><th>Organizácia</th><th>E-mail</th><th>Telefón</th><th>Stav</th><th></th></tr>
      <?php foreach ($advisors as $a): ?>
      <tr class="<?= $a['active'] ? '' : 'inactive' ?>">
        <td>
          <form method="post" class="color-form">
            <input type="hidden" name="color_id" value="<?= (int)$a['id'] ?>">
            <input type="color" name="color" value="<?= h($a['color']) ?>" onchange="this.form.requestSubmit()" title="Farba poradcu">
          </form>
        </td>
        <td><?= h($a['name']) ?><?= $a['is_admin'] ? ' (admin)' : '' ?></td>
        <td><?= h($a['org']) ?></td>
        <td><?= h($a['email']) ?></td>
        <td><?= h($a['phone']) ?></td>
        <td><?= $a['active'] ? 'aktívny' : 'neaktívny' ?></td>
        <td>
          <form method="post" style="margin:0;">
            <input type="hidden" name="toggle_id" value="<?= (int)$a['id'] ?>">
            <button type="submit" class="toggle-btn"><?= $a['active'] ? 'Deaktivovať' : 'Aktivovať' ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" class="add-form">
      <input name="add_name" placeholder="Meno" required>
      <input name="add_org" placeholder="Organizácia">
      <input name="add_email" type="email" placeholder="E-mail" required>
      <input name="add_phone" placeholder="Telefón">
      <button type="submit">Pridať poradcu</button>
    </form>
  </div>

  <div class="card">
    <h2>Vygenerované dokumenty (posledných 200)</h2>
    <table>
      <tr><th>Poradca</th><th>Klient</th><th>Nástroj</th><th>Zdroj</th><th>Kedy</th></tr>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><?= h($d['advisor_name']) ?></td>
        <td><?= h($d['client_label']) ?></td>
        <td><?= h($d['tool']) ?></td>
        <td><?= $d['source'] === 'client' ? 'klient' : 'poradca' ?></td>
        <td><?= h($d['generated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$docs): ?><tr><td colspan="5" style="color:var(--muted);">Zatiaľ žiadne dokumenty.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>Klientske odkazy</h2>
    <table>
      <tr><th>Poradca</th><th>Klient</th><th>Nástroj</th><th>Stav</th><th>Vytvorené</th></tr>
      <?php foreach ($links as $l): ?>
      <tr>
        <td><?= h($l['advisor_name']) ?></td>
        <td><?= h($l['client_label']) ?></td>
        <td><?= h($l['tool']) ?></td>
        <td><span class="pill <?= $l['status'] ?>"><?= $l['status']==='submitted' ? 'Vyplnené' : 'Čaká' ?></span></td>
        <td><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$links): ?><tr><td colspan="5" style="color:var(--muted);">Zatiaľ žiadne odkazy.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
</body></html>
