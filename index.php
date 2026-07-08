<?php
require_once __DIR__ . '/db.php';

// Kliknutie na dlaždicu poradcu — nastaví "kto som" na 365 dní a presmeruje
// na zoznam nástrojov. Žiadne obmedzenie medzi poradcami (vzájomný prístup
// je zámerne povolený).
if (isset($_GET['adv'])) {
    try {
        $advId = (int)$_GET['adv'];
        $stmt = db()->prepare('SELECT id FROM formulare_advisors WHERE id = ? AND active = 1');
        $stmt->execute([$advId]);
        if ($stmt->fetch()) {
            setcookie('cur_advisor', signAdvisorId($advId), [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    } catch (Throwable $e) { /* DB nedostupná — pokračuj bez nastavenia poradcu */ }
    header('Location: /nastroje.php');
    exit;
}

// Ak je DB dočasne nedostupná, stránka musí ostať funkčná — jednoducho sa
// nezobrazia žiadne dlaždice poradcov.
try {
    $advisors = db()->query('SELECT id, name, org, color FROM formulare_advisors WHERE active = 1 ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $advisors = [];
}
$curAdvisorId = curAdvisorId() ?: null;

function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function advisorRgba(string $hex, float $alpha): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r,$g,$b,$alpha)";
}
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Formuláre — výber poradcu</title>
<style>
  /* Dizajnový jazyk „Atrament & Meď" — rovnaké tokeny ako assets/ui.css */
  :root{
    --chrome:#181b21;
    --chrome-line:#2e323c;
    --chrome-ink:#eceef1;
    --chrome-muted:#8b919d;
    --paper:#ffffff;
    --ink:#1c1f26;
    --muted:#8a8f98;
    --label:#5b616b;
    --border:#dcdfe4;
    --accent:#b45309;
    --accent-soft:#f7efe4;
    --mono:ui-monospace,'SF Mono','Cascadia Mono','Roboto Mono',Consolas,'Courier New',monospace;
    --sans:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; color:var(--chrome-ink);
    font-family:var(--sans); -webkit-font-smoothing:antialiased;
    padding:0 24px 64px;
    background:
      radial-gradient(rgba(255,255,255,.055) 1px, transparent 1.5px) 0 0/22px 22px,
      linear-gradient(180deg,#20242c 0%, var(--chrome) 420px);
    background-color:var(--chrome);
  }
  a{color:inherit; text-decoration:none;}
  .wrap{max-width:920px; margin:0 auto;}

  /* ── Horná lišta s wordmarkom ── */
  .topbar{
    display:flex; align-items:center; gap:12px;
    padding:22px 0 0;
  }
  .mark{
    width:34px; height:34px; border-radius:5px; background:var(--accent); color:#fff;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .wordmark{
    font-family:var(--mono); font-size:13px; font-weight:700;
    letter-spacing:.22em; text-transform:uppercase;
  }
  .topbar .tag{
    margin-left:auto; font-family:var(--mono); font-size:10px; letter-spacing:.14em;
    text-transform:uppercase; color:var(--chrome-muted);
    border:1px solid var(--chrome-line); border-radius:999px; padding:5px 12px;
  }

  /* ── Uvítanie ── */
  .hero{
    padding:72px 0 36px;
    opacity:0; transform:translateY(10px);
    animation:rise .5s ease forwards;
  }
  .hero .kicker{
    font-family:var(--mono); font-size:11px; font-weight:600;
    letter-spacing:.22em; text-transform:uppercase; color:var(--accent);
    margin-bottom:14px;
  }
  .hero h1{
    margin:0; font-size:40px; font-weight:800; letter-spacing:-.02em; line-height:1.08;
    color:#fff;
  }
  .hero p{margin:14px 0 0; font-size:15px; color:var(--chrome-muted); line-height:1.6; max-width:52ch;}

  @keyframes rise{ to{opacity:1; transform:translateY(0);} }

  /* ── Zoznam poradcov ── */
  .list-head{
    display:flex; align-items:center; gap:12px; margin:0 0 14px;
    opacity:0; animation:rise .5s ease .08s forwards;
  }
  .list-head .num{font-family:var(--mono); font-size:12px; font-weight:700; color:var(--accent);}
  .list-head .t{
    font-family:var(--mono); font-size:11px; font-weight:600;
    letter-spacing:.16em; text-transform:uppercase; color:var(--chrome-muted);
  }
  .list-head .rule{flex:1; height:1px; background:var(--chrome-line);}

  .grid{
    display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:14px;
    opacity:0; animation:rise .5s ease .14s forwards;
  }
  .card{
    background:var(--paper); color:var(--ink);
    border-radius:8px; border-left:4px solid var(--adv,var(--accent));
    padding:20px 20px 18px;
    display:flex; flex-direction:column; gap:12px;
    box-shadow:0 2px 6px rgba(0,0,0,.25), 0 18px 44px -18px rgba(0,0,0,.5);
    transition:transform .18s ease, box-shadow .18s ease;
    position:relative;
  }
  a.card:hover{
    transform:translateY(-3px);
    box-shadow:0 2px 6px rgba(0,0,0,.25), 0 26px 54px -16px rgba(0,0,0,.6), 0 0 0 3px var(--adv-ring, rgba(180,83,9,.25));
  }
  a.card:active{transform:translateY(-1px);}
  .card .ini{
    width:44px; height:44px; border-radius:6px; flex-shrink:0;
    background:var(--adv,var(--accent)); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-family:var(--mono); font-size:15px; font-weight:700; letter-spacing:.04em;
  }
  .card h2{margin:0; font-size:16.5px; font-weight:800; letter-spacing:-.01em;}
  .card .org{margin:2px 0 0; font-size:12.5px; color:var(--muted);}
  .card .cur{
    display:inline-block; margin-top:6px; font-family:var(--mono); font-size:9.5px;
    font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--accent);
    background:var(--accent-soft); border-radius:3px; padding:3px 8px;
  }
  .card .go{
    display:flex; align-items:center; justify-content:space-between;
    margin-top:auto; padding-top:12px; border-top:1px solid var(--border);
    font-family:var(--mono); font-size:11px; font-weight:600;
    letter-spacing:.08em; text-transform:uppercase; color:var(--label);
  }
  .card .go svg{transition:transform .18s ease; color:var(--adv,var(--accent));}
  a.card:hover .go svg{transform:translateX(4px);}
  a.card:hover .go{color:var(--ink);}

  .empty{
    background:transparent; border:1px dashed var(--chrome-line); border-radius:8px;
    padding:28px; text-align:center; color:var(--chrome-muted); font-size:13.5px;
  }

  .foot{
    margin-top:56px; font-family:var(--mono); font-size:10px; letter-spacing:.12em;
    text-transform:uppercase; color:#565c66; text-align:center;
  }

  @media(max-width:560px){
    .hero{padding:48px 0 28px;}
    .hero h1{font-size:30px;}
    .topbar .tag{display:none;}
  }
  @media(prefers-reduced-motion:reduce){
    .hero,.list-head,.grid{animation:none; opacity:1; transform:none;}
  }
</style>
</head>
<body>

<div class="wrap">

  <div class="topbar">
    <div class="mark">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
      </svg>
    </div>
    <span class="wordmark">Formuláre</span>
    <span class="tag">Interný nástroj</span>
  </div>

  <div class="hero">
    <div class="kicker">Pracovný pult poradcu</div>
    <h1>Kto dnes pracuje?</h1>
    <p>Vyber svoje meno — dokumenty a klientske odkazy sa budú ukladať pod tvojím profilom. Voľba sa zapamätá na tomto zariadení.</p>
  </div>

  <div class="list-head">
    <span class="num">01</span>
    <span class="t">Poradcovia · <?= count($advisors) ?></span>
    <span class="rule"></span>
  </div>

  <div class="grid">
    <?php foreach ($advisors as $adv): ?>
    <a class="card" href="?adv=<?= (int)$adv['id'] ?>" style="--adv:<?= htmlspecialchars($adv['color']) ?>; --adv-ring:<?= advisorRgba($adv['color'], 0.30) ?>;">
      <div class="ini"><?= htmlspecialchars(advisorInitials($adv['name'])) ?></div>
      <div>
        <h2><?= htmlspecialchars($adv['name']) ?></h2>
        <p class="org"><?= htmlspecialchars($adv['org']) ?></p>
        <?php if ($adv['id'] == $curAdvisorId): ?><span class="cur">Aktuálne prihlásený/-á</span><?php endif; ?>
      </div>
      <span class="go">Vstúpiť ako <?= htmlspecialchars(explode(' ', $adv['name'])[0]) ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </span>
    </a>
    <?php endforeach; ?>
    <?php if (!$advisors): ?>
    <div class="empty">Momentálne sa nepodarilo načítať zoznam poradcov. Skús obnoviť stránku.</div>
    <?php endif; ?>
  </div>

  <div class="foot">Formuláre · prístup len pre poradcov</div>

</div>
</body>
</html>
