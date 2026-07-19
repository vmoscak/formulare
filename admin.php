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

/**
 * Spustí jeden sql/*.sql súbor — rozdelí na jednotlivé príkazy podľa ";"
 * (migrácie v tomto projekte neobsahujú uložené procedúry ani bodkočiarky
 * v reťazcoch, overené pri zavedení tejto funkcie) a vykoná ich postupne.
 * Vráti true pri úplnom úspechu, inak textový popis chyby vrátane toho,
 * koľko príkazov pred zlyhaním prebehlo — DDL príkazy (CREATE/ALTER TABLE)
 * v MySQL aj tak commitujú samé osebe, takže transakcia by čiastočný
 * priebeh nezachránila; radšej sa to úprimne prizná než by appka predstierala
 * atomickosť, ktorú nemá.
 */
function runMigrationFile(string $path): true|string {
    $sql = file_get_contents($path);
    if ($sql === false) return 'súbor sa nedá prečítať';
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql)), function ($s) {
        // Vynechať prázdne úseky a úseky, čo sú len komentáre (žiadny riadok neobsahuje reálny príkaz).
        foreach (explode("\n", $s) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '--')) return true;
        }
        return false;
    }));
    if (!$statements) return 'súbor neobsahuje žiadny príkaz';
    $pdo = db();
    foreach ($statements as $i => $stmt) {
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            $done = $i;
            return "príkaz č. " . ($i + 1) . "/" . count($statements) . " zlyhal ($done predchádzajúcich prebehlo): " . $e->getMessage();
        }
    }
    return true;
}

// --- akcie: pridanie / deaktivácia poradcu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfCheck()) { http_response_code(403); exit('Neplatný CSRF token — obnov stránku a skús to znova.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_name'])) {
        $name = trim((string)$_POST['add_name']);
        $org = trim((string)($_POST['add_org'] ?? ''));
        $email = trim((string)($_POST['add_email'] ?? ''));
        $phone = trim((string)($_POST['add_phone'] ?? ''));
        $sfaAcq = trim((string)($_POST['add_sfa_acquisition_no'] ?? ''));
        $sfaPer = trim((string)($_POST['add_sfa_personal_no'] ?? ''));
        $nbsNo = trim((string)($_POST['add_nbs_registration_no'] ?? ''));
        if ($name !== '' && $email !== '') {
            $stmt = db()->prepare('INSERT INTO formulare_advisors (name, org, email, phone, sfa_acquisition_no, sfa_personal_no, nbs_registration_no) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $org, $email, $phone, $sfaAcq, $sfaPer, $nbsNo]);
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
        $sfaAcq = trim((string)($_POST['edit_sfa_acquisition_no'] ?? ''));
        $sfaPer = trim((string)($_POST['edit_sfa_personal_no'] ?? ''));
        $nbsNo = trim((string)($_POST['edit_nbs_registration_no'] ?? ''));
        if ($name !== '' && $email !== '') {
            $stmt = db()->prepare('UPDATE formulare_advisors SET name = ?, org = ?, email = ?, phone = ?, sfa_acquisition_no = ?, sfa_personal_no = ?, nbs_registration_no = ? WHERE id = ?');
            $stmt->execute([$name, $org, $email, $phone, $sfaAcq, $sfaPer, $nbsNo, $id]);
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
    } elseif (isset($_POST['mark_applied_file'])) {
        $file = basename((string)$_POST['mark_applied_file']);
        if (preg_match('/^\d{3}_[\w.-]+\.sql$/', $file) && is_file(__DIR__ . '/sql/' . $file)) {
            try {
                db()->prepare('INSERT INTO formulare_schema_migrations (filename, applied_by, note) VALUES (?, ?, ?)')
                    ->execute([$file, $me['name'], 'Označené ako už spustené (bez automatického behu)']);
            } catch (Throwable $e) { $migrationRunError = 'Tabuľka formulare_schema_migrations ešte neexistuje — spusti najprv sql/036_schema_migrations_tracking.sql ručne v phpMyAdmin.'; }
        }
    } elseif (isset($_POST['run_migration_file'])) {
        $file = basename((string)$_POST['run_migration_file']);
        if (preg_match('/^\d{3}_[\w.-]+\.sql$/', $file) && is_file(__DIR__ . '/sql/' . $file)) {
            $result = runMigrationFile(__DIR__ . '/sql/' . $file);
            if ($result === true) {
                try {
                    db()->prepare('INSERT INTO formulare_schema_migrations (filename, applied_by, note) VALUES (?, ?, ?)')
                        ->execute([$file, $me['name'], 'Spustené z admin panela']);
                } catch (Throwable $e) { $migrationRunError = 'Migrácia prebehla, ale nepodarilo sa ju zapísať do formulare_schema_migrations: ' . $e->getMessage(); }
            } else {
                $migrationRunError = "Migrácia $file zlyhala: $result";
            }
        }
    }
    if (empty($migrationRunError)) { header('Location: /admin.php'); exit; }
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

$allToolSlugs = [];
foreach ($TOOL_CATEGORIES as $cat) foreach ($cat['tools'] as $t) $allToolSlugs[] = toolSlug($t['href']);
$totalToolCount = count($allToolSlugs);

// --- databázové migrácie: porovnanie sql/*.sql so záznamami v DB ---
$migrationFiles = glob(__DIR__ . '/sql/*.sql') ?: [];
sort($migrationFiles);
$migrationFiles = array_map('basename', $migrationFiles);
$appliedMigrations = [];
$migrationsTrackingMissing = false;
try {
    foreach (db()->query('SELECT filename, applied_at, applied_by, note FROM formulare_schema_migrations') as $row) {
        $appliedMigrations[$row['filename']] = $row;
    }
} catch (Throwable $e) { $migrationsTrackingMissing = true; }

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
<link rel="stylesheet" href="<?= asset('fonts.css') ?>">
<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('panel.css') ?>">
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
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">Bez nastaveného PIN-u sa poradca nevie prihlásiť do svojej zóny — po pridaní nezabudni nastaviť PIN. Získateľské/osobné číslo SFA/VFA a registračné číslo v NBS (nastavíš cez „Upraviť") sa automaticky predvyplnia v nástroji Zmena správcu zmluvy.</p>
    <table>
      <tr><th>Farba</th><th>Meno</th><th>Organizácia</th><th>E-mail</th><th>Telefón</th><th>PIN</th><th>Nástroje</th><th>Stav</th><th></th></tr>
      <?php foreach ($advisors as $a):
        $aDisabled = advisorDisabledSlugs($a, $allToolSlugs);
        $aEnabledCount = $totalToolCount - count($aDisabled);
      ?>
      <tr id="view-<?= (int)$a['id'] ?>" class="<?= $a['active'] ? '' : 'inactive' ?>">
        <td data-label="Farba">
          <form method="post" class="color-form"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="color_id" value="<?= (int)$a['id'] ?>">
            <input type="color" name="color" value="<?= h($a['color']) ?>" onchange="this.form.requestSubmit()" title="Farba poradcu">
          </form>
        </td>
        <td data-label="Meno"><?= h($a['name']) ?><?= $a['is_admin'] ? ' (admin)' : '' ?><?= !empty($a['is_owner']) ? ' (vlastník)' : '' ?></td>
        <td data-label="Organizácia"><?= h($a['org']) ?></td>
        <td data-label="E-mail"><?= h($a['email']) ?></td>
        <td data-label="Telefón"><?= h($a['phone']) ?></td>
        <td data-label="PIN"><span class="pill <?= empty($a['pin_hash']) ? 'pending' : 'submitted' ?>"><?= empty($a['pin_hash']) ? 'Nenastavený' : 'Nastavený' ?></span></td>
        <td data-label="Nástroje"><span class="pill <?= $aEnabledCount === $totalToolCount ? 'submitted' : 'pending' ?>"><?= $aEnabledCount ?>/<?= $totalToolCount ?></span></td>
        <td data-label="Stav"><?= $a['active'] ? 'aktívny' : 'neaktívny' ?></td>
        <td style="display:grid; grid-template-columns:repeat(2,1fr); gap:6px; min-width:184px;">
          <button type="button" class="toggle-btn" onclick="editAdvisor(<?= (int)$a['id'] ?>)">Upraviť</button>
          <button type="button" class="toggle-btn" onclick="editPin(<?= (int)$a['id'] ?>)">PIN</button>
          <button type="button" class="toggle-btn" onclick="editTools(<?= (int)$a['id'] ?>)">Nástroje</button>
          <form method="post" style="display:contents;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="toggle_id" value="<?= (int)$a['id'] ?>">
            <button type="submit" class="toggle-btn" style="width:100%;"><?= $a['active'] ? 'Deaktivovať' : 'Aktivovať' ?></button>
          </form>
        </td>
      </tr>
      <tr id="edit-<?= (int)$a['id'] ?>" style="display:none;">
        <td colspan="9">
          <form method="post" class="add-form" style="display:flex; flex-wrap:wrap; gap:10px;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="edit_id" value="<?= (int)$a['id'] ?>">
            <input name="edit_name" value="<?= h($a['name']) ?>" placeholder="Meno" required>
            <input name="edit_org" value="<?= h($a['org']) ?>" placeholder="Organizácia">
            <input name="edit_email" type="email" value="<?= h($a['email']) ?>" placeholder="E-mail" required>
            <input name="edit_phone" value="<?= h($a['phone']) ?>" placeholder="Telefón">
            <input name="edit_sfa_acquisition_no" value="<?= h($a['sfa_acquisition_no'] ?? '') ?>" placeholder="Získateľské číslo SFA/VFA" style="min-width:190px;">
            <input name="edit_sfa_personal_no" value="<?= h($a['sfa_personal_no'] ?? '') ?>" placeholder="Osobné číslo SFA/VFA" style="min-width:170px;">
            <input name="edit_nbs_registration_no" value="<?= h($a['nbs_registration_no'] ?? '') ?>" placeholder="Registračné číslo v NBS" style="min-width:190px;">
            <button type="submit">Uložiť</button>
            <button type="button" class="toggle-btn" onclick="cancelEdit(<?= (int)$a['id'] ?>)">Zrušiť</button>
          </form>
        </td>
      </tr>
      <tr id="pin-<?= (int)$a['id'] ?>" style="display:none;">
        <td colspan="9">
          <form method="post" class="add-form" style="display:flex; flex-wrap:wrap; gap:10px; max-width:340px;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
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
          <form method="post" style="max-width:640px;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="tools_id" value="<?= (int)$a['id'] ?>">
            <div style="display:flex; gap:8px; margin-bottom:10px;">
              <button type="button" class="toggle-btn" onclick="setAllTools(<?= (int)$a['id'] ?>, true)">Zapnúť všetko</button>
              <button type="button" class="toggle-btn" onclick="setAllTools(<?= (int)$a['id'] ?>, false)">Vypnúť všetko</button>
            </div>
            <?php foreach ($TOOL_CATEGORIES as $cat): ?>
            <div style="margin-bottom:12px;">
              <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin-bottom:6px;"><?= h($cat['title']) ?></div>
              <div class="tools-grid">
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
    <form method="post" class="add-form"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <input name="add_name" placeholder="Meno" required>
      <input name="add_org" placeholder="Organizácia">
      <input name="add_email" type="email" placeholder="E-mail" required>
      <input name="add_phone" placeholder="Telefón">
      <input name="add_sfa_acquisition_no" placeholder="Získateľské číslo SFA/VFA" style="min-width:190px;">
      <input name="add_sfa_personal_no" placeholder="Osobné číslo SFA/VFA" style="min-width:170px;">
      <input name="add_nbs_registration_no" placeholder="Registračné číslo v NBS" style="min-width:190px;">
      <button type="submit">Pridať poradcu</button>
    </form>
  </div>

  <div class="card">
    <h3>Vygenerované dokumenty · posledných 200</h3>
    <table>
      <tr><th>Poradca</th><th>Klient</th><th>Nástroj</th><th>Zdroj</th><th>Kedy</th><th></th></tr>
      <?php foreach ($docs as $d): $isDraft = !empty($d['is_draft']); ?>
      <tr>
        <td data-label="Poradca"><?= h($d['advisor_name']) ?></td>
        <td data-label="Klient"><?= h($d['client_label']) ?><?php if ($isDraft): ?> <span class="pill pending">Koncept</span><?php endif; ?></td>
        <td data-label="Nástroj"><?= h(toolLabel($d['tool'])) ?></td>
        <td data-label="Zdroj"><?= $d['source'] === 'client' ? 'klient' : 'poradca' ?></td>
        <td class="date" data-label="Kedy"><?= h($d['generated_at']) ?></td>
        <td style="display:flex; gap:6px; justify-content:flex-start;">
          <a class="toggle-btn" href="/<?= rawurlencode($d['tool']) ?>/index.html?loadDoc=<?= (int)$d['id'] ?>" target="_blank"><?= $isDraft ? 'Pokračovať' : 'PDF' ?></a>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento dokument?');"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
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
        <td data-label="Poradca"><?= h($l['advisor_name']) ?></td>
        <td data-label="Klient"><?= h($l['client_label']) ?></td>
        <td data-label="Nástroj"><?= h(toolLabel($l['tool'])) ?></td>
        <td data-label="Stav"><span class="pill <?= $l['status'] ?>"><?= $l['status']==='submitted' ? 'Vyplnené' : 'Čaká' ?></span></td>
        <td class="date" data-label="Vytvorené"><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$links): ?><tr><td colspan="5" style="color:var(--muted);">Zatiaľ žiadne odkazy.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h3>Databázové migrácie</h3>
    <?php if (!empty($migrationRunError)): ?>
    <div class="pill" style="background:#fee2e2; color:#b91c1c; display:block; padding:10px 14px; margin-bottom:14px; white-space:pre-wrap;"><?= h($migrationRunError) ?></div>
    <?php endif; ?>
    <?php if ($migrationsTrackingMissing): ?>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Sledovanie migrácií ešte nie je zapnuté — spusti ručne v phpMyAdmin <code>sql/036_schema_migrations_tracking.sql</code>, potom sa tu objaví zoznam so stavom každej migrácie.
    </p>
    <?php else: ?>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Súbory 001–036 si už (podľa histórie appky) pravdepodobne spustil ručne v phpMyAdmin — potvrď to tlačidlom „Označiť ako už spustené" (appka to nevie zistiť sama). Od ďalšej novej migrácie stačí „Spustiť".
    </p>
    <table>
      <tr><th>Súbor</th><th>Stav</th><th></th></tr>
      <?php foreach ($migrationFiles as $mf): $applied = $appliedMigrations[$mf] ?? null; ?>
      <tr>
        <td data-label="Súbor"><code><?= h($mf) ?></code></td>
        <td data-label="Stav">
          <?php if ($applied): ?>
          <span class="pill submitted">Spustená <?= h($applied['applied_at']) ?><?= $applied['applied_by'] ? ' · ' . h($applied['applied_by']) : '' ?></span>
          <?php else: ?>
          <span class="pill pending">Čaká</span>
          <?php endif; ?>
        </td>
        <td style="display:flex; gap:6px;">
          <?php if (!$applied): ?>
          <form method="post" style="margin:0;" onsubmit="return confirm('Spustiť <?= h($mf) ?><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"> priamo na produkčnej DB?');">
            <input type="hidden" name="run_migration_file" value="<?= h($mf) ?>">
            <button type="submit" class="toggle-btn">▶ Spustiť</button>
          </form>
          <form method="post" style="margin:0;" onsubmit="return confirm('Označiť <?= h($mf) ?><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"> ako už spustenú BEZ jej reálneho behu? Použi len ak si ju už spustil ručne v phpMyAdmin.');">
            <input type="hidden" name="mark_applied_file" value="<?= h($mf) ?>">
            <button type="submit" class="toggle-btn">✓ Označiť ako už spustené</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</main>
<script src="<?= asset('shell.js') ?>"></script>
<script>
function editAdvisor(id){
  document.getElementById('view-'+id).style.display = 'none';
  document.getElementById('edit-'+id).style.display = '';
}
function cancelEdit(id){
  document.getElementById('edit-'+id).style.display = 'none';
  document.getElementById('view-'+id).style.display = '';
}
function editPin(id){
  document.getElementById('pin-'+id).style.display = '';
}
function cancelPin(id){
  document.getElementById('pin-'+id).style.display = 'none';
}
function editTools(id){
  document.getElementById('tools-'+id).style.display = '';
}
function cancelTools(id){
  document.getElementById('tools-'+id).style.display = 'none';
}
function setAllTools(id, on){
  document.querySelectorAll('#tools-'+id+' input[type=checkbox]').forEach(function(cb){ cb.checked = on; });
}
</script>
</body></html>
