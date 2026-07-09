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
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Formuláre — výber poradcu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f5f6f8; --paper:#fff; --border:#eef0f3; --line-strong:#e2e6ec;
    --ink:#111827; --muted:#6b7280; --label:#98a2b3;
    --accent:#4f46e5; --accent-ink:#4338ca; --accent-soft:#eef2ff; --accent-line:#c7d2fe;
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

  @media(max-width:560px){ .hero{padding:40px 4px 24px;} .hero h1{font-size:27px;} .topbar .tag{display:none;} }
  @media(prefers-reduced-motion:reduce){ .hero,.grid{animation:none; opacity:1; transform:none;} }
</style>
</head>
<body>

  <div class="topbar">
    <div class="mark">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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

  <div class="foot">Formuláre · prístup len pre poradcov</div>

</body>
</html>
