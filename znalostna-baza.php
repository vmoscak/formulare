<?php
/**
 * Znalostná báza — interné FAQ / rýchle texty. Prístup VÝHRADNE pre poradcu
 * s is_owner=1 (rovnaký vzor ako nabor.php) — kým sa nerozhodne, ako/či sa
 * sprístupní aj ostatným poradcom na čítanie.
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
        if ($title !== '' && $body !== '') {
            db()->prepare('INSERT INTO formulare_knowledge_base (title, body, advisor_id, advisor_name) VALUES (?, ?, ?, ?)')
                ->execute([$title, $body, $advisorId, $me['name']]);
        }
    } elseif (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if ($id && $title !== '' && $body !== '') {
            db()->prepare("UPDATE formulare_knowledge_base SET title = ?, body = ?, updated_at = ? WHERE id = ?")
                ->execute([$title, $body, date('Y-m-d H:i:s'), $id]);
        }
    } elseif (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_knowledge_base WHERE id = ?')->execute([$id]);
    }
    header('Location: /znalostna-baza.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$entries = [];
try {
    if ($q !== '') {
        $stmt = db()->prepare('SELECT * FROM formulare_knowledge_base WHERE title LIKE ? OR body LIKE ? ORDER BY title');
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
    } else {
        $stmt = db()->query('SELECT * FROM formulare_knowledge_base ORDER BY title');
    }
    $entries = $stmt->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Znalostná báza</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=6">
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Znalostná báza</h1>
    <p>Interné FAQ a rýchle texty · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Hľadať</h3>
    <form method="get" class="filter-form">
      <div class="f-field" style="min-width:280px;">
        <label>Text</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="napr. výpoveď, ČSOB, reklamácia...">
      </div>
      <div class="f-field" style="min-width:0;">
        <button type="submit" class="pillbtn solid">Hľadať</button>
      </div>
      <?php if ($q): ?>
      <div class="f-field" style="min-width:0;">
        <a class="pillbtn" href="/znalostna-baza.php">Zrušiť</a>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <h3>Pridať nový záznam</h3>
    <form method="post" class="kb-form">
      <input type="hidden" name="add" value="1">
      <input type="text" name="title" placeholder="Názov (napr. „ČSOB – čo pýtať pri zaseknutej likvidácii“)" required>
      <textarea name="body" rows="4" placeholder="Text, ktorý sa dá skopírovať a poslať/vložiť..." required></textarea>
      <button type="submit" class="pillbtn solid" style="align-self:flex-start;">Pridať</button>
    </form>
  </div>

  <div class="card">
    <h3>Záznamy · <?= count($entries) ?></h3>
    <div class="kb-list">
      <?php foreach ($entries as $e): ?>
      <div class="kb-item" id="kb-<?= (int)$e['id'] ?>">
        <div class="kb-view">
          <div class="kb-head">
            <h4><?= h($e['title']) ?></h4>
            <div class="kb-actions">
              <button type="button" class="toggle-btn" onclick="kbCopy(<?= (int)$e['id'] ?>)">Kopírovať</button>
              <button type="button" class="toggle-btn" onclick="kbEdit(<?= (int)$e['id'] ?>)">Upraviť</button>
              <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento záznam?');">
                <input type="hidden" name="delete_id" value="<?= (int)$e['id'] ?>">
                <button type="submit" class="toggle-btn">Zmazať</button>
              </form>
            </div>
          </div>
          <p class="kb-body" data-raw="<?= h($e['body']) ?>"><?= nl2br(h($e['body'])) ?></p>
          <div class="kb-meta">Pridal <?= h($e['advisor_name']) ?> · <span class="date"><?= h($e['created_at']) ?></span></div>
        </div>
        <form method="post" class="kb-edit" style="display:none;">
          <input type="hidden" name="edit_id" value="<?= (int)$e['id'] ?>">
          <input type="text" name="title" value="<?= h($e['title']) ?>" required>
          <textarea name="body" rows="4" required><?= h($e['body']) ?></textarea>
          <div style="display:flex; gap:8px;">
            <button type="submit" class="pillbtn solid">Uložiť</button>
            <button type="button" class="pillbtn" onclick="kbCancel(<?= (int)$e['id'] ?>)">Zrušiť</button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
      <?php if (!$entries): ?><p class="empty">Zatiaľ žiadne záznamy<?= $q ? ' pre „' . h($q) . '“' : '' ?>.</p><?php endif; ?>
    </div>
  </div>

</main>
<script>
function kbEdit(id) {
  var item = document.getElementById('kb-' + id);
  item.querySelector('.kb-view').style.display = 'none';
  item.querySelector('.kb-edit').style.display = 'flex';
}
function kbCancel(id) {
  var item = document.getElementById('kb-' + id);
  item.querySelector('.kb-view').style.display = 'block';
  item.querySelector('.kb-edit').style.display = 'none';
}
function kbCopy(id) {
  var item = document.getElementById('kb-' + id);
  var text = item.querySelector('.kb-body').dataset.raw;
  navigator.clipboard.writeText(text).catch(function () {});
}
</script>
<script src="/assets/shell.js?v=4"></script>
</body></html>
