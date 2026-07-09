<?php
/**
 * Jediný zdroj pravdy pre zoznam nástrojov — používa ho nastroje.php
 * (zobrazenie) aj admin.php (prepínače zap./vyp. per poradca), aby sa
 * pri pridaní nového nástroja zoznam nikdy nerozišiel medzi dvoma miestami.
 *
 * `href` je zároveň identifikátor nástroja (slug) používaný v disabled_tools
 * (admin.php), aj v stĺpci `tool` v DB (formulare_generated_documents) —
 * musí sa zhodovať s kľúčmi v toolLabel() (db.php) a s názvom priečinka.
 *
 * KDE ČO UPRAVIŤ: pridanie/úprava nástroja = jeden záznam nižšie.
 */

function toolIco(string $key): string {
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
        'firstaid'  => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M12 8v8M8 12h8"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    ];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$key] ?? $p['help']) . '</svg>';
}

// Tri záložky v ľavej lište (assets/shell.js) — každá kategória nižšie patrí
// do práve jednej skupiny cez kľúč 'group'. Chýbajúci 'group' = 'nastroje'.
$TOOL_GROUPS = [
    'nastroje'  => ['label' => 'Nástroje',  'subtitle' => 'Kalkulačky a analytické nástroje pre klienta.'],
    'formulare' => ['label' => 'Formuláre', 'subtitle' => 'Zmluvy, žiadosti, vyhlásenia a reklamácie na podpis.'],
    'pomocky'   => ['label' => 'Pomôcky',   'subtitle' => 'Kartičky a rýchle texty pre klienta na SMS/WhatsApp.'],
];

// Register nástrojov. `hero` = veľká indigo karta, `color` = farba ikonového čipu.
$TOOL_CATEGORIES = [
    ['title' => 'Hlavné nástroje', 'group' => 'nastroje', 'tools' => [
        ['href' => 'wizard-poistenie/', 'name' => 'Aké poistenie potrebujem', 'ico' => 'help', 'color' => 'violet',
         'desc' => 'Krátky dotazník na 6 otázok – odporúčanie typov poistenia, s prekliknutím do Kalkulačky finančnej medzery.'],
        ['href' => 'financna-medzera/', 'name' => 'Kalkulačka finančnej medzery', 'ico' => 'chart', 'color' => 'indigo',
         'desc' => 'Koľko by rodine chýbalo pri úmrtí, invalidite alebo dlhodobej PN – odporúčané krytie vs. existujúce poistenie.'],
        ['href' => 'checklist-analyza/', 'name' => 'Checklist – výstup z analýzy', 'ico' => 'check', 'color' => 'emerald',
         'desc' => 'Kontrolný zoznam krokov a odporúčaní, s termínmi a zodpovednosťou. Dá sa predvyplniť z Kalkulačky.'],
    ]],
    ['title' => 'Zmluvy a dokumentácia', 'group' => 'formulare', 'tools' => [
        ['href' => 'splnomocnenie/', 'name' => 'Všeobecné splnomocnenie', 'ico' => 'user-plus', 'color' => 'indigo',
         'desc' => 'Rozsah oprávnení, splnomocniteľ/-ka a splnomocnenec/-kyňa, platnosť – text sa doplní automaticky.'],
        ['href' => 'vypoved-poistenia/', 'name' => 'Výpoveď poistnej zmluvy', 'ico' => 'file-x', 'color' => 'rose',
         'desc' => 'Výber poisťovne, dôvodu a termínu – text výpovede sa doplní automaticky.'],
        ['href' => 'preberaci-protokol/', 'name' => 'Preberací protokol', 'ico' => 'clipboard', 'color' => 'teal',
         'desc' => 'Všeobecný preberací / odovzdávací protokol – zoznam odovzdávaných dokumentov, obe strany a podpisy.'],
        ['href' => 'univerzalna-ziadost-zmena/', 'name' => 'Univerzálna žiadosť o zmenu', 'ico' => 'edit', 'color' => 'amber',
         'desc' => 'Zmena osobných údajov, adresy alebo oprávnenej osoby v existujúcej zmluve – jeden formulár na všetko.'],
    ]],
    ['title' => 'Poistné udalosti a škody', 'group' => 'formulare', 'tools' => [
        ['href' => 'nahrada-skody-zodpovednost/', 'name' => 'Žiadosť o náhradu škody', 'ico' => 'alert', 'color' => 'orange',
         'desc' => 'Z poistenia zodpovednosti škodcu/-kyne – typ škody, poisťovňa, popis udalosti a výška škody.'],
        ['href' => 'cestne-vyhlasenie-inej-poistky/', 'name' => 'Čestné prehlásenie', 'ico' => 'shield', 'color' => 'violet',
         'desc' => 'O neuplatňovaní si náhrady z iného poistenia – vyhlasujúci/-a, súvisiaca škoda, poisťovňa.'],
        ['href' => 'cestne-vyhlasenie-kupa-veci/', 'name' => 'Čestné prehlásenie o kúpe veci', 'ico' => 'receipt', 'color' => 'sky',
         'desc' => 'Pre prípad, že chýbajú pôvodné bloky/doklady o kúpe – popis veci, dátum a dôvod chýbajúceho dokladu.'],
        ['href' => 'suhlas-vyplata-inemu-uctu/', 'name' => 'Súhlas s výplatou na iný účet', 'ico' => 'swap', 'color' => 'emerald',
         'desc' => 'Súhlas poškodeného/-ej s výplatou poistného plnenia na účet tretej osoby, napr. priamo autoservisu.'],
    ]],
    ['title' => 'Reklamácie, zmeny a spory', 'group' => 'formulare', 'tools' => [
        ['href' => 'ziadost-vratenie-preplatku/', 'name' => 'Vrátenie preplatku', 'ico' => 'euro', 'color' => 'indigo',
         'desc' => 'Žiadosť o vrátenie preplatku / nespotrebovaného poistného pre zrušené alebo zmenené zmluvy, s IBANom.'],
        ['href' => 'odvolanie-zamietnutie-plnenia/', 'name' => 'Odvolanie voči likvidácii', 'ico' => 'undo', 'color' => 'amber',
         'desc' => 'Nesúhlas s výsledkom likvidácie alebo zamietnutím poistného plnenia – odôvodnenie a požadovaný postup.'],
        ['href' => 'reklamacia-postup-institucie/', 'name' => 'Reklamácia / sťažnosť', 'ico' => 'message', 'color' => 'teal',
         'desc' => 'Oficiálna reklamácia alebo sťažnosť voči postupu inštitúcie – predmet, popis a požadovaná náprava.'],
    ]],
    ['title' => 'Kartičky a rýchle texty pre klienta', 'group' => 'pomocky', 'tools' => [
        ['href' => 'financna-karticka-prvej-pomoci/', 'name' => 'Finančná kartička prvej pomoci', 'ico' => 'firstaid', 'color' => 'rose',
         'desc' => 'Jednostránkový prehľad poistiek a núdzových kontaktov pre klienta – PDF aj text na SMS/WhatsApp.'],
        ['href' => 'checklisty-skody/', 'name' => 'Checklisty podľa typu škody', 'ico' => 'check', 'color' => 'amber',
         'desc' => 'Vyber typ škody – hotový zoznam dokladov pre klienta, na SMS/WhatsApp alebo ako PDF.'],
        ['href' => 'generator-recenzii/', 'name' => 'Generátor 5-hviezdičkovej prosby', 'ico' => 'message', 'color' => 'teal',
         'desc' => 'Rýchla prosba o Google/Facebook recenziu na WhatsApp, s priamym odkazom na odoslanie.'],
        ['href' => 'warm-intro-whatsapp/', 'name' => 'Warm-Intro WhatsApp generátor', 'ico' => 'message', 'color' => 'indigo',
         'desc' => 'Priateľská prvá správa pre nový odporúčaný kontakt, s priamym odkazom na odoslanie.'],
        ['href' => 'karticka-odporucte-ma/', 'name' => 'Kartička „Odporučte ma priateľom“', 'ico' => 'users', 'color' => 'emerald',
         'desc' => 'Poďakovanie a kontakt pre klienta, aby odporučil poradcu ďalej – PDF aj text na SMS/WhatsApp.'],
    ]],
];

/** Slug nástroja z href ('wizard-poistenie/' -> 'wizard-poistenie') — kľúč pre disabled_tools. */
function toolSlug(string $href): string {
    return rtrim($href, '/');
}
