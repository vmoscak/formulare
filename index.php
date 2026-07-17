<?php
require_once __DIR__ . '/db.php';

function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function fetchActiveAdvisor(int $id): ?array {
    try {
        $stmt = db()->prepare('SELECT id, name, org, color, pin_hash FROM formulare_advisors WHERE id = ? AND active = 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

// Klik na dlaždicu poradcu vedie sem s ?adv=ID — namiesto priameho prihlásenia
// (ako predtým) sa zobrazí obrazovka na zadanie OSOBNÉHO PIN-u daného poradcu.
// Cookie "cur_advisor" sa nastaví až po jeho overení nižšie.
$selectedId = isset($_GET['adv']) ? (int)$_GET['adv'] : (isset($_POST['adv']) ? (int)$_POST['adv'] : 0);
$selected = $selectedId ? fetchActiveAdvisor($selectedId) : null;
$pinError = '';
$lockedSeconds = 0;

if ($selected) {
    $scope = 'advisor:' . $selected['id'];
    $lockedSeconds = throttleSecondsLeft($scope);

    $needsSetup = empty($selected['pin_hash']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lockedSeconds === 0 && $needsSetup) {
        // Prvé prihlásenie — poradca si sám nastaví osobný PIN. Bezpečné, lebo
        // sem sa dá dostať až po prejdení hlavnej brány appky (viď brana.php);
        // po nastavení už PIN pozná len on a admin ho vie len resetnúť (nie prečítať).
        $pin1 = trim((string)($_POST['pin'] ?? ''));
        $pin2 = trim((string)($_POST['pin2'] ?? ''));
        if (!preg_match('/^\d{4}$/', $pin1)) {
            $pinError = 'PIN musí mať presne 4 číslice.';
        } elseif ($pin1 !== $pin2) {
            $pinError = 'PIN-y sa nezhodujú, skús to znova.';
        } else {
            db()->prepare('UPDATE formulare_advisors SET pin_hash = ? WHERE id = ?')
                ->execute([password_hash($pin1, PASSWORD_DEFAULT), $selected['id']]);
            setcookie('cur_advisor', signAdvisorId($selected['id']), [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header('Location: /uvod.php');
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $lockedSeconds === 0) {
        $entered = trim((string)($_POST['pin'] ?? ''));
        if ($entered !== '' && password_verify($entered, $selected['pin_hash'])) {
            throttleReset($scope);
            setcookie('cur_advisor', signAdvisorId($selected['id']), [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header('Location: /uvod.php');
            exit;
        }
        throttleRecordFailure($scope);
        $lockedSeconds = throttleSecondsLeft($scope);
        $pinError = $lockedSeconds > 0
            ? 'Príliš veľa pokusov. Skús to znova o ' . (int)ceil($lockedSeconds / 60) . ' min.'
            : 'Nesprávny PIN, skús to znova.';
    } elseif ($lockedSeconds > 0) {
        $pinError = 'Príliš veľa pokusov. Skús to znova o ' . (int)ceil($lockedSeconds / 60) . ' min.';
    }
}

// Ak je DB dočasne nedostupná, stránka musí ostať funkčná — jednoducho sa
// nezobrazia žiadne dlaždice poradcov.
try {
    $advisors = db()->query('SELECT id, name, org, color FROM formulare_advisors WHERE active = 1 ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $advisors = [];
}
$curAdvisorId = curAdvisorId() ?: null;
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<link rel="manifest" href="/assets/manifest.json">
<meta name="theme-color" content="#4f46e5">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Portál">
<title>Portál — výber poradcu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f5f6f8; --paper:#fff; --border:#eef0f3; --line-strong:#e2e6ec;
    --ink:#111827; --muted:#6b7280; --label:#98a2b3;
    --accent:#4f46e5; --accent-ink:#4338ca; --accent-soft:#eef2ff; --accent-line:#c7d2fe;
    --err:#e11d48;
    --radius-2xl:16px; --radius-lg:10px;
    --sans:'Inter',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    --shadow-sm:0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.05);
    --shadow-md:0 4px 6px -2px rgba(16,24,40,.05), 0 14px 26px -12px rgba(16,24,40,.16);
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; color:var(--ink);
    font-family:var(--sans); -webkit-font-smoothing:antialiased;
    background:var(--bg);
    display:flex; flex-direction:column; align-items:center;
    padding:0 24px 64px;
  }
  a{color:inherit; text-decoration:none;}

  .topbar{
    width:100%; max-width:1080px; display:flex; align-items:center; gap:12px;
    padding:22px 4px;
  }
  .mark{
    width:38px; height:38px; border-radius:11px; background:var(--accent); color:#fff;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
    box-shadow:0 6px 16px -4px rgba(79,70,229,.5);
  }
  .wordmark{font-size:15px; font-weight:700; letter-spacing:-.01em;}
  .topbar .tag{
    margin-left:auto; font-size:12px; font-weight:600; color:var(--muted);
    border:1px solid var(--border); border-radius:999px; padding:6px 13px; background:var(--paper);
  }

  .hero{
    width:100%; max-width:1080px; padding:56px 4px 30px;
    opacity:0; transform:translateY(10px); animation:rise .5s ease forwards;
  }
  .hero .kicker{font-size:12px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:var(--accent); margin-bottom:12px;}
  .hero h1{margin:0; font-size:34px; font-weight:700; letter-spacing:-.025em; line-height:1.1; color:var(--ink);}
  .hero p{margin:12px 0 0; font-size:15px; color:var(--muted); line-height:1.6; max-width:52ch;}
  @keyframes rise{ to{opacity:1; transform:translateY(0);} }

  .grid{
    width:100%; max-width:1080px;
    display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:16px;
    opacity:0; animation:rise .5s ease .12s forwards;
  }
  .card{
    background:var(--paper); border:1px solid var(--border); border-radius:var(--radius-2xl);
    padding:22px; display:flex; flex-direction:column; align-items:flex-start; gap:14px;
    box-shadow:var(--shadow-sm);
    transition:transform .18s cubic-bezier(.22,1,.36,1), box-shadow .18s ease, border-color .18s ease;
    position:relative;
  }
  a.card:hover{transform:translateY(-3px); box-shadow:var(--shadow-md); border-color:var(--accent-line);}
  .card .ini{
    width:54px; height:54px; border-radius:16px; flex-shrink:0;
    background:var(--adv,var(--accent)); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:19px; font-weight:600; letter-spacing:.02em;
    box-shadow:0 8px 18px -8px var(--adv,var(--accent));
  }
  .card h2{margin:0; font-size:16px; font-weight:700; letter-spacing:-.01em;}
  .card .org{margin:3px 0 0; font-size:12.5px; color:var(--muted);}
  .card .cur{
    display:inline-block; margin-top:2px; font-size:10.5px; font-weight:600;
    color:var(--accent); background:var(--accent-soft); border-radius:6px; padding:3px 9px;
  }
  .card .go{
    display:flex; align-items:center; justify-content:space-between; width:100%;
    margin-top:auto; padding-top:14px; border-top:1px solid var(--border);
    font-size:12.5px; font-weight:600; color:var(--muted);
  }
  .card .go svg{color:var(--adv,var(--accent)); transition:transform .18s ease;}
  a.card:hover .go{color:var(--ink);}
  a.card:hover .go svg{transform:translateX(4px);}

  .empty{
    grid-column:1/-1; background:var(--paper); border:1px dashed var(--line-strong);
    border-radius:var(--radius-2xl); padding:28px; text-align:center; color:var(--muted); font-size:13.5px;
  }
  .foot{margin-top:52px; font-size:12px; color:var(--label);}

  /* ── Obrazovka osobného PIN-u ── */
  .pin-wrap{
    width:100%; max-width:390px; margin:64px auto 0;
    opacity:0; transform:translateY(10px); animation:rise .4s ease forwards;
  }
  .pin-card{
    background:var(--paper); border:1px solid var(--border); border-radius:var(--radius-2xl);
    padding:36px 32px; text-align:center; box-shadow:var(--shadow-md);
  }
  .pin-ini{
    width:56px; height:56px; border-radius:16px; margin:0 auto 16px;
    background:var(--adv,var(--accent)); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:19px; font-weight:600; box-shadow:0 8px 18px -8px var(--adv,var(--accent));
  }
  .pin-card h1{font-size:18px; font-weight:700; letter-spacing:-.01em; margin:0 0 4px;}
  .pin-card p.sub{font-size:13px; color:var(--muted); margin:0 0 22px;}
  .pin-label{font-size:10.5px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:var(--label); text-align:left; margin:0 0 6px;}
  .pin-input{
    width:100%; padding:16px 14px; border:1px solid var(--line-strong); border-radius:12px;
    font-size:26px; font-weight:700; text-align:center; letter-spacing:.6em; text-indent:.6em;
    margin-bottom:16px; font-family:inherit; background:#f8fafc; color:var(--ink);
    transition:border-color .15s, box-shadow .15s, background .15s;
  }
  .pin-input:focus{outline:none; border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(79,70,229,.14);}
  .pin-card button{
    width:100%; padding:13px; border:none; border-radius:10px; background:var(--accent);
    color:#fff; font-weight:600; font-size:14px; cursor:pointer; font-family:inherit;
    box-shadow:0 8px 18px -8px rgba(79,70,229,.6); transition:background .15s;
  }
  .pin-card button:hover{background:var(--accent-ink);}
  .pin-error{color:var(--err); font-size:13px; font-weight:600; margin-bottom:14px;}
  .pin-back{display:inline-block; margin-top:18px; font-size:12.5px; color:var(--muted); font-weight:600;}
  .pin-back:hover{color:var(--accent);}

  @media(max-width:560px){ .hero{padding:40px 4px 24px;} .hero h1{font-size:27px;} .topbar .tag{display:none;} }
  @media(prefers-reduced-motion:reduce){ .hero,.grid,.pin-wrap{animation:none; opacity:1; transform:none;} }
</style>
</head>
<body>

  <div class="topbar">
    <div class="mark">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
      </svg>
    </div>
    <span class="wordmark">Portál</span>
    <span class="tag">Interný nástroj</span>
  </div>

  <?php if ($selected): ?>
  <!-- ============================================================
       OBRAZOVKA OSOBNÉHO PIN-u — konkrétny poradca z ?adv=ID
  ============================================================ -->
  <div class="pin-wrap">
    <div class="pin-card">
      <div class="pin-ini" style="--adv:<?= htmlspecialchars($selected['color']) ?>;"><?= htmlspecialchars(advisorInitials($selected['name'])) ?></div>
      <h1><?= htmlspecialchars($selected['name']) ?></h1>
      <p class="sub"><?= $needsSetup ? 'Prvé prihlásenie — nastav si osobný PIN' : 'Zadaj svoj osobný PIN' ?></p>
      <?php if ($pinError): ?><div class="pin-error"><?= htmlspecialchars($pinError) ?></div><?php endif; ?>
      <?php if ($lockedSeconds === 0 && $needsSetup): ?>
      <form method="post" id="pinForm" data-mode="setup">
        <div class="pin-label">Nový PIN</div>
        <input class="pin-input" type="tel" inputmode="numeric" pattern="[0-9]*" name="pin"
               placeholder="••••" maxlength="4" autocomplete="off" autofocus required>
        <div class="pin-label">Zopakuj PIN</div>
        <input class="pin-input" type="tel" inputmode="numeric" pattern="[0-9]*" name="pin2"
               placeholder="••••" maxlength="4" autocomplete="off" required>
        <input type="hidden" name="adv" value="<?= (int)$selected['id'] ?>">
        <button type="submit">Nastaviť PIN a vstúpiť</button>
      </form>
      <?php elseif ($lockedSeconds === 0): ?>
      <form method="post" id="pinForm">
        <input class="pin-input" type="tel" inputmode="numeric" pattern="[0-9]*" name="pin"
               placeholder="••••" maxlength="4" autocomplete="off" autofocus required>
        <input type="hidden" name="adv" value="<?= (int)$selected['id'] ?>">
        <button type="submit">Vstúpiť</button>
      </form>
      <?php endif; ?>
      <a class="pin-back" href="/">← Späť na výber poradcu</a>
    </div>
  </div>
  <script>
    (function(){
      var f = document.getElementById('pinForm');
      if (!f) return;
      var inputs = f.querySelectorAll('.pin-input');
      var isSetup = f.dataset.mode === 'setup';
      inputs.forEach(function(i, idx){
        i.addEventListener('input', function(){
          i.value = i.value.replace(/\D/g, '').slice(0, 4);
          if (i.value.length === 4) {
            if (isSetup && idx === 0) { inputs[1].focus(); }
            else if (!isSetup || idx === 1) { f.requestSubmit(); }
          }
        });
      });
    })();
  </script>

  <?php else: ?>
  <!-- ============================================================
       VÝBER PORADCU — dlaždice
  ============================================================ -->
  <div class="hero">
    <div class="kicker">Pracovný pult poradcu</div>
    <h1>Kto dnes pracuje?</h1>
    <p>Vyber svoje meno a zadaj svoj osobný PIN — dokumenty a klientske odkazy sa budú ukladať pod tvojím profilom. Voľba sa zapamätá na tomto zariadení.</p>
  </div>

  <div class="grid">
    <?php foreach ($advisors as $adv): ?>
    <a class="card" href="?adv=<?= (int)$adv['id'] ?>" style="--adv:<?= htmlspecialchars($adv['color']) ?>;">
      <div class="ini"><?= htmlspecialchars(advisorInitials($adv['name'])) ?></div>
      <div>
        <h2><?= htmlspecialchars($adv['name']) ?></h2>
        <p class="org"><?= htmlspecialchars($adv['org']) ?></p>
        <?php if ($adv['id'] == $curAdvisorId): ?><span class="cur">Aktuálne prihlásený/-á</span><?php endif; ?>
      </div>
      <span class="go">Vstúpiť ako <?= htmlspecialchars(explode(' ', $adv['name'])[0]) ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
    <?php endforeach; ?>
    <?php if (!$advisors): ?>
    <div class="empty">Momentálne sa nepodarilo načítať zoznam poradcov. Skús obnoviť stránku.</div>
    <?php endif; ?>
  </div>

  <div class="foot">Portál · prístup len pre poradcov</div>
  <?php endif; ?>

</body>
</html>
