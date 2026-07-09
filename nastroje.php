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

// Jednoduché SVG ikony (feather/lucide štýl).
function ico(string $key): string {
    $p = [
        'help'      => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'check'     => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'file-x'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'clipboard' => '<path d="M16 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><path d="M9 3v4h6V3"/>',
        'edit'      => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        'alert'     => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'receipt'   => '<path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
        'swap'      => '<path d="M17 8l4 4-4 4M3 12h18"/><path d="M7 4l-4 4 4 4"/>',
        'euro'      => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'undo'      => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'message'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
    ];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$key] ?? $p['help']) . '</svg>';
}

// Register nástrojov. `hero` = veľká indigo karta, `color` = farba ikonového čipu.
$categories = [
    ['title' => 'Hlavné nástroje', 'tools' => [
        ['href' => 'wizard-poistenie/', 'name' => 'Aké poistenie potrebujem', 'ico' => 'help', 'color' => 'indigo',
         'desc' => 'Krátky dotazník na 6 otázok – odporúčanie typov poistenia, s prekliknutím do Kalkulačky finančnej medzery.'],
        ['href' => 'financna-medzera/', 'name' => 'Kalkulačka finančnej medzery', 'ico' => 'chart', 'color' => 'indigo', 'hero' => true,
         'desc' => 'Koľko by rodine chýbalo pri úmrtí, invalidite alebo dlhodobej PN – odporúčané krytie vs. existujúce poistenie.'],
        ['href' => 'checklist-analyza/', 'name' => 'Checklist – výstup z analýzy', 'ico' => 'check', 'color' => 'emerald',
         'desc' => 'Kontrolný zoznam krokov a odporúčaní, s termínmi a zodpovednosťou. Dá sa predvyplniť z Kalkulačky.'],
    ]],
    ['title' => 'Zmluvy a dokumentácia', 'tools' => [
        ['href' => 'splnomocnenie/', 'name' => 'Všeobecné splnomocnenie', 'ico' => 'user-plus', 'color' => 'indigo',
         'desc' => 'Rozsah oprávnení, splnomocniteľ/-ka a splnomocnenec/-kyňa, platnosť – text sa doplní automaticky.'],
        ['href' => 'vypoved-poistenia/', 'name' => 'Výpoveď poistnej zmluvy', 'ico' => 'file-x', 'color' => 'rose',
         'desc' => 'Výber poisťovne, dôvodu a termínu – text výpovede sa doplní automaticky.'],
        ['href' => 'preberaci-protokol/', 'name' => 'Preberací protokol', 'ico' => 'clipboard', 'color' => 'teal',
         'desc' => 'Všeobecný preberací / odovzdávací protokol – zoznam odovzdávaných dokumentov, obe strany a podpisy.'],
        ['href' => 'univerzalna-ziadost-zmena/', 'name' => 'Univerzálna žiadosť o zmenu', 'ico' => 'edit', 'color' => 'amber',
         'desc' => 'Zmena osobných údajov, adresy alebo oprávnenej osoby v existujúcej zmluve – jeden formulár na všetko.'],
    ]],
    ['title' => 'Poistné udalosti a škody', 'tools' => [
        ['href' => 'nahrada-skody-zodpovednost/', 'name' => 'Žiadosť o náhradu škody', 'ico' => 'alert', 'color' => 'rose',
         'desc' => 'Z poistenia zodpovednosti škodcu/-kyne – typ škody, poisťovňa, popis udalosti a výška škody.'],
        ['href' => 'cestne-vyhlasenie-inej-poistky/', 'name' => 'Čestné prehlásenie', 'ico' => 'shield', 'color' => 'indigo',
         'desc' => 'O neuplatňovaní si náhrady z iného poistenia – vyhlasujúci/-a, súvisiaca škoda, poisťovňa.'],
        ['href' => 'cestne-vyhlasenie-kupa-veci/', 'name' => 'Čestné prehlásenie o kúpe veci', 'ico' => 'receipt', 'color' => 'teal',
         'desc' => 'Pre prípad, že chýbajú pôvodné bloky/doklady o kúpe – popis veci, dátum a dôvod chýbajúceho dokladu.'],
        ['href' => 'suhlas-vyplata-inemu-uctu/', 'name' => 'Súhlas s výplatou na iný účet', 'ico' => 'swap', 'color' => 'emerald',
         'desc' => 'Súhlas poškodeného/-ej s výplatou poistného plnenia na účet tretej osoby, napr. priamo autoservisu.'],
    ]],
    ['title' => 'Reklamácie, zmeny a spory', 'tools' => [
        ['href' => 'ziadost-vratenie-preplatku/', 'name' => 'Vrátenie preplatku', 'ico' => 'euro', 'color' => 'emerald',
         'desc' => 'Žiadosť o vrátenie preplatku / nespotrebovaného poistného pre zrušené alebo zmenené zmluvy, s IBANom.'],
        ['href' => 'odvolanie-zamietnutie-plnenia/', 'name' => 'Odvolanie voči likvidácii', 'ico' => 'undo', 'color' => 'amber',
         'desc' => 'Nesúhlas s výsledkom likvidácie alebo zamietnutím poistného plnenia – odôvodnenie a požadovaný postup.'],
        ['href' => 'reklamacia-postup-institucie/', 'name' => 'Reklamácia / sťažnosť', 'ico' => 'message', 'color' => 'rose',
         'desc' => 'Oficiálna reklamácia alebo sťažnosť voči postupu inštitúcie – predmet, popis a požadovaná náprava.'],
    ]],
];
$arrow = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
?>
<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Formuláre — nástroje</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/panel.css?v=2">
</head>
<body>

<header class="topbar">
  <div class="tb-title">
    <h1>Nástroje</h1>
    <p>Vyber nástroj — vyplníš údaje a hotový dokument stiahneš ako PDF.</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/moje-dokumenty.php">Moje dokumenty</a>
    <span class="who">
      <span class="ini" style="background:<?= htmlspecialchars($me['color']) ?>;"><?= htmlspecialchars(advisorInitials($me['name'])) ?></span>
      <b><?= htmlspecialchars($me['name']) ?></b>
    </span>
  </div>
</header>

<main class="content">

  <div class="page-head">
    <div class="kicker">Pracovný pult</div>
    <h2>Dobrý deň, <?= htmlspecialchars(explode(' ', $me['name'])[0]) ?></h2>
    <p>Máš pripravených <?= array_sum(array_map(fn($c) => count($c['tools']), $categories)) ?> nástrojov v <?= count($categories) ?> kategóriách.</p>
  </div>

  <?php foreach ($categories as $cat): ?>
  <div class="section">
    <div class="section-head">
      <h3><?= htmlspecialchars($cat['title']) ?></h3>
      <span class="count"><?= count($cat['tools']) ?></span>
    </div>
    <div class="tool-grid">
      <?php foreach ($cat['tools'] as $t): ?>
      <a class="tool-card c-<?= htmlspecialchars($t['color']) ?><?= !empty($t['hero']) ? ' hero' : '' ?>" href="<?= htmlspecialchars($t['href']) ?>">
        <span class="ic"><?= ico($t['ico']) ?></span>
        <h4><?= htmlspecialchars($t['name']) ?></h4>
        <p><?= htmlspecialchars($t['desc']) ?></p>
        <span class="go">Otvoriť <?= $arrow ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="section">
    <div class="section-head">
      <h3>Pripravujeme</h3>
    </div>
    <div class="tool-grid">
      <div class="tool-card soon">
        <span class="ic"><?= ico('plus') ?></span>
        <h4>Ďalšie formuláre</h4>
        <p>Postupne pribudnú ďalšie vzory a žiadosti.</p>
        <span class="badge-soon">Čoskoro</span>
      </div>
    </div>
  </div>

</main>

<script src="/assets/shell.js?v=2"></script>
</body>
</html>
