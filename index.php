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
<title>Formuláre</title>
<style>
  :root{
    --accent:#1f6f6b;
    --accent2:#7a5c8c;
    --accent-soft:#e3efee;
    --accent-line:#cfe1de;
    --ink:#262523;
    --muted:#a5a096;
    --label:#6e6a63;
    --border:#efebe2;
    --bg:#faf8f3;
    --radius:20px;
    --serif:Georgia,'Iowan Old Style','Palatino Linotype',Palatino,serif;
  }
  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  body{
    margin:0; color:var(--ink);
    font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    -webkit-font-smoothing:antialiased;
    padding:40px 24px 60px;
    min-height:100vh;
    background:
      radial-gradient(900px 500px at 12% -8%, rgba(31,111,107,.08), transparent 60%),
      radial-gradient(700px 460px at 108% 8%, rgba(122,92,140,.08), transparent 55%),
      var(--bg);
    position:relative;
    overflow-x:hidden;
  }
  a{color:inherit; text-decoration:none;}

  /* ── Animované pozadie ── */
  .bg-decor{ position:fixed; inset:0; z-index:0; overflow:hidden; pointer-events:none; }
  .blob{ position:absolute; border-radius:50%; filter:blur(70px); will-change:transform; }
  .blob.b1{
    width:460px; height:460px; top:-140px; left:-120px;
    background:radial-gradient(circle at 35% 35%, rgba(122,92,140,.28), transparent 70%);
    animation:drift1 22s ease-in-out infinite;
  }
  .blob.b2{
    width:380px; height:380px; top:38%; right:-160px;
    background:radial-gradient(circle at 60% 40%, rgba(31,111,107,.26), transparent 70%);
    animation:drift2 26s ease-in-out infinite;
  }
  .blob.b3{
    width:340px; height:340px; bottom:-140px; left:26%;
    background:radial-gradient(circle at 50% 50%, rgba(193,135,58,.22), transparent 70%);
    animation:drift3 24s ease-in-out infinite;
  }
  .blob.b4{
    width:260px; height:260px; top:8%; left:48%;
    background:radial-gradient(circle at 50% 50%, rgba(31,111,107,.14), transparent 70%);
    animation:drift4 30s ease-in-out infinite;
  }
  @keyframes drift1{
    0%,100%{ transform:translate(0,0) scale(1); }
    50%{ transform:translate(70px,50px) scale(1.12); }
  }
  @keyframes drift2{
    0%,100%{ transform:translate(0,0) scale(1); }
    50%{ transform:translate(-60px,-40px) scale(.92); }
  }
  @keyframes drift3{
    0%,100%{ transform:translate(0,0) scale(1); }
    50%{ transform:translate(50px,-60px) scale(1.08); }
  }
  @keyframes drift4{
    0%,100%{ transform:translate(0,0) scale(1); opacity:.7; }
    50%{ transform:translate(-40px,30px) scale(1.15); opacity:1; }
  }

  .wrap{max-width:960px; margin:0 auto; position:relative; z-index:1;}

  /* ── Hlavička ── */
  .head{
    display:flex; align-items:center; gap:18px;
    padding:6px 4px 40px;
    opacity:0; transform:translateY(14px);
    animation:riseIn .6s cubic-bezier(.22,1,.36,1) forwards;
  }
  .logo{
    width:56px; height:56px; border-radius:16px; flex-shrink:0;
    background:linear-gradient(140deg,var(--accent),var(--accent2));
    color:#fff;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 10px 26px rgba(31,111,107,.28), inset 0 1px 0 rgba(255,255,255,.25);
    position:relative;
  }
  .logo::after{
    content:'';
    position:absolute; inset:-7px;
    border-radius:20px;
    border:1.5px solid rgba(31,111,107,.16);
    animation:pulseRing 2.8s ease-in-out infinite;
  }
  .head h1{
    font-family:var(--serif);
    font-size:30px; font-weight:700; letter-spacing:-.01em; margin:0;
    background:linear-gradient(100deg,var(--ink) 35%,var(--accent) 100%);
    -webkit-background-clip:text; background-clip:text; color:transparent;
    line-height:1.1;
  }
  .head p{font-size:14px; color:var(--muted); margin:6px 0 0; font-weight:500;}

  @keyframes riseIn{ to{opacity:1; transform:translateY(0);} }
  @keyframes pulseRing{
    0%,100%{ transform:scale(1); opacity:.55; }
    50%{ transform:scale(1.08); opacity:0; }
  }

  /* ── Kategórie ── */
  .cat{ margin-bottom:38px; }
  .cat:last-child{ margin-bottom:0; }
  .cat-head{
    display:flex; align-items:center; gap:10px;
    margin:0 2px 16px;
    opacity:0; transform:translateY(10px);
    animation:riseIn .5s cubic-bezier(.22,1,.36,1) forwards;
  }
  .cat:nth-of-type(1) .cat-head{ animation-delay:.04s; }
  .cat:nth-of-type(2) .cat-head{ animation-delay:.08s; }
  .cat:nth-of-type(3) .cat-head{ animation-delay:.12s; }
  .cat:nth-of-type(4) .cat-head{ animation-delay:.16s; }
  .cat:nth-of-type(5) .cat-head{ animation-delay:.20s; }
  .cat-ic{
    width:28px; height:28px; border-radius:9px; flex-shrink:0;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
  }
  .cat-title{
    font-size:12.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase;
    color:var(--label); white-space:nowrap;
  }
  .cat-count{
    font-size:11px; font-weight:700; color:var(--muted);
    background:#fff; border:1px solid var(--border); border-radius:999px;
    padding:2px 9px; flex-shrink:0;
  }
  .cat-line{ flex:1; height:1px; background:linear-gradient(90deg, var(--border), transparent); }

  .cat.soon .cat-ic{ background:#efece4; color:#b7b2a6; }

  /* ── Mriežka formulárov ── */
  .grid{
    display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
    gap:18px;
  }

  .card{
    background:#fff; border:1px solid var(--border); border-radius:var(--radius);
    padding:24px 24px 22px; position:relative;
    display:flex; flex-direction:column; gap:14px;
    box-shadow:0 6px 22px rgba(30,38,56,.055);
    transition:transform .28s cubic-bezier(.22,1,.36,1), box-shadow .28s ease, border-color .28s ease;
    overflow:hidden;
    opacity:0; transform:translateY(18px);
    animation:cardIn .55s cubic-bezier(.22,1,.36,1) forwards;
  }
  .card:nth-child(1){animation-delay:.08s;}
  .card:nth-child(2){animation-delay:.16s;}
  .card:nth-child(3){animation-delay:.24s;}
  .card:nth-child(4){animation-delay:.32s;}
  @keyframes cardIn{ to{opacity:1; transform:translateY(0);} }

  /* jemný lesk pri hoveri */
  .card::before{
    content:'';
    position:absolute; inset:0;
    background:linear-gradient(120deg, transparent 40%, rgba(31,111,107,.06) 50%, transparent 60%);
    transform:translateX(-120%);
    transition:transform .65s ease;
    pointer-events:none;
  }
  a.card:hover::before{ transform:translateX(120%); }

  a.card:hover{
    transform:translateY(-5px);
    box-shadow:0 20px 44px var(--tile-shadow, rgba(31,111,107,.14));
    border-color:var(--tile, var(--accent-line));
  }
  a.card:active{ transform:translateY(-2px); }

  .card .ic{
    width:46px; height:46px; border-radius:13px;
    background:var(--tile, var(--accent-soft)); color:var(--tile-ink, var(--accent));
    display:flex; align-items:center; justify-content:center;
    transition:transform .32s cubic-bezier(.22,1,.36,1), box-shadow .32s ease;
    flex-shrink:0;
  }
  .card .ic.avatar{ font-size:15.5px; font-weight:800; letter-spacing:.02em; }
  a.card:hover .ic.avatar{ box-shadow:0 0 0 6px var(--tile-ring, transparent); }
  a.card:hover .ic{
    transform:scale(1.08) rotate(-6deg);
  }

  .card h2{font-size:16.5px; font-weight:800; margin:0; letter-spacing:-.01em;}
  .card p{font-size:13px; color:var(--muted); margin:0; line-height:1.6; flex:1;}

  .card .go{
    display:inline-flex; align-items:center; gap:6px;
    font-size:13.5px; font-weight:700; color:var(--accent);
  }
  .card .go svg{ transition:transform .25s cubic-bezier(.22,1,.36,1); }
  a.card:hover .go svg{ transform:translateX(4px); }

  /* ── Pripravujeme ── */
  .card.soon{
    background:repeating-linear-gradient(135deg,#fff,#fff 12px,#fbfcfe 12px,#fbfcfe 24px);
    border:1.5px dashed var(--border);
    box-shadow:none;
  }
  .card.soon .ic{background:#efece4; color:#b7b2a6;}
  .badge{
    position:absolute; top:16px; right:16px;
    font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase;
    color:var(--muted); background:#fff; border:1px solid var(--border);
    padding:5px 10px; border-radius:999px;
  }

  @media(max-width:520px){
    body{padding:28px 16px 48px;}
    .head{gap:14px; padding-bottom:28px;}
    .logo{width:48px; height:48px; border-radius:14px;}
    .head h1{font-size:24px;}
    .blob{ filter:blur(50px); }
  }

  @media(prefers-reduced-motion:reduce){
    .head, .card, .cat-head{animation:none; opacity:1; transform:none;}
    .logo::after{animation:none;}
    .card::before{display:none;}
    .blob{animation:none!important;}
  }
</style>
</head>
<body>

<div class="bg-decor" aria-hidden="true">
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>
  <div class="blob b4"></div>
</div>

<div class="wrap">

  <div class="head">
    <div class="logo">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
      </svg>
    </div>
    <div>
      <h1>Formuláre</h1>
      <p>Klikni na svoje meno</p>
    </div>
  </div>

  <!-- ============================================================
       KATEGÓRIA: Poradcovia
  ============================================================ -->
  <div class="cat">
    <div class="cat-head">
      <div class="cat-ic">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <span class="cat-title">Poradcovia</span>
      <span class="cat-count"><?= count($advisors) ?></span>
      <span class="cat-line"></span>
    </div>
    <div class="grid">
      <?php foreach ($advisors as $adv): ?>
      <a class="card" href="?adv=<?= (int)$adv['id'] ?>" style="--tile:<?= htmlspecialchars($adv['color']) ?>; --tile-ink:#fff; --tile-shadow:<?= advisorRgba($adv['color'], 0.20) ?>; --tile-ring:<?= advisorRgba($adv['color'], 0.22) ?>;">
        <div class="ic avatar"><?= htmlspecialchars(advisorInitials($adv['name'])) ?></div>
        <h2><?= htmlspecialchars($adv['name']) ?></h2>
        <p><?= htmlspecialchars($adv['org']) ?><?= $adv['id'] == $curAdvisorId ? ' — aktuálne prihlásený/-á' : '' ?></p>
        <span class="go">Vstúpiť ako <?= htmlspecialchars(explode(' ', $adv['name'])[0]) ?>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
