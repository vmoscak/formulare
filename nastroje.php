<?php
require_once __DIR__ . '/db.php';

// Vyžaduje zvoleného poradcu — bez neho niet čo personalizovať, vráť na výber.
$curAdvisorId = curAdvisorId();
if (!$curAdvisorId) { header('Location: /'); exit; }

try {
    $stmt = db()->prepare('SELECT name, color FROM formulare_advisors WHERE id = ? AND active = 1');
    $stmt->execute([$curAdvisorId]);
    $me = $stmt->fetch();
} catch (Throwable $e) { $me = null; }
if (!$me) { header('Location: /'); exit; }

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
    color:#fff;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 10px 26px rgba(20,24,36,.28), inset 0 1px 0 rgba(255,255,255,.25);
    position:relative;
  }
  .logo::after{
    content:'';
    position:absolute; inset:-7px;
    border-radius:20px;
    border:1.5px solid rgba(20,24,36,.14);
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

  /* ── Zoznam nástrojov (kompaktné riadky, odlišné od dlaždíc poradcov) ── */
  .tool-list{
    background:#fff; border:1px solid var(--border); border-radius:16px;
    overflow:hidden;
    box-shadow:0 4px 16px rgba(30,38,56,.045);
    opacity:0; transform:translateY(14px);
    animation:listIn .5s cubic-bezier(.22,1,.36,1) forwards;
  }
  .cat:nth-of-type(1) .tool-list{animation-delay:.06s;}
  .cat:nth-of-type(2) .tool-list{animation-delay:.10s;}
  .cat:nth-of-type(3) .tool-list{animation-delay:.14s;}
  .cat:nth-of-type(4) .tool-list{animation-delay:.18s;}
  .cat:nth-of-type(5) .tool-list{animation-delay:.22s;}
  @keyframes listIn{ to{opacity:1; transform:translateY(0);} }

  .tool-row{
    display:flex; align-items:center; gap:14px;
    padding:13px 20px;
    border-bottom:1px solid var(--border);
    transition:background-color .18s ease, padding-left .18s ease;
  }
  .tool-row:last-child{ border-bottom:none; }
  a.tool-row:hover{ background-color:var(--accent-soft); padding-left:26px; }
  a.tool-row:active{ background-color:#dbe9e7; }

  .tool-row .ic{
    width:32px; height:32px; border-radius:9px; flex-shrink:0;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
    transition:transform .22s ease;
  }
  a.tool-row:hover .ic{ transform:scale(1.1); }

  .tool-row .body{ flex:1; min-width:0; }
  .tool-row h2{ font-size:14px; font-weight:700; margin:0; letter-spacing:-.005em; }
  .tool-row p{
    font-size:12px; color:var(--muted); margin:2px 0 0; line-height:1.4;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .tool-row .arrow{ color:var(--muted); flex-shrink:0; transition:transform .2s ease, color .2s ease; }
  a.tool-row:hover .arrow{ color:var(--accent); transform:translateX(3px); }

  /* ── Pripravujeme ── */
  .tool-row.soon{ cursor:default; }
  .tool-row.soon .ic{ background:#efece4; color:#b7b2a6; }
  .tool-row.soon p{ white-space:normal; }
  .badge-soon{
    font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase;
    color:var(--muted); background:#f3f4f8; border:1px solid var(--border);
    padding:4px 9px; border-radius:999px; flex-shrink:0;
  }

  @media(max-width:520px){
    body{padding:28px 16px 48px;}
    .head{gap:14px; padding-bottom:28px;}
    .logo{width:48px; height:48px; border-radius:14px;}
    .head h1{font-size:24px;}
    .blob{ filter:blur(50px); }
    .tool-row{ padding:12px 16px; gap:12px; }
    a.tool-row:hover{ padding-left:19px; }
    .tool-row p{ white-space:normal; }
  }

  @media(prefers-reduced-motion:reduce){
    .head, .tool-list, .cat-head{animation:none; opacity:1; transform:none;}
    .logo::after{animation:none;}
    .blob{animation:none!important;}
    a.tool-row:hover{ padding-left:20px; }
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
    <div class="logo" style="background:<?= htmlspecialchars($me['color']) ?>; font-size:19px; font-weight:800;">
      <?= htmlspecialchars(advisorInitials($me['name'])) ?>
    </div>
    <div>
      <h1>Formuláre</h1>
      <p>Prihlásený/-á ako <b><?= htmlspecialchars($me['name']) ?></b> — <a href="/moje-dokumenty.php" style="color:var(--accent); text-decoration:underline;">moje dokumenty</a></p>
    </div>
  </div>

  <!-- ============================================================
       KATEGÓRIA: Hlavné nástroje
  ============================================================ -->
  <div class="cat">
    <div class="cat-head">
      <div class="cat-ic">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      </div>
      <span class="cat-title">Hlavné nástroje</span>
      <span class="cat-count">3</span>
      <span class="cat-line"></span>
    </div>
    <div class="tool-list">

      <!-- Wizard "Aké poistenie potrebujem" – AKTÍVNA -->
      <a class="tool-row" href="wizard-poistenie/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/><circle cx="12" cy="12" r="10"/>
          </svg>
        </div>
        <div class="body">
        <h2>Aké poistenie potrebujem</h2>
        <p>Krátky dotazník na 6 otázok – odporúčanie typov poistenia, s prekliknutím rovno do Kalkulačky finančnej medzery.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Kalkulačka finančnej medzery – AKTÍVNA, hlavný nástroj -->
      <a class="tool-row" href="financna-medzera/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>
          </svg>
        </div>
        <div class="body">
        <h2>Kalkulačka finančnej medzery</h2>
        <p>Koľko by rodine chýbalo pri úmrtí, invalidite alebo dlhodobej PN – odporúčané krytie vs. existujúce poistenie. Poradcovský aj klientsky režim, výstup aj ako checklist.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Checklist – výstup z analýzy – AKTÍVNA -->
      <a class="tool-row" href="checklist-analyza/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <div class="body">
        <h2>Checklist – výstup z analýzy</h2>
        <p>Kontrolný zoznam krokov a odporúčaní, s termínmi a zodpovednosťou. Dá sa predvyplniť rovno z Kalkulačky finančnej medzery.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

    </div>
  </div>

  <!-- ============================================================
       KATEGÓRIA: Zmluvy a dokumentácia
  ============================================================ -->
  <div class="cat">
    <div class="cat-head">
      <div class="cat-ic">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
      </div>
      <span class="cat-title">Zmluvy a dokumentácia</span>
      <span class="cat-count">4</span>
      <span class="cat-line"></span>
    </div>
    <div class="tool-list">

      <!-- Splnomocnenie – AKTÍVNA -->
      <a class="tool-row" href="splnomocnenie/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>
          </svg>
        </div>
        <div class="body">
        <h2>Všeobecné splnomocnenie</h2>
        <p>Rozsah oprávnení, splnomocniteľ/-ka a splnomocnenec/-kyňa, platnosť – text sa doplní automaticky.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Výpoveď poistnej zmluvy – AKTÍVNA -->
      <a class="tool-row" href="vypoved-poistenia/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
          </svg>
        </div>
        <div class="body">
        <h2>Výpoveď poistnej zmluvy</h2>
        <p>Výber poisťovne, dôvodu a termínu – text výpovede sa doplní automaticky.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Preberací / odovzdávací protokol – AKTÍVNA -->
      <a class="tool-row" href="preberaci-protokol/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><path d="M9 3v4h6V3"/>
          </svg>
        </div>
        <div class="body">
        <h2>Preberací protokol</h2>
        <p>Všeobecný preberací / odovzdávací protokol na dokumentáciu – zoznam odovzdávaných dokumentov, obe strany a podpisy.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Univerzálna žiadosť o zmenu – AKTÍVNA -->
      <a class="tool-row" href="univerzalna-ziadost-zmena/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
          </svg>
        </div>
        <div class="body">
        <h2>Univerzálna žiadosť o zmenu</h2>
        <p>Zmena osobných údajov, adresy alebo oprávnenej osoby v existujúcej zmluve – jeden formulár na všetko.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

    </div>
  </div>

  <!-- ============================================================
       KATEGÓRIA: Poistné udalosti a škody
  ============================================================ -->
  <div class="cat">
    <div class="cat-head">
      <div class="cat-ic">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span class="cat-title">Poistné udalosti a škody</span>
      <span class="cat-count">4</span>
      <span class="cat-line"></span>
    </div>
    <div class="tool-list">

      <!-- Žiadosť o náhradu škody z poistenia zodpovednosti – AKTÍVNA -->
      <a class="tool-row" href="nahrada-skody-zodpovednost/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </div>
        <div class="body">
        <h2>Žiadosť o náhradu škody</h2>
        <p>Z poistenia zodpovednosti škodcu/-kyne – typ škody, poisťovňa, popis udalosti a výška škody.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Čestné prehlásenie o neuplatňovaní si náhrady z iného poistenia – AKTÍVNA -->
      <a class="tool-row" href="cestne-vyhlasenie-inej-poistky/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/>
          </svg>
        </div>
        <div class="body">
        <h2>Čestné prehlásenie</h2>
        <p>O neuplatňovaní si náhrady z iného poistenia – vyhlasujúci/-a, súvisiaca škoda, poisťovňa.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Čestné prehlásenie o kúpe / vlastníctve veci – AKTÍVNA -->
      <a class="tool-row" href="cestne-vyhlasenie-kupa-veci/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
          </svg>
        </div>
        <div class="body">
        <h2>Čestné prehlásenie o kúpe veci</h2>
        <p>Pre prípad, že chýbajú pôvodné bloky/doklady o kúpe – popis veci, dátum a dôvod chýbajúceho dokladu.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Súhlas poškodeného s výplatou poistného plnenia na iný účet – AKTÍVNA -->
      <a class="tool-row" href="suhlas-vyplata-inemu-uctu/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 8l4 4-4 4M3 12h18"/><path d="M7 4l-4 4 4 4"/>
          </svg>
        </div>
        <div class="body">
        <h2>Súhlas s výplatou na iný účet</h2>
        <p>Súhlas poškodeného/-ej s výplatou poistného plnenia na účet tretej osoby, napr. priamo autoservisu.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

    </div>
  </div>

  <!-- ============================================================
       KATEGÓRIA: Reklamácie, zmeny a spory
  ============================================================ -->
  <div class="cat">
    <div class="cat-head">
      <div class="cat-ic">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
      </div>
      <span class="cat-title">Reklamácie, zmeny a spory</span>
      <span class="cat-count">3</span>
      <span class="cat-line"></span>
    </div>
    <div class="tool-list">

      <!-- Žiadosť o vrátenie preplatku / nespotrebovaného poistného – AKTÍVNA -->
      <a class="tool-row" href="ziadost-vratenie-preplatku/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
        </div>
        <div class="body">
        <h2>Vrátenie preplatku</h2>
        <p>Žiadosť o vrátenie preplatku / nespotrebovaného poistného pre zrušené alebo zmenené zmluvy, s IBANom na vrátenie.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Nesúhlas s výsledkom likvidácie / odvolanie voči zamietnutiu plnenia – AKTÍVNA -->
      <a class="tool-row" href="odvolanie-zamietnutie-plnenia/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
          </svg>
        </div>
        <div class="body">
        <h2>Odvolanie voči likvidácii</h2>
        <p>Nesúhlas s výsledkom likvidácie alebo zamietnutím poistného plnenia – odôvodnenie a požadovaný postup.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

      <!-- Oficiálna reklamácia / sťažnosť voči postupu inštitúcie – AKTÍVNA -->
      <a class="tool-row" href="reklamacia-postup-institucie/">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <div class="body">
        <h2>Reklamácia / sťažnosť</h2>
        <p>Oficiálna reklamácia alebo sťažnosť voči postupu inštitúcie – predmet, popis a požadovaná náprava.</p>
        </div>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>

    </div>
  </div>

  <!-- ============================================================
       Pripravujeme
  ============================================================ -->
  <div class="cat soon">
    <div class="cat-head">
      <div class="cat-ic">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      </div>
      <span class="cat-title">Pripravujeme</span>
      <span class="cat-line"></span>
    </div>
    <div class="tool-list">

      <div class="tool-row soon">
        <div class="ic">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 5v14M5 12h14"/>
          </svg>
        </div>
        <div class="body">
        <h2>Ďalšie formuláre</h2>
        <p>Postupne pribudnú ďalšie vzory a žiadosti.</p>
        </div>
        <span class="badge-soon">Čoskoro</span>
      </div>

    </div>
  </div>

</div>
</body>
</html>