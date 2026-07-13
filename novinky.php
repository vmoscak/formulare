<?php
/**
 * Novinky — oznamy pre poradcov, editovať smie výhradne owner (rovnaký
 * vzor ako znalostna-baza.php). Zobrazujú sa ako banner na index.php
 * (výber poradcu), zoradené: dôležité navrchu, potom podľa dátumu.
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
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $important = isset($_POST['important']) ? 1 : 0;
        if ($title !== '' && $body !== '') {
            db()->prepare('INSERT INTO formulare_news (title, body, important) VALUES (?, ?, ?)')
                ->execute([$title, $body, $important]);
        }
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $important = isset($_POST['important']) ? 1 : 0;
        if ($id && $title !== '' && $body !== '') {
            db()->prepare('UPDATE formulare_news SET title = ?, body = ?, important = ?, updated_at = ? WHERE id = ?')
                ->execute([$title, $body, $important, date('Y-m-d H:i:s'), $id]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_news WHERE id = ?')->execute([$id]);
    }
    header('Location: /novinky.php');
    exit;
}

$entries = [];
try {
    $entries = db()->query('SELECT * FROM formulare_news ORDER BY important DESC, created_at DESC')->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Novinky</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=19">
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Novinky</h1>
    <p>Oznamy pre poradcov · zobrazujú sa na hlavnej stránke pri výbere poradcu</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Pridať novinku</h3>
    <form method="post" class="kb-form">
      <input type="hidden" name="add" value="1">
      <input type="text" name="title" placeholder="Nadpis (napr. „Nový nástroj: Ťahák čo pýtať od klienta")" required>
      <textarea name="body" rows="4" placeholder="Text novinky..." required></textarea>
      <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--ink-2); cursor:pointer;">
        <input type="checkbox" name="important" value="1"> Dôležité (zostane navrchu, kým ho nezrušíš)
      </label>
      <button type="submit" class="pillbtn solid" style="align-self:flex-start;">Pridať</button>
    </form>
  </div>

  <div class="card">
    <h3>Novinky · <?= count($entries) ?></h3>
    <div class="kb-list">
      <?php foreach ($entries as $e): ?>
      <div class="kb-item" id="news-<?= (int)$e['id'] ?>">
        <div class="kb-view">
          <div class="kb-head">
            <h4><?= h($e['title']) ?><?php if ($e['important']): ?> <span class="pill pending" style="margin-left:6px;">Dôležité</span><?php endif; ?></h4>
            <div class="kb-actions">
              <button type="button" class="toggle-btn" onclick="newsEdit(<?= (int)$e['id'] ?>)">Upraviť</button>
              <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať túto novinku?');">
                <input type="hidden" name="delete_id" value="<?= (int)$e['id'] ?>">
                <button type="submit" class="toggle-btn">Zmazať</button>
              </form>
            </div>
          </div>
          <p class="kb-body"><?= nl2br(h($e['body'])) ?></p>
          <div class="kb-meta"><span class="date"><?= h($e['created_at']) ?></span></div>
        </div>
        <form method="post" class="kb-edit" style="display:none;">
          <input type="hidden" name="edit_id" value="<?= (int)$e['id'] ?>">
          <input type="text" name="title" value="<?= h($e['title']) ?>" required>
          <textarea name="body" rows="4" required><?= h($e['body']) ?></textarea>
          <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--ink-2); cursor:pointer;">
            <input type="checkbox" name="important" value="1" <?= $e['important'] ? 'checked' : '' ?>> Dôležité (zostane navrchu, kým ho nezrušíš)
          </label>
          <div style="display:flex; gap:8px;">
            <button type="submit" class="pillbtn solid">Uložiť</button>
            <button type="button" class="pillbtn" onclick="newsCancel(<?= (int)$e['id'] ?>)">Zrušiť</button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
      <?php if (!$entries): ?><p class="empty">Zatiaľ žiadne novinky.</p><?php endif; ?>
    </div>
  </div>

</main>
<script>
function newsEdit(id) {
  var item = document.getElementById('news-' + id);
  item.querySelector('.kb-view').style.display = 'none';
  item.querySelector('.kb-edit').style.display = 'flex';
}
function newsCancel(id) {
  var item = document.getElementById('news-' + id);
  item.querySelector('.kb-view').style.display = 'block';
  item.querySelector('.kb-edit').style.display = 'none';
}
</script>
<script src="/assets/shell.js?v=17"></script>
</body></html>
