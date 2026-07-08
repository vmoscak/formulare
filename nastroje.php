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

// Register nástrojov — tu pridávaš / upravuješ položky zoznamu.
$categories = [
    [
        'title' => 'Hlavné nástroje',
        'tools' => [
            ['href' => 'wizard-poistenie/', 'name' => 'Aké poistenie potrebujem',
             'desc' => 'Krátky dotazník na 6 otázok – odporúčanie typov poistenia, s prekliknutím rovno do Kalkulačky finančnej medzery.'],
            ['href' => 'financna-medzera/', 'name' => 'Kalkulačka finančnej medzery',
             'desc' => 'Koľko by rodine chýbalo pri úmrtí, invalidite alebo dlhodobej PN – odporúčané krytie vs. existujúce poistenie. Poradcovský aj klientsky režim.'],
            ['href' => 'checklist-analyza/', 'name' => 'Checklist – výstup z analýzy',
             'desc' => 'Kontrolný zoznam krokov a odporúčaní, s termínmi a zodpovednosťou. Dá sa predvyplniť rovno z Kalkulačky finančnej medzery.'],
        ],
    ],
    [
        'title' => 'Zmluvy a dokumentácia',
        'tools' => [
            ['href' => 'splnomocnenie/', 'name' => 'Všeobecné splnomocnenie',
             'desc' => 'Rozsah oprávnení, splnomocniteľ/-ka a splnomocnenec/-kyňa, platnosť – text sa doplní automaticky.'],
            ['href' => 'vypoved-poistenia/', 'name' => 'Výpoveď poistnej zmluvy',
             'desc' => 'Výber poisťovne, dôvodu a termínu – text výpovede sa doplní automaticky.'],
            ['href' => 'preberaci-protokol/', 'name' => 'Preberací protokol',
             'desc' => 'Všeobecný preberací / odovzdávací protokol na dokumentáciu – zoznam odovzdávaných dokumentov, obe strany a podpisy.'],
            ['href' => 'univerzalna-ziadost-zmena/', 'name' => 'Univerzálna žiadosť o zmenu',
             'desc' => 'Zmena osobných údajov, adresy alebo oprávnenej osoby v existujúcej zmluve – jeden formulár na všetko.'],
        ],
    ],
    [
        'title' => 'Poistné udalosti a škody',
        'tools' => [
            ['href' => 'nahrada-skody-zodpovednost/', 'name' => 'Žiadosť o náhradu škody',
             'desc' => 'Z poistenia zodpovednosti škodcu/-kyne – typ škody, poisťovňa, popis udalosti a výška škody.'],
            ['href' => 'cestne-vyhlasenie-inej-poistky/', 'name' => 'Čestné prehlásenie',
             'desc' => 'O neuplatňovaní si náhrady z iného poistenia – vyhlasujúci/-a, súvisiaca škoda, poisťovňa.'],
            ['href' => 'cestne-vyhlasenie-kupa-veci/', 'name' => 'Čestné prehlásenie o kúpe veci',
             'desc' => 'Pre prípad, že chýbajú pôvodné bloky/doklady o kúpe – popis veci, dátum a dôvod chýbajúceho dokladu.'],
            ['href' => 'suhlas-vyplata-inemu-uctu/', 'name' => 'Súhlas s výplatou na iný účet',
             'desc' => 'Súhlas poškodeného/-ej s výplatou poistného plnenia na účet tretej osoby, napr. priamo autoservisu.'],
        ],
    ],
    [
        'title' => 'Reklamácie, zmeny a spory',
        'tools' => [
            ['href' => 'ziadost-vratenie-preplatku/', 'name' => 'Vrátenie preplatku',
             'desc' => 'Žiadosť o vrátenie preplatku / nespotrebovaného poistného pre zrušené alebo zmenené zmluvy, s IBANom na vrátenie.'],
            ['href' => 'odvolanie-zamietnutie-plnenia/', 'name' => 'Odvolanie voči likvidácii',
             'desc' => 'Nesúhlas s výsledkom likvidácie alebo zamietnutím poistného plnenia – odôvodnenie a požadovaný postup.'],
            ['href' => 'reklamacia-postup-institucie/', 'name' => 'Reklamácia / sťažnosť',
             'desc' => 'Oficiálna reklamácia alebo sťažnosť voči postupu inštitúcie – predmet, popis a požadovaná náprava.'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Formuláre — nástroje</title>
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

  /* ── Horná lišta ── */
  .topbar{display:flex; align-items:center; gap:12px; padding:22px 0 0; flex-wrap:wrap;}
  .mark{
    width:34px; height:34px; border-radius:5px; background:var(--accent); color:#fff;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .wordmark{
    font-family:var(--mono); font-size:13px; font-weight:700;
    letter-spacing:.22em; text-transform:uppercase;
  }
  .topbar .right{margin-left:auto; display:flex; align-items:center; gap:10px;}
  .who{
    display:flex; align-items:center; gap:9px;
    border:1px solid var(--chrome-line); border-radius:999px; padding:5px 14px 5px 6px;
  }
  .who .ini{
    width:26px; height:26px; border-radius:50%; color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-family:var(--mono); font-size:10.5px; font-weight:700;
  }
  .who b{font-size:12.5px; font-weight:600; color:var(--chrome-ink); white-space:nowrap;}
  .who a{
    font-family:var(--mono); font-size:9.5px; letter-spacing:.1em; text-transform:uppercase;
    color:var(--chrome-muted); border-left:1px solid var(--chrome-line); padding-left:9px;
  }
  .who a:hover{color:var(--accent);}
  .docs-link{
    font-family:var(--mono); font-size:10px; letter-spacing:.12em; text-transform:uppercase;
    color:var(--chrome-muted); border:1px solid var(--chrome-line); border-radius:999px;
    padding:7px 14px; transition:color .15s, border-color .15s;
  }
  .docs-link:hover{color:#fff; border-color:var(--accent);}

  /* ── Uvítanie ── */
  .hero{padding:52px 0 34px; opacity:0; transform:translateY(10px); animation:rise .5s ease forwards;}
  .hero .kicker{
    font-family:var(--mono); font-size:11px; font-weight:600;
    letter-spacing:.22em; text-transform:uppercase; color:var(--accent); margin-bottom:12px;
  }
  .hero h1{margin:0; font-size:32px; font-weight:800; letter-spacing:-.02em; color:#fff; line-height:1.1;}
  .hero p{margin:10px 0 0; font-size:14px; color:var(--chrome-muted); line-height:1.6;}
  @keyframes rise{ to{opacity:1; transform:translateY(0);} }

  /* ── Kategórie = register dokumentov ── */
  .cat{margin-bottom:36px; opacity:0; transform:translateY(10px); animation:rise .5s ease forwards;}
  .cat:nth-of-type(1){animation-delay:.06s;}
  .cat:nth-of-type(2){animation-delay:.12s;}
  .cat:nth-of-type(3){animation-delay:.18s;}
  .cat:nth-of-type(4){animation-delay:.24s;}
  .cat:nth-of-type(5){animation-delay:.30s;}
  .cat-head{display:flex; align-items:center; gap:12px; margin-bottom:12px;}
  .cat-head .num{font-family:var(--mono); font-size:12px; font-weight:700; color:var(--accent);}
  .cat-head .t{
    font-family:var(--mono); font-size:11px; font-weight:600;
    letter-spacing:.16em; text-transform:uppercase; color:var(--chrome-muted);
  }
  .cat-head .count{
    font-family:var(--mono); font-size:10px; color:var(--chrome-muted);
    border:1px solid var(--chrome-line); border-radius:999px; padding:2px 9px;
  }
  .cat-head .rule{flex:1; height:1px; background:var(--chrome-line);}

  .sheet{
    background:var(--paper); color:var(--ink); border-radius:8px; overflow:hidden;
    box-shadow:0 2px 6px rgba(0,0,0,.25), 0 20px 50px -20px rgba(0,0,0,.55);
  }
  .row{
    display:flex; align-items:center; gap:16px;
    padding:15px 22px; border-bottom:1px solid var(--border);
    transition:background .15s;
    position:relative;
  }
  .row:last-child{border-bottom:none;}
  a.row::before{
    content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
    background:var(--accent); transform:scaleY(0); transition:transform .18s ease;
  }
  a.row:hover{background:#faf8f5;}
  a.row:hover::before{transform:scaleY(1);}
  .row .idx{
    font-family:var(--mono); font-size:11.5px; font-weight:700; color:var(--muted);
    width:26px; flex-shrink:0; text-align:right;
  }
  a.row:hover .idx{color:var(--accent);}
  .row .body{flex:1; min-width:0;}
  .row h2{margin:0; font-size:14.5px; font-weight:700; letter-spacing:-.005em;}
  .row p{
    margin:3px 0 0; font-size:12px; color:var(--muted); line-height:1.5;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .row .arrow{color:var(--border); flex-shrink:0; transition:transform .18s, color .18s;}
  a.row:hover .arrow{color:var(--accent); transform:translateX(3px);}

  .row.soon{color:var(--muted);}
  .row.soon h2{color:var(--label);}
  .badge-soon{
    font-family:var(--mono); font-size:9px; font-weight:600; letter-spacing:.12em;
    text-transform:uppercase; color:var(--muted); background:#f1f2f4;
    border:1px solid var(--border); padding:4px 9px; border-radius:3px; flex-shrink:0;
  }

  .foot{
    margin-top:52px; font-family:var(--mono); font-size:10px; letter-spacing:.12em;
    text-transform:uppercase; color:#565c66; text-align:center;
  }

  @media(max-width:640px){
    .hero{padding:38px 0 26px;}
    .hero h1{font-size:25px;}
    .topbar .right{width:100%; order:3;}
    .row p{white-space:normal;}
    .row{padding:13px 16px;}
  }
  @media(prefers-reduced-motion:reduce){
    .hero,.cat{animation:none; opacity:1; transform:none;}
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
    <div class="right">
      <a class="docs-link" href="/moje-dokumenty.php">Moje dokumenty</a>
      <span class="who">
        <span class="ini" style="background:<?= htmlspecialchars($me['color']) ?>;"><?= htmlspecialchars(advisorInitials($me['name'])) ?></span>
        <b><?= htmlspecialchars($me['name']) ?></b>
        <a href="/" title="Prepnúť poradcu">Zmeniť</a>
      </span>
    </div>
  </div>

  <div class="hero">
    <div class="kicker">Register nástrojov</div>
    <h1>Čo dnes pripravujeme?</h1>
    <p>Vyber nástroj — vyplníš údaje, náhľad sa priebežne aktualizuje a hotový dokument stiahneš ako PDF.</p>
  </div>

  <?php $catNo = 0; foreach ($categories as $cat): $catNo++; ?>
  <div class="cat">
    <div class="cat-head">
      <span class="num"><?= str_pad((string)$catNo, 2, '0', STR_PAD_LEFT) ?></span>
      <span class="t"><?= htmlspecialchars($cat['title']) ?></span>
      <span class="count"><?= count($cat['tools']) ?></span>
      <span class="rule"></span>
    </div>
    <div class="sheet">
      <?php $i = 0; foreach ($cat['tools'] as $tool): $i++; ?>
      <a class="row" href="<?= htmlspecialchars($tool['href']) ?>">
        <span class="idx"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="body">
          <h2><?= htmlspecialchars($tool['name']) ?></h2>
          <p><?= htmlspecialchars($tool['desc']) ?></p>
        </span>
        <span class="arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="cat">
    <div class="cat-head">
      <span class="num"><?= str_pad((string)($catNo + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <span class="t">Pripravujeme</span>
      <span class="rule"></span>
    </div>
    <div class="sheet">
      <div class="row soon">
        <span class="idx">··</span>
        <span class="body">
          <h2>Ďalšie formuláre</h2>
          <p>Postupne pribudnú ďalšie vzory a žiadosti.</p>
        </span>
        <span class="badge-soon">Čoskoro</span>
      </div>
    </div>
  </div>

  <div class="foot">Formuláre · prístup len pre poradcov</div>

</div>
</body>
</html>
