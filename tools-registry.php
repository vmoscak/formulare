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
        'book'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'folder'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'megaphone' => '<path d="M3 11v3a1 1 0 0 0 1 1h2l4 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 8a4 4 0 0 1 0 8"/><path d="M17.5 5.5a8 8 0 0 1 0 13"/>',
        'pyramid'   => '<path d="M12 3l9 18H3z"/><path d="M7.5 12h9"/><path d="M5.2 16.5h13.6"/>',
        'coffee'    => '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/>',
        'spectrum'  => '<circle cx="5" cy="12" r="2.5"/><circle cx="19" cy="12" r="2.5"/><path d="M7.5 12h9"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'route'     => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
        'semafor'   => '<rect x="8" y="2" width="8" height="20" rx="4"/><circle cx="12" cy="7" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="17" r="1.6"/>',
        'percent'   => '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'target'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.3"/>',
        'trending'  => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    ];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$key] ?? $p['help']) . '</svg>';
}

// Tri záložky v ľavej lište (assets/shell.js) — každá kategória nižšie patrí
// do práve jednej skupiny cez kľúč 'group'. Chýbajúci 'group' = 'nastroje'.
$TOOL_GROUPS = [
    'nastroje'  => ['label' => 'Nástroje',  'subtitle' => 'Kalkulačky a analytické nástroje pre klienta.'],
    'formulare' => ['label' => 'Formuláre', 'subtitle' => 'Zmluvy, žiadosti, vyhlásenia a reklamácie na podpis.'],
    'pomocky'   => ['label' => 'Pomôcky',   'subtitle' => 'Kartičky a rýchle texty pre klienta na SMS/WhatsApp.'],
    'uniqa'     => ['label' => 'Tlačivá UNIQA', 'subtitle' => 'Interné oficiálne tlačivá UNIQA – presný pretlač na predlohu.'],
];

// Register nástrojov. `hero` = veľká indigo karta, `color` = farba ikonového čipu.
$TOOL_CATEGORIES = [
    // Wizard/kalkulačka/checklist ostávajú tu len formálne (kvôli poradiu) —
    // v mriežke sa nezobrazia, keďže sú zvýraznené vo flow banneri vyššie
    // (viď inc-tools-page.php, $FLOW_STEPS).
    ['title' => 'Pred stretnutím / motivácia', 'group' => 'nastroje', 'tools' => [
        ['href' => 'wizard-poistenie/', 'name' => 'Aké poistenie potrebujem', 'ico' => 'help', 'color' => 'violet',
         'desc' => 'Krátky dotazník na 8 otázok – odporúčanie typov poistenia, s prekliknutím do Kalkulačky poistného krytia.'],
        ['href' => 'financna-medzera/', 'name' => 'Kalkulačka poistného krytia', 'ico' => 'chart', 'color' => 'indigo',
         'desc' => 'Koľko by rodine chýbalo pri úmrtí, invalidite alebo dlhodobej PN – odporúčané krytie vs. existujúce poistenie.'],
        ['href' => 'checklist-analyza/', 'name' => 'Checklist – výstup z analýzy', 'ico' => 'check', 'color' => 'emerald',
         'desc' => 'Kontrolný zoznam krokov a odporúčaní, s termínmi a zodpovednosťou. Dá sa predvyplniť z Kalkulačky.'],
        ['href' => 'simulator-dvoch-extremov/', 'name' => 'Simulátor dvoch extrémov', 'ico' => 'spectrum', 'color' => 'teal',
         'desc' => 'Dva krajné scenáre vedľa seba — dlhý život vs. náhla strata príjmu zajtra, s príbehom pre každý z nich. Na presvedčenie o zmysle riešiť poistenie, ešte pred dotazníkom.'],
        ['href' => 'sprievodca-udalosti/', 'name' => 'Sprievodca podľa životnej udalosti', 'ico' => 'route', 'color' => 'indigo',
         'desc' => 'Svadba, dieťa, kúpa bytu, zmena zamestnania, koniec fixácie hypotéky — odporúčané poradie existujúcich nástrojov, nič nové sa nestavia.'],
    ]],
    ['title' => 'Vysvetľovanie klientovi', 'group' => 'nastroje', 'tools' => [
        ['href' => 'pyramida-istoty/', 'name' => 'Interaktívna Pyramída istoty', 'ico' => 'pyramid', 'color' => 'sky',
         'desc' => 'V akom poradí budovať finančnú istotu — ochrana, rezerva, ciele, zhodnocovanie. Klikacia pyramída s kontrolným zoznamom pre každú vrstvu.'],
        ['href' => 'latte-faktor/', 'name' => 'Latte Faktor', 'ico' => 'coffee', 'color' => 'rose',
         'desc' => 'Koľko narastie malý pravidelný výdavok (káva, predplatné…), keby sa namiesto minutia investoval. Naživo prepočítava pri zmene súm, rokov aj zhodnotenia.'],
        ['href' => 'simulator-kratenia-plnenia/', 'name' => 'Simulátor krátenia plnenia', 'ico' => 'percent', 'color' => 'rose',
         'desc' => 'Koľko reálne dostane klient pri škode, ak je majetok podpoistený – princíp pomerného plnenia, so správnou sumou vedľa seba na porovnanie.'],
        ['href' => 'poistenie-uveru-banka-vs-uniqa/', 'name' => 'Poistenie úveru: banka vs. UNIQA', 'ico' => 'swap', 'color' => 'indigo',
         'desc' => 'Argumenty, prečo je pre klienta výhodnejšia samostatná poistka UNIQA namiesto bankového balíka k úveru – životné aj majetkové poistenie.'],
    ]],
    ['title' => 'Podpora poradcu', 'group' => 'pomocky', 'tools' => [
        ['href' => 'tahak-co-pytat-od-klienta/', 'name' => 'Ťahák „Čo pýtať od klienta“', 'ico' => 'check', 'color' => 'amber',
         'desc' => 'Upisovacie otázky ku konkrétnej ponuke (majetok, zodpovednosť, auto...) so zápisom odpovedí počas stretnutia.'],
        ['href' => 'argument-builder/', 'name' => 'Argument Builder', 'ico' => 'megaphone', 'color' => 'orange',
         'desc' => 'Vyber produkt a typ klienta — poskladá sa zoznam odporúčaných argumentov na jeho predstavenie. Opak Ťaháku „čo pýtať“ — toto je čo povedať.'],
        ['href' => 'vybavovac-namietok/', 'name' => 'Vybavovač námietok', 'ico' => 'help', 'color' => 'violet',
         'desc' => 'Vyber typickú námietku klienta („je to drahé“, „musím si to premyslieť“...) — appka ukáže odporúčané reakcie. Interný nástroj, čo povedať, keď klient zaváha.'],
    ]],
    ['title' => 'Po podpise', 'group' => 'pomocky', 'tools' => [
        ['href' => 'prvych-30-dni/', 'name' => 'Prvých 30 dní', 'ico' => 'calendar', 'color' => 'emerald',
         'desc' => 'Kartička pre klienta hneď po podpise — čo teraz nasleduje, kedy začína platiť krytie, ako postupovať pri poistnej udalosti. PDF aj text na SMS/WhatsApp.'],
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
        ['href' => 'ziadost-krycie-list/', 'name' => 'Žiadosť o vystavenie krycieho listu', 'ico' => 'shield', 'color' => 'sky',
         'desc' => 'Vozidlo, typ poistenia, platnosť od a dôvod žiadosti (nová zmluva, prevod, evidencia vozidiel) – text sa doplní automaticky.'],
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
        ['href' => 'ziadost-vyplata-poistneho-plnenia/', 'name' => 'Výplata poistného plnenia', 'ico' => 'euro', 'color' => 'rose',
         'desc' => 'Žiadosť o vyplatenie poistného plnenia z poistnej udalosti na bankový účet žiadateľa, s IBANom.'],
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
        ['href' => 'vysvetlivky-pre-klienta/', 'name' => 'Vysvetlivky pre klienta', 'ico' => 'book', 'color' => 'sky',
         'desc' => 'Krátke jednostránkové vysvetlenie bežného poistného pojmu v ľudskej reči – PDF aj text.'],
        ['href' => 'emailove-pozdravy/', 'name' => 'Emailové pozdravy', 'ico' => 'mail', 'color' => 'violet',
         'desc' => 'Narodeniny, Vianoce/Nový rok, výročie zmluvy – formátovaný (grafický) e-mail na skopírovanie priamo do interného e-mailového klienta.'],
    ]],
    ['title' => 'Zmluvy a správa', 'group' => 'uniqa', 'tools' => [
        ['href' => 'zmena-spravcu-zmluvy/', 'name' => 'Zmena správcu zmluvy', 'ico' => 'swap', 'color' => 'emerald',
         'desc' => 'Príloha 3 (UNIQA) – presný pretlač zadaných údajov na oficiálnu predlohu, až 5 zmlúv na jednu žiadosť.'],
    ]],
];

/** Slug nástroja z href ('wizard-poistenie/' -> 'wizard-poistenie') — kľúč pre disabled_tools. */
function toolSlug(string $href): string {
    return rtrim($href, '/');
}
