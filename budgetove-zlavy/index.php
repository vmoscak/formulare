<?php
/**
 * Budgetové zľavy — referenčná stránka pravidiel UNIQA (autopoistenie,
 * majetok). Vidí každý prihlásený poradca, upravovať (pridať/upraviť/zmazať/
 * presunúť) smie len owner — pravidlá sa občas menia (napr. akčná zľava pri
 * majetku neplatí trvalo), preto sú v DB namiesto natvrdo v HTML.
 */
require_once __DIR__ . '/../db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }
$isOwner = !empty($me['is_owner']);

const BZ_CATEGORIES = ['auto' => 'Autopoistenie', 'majetok' => 'Majetok'];
const BZ_BADGE_LABELS = ['none' => 'Bez odznaku', 'asist' => 'Asistentka (zelený)', 'daniel' => 'Daniel Jurčík (ružový)', 'both' => 'Oba/kombinácia (jantárový)'];

if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_rule'])) {
        $category = (string)($_POST['category'] ?? '');
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $badgeText = trim((string)($_POST['badge_text'] ?? ''));
        $badgeColor = (string)($_POST['badge_color'] ?? 'none');
        if (isset(BZ_CATEGORIES[$category]) && $title !== '' && $body !== '') {
            $ordStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM formulare_budget_rules WHERE category = ?');
            $ordStmt->execute([$category]);
            $nextOrd = (int)$ordStmt->fetchColumn();
            db()->prepare('INSERT INTO formulare_budget_rules (category, title, body, badge_text, badge_color, sort_order, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$category, $title, $body, $badgeText !== '' ? $badgeText : null, $badgeColor, $nextOrd, date('Y-m-d H:i:s')]);
        }
    } elseif (isset($_POST['edit_rule_id'])) {
        $id = (int)$_POST['edit_rule_id'];
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $badgeText = trim((string)($_POST['badge_text'] ?? ''));
        $badgeColor = (string)($_POST['badge_color'] ?? 'none');
        if ($id && $title !== '' && $body !== '') {
            db()->prepare('UPDATE formulare_budget_rules SET title = ?, body = ?, badge_text = ?, badge_color = ?, updated_at = ? WHERE id = ?')
                ->execute([$title, $body, $badgeText !== '' ? $badgeText : null, $badgeColor, date('Y-m-d H:i:s'), $id]);
        }
    } elseif (isset($_POST['delete_rule_id'])) {
        db()->prepare('DELETE FROM formulare_budget_rules WHERE id = ?')->execute([(int)$_POST['delete_rule_id']]);
    } elseif (isset($_POST['move_rule_id'])) {
        $id = (int)$_POST['move_rule_id'];
        $dir = (string)($_POST['direction'] ?? '');
        $stmt2 = db()->prepare('SELECT id, category, sort_order FROM formulare_budget_rules WHERE id = ?');
        $stmt2->execute([$id]);
        $rule = $stmt2->fetch();
        if ($rule) {
            $sibStmt = db()->prepare('SELECT id, sort_order FROM formulare_budget_rules WHERE category = ? ORDER BY sort_order, id');
            $sibStmt->execute([$rule['category']]);
            $siblings = $sibStmt->fetchAll();
            $pos = null;
            foreach ($siblings as $i => $sib) { if ((int)$sib['id'] === $id) { $pos = $i; break; } }
            if ($pos !== null) {
                $swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
                if (isset($siblings[$swapPos])) {
                    $other = $siblings[$swapPos];
                    db()->prepare('UPDATE formulare_budget_rules SET sort_order = ? WHERE id = ?')->execute([$other['sort_order'], $id]);
                    db()->prepare('UPDATE formulare_budget_rules SET sort_order = ? WHERE id = ?')->execute([$rule['sort_order'], $other['id']]);
                }
            }
        }
    } elseif (isset($_POST['add_row'])) {
        $label = trim((string)($_POST['label'] ?? ''));
        $effect = trim((string)($_POST['effect_text'] ?? ''));
        $polarity = (string)($_POST['polarity'] ?? 'neg');
        if ($label !== '' && $effect !== '') {
            $ordStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM formulare_budget_table_rows');
            $ordStmt->execute();
            $nextOrd = (int)$ordStmt->fetchColumn();
            db()->prepare('INSERT INTO formulare_budget_table_rows (label, effect_text, polarity, sort_order, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$label, $effect, $polarity, $nextOrd, date('Y-m-d H:i:s')]);
        }
    } elseif (isset($_POST['edit_row_id'])) {
        $id = (int)$_POST['edit_row_id'];
        $label = trim((string)($_POST['label'] ?? ''));
        $effect = trim((string)($_POST['effect_text'] ?? ''));
        $polarity = (string)($_POST['polarity'] ?? 'neg');
        if ($id && $label !== '' && $effect !== '') {
            db()->prepare('UPDATE formulare_budget_table_rows SET label = ?, effect_text = ?, polarity = ?, updated_at = ? WHERE id = ?')
                ->execute([$label, $effect, $polarity, date('Y-m-d H:i:s'), $id]);
        }
    } elseif (isset($_POST['delete_row_id'])) {
        db()->prepare('DELETE FROM formulare_budget_table_rows WHERE id = ?')->execute([(int)$_POST['delete_row_id']]);
    } elseif (isset($_POST['move_row_id'])) {
        $id = (int)$_POST['move_row_id'];
        $dir = (string)($_POST['direction'] ?? '');
        $rows = db()->query('SELECT id, sort_order FROM formulare_budget_table_rows ORDER BY sort_order, id')->fetchAll();
        $pos = null;
        foreach ($rows as $i => $row) { if ((int)$row['id'] === $id) { $pos = $i; break; } }
        if ($pos !== null) {
            $swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
            if (isset($rows[$swapPos])) {
                $other = $rows[$swapPos];
                db()->prepare('UPDATE formulare_budget_table_rows SET sort_order = ? WHERE id = ?')->execute([$other['sort_order'], $id]);
                db()->prepare('UPDATE formulare_budget_table_rows SET sort_order = ? WHERE id = ?')->execute([$rows[$pos]['sort_order'], $other['id']]);
            }
        }
    } elseif (isset($_POST['edit_tip'])) {
        $tip = trim((string)($_POST['tip_text'] ?? ''));
        $upd = db()->prepare('UPDATE formulare_budget_meta SET tip_text = ?, updated_at = ? WHERE id = 1');
        $upd->execute([$tip, date('Y-m-d H:i:s')]);
        if ($upd->rowCount() === 0) {
            db()->prepare('INSERT INTO formulare_budget_meta (id, tip_text, updated_at) VALUES (1, ?, ?)')
                ->execute([$tip, date('Y-m-d H:i:s')]);
        }
    }
    header('Location: /budgetove-zlavy/');
    exit;
}

$allRules = db()->query('SELECT * FROM formulare_budget_rules ORDER BY category, sort_order, id')->fetchAll();
$rulesByCat = ['auto' => [], 'majetok' => []];
foreach ($allRules as $r) { if (isset($rulesByCat[$r['category']])) $rulesByCat[$r['category']][] = $r; }

$tableRows = db()->query('SELECT * FROM formulare_budget_table_rows ORDER BY sort_order, id')->fetchAll();

$tipStmt = db()->query('SELECT tip_text, updated_at FROM formulare_budget_meta WHERE id = 1');
$tipRow = $tipStmt ? $tipStmt->fetch() : null;
$tipText = $tipRow['tip_text'] ?? '';

$updatedTimes = array_column($allRules, 'updated_at');
$updatedTimes = array_merge($updatedTimes, array_column($tableRows, 'updated_at'));
if (!empty($tipRow['updated_at'])) $updatedTimes[] = $tipRow['updated_at'];
$lastUpdated = $updatedTimes ? max($updatedTimes) : null;

function bzBadge(array $r): string {
    if (empty($r['badge_text']) || $r['badge_color'] === 'none') return '';
    return '<br><span class="bz-approver ' . h($r['badge_color']) . '">' . h($r['badge_text']) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Budgetové zľavy</title>

<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('ui.css') ?>">
<style>
  .bz-updated{font-size:12px; color:var(--muted); margin:-6px 0 22px;}
  .bz-section-title{display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0 0 14px;}
  .bz-section-title-left{display:flex; align-items:center; gap:10px;}
  .bz-section-title h2{margin:0; font-size:16.5px; font-weight:700; color:var(--ink);}
  .bz-section-title .ico{width:30px; height:30px; border-radius:9px; background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center; flex-shrink:0;}
  .bz-approver{display:inline-block; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; margin-top:8px;}
  .bz-approver.asist{background:var(--good-soft); color:var(--good);}
  .bz-approver.daniel{background:var(--rose-soft); color:var(--rose);}
  .bz-approver.both{background:var(--amber-soft); color:var(--amber);}
  .bz-callout{border:1px solid var(--accent-line); background:var(--accent-soft); border-radius:var(--radius-xl);
    padding:14px 16px; margin:0 0 12px; font-size:13px; color:var(--ink-2); line-height:1.55;}
  .bz-callout b{color:var(--ink);}
  .bz-table-wrap{overflow-x:auto; margin:0 0 16px;}
  .bz-table{width:100%; border-collapse:collapse; font-size:13px;}
  .bz-table th{text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em;
    color:var(--muted); padding:8px 12px; border-bottom:1px solid var(--border);}
  .bz-table td{padding:10px 12px; border-bottom:1px solid var(--border); color:var(--ink);}
  .bz-table tr:last-child td{border-bottom:none;}
  .bz-table .neg{color:var(--rose); font-weight:600;}
  .bz-table .pos{color:var(--good); font-weight:600;}
  .bz-table .bz-row-actions{display:flex; gap:4px; justify-content:flex-end;}
  .bz-table .bz-owner-cell{display:none;}
  .app.bz-editing .bz-table .bz-owner-cell{display:table-cell;}
  .bz-cols{display:grid; grid-template-columns:1fr 1fr; gap:36px; align-items:start; margin:0 0 32px;}
  .bz-col{min-width:0;}
  @media(max-width:760px){.bz-cols{grid-template-columns:1fr; gap:0;} .bz-col + .bz-col{margin-top:28px;}}
  .bz-content{padding:6px 30px 30px;}
  .bz-info{border-top:1px solid var(--border); padding-top:26px;}
  .bz-info .bz-section-title .ico{background:var(--desk); color:var(--muted);}
  .bz-info-intro{font-size:13px; color:var(--muted); margin:-4px 0 16px; line-height:1.5;}
  .bz-rc-actions{display:none; gap:6px; margin-top:10px;}
  .app.bz-editing .bz-rc-actions{display:flex;}
  .bz-edit-form{display:none; flex-direction:column; gap:8px; border:1px solid var(--border); border-radius:var(--radius-xl);
    padding:14px 16px; margin-bottom:14px; background:var(--desk);}
  .bz-edit-form input, .bz-edit-form textarea, .bz-edit-form select{width:100%; box-sizing:border-box;}
  .bz-add-form{display:none; flex-direction:column; gap:8px; border:1.5px dashed var(--border); border-radius:var(--radius-xl);
    padding:14px 16px; margin-top:4px;}
  .app.bz-editing .bz-add-form{display:flex;}
  .bz-add-form input, .bz-add-form textarea, .bz-add-form select{width:100%; box-sizing:border-box;}
  .bz-row-edit{display:none;}
  .bz-row-edit td{padding:8px 12px;}
  .bz-row-edit input, .bz-row-edit select{width:100%; box-sizing:border-box;}
  .bz-tip-edit{display:none; flex-direction:column; gap:8px;}
  .bz-tip-edit textarea{width:100%; box-sizing:border-box;}
  .bz-owner-inline.toggle-btn{display:none;}
  .app.bz-editing .bz-owner-inline.toggle-btn{display:inline-block;}
  .toggle-btn{display:inline-block; padding:6px 12px; border:1px solid var(--line-strong); border-radius:var(--radius-md);
    background:var(--paper); font-size:12px; font-weight:600; cursor:pointer; color:var(--ink-2); text-decoration:none;
    transition:border-color .15s, color .15s, background .15s, transform .1s;}
  .toggle-btn:hover{border-color:var(--accent); color:var(--accent); background:var(--accent-soft);}
  .toggle-btn:active{transform:scale(.95);}
  .head-actions{display:flex; align-items:center; gap:10px;}
  .pillbtn{display:inline-flex; align-items:center; gap:7px; padding:9px 15px; border-radius:var(--radius-lg);
    border:1px solid var(--line-strong); background:var(--paper); color:var(--ink-2); font-size:13px; font-weight:600;
    cursor:pointer; text-decoration:none; transition:border-color .15s, color .15s, background .15s, transform .1s;}
  .pillbtn:hover{border-color:var(--accent); color:var(--accent); background:var(--accent-soft);}
  .pillbtn:active{transform:scale(.96);}
  .pillbtn.solid{background:var(--accent); color:#fff; border-color:var(--accent);}
  .pillbtn.solid:hover{background:var(--accent-ink); color:#fff;}
</style>
</head>
<body>

<div class="app" id="bzApp" style="max-width:1080px;">
  <div class="head">
    <div class="head-left">
      <a href="../uniqa-tlaciva.php" class="back-btn" title="Späť na UNIQA">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </a>
      <div class="brand">
        <div class="logo">%</div>
        <div>
          <b>Budgetové zľavy</b><br>
          <small>Podmienky poskytovania zliav z budgetu — autopoistenie a majetok</small>
        </div>
      </div>
    </div>
    <?php if ($isOwner): ?>
    <div class="head-actions">
      <button type="button" class="pillbtn" id="bzEditToggle" onclick="bzToggleEdit()">✏️ Upraviť obsah</button>
    </div>
    <?php endif; ?>
  </div>

  <div class="bz-content">

  <p class="bz-updated">Aktualizované: <?= $lastUpdated ? h((new DateTime($lastUpdated))->format('j. n. Y')) : '—' ?> · zdroj: interná sumarizácia UNIQA<?= $isOwner ? ' · upravuješ priamo tu' : '' ?></p>

  <div class="bz-cols">
  <?php foreach (BZ_CATEGORIES as $catKey => $catLabel): ?>
  <div class="bz-col">

  <div class="bz-section-title">
    <div class="bz-section-title-left">
      <span class="ico">
      <?php if ($catKey === 'auto'): ?>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="7" r="4"/><circle cx="17" cy="17" r="4"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
      <?php else: ?>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9"/></svg>
      <?php endif; ?>
      </span>
      <h2><?= h($catLabel) ?></h2>
    </div>
  </div>
  <div class="rec-list">
    <?php $catRules = $rulesByCat[$catKey]; foreach ($catRules as $i => $r): $isFirst = $i === 0; $isLast = $i === count($catRules) - 1; ?>
    <div class="rec-card" id="rule-view-<?= (int)$r['id'] ?>">
      <div class="rc-title"><span class="rc-num"><?= $i + 1 ?></span><?= h($r['title']) ?></div>
      <div class="rc-why"><?= nl2br(h($r['body'])) ?><?= bzBadge($r) ?></div>
      <?php if ($isOwner): ?>
      <div class="bz-rc-actions">
        <?php if (!$isFirst): ?>
        <form method="post" style="margin:0;"><input type="hidden" name="move_rule_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="toggle-btn" title="Posunúť hore">↑</button></form>
        <?php endif; ?>
        <?php if (!$isLast): ?>
        <form method="post" style="margin:0;"><input type="hidden" name="move_rule_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="toggle-btn" title="Posunúť dole">↓</button></form>
        <?php endif; ?>
        <button type="button" class="toggle-btn" onclick="bzEditRule(<?= (int)$r['id'] ?>)">Upraviť</button>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať toto pravidlo?');"><input type="hidden" name="delete_rule_id" value="<?= (int)$r['id'] ?>"><button type="submit" class="toggle-btn">Zmazať</button></form>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($isOwner): ?>
    <form method="post" class="bz-edit-form" id="rule-edit-<?= (int)$r['id'] ?>">
      <input type="hidden" name="edit_rule_id" value="<?= (int)$r['id'] ?>">
      <input type="text" name="title" value="<?= h($r['title']) ?>" placeholder="Názov pravidla" required>
      <textarea name="body" rows="3" placeholder="Text pravidla" required><?= h($r['body']) ?></textarea>
      <input type="text" name="badge_text" value="<?= h((string)$r['badge_text']) ?>" placeholder="Text odznaku (nepovinné, napr. „Schvaľuje: asistentka“)">
      <select name="badge_color">
        <?php foreach (BZ_BADGE_LABELS as $val => $lbl): ?>
        <option value="<?= h($val) ?>" <?= $r['badge_color'] === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <button type="button" class="pillbtn" onclick="bzCancelRule(<?= (int)$r['id'] ?>)">Zrušiť</button>
      </div>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$catRules): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/></svg>
      <span class="es-title">Zatiaľ žiadne pravidlá</span>
      <?php if ($isOwner): ?><span class="es-sub">Zapni „Upraviť obsah“ hore a pridaj prvé.</span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($isOwner): ?>
  <form method="post" class="bz-add-form">
    <input type="hidden" name="add_rule" value="1">
    <input type="hidden" name="category" value="<?= h($catKey) ?>">
    <input type="text" name="title" placeholder="Názov pravidla" required>
    <textarea name="body" rows="2" placeholder="Text pravidla" required></textarea>
    <input type="text" name="badge_text" placeholder="Text odznaku (nepovinné)">
    <select name="badge_color">
      <?php foreach (BZ_BADGE_LABELS as $val => $lbl): ?>
      <option value="<?= h($val) ?>"><?= h($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="pillbtn solid" style="align-self:start;">Pridať pravidlo — <?= h($catLabel) ?></button>
  </form>
  <?php endif; ?>

  </div>
  <?php endforeach; ?>
  </div>

  <div class="bz-info">
    <div class="bz-section-title">
      <div class="bz-section-title-left">
        <span class="ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/></svg></span>
        <h2>Spoluúčasť — vplyv na poistné</h2>
      </div>
    </div>
    <p class="bz-info-intro">Toto nie je pravidlo pre schvaľovanie budgetovej zľavy — len prehľad toho, ako výška spoluúčasti pri autopoistení mení výšku poistného.</p>

    <div class="bz-table-wrap">
      <table class="bz-table">
        <thead><tr><th>Spoluúčasť</th><th>Vplyv na výšku poistného</th><?php if ($isOwner): ?><th class="bz-owner-cell"></th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($tableRows as $i => $row): $isFirst = $i === 0; $isLast = $i === count($tableRows) - 1; ?>
          <tr id="row-view-<?= (int)$row['id'] ?>">
            <td><?= h($row['label']) ?></td>
            <td class="<?= h($row['polarity']) ?>"><?= h($row['effect_text']) ?></td>
            <?php if ($isOwner): ?>
            <td class="bz-owner-cell">
              <div class="bz-row-actions">
                <?php if (!$isFirst): ?><form method="post" style="margin:0;"><input type="hidden" name="move_row_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="toggle-btn" title="Hore">↑</button></form><?php endif; ?>
                <?php if (!$isLast): ?><form method="post" style="margin:0;"><input type="hidden" name="move_row_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="toggle-btn" title="Dole">↓</button></form><?php endif; ?>
                <button type="button" class="toggle-btn" onclick="bzEditRow(<?= (int)$row['id'] ?>)">Upraviť</button>
                <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento riadok?');"><input type="hidden" name="delete_row_id" value="<?= (int)$row['id'] ?>"><button type="submit" class="toggle-btn">Zmazať</button></form>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php if ($isOwner): ?>
          <tr class="bz-row-edit" id="row-edit-<?= (int)$row['id'] ?>">
            <td colspan="3">
              <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="edit_row_id" value="<?= (int)$row['id'] ?>">
                <input type="text" name="label" value="<?= h($row['label']) ?>" placeholder="Spoluúčasť" style="flex:1 1 160px;" required>
                <input type="text" name="effect_text" value="<?= h($row['effect_text']) ?>" placeholder="Vplyv na poistné" style="flex:1 1 200px;" required>
                <select name="polarity" style="flex:0 0 140px;">
                  <option value="neg" <?= $row['polarity'] === 'neg' ? 'selected' : '' ?>>Prirážka (červená)</option>
                  <option value="pos" <?= $row['polarity'] === 'pos' ? 'selected' : '' ?>>Zľava (zelená)</option>
                </select>
                <button type="submit" class="pillbtn solid">Uložiť</button>
                <button type="button" class="pillbtn" onclick="bzCancelRow(<?= (int)$row['id'] ?>)">Zrušiť</button>
              </form>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($isOwner): ?>
    <form method="post" class="bz-add-form" style="flex-direction:row; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
      <input type="hidden" name="add_row" value="1">
      <input type="text" name="label" placeholder="Spoluúčasť" style="flex:1 1 160px;" required>
      <input type="text" name="effect_text" placeholder="Vplyv na poistné" style="flex:1 1 200px;" required>
      <select name="polarity" style="flex:0 0 140px;">
        <option value="neg">Prirážka (červená)</option>
        <option value="pos">Zľava (zelená)</option>
      </select>
      <button type="submit" class="pillbtn solid">Pridať riadok</button>
    </form>
    <?php endif; ?>

    <div id="tip-view">
      <div class="bz-callout">
        <b>Tip:</b> <?= nl2br(h($tipText)) ?>
      </div>
      <?php if ($isOwner): ?><button type="button" class="toggle-btn bz-owner-inline" onclick="bzEditTip()">Upraviť tip</button><?php endif; ?>
    </div>
    <?php if ($isOwner): ?>
    <form method="post" class="bz-tip-edit" id="tip-edit">
      <input type="hidden" name="edit_tip" value="1">
      <textarea name="tip_text" rows="3"><?= h($tipText) ?></textarea>
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <button type="button" class="pillbtn" onclick="bzCancelTip()">Zrušiť</button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  </div>
</div>
<script>
function bzToggleEdit() {
  var app = document.getElementById('bzApp');
  app.classList.toggle('bz-editing');
  var editing = app.classList.contains('bz-editing');
  var btn = document.getElementById('bzEditToggle');
  if (btn) btn.textContent = editing ? '✕ Zavrieť úpravy' : '✏️ Upraviť obsah';
  if (!editing) {
    document.querySelectorAll('.bz-edit-form').forEach(function (f) { f.style.display = 'none'; });
    document.querySelectorAll('.bz-row-edit').forEach(function (f) { f.style.display = 'none'; });
    document.querySelectorAll('[id^="rule-view-"]').forEach(function (v) { v.style.display = 'block'; });
    document.querySelectorAll('[id^="row-view-"]').forEach(function (v) { v.style.display = 'table-row'; });
    var tipEdit = document.getElementById('tip-edit'); if (tipEdit) tipEdit.style.display = 'none';
    var tipView = document.getElementById('tip-view'); if (tipView) tipView.style.display = 'block';
  }
}
function bzEditRule(id) {
  document.getElementById('rule-view-' + id).style.display = 'none';
  var f = document.getElementById('rule-edit-' + id);
  f.style.display = 'flex';
}
function bzCancelRule(id) {
  document.getElementById('rule-view-' + id).style.display = 'block';
  document.getElementById('rule-edit-' + id).style.display = 'none';
}
function bzEditRow(id) {
  document.getElementById('row-view-' + id).style.display = 'none';
  document.getElementById('row-edit-' + id).style.display = 'table-row';
}
function bzCancelRow(id) {
  document.getElementById('row-view-' + id).style.display = 'table-row';
  document.getElementById('row-edit-' + id).style.display = 'none';
}
function bzEditTip() {
  document.getElementById('tip-view').style.display = 'none';
  document.getElementById('tip-edit').style.display = 'flex';
}
function bzCancelTip() {
  document.getElementById('tip-view').style.display = 'block';
  document.getElementById('tip-edit').style.display = 'none';
}
</script>
<script src="<?= asset('shell.js') ?>"></script>
</body></html>
