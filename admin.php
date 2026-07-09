<?php
/**
 * Prehľad pre majiteľa — zoznam poradcov, história dokumentov naprieč
 * všetkými poradcami, prehľad klientskych odkazov. Prístup: gate cookie
 * (rieši .htaccess) + cur_advisor musí patriť poradcovi s is_admin=1.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tools-registry.php';

$advisorId = curAdvisorId();
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
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $name = trim((string)($_POST['edit_name'] ?? ''));
        $org = trim((string)($_POST['edit_org'] ?? ''));
        $email = trim((string)($_POST['edit_email'] ?? ''));
        $phone = trim((string)($_POST['edit_phone'] ?? ''));
        if ($name !== '' && $email !== '') {
            $stmt = db()->prepare('UPDATE formulare_advisors SET name = ?, org = ?, email = ?, phone = ? WHERE id = ?');
            $stmt->execute([$name, $org, $email, $phone, $id]);
        }
    } elseif (isset($_POST['delete_doc_id'])) {
        $id = (int)$_POST['delete_doc_id'];
        db()->prepare('DELETE FROM formulare_generated_documents WHERE id = ?')->execute([$id]);
    } elseif (isset($_POST['pin_id'])) {
        $id = (int)$_POST['pin_id'];
        $newPin = trim((string)($_POST['pin'] ?? ''));
        if (preg_match('/^\d{4}$/', $newPin)) {
            $hash = password_hash($newPin, PASSWORD_DEFAULT);
            db()->prepare('UPDATE formulare_advisors SET pin_hash = ? WHERE id = ?')->execute([$hash, $id]);
            throttleReset('advisor:' . $id);
        }
    } elseif (isset($_POST['tools_id'])) {
        $id = (int)$_POST['tools_id'];
        $allSlugs = [];
        foreach ($TOOL_CATEGORIES as $cat) foreach ($cat['tools'] as $t) $allSlugs[] = toolSlug($t['href']);
        $enabledSlugs = array_map('strval', $_POST['enabled_tools'] ?? []);
        $disabledSlugs = array_values(array_diff($allSlugs, $enabledSlugs));
        db()->prepare('UPDATE formulare_advisors SET disabled_tools = ? WHERE id = ?')
            ->execute([json_encode($disabledSlugs), $id]);
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

$allToolSlugs = [];
foreach ($TOOL_CATEGORIES as $cat) foreach ($cat['tools'] as $t) $allToolSlugs[] = toolSlug($t['href']);
$totalToolCount = count($allToolSlugs);

function advisorDisabledSlugs(array $a, array $allToolSlugs): array {
    if (empty($a['disabled_tools'])) return [];
    $decoded = json_decode($a['disabled_tools'], true);
    if (!is_array($decoded)) return [];
    return array_values(array_intersect($decoded, $allToolSlugs)); // ignoruj zastarané/zmazané slugy
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Prehľad pre majiteľa</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=6">
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Prehľad pre majiteľa</h1>
    <p>Administrácia · poradcovia, dokumenty a klientske odkazy</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Poradcovia</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">Bez nastaveného PIN-u sa poradca nevie prihlásiť do svojej zóny — po pridaní nezabudni nastaviť PIN.</p>
    <table>
      <tr><th>Farba</th><th>Meno</th><th>Organizácia</th><th>E-mail</th><th>Telefón</th><th>PIN</th><th>Nástroje</th><th>Stav</th><th></th></tr>
      <?php foreach ($advisors as $a):
        $aDisabled = advisorDisabledSlugs($a, $allToolSlugs);
        $aEnabledCount = $totalToolCount - count($aDisabled);
      ?>
      <tr id="view-<?= (int)$a['id'] ?>" class="<?= $a['active'] ? '' : 'inactive' ?>">
        <td>
          <form method="post" class="color-form">
            <input type="hidden" name="color_id" value="<?= (int)$a['id'] ?>">
            <input type="color" name="color" value="<?= h($a['color']) ?>" onchange="this.form.requestSubmit()" title="Farba poradcu">
          </form>
        </td>
        <td><?= h($a['name']) ?><?= $a['is_admin'] ? ' (admin)' : '' ?><?= !empty($a['is_owner']) ? ' (vlastník)' : '' ?></td>
        <td><?= h($a['org']) ?></td>
        <td><?= h($a['email']) ?></td>
        <td><?= h($a['phone']) ?></td>
        <td><span class="pill <?= empty($a['pin_hash']) ? 'pending' : 'submitted' ?>"><?= empty($a['pin_hash']) ? 'Nenastavený' : 'Nastavený' ?></span></td>
        <td><span class="pill <?= $aEnabledCount === $totalToolCount ? 'submitted' : 'pending' ?>"><?= $aEnabledCount ?>/<?= $totalToolCount ?></span></td>
        <td><?= $a['active'] ? 'aktívny' : 'neaktívny' ?></td>
        <td style="display:grid; grid-template-columns:repeat(2,1fr); gap:6px; min-width:184px;">
          <button type="button" class="toggle-btn" onclick="editAdvisor(<?= (int)$a['id'] ?>)">Upraviť</button>
          <button type="button" class="toggle-btn" onclick="editPin(<?= (int)$a['id'] ?>)">PIN</button>
          <button type="button" class="toggle-btn" onclick="editTools(<?= (int)$a['id'] ?>)">Nástroje</button>
          <form method="post" style="display:contents;">
            <input type="hidden" name="toggle_id" value="<?= (int)$a['id'] ?>">
            <button type="submit" class="toggle-btn" style="width:100%;"><?= $a['active'] ? 'Deaktivovať' : 'Aktivovať' ?></button>
          </form>
        </td>
      </tr>
      <tr id="edit-<?= (int)$a['id'] ?>" style="display:none;">
        <td colspan="9">
          <form method="post" class="add-form" style="display:flex; flex-wrap:wrap; gap:10px;">
            <input type="hidden" name="edit_id" value="<?= (int)$a['id'] ?>">
            <input name="edit_name" value="<?= h($a['name']) ?>" placeholder="Meno" required>
            <input name="edit_org" value="<?= h($a['org']) ?>" placeholder="Organizácia">
            <input name="edit_email" type="email" value="<?= h($a['email']) ?>" placeholder="E-mail" required>
            <input name="edit_phone" value="<?= h($a['phone']) ?>" placeholder="Telefón">
            <button type="submit">Uložiť</button>
            <button type="button" class="toggle-btn" onclick="cancelEdit(<?= (int)$a['id'] ?>)">Zrušiť</button>
          </form>
        </td>
      </tr>
      <tr id="pin-<?= (int)$a['id'] ?>" style="display:none;">
        <td colspan="9">
          <form method="post" class="add-form" style="display:flex; flex-wrap:wrap; gap:10px; max-width:340px;">
            <input type="hidden" name="pin_id" value="<?= (int)$a['id'] ?>">
            <input name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="Nový 4-miestny PIN"
                   style="text-align:center; letter-spacing:.3em;" required>
            <button type="submit">Uložiť PIN</button>
            <button type="button" class="toggle-btn" onclick="cancelPin(<?= (int)$a['id'] ?>)">Zrušiť</button>
          </form>
        </td>
      </tr>
      <tr id="tools-<?= (int)$a['id'] ?>" style="display:none;">
        <td colspan="9">
          <form method="post" style="max-width:640px;">
            <input type="hidden" name="tools_id" value="<?= (int)$a['id'] ?>">
            <div style="display:flex; gap:8px; margin-bottom:10px;">
              <button type="button" class="toggle-btn" onclick="setAllTools(<?= (int)$a['id'] ?>, true)">Zapnúť všetko</button>
              <button type="button" class="toggle-btn" onclick="setAllTools(<?= (int)$a['id'] ?>, false)">Vypnúť všetko</button>
            </div>
            <?php foreach ($TOOL_CATEGORIES as $cat): ?>
            <div style="margin-bottom:12px;">
              <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin-bottom:6px;"><?= h($cat['title']) ?></div>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 16px;">
                <?php foreach ($cat['tools'] as $t): $slug = toolSlug($t['href']); ?>
                <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                  <input type="checkbox" name="enabled_tools[]" value="<?= h($slug) ?>" <?= in_array($slug, $aDisabled, true) ? '' : 'checked' ?>>
                  <?= h($t['name']) ?>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <div style="display:flex; gap:8px; margin-top:6px;">
              <button type="submit">Uložiť nástroje</button>
              <button type="button" class="toggle-btn" onclick="cancelTools(<?= (int)$a['id'] ?>)">Zrušiť</button>
            </div>
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
    <h3>Vygenerované dokumenty · posledných 200</h3>
    <table>
      <tr><th>Poradca</th><th>Klient</th><th>Nástroj</th><th>Zdroj</th><th>Kedy</th><th></th></tr>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><?= h($d['advisor_name']) ?></td>
        <td><?= h($d['client_label']) ?></td>
        <td><?= h(toolLabel($d['tool'])) ?></td>
        <td><?= $d['source'] === 'client' ? 'klient' : 'poradca' ?></td>
        <td class="date"><?= h($d['generated_at']) ?></td>
        <td style="display:flex; gap:6px;">
          <a class="toggle-btn" href="/<?= rawurlencode($d['tool']) ?>/index.html?loadDoc=<?= (int)$d['id'] ?>" target="_blank">PDF</a>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento dokument?');">
            <input type="hidden" name="delete_doc_id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="toggle-btn">Zmazať</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$docs): ?><tr><td colspan="6" style="color:var(--muted);">Zatiaľ žiadne dokumenty.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h3>Klientske odkazy</h3>
    <table>
      <tr><th>Poradca</th><th>Klient</th><th>Nástroj</th><th>Stav</th><th>Vytvorené</th></tr>
      <?php foreach ($links as $l): ?>
      <tr>
        <td><?= h($l['advisor_name']) ?></td>
        <td><?= h($l['client_label']) ?></td>
        <td><?= h(toolLabel($l['tool'])) ?></td>
        <td><span class="pill <?= $l['status'] ?>"><?= $l['status']==='submitted' ? 'Vyplnené' : 'Čaká' ?></span></td>
        <td class="date"><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$links): ?><tr><td colspan="5" style="color:var(--muted);">Zatiaľ žiadne odkazy.</td></tr><?php endif; ?>
    </table>
  </div>
</main>
<script src="/assets/shell.js?v=4"></script>
<script>
function editAdvisor(id){
  document.getElementById('view-'+id).style.display = 'none';
  document.getElementById('edit-'+id).style.display = 'table-row';
}
function cancelEdit(id){
  document.getElementById('edit-'+id).style.display = 'none';
  document.getElementById('view-'+id).style.display = '';
}
function editPin(id){
  document.getElementById('pin-'+id).style.display = 'table-row';
}
function cancelPin(id){
  document.getElementById('pin-'+id).style.display = 'none';
}
function editTools(id){
  document.getElementById('tools-'+id).style.display = 'table-row';
}
function cancelTools(id){
  document.getElementById('tools-'+id).style.display = 'none';
}
function setAllTools(id, on){
  document.querySelectorAll('#tools-'+id+' input[type=checkbox]').forEach(function(cb){ cb.checked = on; });
}
</script>
</body></html>
