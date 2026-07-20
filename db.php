<?php
/**
 * Jednoduchý PDO singleton. Číta prístupové údaje z config.local.php
 * (lokálne SQLite pri vývoji, MySQL na produkcii — pozri config.sample.php).
 */

require_once __DIR__ . '/config.local.php';

// Na produkcii (MySQL) nikdy nezobrazovať chyby priamo v odpovedi — môžu
// prezradiť cesty na serveri alebo obsah dát. Lokálne (SQLite) ostávajú
// zapnuté, nech je vývoj pohodlný. Platí pre každú stránku, čo includne
// db.php (čiže prakticky celú appku).
if (!str_starts_with(DB_DSN, 'sqlite:')) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    // Chyby sa doteraz nikam neukladali (len potlačili) — bez logu nebolo
    // ako zistiť, že niečo zlyhalo, kým si nesťažil poradca. tmp/ je jediný
    // priečinok mimo git deploy synchronizácie (viď .github/workflows/deploy.yml
    // --exclude ^tmp/) a zároveň zablokovaný pre priamy HTTP prístup (.htaccess),
    // takže log tu prežije medzi nasadeniami a nie je verejne čitateľný.
    $errLogDir = __DIR__ . '/tmp';
    if (!is_dir($errLogDir)) { @mkdir($errLogDir, 0755, true); }
    ini_set('log_errors', '1');
    ini_set('error_log', $errLogDir . '/php-error.log');
}

/** HTML-escapovanie — spoločné pre všetky stránky (bývalo duplikované v 14 súboroch). */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Iniciály poradcu pre okrúhle avatary (bývalo duplikované v 7 súboroch). */
function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/**
 * URL statického súboru z /assets s automatickým cache-busting parametrom
 * podľa času poslednej zmeny súboru (namiesto ručne udržiavaného ?v=NN,
 * ktoré sa v praxi rozchádzalo medzi stránkami — napr. budgetove-zlavy/
 * dlho ťahalo starú verziu ui.css, kým si to niekto nevšimol).
 */
function asset(string $relPath): string {
    $file = __DIR__ . '/assets/' . $relPath;
    $v = @filemtime($file) ?: '1';
    return '/assets/' . $relPath . '?v=' . $v;
}

/**
 * Podpísaná hodnota cur_advisor cookie ("id.podpis") — HMAC kľúčom GATE_TOKEN.
 * Bráni tomu, aby si poradca len zmenou cookie v prehliadači vydával za iného
 * poradcu (napr. mazal jeho dokumenty). Nevyžaduje žiadne dodatočné heslo.
 */
function signAdvisorId(int $id): string {
    return $id . '.' . substr(hash_hmac('sha256', (string)$id, GATE_TOKEN), 0, 20);
}

function curAdvisorId(): int {
    $raw = $_COOKIE['cur_advisor'] ?? '';
    if (!is_string($raw) || !str_contains($raw, '.')) return 0;
    [$id, $sig] = explode('.', $raw, 2);
    if (!ctype_digit($id)) return 0;
    $expected = substr(hash_hmac('sha256', $id, GATE_TOKEN), 0, 20);
    return hash_equals($expected, $sig) ? (int)$id : 0;
}

/**
 * Bezstavový CSRF token (HMAC z GATE_TOKEN + aktuálneho poradcu) — zámerne
 * bez PHP session (appka doteraz žiadnu nepoužíva, netreba ju kvôli tomuto
 * zavádzať). Útočník na cudzej stránke token nevie vypočítať bez GATE_TOKEN,
 * aj keby vedel prinútiť prihlásený prehliadač odoslať POST na admin.php.
 * Použitie: skrytý input vo formulári + csrfCheck() na začiatku POST vetvy.
 */
function csrfToken(): string {
    return hash_hmac('sha256', 'csrf:' . curAdvisorId(), GATE_TOKEN);
}
function csrfCheck(): bool {
    return hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''));
}

/**
 * Ľudsky čitateľný názov nástroja podľa slugu priečinka — používa sa v tabuľkách
 * histórie dokumentov (admin.php, moje-dokumenty.php), kde sa inak zobrazoval
 * surový slug ako "nahrada-skody-zodpovednost".
 */
function toolLabel(string $slug): string {
    static $labels = [
        'wizard-poistenie' => 'Aké poistenie potrebujem',
        'financna-medzera' => 'Kalkulačka poistného krytia',
        'financna-analyza' => 'Finančná analýza (staršia verzia)',
        'checklist-analyza' => 'Checklist – výstup z analýzy',
        'splnomocnenie' => 'Všeobecné splnomocnenie',
        'vypoved-poistenia' => 'Výpoveď poistnej zmluvy',
        'preberaci-protokol' => 'Preberací protokol',
        'univerzalna-ziadost-zmena' => 'Univerzálna žiadosť o zmenu',
        'ziadost-krycie-list' => 'Žiadosť o vystavenie krycieho listu',
        'nahrada-skody-zodpovednost' => 'Žiadosť o náhradu škody',
        'cestne-vyhlasenie-inej-poistky' => 'Čestné prehlásenie',
        'cestne-vyhlasenie-kupa-veci' => 'Čestné prehlásenie o kúpe veci',
        'suhlas-vyplata-inemu-uctu' => 'Súhlas s výplatou na iný účet',
        'ziadost-vratenie-preplatku' => 'Vrátenie preplatku',
        'odvolanie-zamietnutie-plnenia' => 'Odvolanie voči likvidácii',
        'reklamacia-postup-institucie' => 'Reklamácia / sťažnosť',
        'ziadost-vyplata-poistneho-plnenia' => 'Výplata poistného plnenia',
        'financna-karticka-prvej-pomoci' => 'Prehľad poistiek klienta',
        'checklisty-skody' => 'Checklisty podľa typu škody',
        'generator-recenzii' => 'Generátor 5-hviezdičkovej prosby',
        'warm-intro-whatsapp' => 'Warm-Intro WhatsApp generátor',
        'karticka-odporucte-ma' => 'Kartička „Odporučte ma priateľom“',
        'vysvetlivky-pre-klienta' => 'Vysvetlivky pre klienta',
        'tahak-co-pytat-od-klienta' => 'Ťahák „Čo pýtať od klienta“',
        'pyramida-istoty' => 'Interaktívna Pyramída istoty',
        'latte-faktor' => 'Latte Faktor',
        'simulator-dvoch-extremov' => 'Simulátor dvoch extrémov',
        'argument-builder' => 'Argument Builder',
        'vybavovac-namietok' => 'Vybavovač námietok',
        'prvych-30-dni' => 'Prvých 30 dní',
        'emailove-pozdravy' => 'Emailové pozdravy',
        'sprievodca-udalosti' => 'Sprievodca podľa životnej udalosti',
        'oplati-sa-refinancovat' => 'Oplatí sa mi refinancovať?',
        'poistny-semafor' => 'Poistný semafor',
        'simulator-kratenia-plnenia' => 'Simulátor krátenia plnenia',
        'poistenie-uveru-banka-vs-uniqa' => 'Poistenie úveru: banka vs. UNIQA',
        'obchadzac-notara' => 'Obchádzač notára',
        'financny-rontgen' => 'Finančný röntgen (Pred/Po)',
        'zmena-spravcu-zmluvy' => 'Zmena správcu zmluvy',
        'rocny-servisny-plan' => 'Ročný servisný plán',
    ];
    return $labels[$slug] ?? $slug;
}

/**
 * Centrálne priehradenie brute-force pokusov o PIN — hlavná brána (scope
 * 'gate') aj PIN jednotlivých poradcov (scope 'advisor:<id>'). Zámerne bez
 * väzby na IP: pri malom internom tíme stačí, že po sérii chybných pokusov
 * sa daný vstup na pár minút zamkne pre kohokoľvek (4-miestny PIN má len
 * 10 000 kombinácií, bez priehradenia by bol behom sekúnd uhádnuteľný).
 * Throttling nesmie appku nikdy zhodiť — pri nedostupnej DB sa ticho vynechá.
 */
function throttleSecondsLeft(string $scope): int {
    try {
        $stmt = db()->prepare('SELECT locked_until FROM formulare_login_throttle WHERE scope = ?');
        $stmt->execute([$scope]);
        $row = $stmt->fetch();
        if (!$row || !$row['locked_until']) return 0;
        $left = strtotime($row['locked_until']) - time();
        return $left > 0 ? $left : 0;
    } catch (Throwable $e) { return 0; }
}

function throttleRecordFailure(string $scope, int $maxAttempts = 8, int $lockSeconds = 300): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT fail_count FROM formulare_login_throttle WHERE scope = ?');
        $stmt->execute([$scope]);
        $count = (int)($stmt->fetch()['fail_count'] ?? 0) + 1;
        if ($count >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockSeconds);
            $pdo->prepare('REPLACE INTO formulare_login_throttle (scope, fail_count, locked_until) VALUES (?, 0, ?)')
                ->execute([$scope, $lockedUntil]);
        } else {
            $pdo->prepare('REPLACE INTO formulare_login_throttle (scope, fail_count, locked_until) VALUES (?, ?, NULL)')
                ->execute([$scope, $count]);
        }
    } catch (Throwable $e) { /* throttling nie je kritické pre chod appky */ }
}

function throttleReset(string $scope): void {
    try {
        db()->prepare('DELETE FROM formulare_login_throttle WHERE scope = ?')->execute([$scope]);
    } catch (Throwable $e) { /* ticho ignoruj */ }
}

/**
 * Rozloží vnorenú "licenses" štruktúru NBS registra na ploché polia pre
 * rýchle filtrovanie/hľadanie (uložené ako JSON v samostatných stĺpcoch):
 *   - categories    = scope na najvyššej úrovni (napr. "viazaný finančný agent")
 *   - sectors       = scope vo vnorených licenciách (napr. "sektor poistenia alebo zaistenia")
 *   - parent_names  = parent_entity_name kdekoľvek vo vnorení — pre koho je
 *                     daný viazaný agent registrovaný (kľúčové pre nábor).
 * Použité pri importe (nabor-import.php) aj v nabor.php pri zobrazovaní.
 */
function registryDeriveFlags(array $licenses): array {
    $categories = [];
    $sectors = [];
    $parentNames = [];

    $walkNested = function (array $items) use (&$walkNested, &$sectors, &$parentNames) {
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            if (!empty($item['scope'])) $sectors[] = $item['scope'];
            if (!empty($item['parent_entity_name'])) $parentNames[] = $item['parent_entity_name'];
            if (!empty($item['licenses']) && is_array($item['licenses'])) $walkNested($item['licenses']);
        }
    };

    foreach ($licenses as $item) {
        if (!is_array($item)) continue;
        if (!empty($item['scope'])) $categories[] = $item['scope'];
        if (!empty($item['parent_entity_name'])) $parentNames[] = $item['parent_entity_name'];
        if (!empty($item['licenses']) && is_array($item['licenses'])) $walkNested($item['licenses']);
    }

    return [
        'categories' => array_values(array_unique($categories)),
        'sectors' => array_values(array_unique($sectors)),
        'parent_names' => array_values(array_unique($parentNames)),
    ];
}

/** Rozdelí adresu tvaru "Ulica 4, 81102 Bratislava" na mesto a PSČ (SK formát). */
function registryParseAddress(string $address): array {
    $city = ''; $zip = '';
    if (preg_match('/(\d{3}\s?\d{2})\s+(.+)$/u', $address, $m)) {
        $zip = trim($m[1]);
        $city = trim($m[2]);
    }
    return [$city, $zip];
}

const REGISTRY_DATA_FILE = __DIR__ . '/data/nbs-register.json';
const REGISTRY_FACETS_FILE = __DIR__ . '/data/facets.json';

// Náborová zóna má zmysel len pre jednotlivých agentov (ľudí, ktorých má kto
// "prebrať"), nie inštitúcie ako banky/poisťovne/sporiteľne — preto je rozsah
// natrvalo obmedzený len na tieto dve kategórie (rozhodnutie používateľa).
// Zdieľané medzi nabor.php, api/nabor-markers.php a nabor-geocode.php.
const AGENT_CATEGORIES = ['viazaný finančný agent', 'podriadený finančný agent'];

// Náborová zóna (aj presné platené geokódovanie) sa obmedzuje len na tieto
// kraje — používateľ reálne pôsobí len tu. Používa sa jednak na filtrovanie
// zobrazených/importovaných záznamov (nabor.php, api/nabor-markers.php),
// jednak na obmedzenie rozsahu platenej presnej geokódovacej služby.
const NABOR_ACTIVE_REGIONS = ['Prešovský kraj', 'Košický kraj'];

/**
 * Vzdušná vzdialenosť dvoch bodov v km (Haversine) — používa sa na
 * odhalenie zjavne zlých výsledkov presného geokódovania (viď GEOCODE_OUTLIER_KM).
 */
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Maximálna prijateľná vzdialenosť medzi presnou polohou z LocationIQ a
 * približným stredom obce podľa PSČ (coordsForZip) — nad tento limit sa
 * presný výsledok považuje za pravdepodobne zlý (LocationIQ si pri
 * nejednoznačnej/neúplnej adrese niekedy vyberie zhodnú ulicu v úplne inom
 * meste, typicky Bratislavu) a použije sa radšej overený približný bod.
 * 60 km s rezervou pokrýva aj nepresnosť trojmiestnej PSČ predpony (priemer
 * cez celý okres), ale spoľahlivo odchytí medzikrajský skok.
 */
const GEOCODE_OUTLIER_KM = 60.0;

/**
 * Vráti kraj SR podľa 5-miestneho PSČ, alebo null ak sa nenašlo (napr.
 * neplatné/chýbajúce PSČ). Tabuľka psc-kraj.php je generovaná z overených
 * dát (obce/okresy/kraje SR) — nikdy neupravuj ručne, pozri hlavičku súboru.
 */
function regionForZip(string $zip): ?string {
    static $table = null;
    if ($table === null) $table = require __DIR__ . '/psc-kraj.php';
    $zip5 = preg_replace('/\D/', '', $zip);
    if (isset($table['exact'][$zip5])) return $table['exact'][$zip5];
    return $table['prefix3'][substr($zip5, 0, 3)] ?? null;
}

/**
 * Vráti okres SR podľa 5-miestneho PSČ, alebo null ak sa nenašlo. Tabuľka
 * psc-okres.php je generovaná z rovnakého overeného datasetu ako
 * psc-kraj.php (obce/okresy/kraje SR) — nikdy neupravuj ručne, pozri
 * hlavičku súboru.
 */
function okresForZip(string $zip): ?string {
    static $table = null;
    if ($table === null) $table = require __DIR__ . '/psc-okres.php';
    $zip5 = preg_replace('/\D/', '', $zip);
    if (isset($table['exact'][$zip5])) return $table['exact'][$zip5];
    return $table['prefix3'][substr($zip5, 0, 3)] ?? null;
}

/**
 * Vráti [lat, lon] (stred obce, nie presná adresa) podľa 5-miestneho PSČ,
 * alebo null. Tabuľka psc-suradnice.php — pozri hlavičku súboru, nemeniť ručne.
 */
function coordsForZip(string $zip): ?array {
    static $table = null;
    if ($table === null) $table = require __DIR__ . '/psc-suradnice.php';
    $zip5 = preg_replace('/\D/', '', $zip);
    if (isset($table['exact'][$zip5])) return $table['exact'][$zip5];
    return $table['prefix3'][substr($zip5, 0, 3)] ?? null;
}

/** Kľúč do formulare_geocode_cache — normalizovaná adresa (bez ohľadu na veľkosť písmen/medzery navyše). */
function geocodeCacheKey(string $address): string {
    return md5(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $address))));
}

/**
 * Načíta data/nbs-register.json a naplní ním formulare_registry_entities
 * (plný refresh — zmaže staré a vloží nanovo, aby dáta vždy presne
 * zodpovedali poslednému nahratému súboru). Vracia počet a dátum datasetu.
 * Volané z nabor.php (owner-only tlačidlo) aj priamo pri lokálnom testovaní.
 *
 * Pre agentov (AGENT_CATEGORIES) sa navyše skúša presnejšia poloha z trvalej
 * cache formulare_geocode_cache (geokódovaná podľa celej adresy, nie len PSČ)
 * — tá prežíva reimport, takže sa nabudúce negeokóduje odznova. Adresy, čo
 * ešte nie sú v cache, sa tam zaradia ako 'pending' pre nabor-geocode.php.
 */
function registryImport(string $filePath, string $facetsFile): array {
    if (!is_file($filePath)) {
        throw new RuntimeException('Súbor sa nenašiel (' . basename($filePath) . ') — nahraj ho cez FTP do priečinka data/.');
    }
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    $raw = file_get_contents($filePath);
    if ($raw === false) throw new RuntimeException('Súbor sa nepodarilo prečítať.');

    $data = json_decode($raw, true);
    unset($raw);
    if (!is_array($data) || !isset($data['institutions']) || !is_array($data['institutions'])) {
        throw new RuntimeException('Neočakávaný formát JSON — chýba pole "institutions".');
    }

    $pdo = db();

    // Prednačítať, kedy bolo ktoré IČO prvýkrát videné (first_seen_at) — aby
    // reimport nezresetoval "nové od posledného importu" pre záznamy, ktoré
    // už existovali predtým. Nové IČO (nie je v tejto mape) dostane first_seen_at
    // rovný $now, čím sa odlíši od starších záznamov (viď nižšie pri INSERTe).
    $firstSeenCache = [];
    try {
        foreach ($pdo->query('SELECT ico, first_seen_at FROM formulare_registry_entities') as $r) {
            $firstSeenCache[$r['ico']] = $r['first_seen_at'];
        }
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať (pred migráciou) */ }

    // Prednačítať celú cache presného geokódovania do pamäte (max pár desiatok
    // tisíc riadkov, malé) — rýchlejšie než dotaz na DB pre každý riadok importu.
    $geocodeCache = [];
    try {
        foreach ($pdo->query('SELECT address_hash, lat, lon, status FROM formulare_geocode_cache') as $g) {
            $geocodeCache[$g['address_hash']] = $g;
        }
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať (pred migráciou) */ }
    $enqueue = $pdo->prepare('INSERT INTO formulare_geocode_cache (address_hash, address, status) VALUES (?, ?, \'pending\')');
    $enqueuedHashes = [];
    // Hashe adries, ktoré reálne patria do rozsahu presného geokódovania
    // (NABOR_ACTIVE_REGIONS) — po importe sa všetko mimo tejto množiny
    // v cache, čo ešte čaká na spracovanie ('pending'), vymaže (viď nižšie).
    $wantedHashes = [];

    $pdo->exec('DELETE FROM formulare_registry_entities');
    $insert = $pdo->prepare('INSERT INTO formulare_registry_entities
        (ico, name, address, city, zip, country, categories, sectors, parent_names, region, okres, lat, lon, geocoded_at, raw_json, imported_at, first_seen_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $allCategories = [];
    $allSectors = [];
    $allParents = [];
    $allRegions = [];
    $allOkres = [];
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    $count = 0;
    foreach ($data['institutions'] as $inst) {
        if (!is_array($inst) || empty($inst['id'])) continue;
        $licenses = is_array($inst['licenses'] ?? null) ? $inst['licenses'] : [];
        $flags = registryDeriveFlags($licenses);
        $address = (string)($inst['address'] ?? '');
        [$city, $zip] = registryParseAddress($address);
        $region = $zip !== '' ? regionForZip($zip) : null;
        $okres = $zip !== '' ? okresForZip($zip) : null;
        $coords = $zip !== '' ? coordsForZip($zip) : null;
        $geocodedAt = $coords ? $now : null;

        $ico = (string)$inst['id'];
        $firstSeenAt = $firstSeenCache[$ico] ?? $now;

        $isAgent = (bool)array_intersect($flags['categories'], AGENT_CATEGORIES);
        $inPreciseScope = $region !== null && in_array($region, NABOR_ACTIVE_REGIONS, true);
        if ($isAgent && $address !== '' && $inPreciseScope) {
            $hash = geocodeCacheKey($address);
            $wantedHashes[$hash] = true;
            $cached = $geocodeCache[$hash] ?? null;
            if ($cached && $cached['status'] === 'found' && $cached['lat'] !== null) {
                $coords = [(float)$cached['lat'], (float)$cached['lon']];
                $geocodedAt = $cached['updated_at'] ?? $now;
            } elseif (!$cached && !isset($enqueuedHashes[$hash])) {
                $enqueue->execute([$hash, mb_substr($address, 0, 255)]);
                $enqueuedHashes[$hash] = true;
            }
        }

        $insert->execute([
            $ico,
            (string)($inst['name'] ?? ''),
            $address,
            $city,
            $zip,
            (string)($inst['country'] ?? 'SK'),
            json_encode($flags['categories'], JSON_UNESCAPED_UNICODE),
            json_encode($flags['sectors'], JSON_UNESCAPED_UNICODE),
            json_encode($flags['parent_names'], JSON_UNESCAPED_UNICODE),
            $region,
            $okres,
            $coords[0] ?? null,
            $coords[1] ?? null,
            $geocodedAt,
            json_encode($licenses, JSON_UNESCAPED_UNICODE),
            $now,
            $firstSeenAt,
        ]);
        $count++;

        foreach ($flags['categories'] as $c) $allCategories[$c] = true;
        foreach ($flags['sectors'] as $s) $allSectors[$s] = true;
        foreach ($flags['parent_names'] as $p) $allParents[$p] = true;
        if ($region) $allRegions[$region] = true;
        if ($okres && $inPreciseScope) $allOkres[$okres] = true;

        if ($count % 500 === 0) { $pdo->commit(); $pdo->beginTransaction(); }
    }
    $pdo->commit();

    // Vyčistiť frontu presného geokódovania od adries mimo NABOR_ACTIVE_REGIONS
    // (napr. zostatok z čias, keď sa geokódovalo celé Slovensko) — nemá zmysel
    // za ne platiť. Netýka sa už hotových 'found'/'not_found' záznamov.
    $staleHashes = [];
    foreach ($geocodeCache as $hash => $g) {
        if ($g['status'] === 'pending' && !isset($wantedHashes[$hash])) $staleHashes[] = $hash;
    }
    if ($staleHashes) {
        foreach (array_chunk($staleHashes, 400) as $chunk) {
            $pdo->prepare('DELETE FROM formulare_geocode_cache WHERE status = \'pending\' AND address_hash IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')')
                ->execute($chunk);
        }
    }

    $categories = array_keys($allCategories); sort($categories);
    $sectors = array_keys($allSectors); sort($sectors);
    $parents = array_keys($allParents); sort($parents);
    $regions = array_keys($allRegions); sort($regions);
    $okresy = array_keys($allOkres); sort($okresy);
    file_put_contents($facetsFile, json_encode([
        'categories' => $categories,
        'sectors' => $sectors,
        'parent_names' => $parents,
        'regions' => $regions,
        'okresy' => $okresy,
        'dataset_updated' => $data['updated'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    return ['count' => $count, 'updated' => $data['updated'] ?? null];
}

/**
 * Zavolá LocationIQ (platená služba, Nominatim-kompatibilné API) pre presné
 * súradnice jednej adresy. Vyžaduje LOCATIONIQ_TOKEN v config.local.php.
 * Vráti ['status' => 'found'|'not_found'|'retry', 'coords' => [lat,lon]|null].
 * 'retry' = dočasné zlyhanie (napr. HTTP 429 rate-limit, sieťová chyba) —
 * adresa sa NESMIE označiť ako 'not_found', musí ostať 'pending' na ďalší pokus.
 * $error (voliteľné) sa naplní diagnostickou správou pri 'retry'/'not_found'.
 */
function geocodeAddress(string $address, ?string &$error = null): array {
    if (!defined('LOCATIONIQ_TOKEN') || LOCATIONIQ_TOKEN === '') {
        $error = 'Chýba LOCATIONIQ_TOKEN v konfigurácii.';
        return ['status' => 'retry', 'coords' => null];
    }
    $url = 'https://us1.locationiq.com/v1/search?' . http_build_query([
        'key' => LOCATIONIQ_TOKEN,
        'format' => 'json',
        'q' => $address,
        'countrycodes' => 'sk',
        'limit' => 1,
    ]);
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    error_clear_last();
    $resp = @file_get_contents($url, false, $ctx);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }

    if ($resp === false) {
        $last = error_get_last();
        $error = 'Spojenie zlyhalo: ' . ($last['message'] ?? 'neznáma chyba');
        return ['status' => 'retry', 'coords' => null];
    }
    if ($status === 429 || $status >= 500) {
        $error = "LocationIQ vrátil HTTP $status (dočasné, skúsi sa znova neskôr). Odpoveď: " . mb_substr($resp, 0, 200);
        return ['status' => 'retry', 'coords' => null];
    }
    $data = json_decode($resp, true);
    if (!is_array($data)) {
        // LocationIQ vracia pri "nič nenájdené" JSON objekt s "error" kľúčom,
        // nie prázdne pole — to je legitímny not_found, nie chyba parsovania.
        if ($status === 404) {
            $error = 'LocationIQ nenašiel žiadny výsledok pre túto adresu.';
            return ['status' => 'not_found', 'coords' => null];
        }
        $error = 'Neočakávaná odpoveď (nie JSON), HTTP ' . $status . ': ' . mb_substr($resp, 0, 200);
        return ['status' => 'retry', 'coords' => null];
    }
    if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
        $error = 'LocationIQ nenašiel žiadny výsledok pre túto adresu.';
        return ['status' => 'not_found', 'coords' => null];
    }
    return ['status' => 'found', 'coords' => [(float)$data[0]['lat'], (float)$data[0]['lon']]];
}

/**
 * Spracuje jednu dávku čakajúcich adries z formulare_geocode_cache cez
 * LocationIQ (voľný plán 2 dotazy/s). Zapíše výsledok do cache (prežije
 * reimport) AJ priamo do formulare_registry_entities (nech sa to na mape
 * prejaví hneď, bez čakania na ďalší import).
 * Pri rate-limite (HTTP 429) sa dávka predčasne ukončí — nemá zmysel
 * pokračovať, kým LocationIQ odmieta dotazy.
 * Volané z nabor-geocode.php (cron cez Plánovač úloh alebo ručne z nabor.php).
 */
function geocodeBatchProcess(int $limit = 35): array {
    set_time_limit(max(120, $limit * 2));
    $pdo = db();
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->query("SELECT address_hash, address FROM formulare_geocode_cache WHERE status = 'pending' LIMIT $limit");
    $rows = $stmt->fetchAll();

    $updateCache = $pdo->prepare('UPDATE formulare_geocode_cache SET lat = ?, lon = ?, status = ?, updated_at = ? WHERE address_hash = ?');
    $updateRegistry = $pdo->prepare('UPDATE formulare_registry_entities SET lat = ?, lon = ?, geocoded_at = ? WHERE address = ?');

    $found = 0; $notFound = 0; $retried = 0; $firstError = null;
    foreach ($rows as $i => $row) {
        $error = null;
        $result = geocodeAddress($row['address'], $error);
        $now = date('Y-m-d H:i:s');
        if ($result['status'] === 'found') {
            [$lat, $lon] = $result['coords'];
            // Poistka: ak LocationIQ vráti bod ďaleko od približného stredu obce
            // podľa PSČ (typicky si pri nejednoznačnej adrese vyberie zhodnú
            // ulicu v inom meste), radšej použiť overený približný bod.
            [, $zip] = registryParseAddress($row['address']);
            $approx = $zip !== '' ? coordsForZip($zip) : null;
            if ($approx && haversineKm($lat, $lon, $approx[0], $approx[1]) > GEOCODE_OUTLIER_KM) {
                [$lat, $lon] = $approx;
            }
            $updateCache->execute([$lat, $lon, 'found', $now, $row['address_hash']]);
            $updateRegistry->execute([$lat, $lon, $now, $row['address']]);
            $found++;
        } elseif ($result['status'] === 'not_found') {
            $updateCache->execute([null, null, 'not_found', $now, $row['address_hash']]);
            $notFound++;
        } else { // 'retry' — necháva sa ako 'pending', skúsi sa nabudúce
            $retried++;
            if ($firstError === null) $firstError = $error;
            break; // rate-limit/sieťová chyba — ďalšie pokusy v tejto dávke by dopadli rovnako
        }
        if ($i < count($rows) - 1) usleep(550000); // 0.55s medzera medzi dotazmi (LocationIQ voľný plán 2/s)
    }

    $remaining = (int)$pdo->query("SELECT COUNT(*) c FROM formulare_geocode_cache WHERE status = 'pending'")->fetch()['c'];
    return ['processed' => $found + $notFound + $retried, 'found' => $found, 'not_found' => $notFound, 'retried' => $retried, 'remaining' => $remaining, 'first_error' => $firstError];
}

/**
 * Jednorazová oprava už uložených presných súradníc, ktoré prešli do appky
 * PRED zavedením poistky v geocodeBatchProcess() (viď GEOCODE_OUTLIER_KM) —
 * napr. body zjavne v Bratislave/Žiline namiesto Prešovského/Košického kraja.
 * Prejde celú formulare_geocode_cache so status='found', porovná uloženú
 * polohu s približným stredom obce podľa PSČ a odľahlé prepíše približným
 * bodom (v cache aj v formulare_registry_entities). Bezpečné spúšťať opakovane.
 */
function geocodeFixOutliers(): array {
    $pdo = db();
    $rows = $pdo->query("SELECT address_hash, address, lat, lon FROM formulare_geocode_cache WHERE status = 'found' AND lat IS NOT NULL")->fetchAll();
    $updateCache = $pdo->prepare('UPDATE formulare_geocode_cache SET lat = ?, lon = ?, updated_at = ? WHERE address_hash = ?');
    $updateRegistry = $pdo->prepare('UPDATE formulare_registry_entities SET lat = ?, lon = ?, geocoded_at = ? WHERE address = ?');
    $now = date('Y-m-d H:i:s');
    $checked = 0; $fixed = 0;
    foreach ($rows as $row) {
        [, $zip] = registryParseAddress($row['address']);
        if ($zip === '') continue;
        $approx = coordsForZip($zip);
        if (!$approx) continue;
        $checked++;
        if (haversineKm((float)$row['lat'], (float)$row['lon'], $approx[0], $approx[1]) > GEOCODE_OUTLIER_KM) {
            $updateCache->execute([$approx[0], $approx[1], $now, $row['address_hash']]);
            $updateRegistry->execute([$approx[0], $approx[1], $now, $row['address']]);
            $fixed++;
        }
    }
    return ['checked' => $checked, 'fixed' => $fixed];
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if (str_starts_with(DB_DSN, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
            dbInitSqlite($pdo);
        }
    }
    return $pdo;
}

/**
 * Vytvorí schému v lokálnej SQLite databáze pri prvom pripojení — LEN pre
 * lokálne testovanie. Produkcia (MySQL) sa nastavuje ručne cez sql/001_init.sql,
 * nikdy automaticky z webu.
 */
function dbInitSqlite(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_advisors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        org TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT '',
        color TEXT NOT NULL DEFAULT '#1f5fd1',
        pin_hash TEXT NULL,
        disabled_tools TEXT NULL,
        favorite_tools TEXT NULL,
        is_admin INTEGER NOT NULL DEFAULT 0,
        is_owner INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    // Defenzívne pre už existujúce lokálne SQLite DB založené pred zavedením PIN-u / prepínačov nástrojov / náborovej zóny.
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN pin_hash TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN disabled_tools TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN favorite_tools TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN is_owner INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN onboarding_started_at TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN onboarding_start_date TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN onboarding_completed_at TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN sfa_acquisition_no TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN sfa_personal_no TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN nbs_registration_no TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_login_throttle (
        scope TEXT PRIMARY KEY,
        fail_count INTEGER NOT NULL DEFAULT 0,
        locked_until TEXT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_client_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL UNIQUE,
        advisor_id INTEGER NOT NULL,
        tool TEXT NOT NULL,
        client_label TEXT NOT NULL,
        form_data TEXT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        submitted_at TEXT NULL,
        claimed_at TEXT NULL,
        expires_at TEXT NULL,
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_generated_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        advisor_id INTEGER NOT NULL,
        client_link_id INTEGER NULL,
        source TEXT NOT NULL DEFAULT 'advisor',
        tool TEXT NOT NULL,
        client_label TEXT NOT NULL,
        form_data TEXT NOT NULL,
        is_draft INTEGER NOT NULL DEFAULT 0,
        generated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id),
        FOREIGN KEY (client_link_id) REFERENCES formulare_client_links(id)
    )");
    try { $pdo->exec("ALTER TABLE formulare_generated_documents ADD COLUMN is_draft INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_gendocs_advisor_date ON formulare_generated_documents (advisor_id, generated_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clientlinks_advisor_date ON formulare_client_links (advisor_id, created_at)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_registry_entities (
        ico TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        address TEXT NOT NULL DEFAULT '',
        city TEXT NOT NULL DEFAULT '',
        zip TEXT NOT NULL DEFAULT '',
        country TEXT NOT NULL DEFAULT 'SK',
        categories TEXT NULL,
        sectors TEXT NULL,
        parent_names TEXT NULL,
        region TEXT NULL,
        okres TEXT NULL,
        raw_json TEXT NOT NULL,
        lat REAL NULL,
        lon REAL NULL,
        geocoded_at TEXT NULL,
        imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        first_seen_at TEXT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registry_name ON formulare_registry_entities(name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registry_region ON formulare_registry_entities(region)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_registry_okres ON formulare_registry_entities(okres)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_geocode_cache (
        address_hash TEXT PRIMARY KEY,
        address TEXT NOT NULL,
        lat REAL NULL,
        lon REAL NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_geocode_status ON formulare_geocode_cache(status)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_knowledge_base (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        advisor_id INTEGER NOT NULL,
        advisor_name TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_news (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        important INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_refi_rates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bank TEXT NOT NULL,
        fixation TEXT NOT NULL,
        rate REAL NOT NULL,
        note TEXT NOT NULL DEFAULT '',
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_snippets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        advisor_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_fa_clients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        advisor_id INTEGER NOT NULL,
        client_id TEXT NOT NULL,
        case_name TEXT NOT NULL DEFAULT '',
        a_name TEXT NOT NULL DEFAULT '',
        b_name TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT '',
        data TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (advisor_id, client_id),
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_fa_clients_advisor ON formulare_fa_clients (advisor_id, updated_at)");
    // Cesta nováčika — koncept "Mapa cesty a odmeny": fázy sú samostatné
    // entity s dĺžkou trvania (dni), postup je automatický podľa uplynutého
    // času od formulare_advisors.onboarding_started_at (žiadne ručné
    // odškrtávanie jednotlivých krokov). formulare_onboarding_steps teraz
    // slúži ako zoznam referenčných materiálov (odkazy/popisy) per fáza.
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_onboarding_phases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        icon TEXT NOT NULL DEFAULT '📍',
        sort_order INTEGER NOT NULL DEFAULT 0,
        duration_days INTEGER NOT NULL DEFAULT 30,
        duration_months INTEGER NOT NULL DEFAULT 1,
        is_ongoing INTEGER NOT NULL DEFAULT 0,
        reward_text TEXT NOT NULL DEFAULT '',
        support_text TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE formulare_onboarding_phases ADD COLUMN duration_months INTEGER NOT NULL DEFAULT 1"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    $pdo->exec("UPDATE formulare_onboarding_phases SET duration_months = CASE WHEN is_ongoing = 1 THEN 0 ELSE MAX(1, ROUND(duration_days / 30.0)) END WHERE duration_months = 1 AND duration_days != 30");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_onboarding_steps (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phase TEXT NOT NULL,
        phase_id INTEGER NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        link_url TEXT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE formulare_onboarding_steps ADD COLUMN phase_id INTEGER NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    // Osobné odškrtávanie materiálov nováčikom (nie pre kontrolu ownerom —
    // nikde sa nezobrazuje súhrnne, len ako vlastný checklist poradcu).
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_onboarding_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        advisor_id INTEGER NOT NULL,
        step_id INTEGER NOT NULL,
        done_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(advisor_id, step_id),
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id),
        FOREIGN KEY (step_id) REFERENCES formulare_onboarding_steps(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_team_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_date TEXT NOT NULL,
        end_date TEXT NULL,
        title TEXT NOT NULL,
        note TEXT NOT NULL DEFAULT '',
        assigned_advisor_id INTEGER NULL,
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES formulare_advisors(id),
        FOREIGN KEY (assigned_advisor_id) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_team_events_date ON formulare_team_events(event_date)");
    try { $pdo->exec("ALTER TABLE formulare_team_events ADD COLUMN assigned_advisor_id INTEGER NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_team_events ADD COLUMN end_date TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    $pdo->exec("UPDATE formulare_team_events SET end_date = event_date WHERE end_date IS NULL");
    // Viacnásobné priradenie udalosti (2-3 kolegom naraz) — nahrádza pôvodný
    // jediný assigned_advisor_id stĺpec, ktorý ostáva v DB len kvôli starým
    // riadkom (nečíta sa už nikde v kóde). Prázdna množina priradení = celý tím.
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_team_event_assignees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL,
        advisor_id INTEGER NOT NULL,
        UNIQUE(event_id, advisor_id),
        FOREIGN KEY (event_id) REFERENCES formulare_team_events(id),
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_team_event_assignees_event ON formulare_team_event_assignees(event_id)");
    try {
        $pdo->exec("INSERT OR IGNORE INTO formulare_team_event_assignees (event_id, advisor_id)
                     SELECT id, assigned_advisor_id FROM formulare_team_events WHERE assigned_advisor_id IS NOT NULL");
    } catch (Throwable $e) { /* jednorazový presun starých dát, bezpečné spustiť opakovane (OR IGNORE) */ }

    // Predvolené fázy + materiály pre Cestu nováčika — len ak sú tabuľky
    // ešte prázdne (aby sa neduplikovali pri každom reštarte lokálneho
    // servera). Owner si ich kedykoľvek upraví/zmaže/pridá vlastné cez
    // samotnú stránku.
    $phaseCount = (int)$pdo->query('SELECT COUNT(*) FROM formulare_onboarding_phases')->fetchColumn();
    if ($phaseCount === 0) {
        $seedPhases = dbOnboardingSeedPhases();
        $insP = $pdo->prepare('INSERT INTO formulare_onboarding_phases (name, icon, sort_order, duration_days, duration_months, is_ongoing, reward_text, support_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($seedPhases as $i => $p) {
            $insP->execute([$p['name'], $p['icon'], $i, $p['duration_days'], $p['duration_months'], $p['is_ongoing'] ? 1 : 0, $p['reward_text'], $p['support_text']]);
        }
    }
    $stepCount = (int)$pdo->query('SELECT COUNT(*) FROM formulare_onboarding_steps')->fetchColumn();
    if ($stepCount === 0) {
        $phaseIdByName = [];
        foreach ($pdo->query('SELECT id, name FROM formulare_onboarding_phases') as $row) { $phaseIdByName[$row['name']] = $row['id']; }
        $seedSteps = dbOnboardingSeedSteps();
        $ins = $pdo->prepare('INSERT INTO formulare_onboarding_steps (phase, phase_id, title, description, link_url, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($seedSteps as $i => $s) {
            $ins->execute([$s['phase'], $phaseIdByName[$s['phase']] ?? null, $s['title'], $s['description'], $s['link_url'], $i]);
        }
    }

    // Náborová zóna — vlastná evidencia oslovených kandidátov (nezávislá od
    // registra NBS/mapy — kandidát nemusí byť vôbec v tom datasete). Len owner.
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_recruit_candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        initiator TEXT NOT NULL DEFAULT 'ja',
        status TEXT NOT NULL DEFAULT 'novy',
        note TEXT NOT NULL DEFAULT '',
        contact_date TEXT NULL,
        created_by INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_recruit_candidates_status ON formulare_recruit_candidates(status)");

    // Budgetové zľavy — pravidlá editovateľné ownerom priamo v appke (viď
    // sql/026_budget_rules.sql pre produkčný náprotivok tejto migrácie).
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_budget_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        title TEXT NOT NULL,
        body TEXT NOT NULL,
        badge_text TEXT NULL,
        badge_color TEXT NOT NULL DEFAULT 'none',
        sort_order INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_budget_table_rows (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL,
        effect_text TEXT NOT NULL,
        polarity TEXT NOT NULL DEFAULT 'neg',
        sort_order INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_budget_meta (
        id INTEGER PRIMARY KEY,
        tip_text TEXT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $ruleCount = (int)$pdo->query('SELECT COUNT(*) FROM formulare_budget_rules')->fetchColumn();
    if ($ruleCount === 0) {
        $ins = $pdo->prepare('INSERT INTO formulare_budget_rules (category, title, body, badge_text, badge_color, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach (dbBudgetSeedRules() as $r) {
            $ins->execute([$r['category'], $r['title'], $r['body'], $r['badge_text'], $r['badge_color'], $r['sort_order']]);
        }
        $insRow = $pdo->prepare('INSERT INTO formulare_budget_table_rows (label, effect_text, polarity, sort_order) VALUES (?, ?, ?, ?)');
        foreach (dbBudgetSeedTableRows() as $r) {
            $insRow->execute([$r['label'], $r['effect_text'], $r['polarity'], $r['sort_order']]);
        }
        $pdo->prepare('INSERT INTO formulare_budget_meta (id, tip_text) VALUES (1, ?)')
            ->execute(['Spoluúčasť fix 400 € (320 € v autorizovanom servise) je spôsob, ako znížiť poistné pre klienta aj v prípade, že je budget minutý. V niektorých prípadoch to klient bez problémov zoberie.']);
    }

    // Model zapracovania — mesačný tracker statusu (FIT/STD/TOP) za mesiace
    // 6.–24., na výpočet kvartálneho doplatku DP v Ceste nováčika.
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_mz_status (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        advisor_id INTEGER NOT NULL,
        month_number INTEGER NOT NULL,
        status TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(advisor_id, month_number),
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS formulare_schema_migrations (
        filename TEXT PRIMARY KEY,
        applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        applied_by TEXT NOT NULL DEFAULT '',
        note TEXT NOT NULL DEFAULT ''
    )");
}

/**
 * Predvolené fázy Cesty nováčika (koncept "Mapa cesty a odmeny") — zdieľané
 * medzi lokálnym SQLite seedom (dbInitSqlite) aj produkčnou migráciou
 * (sql/033_onboarding_roadmap_concept.sql má rovnaký zoznam natvrdo v SQL).
 * `duration_days` je dĺžka fázy v dňoch od nástupu — určuje, kedy sa fáza
 * automaticky považuje za dokončenú (žiadne ručné odškrtávanie).
 * `is_ongoing` (napr. "Priebežne") je mimo časovej osi — nemá pevné trvanie.
 */
function dbOnboardingSeedPhases(): array {
    return [
        ['name' => 'Pred nástupom', 'icon' => '📋', 'duration_days' => 7, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => 'Dodatková provízia (DP) z Modelu zapracovania začína od 0. mesiaca (pozri ďalšiu fázu) — táto fáza je len príprava pred jeho štartom.', 'support_text' => 'Papierovanie a školenia na začiatku vyzerajú ako veľa — a naozaj toho je veľa. Netreba to zvládnuť dokonale na prvýkrát. Ak si niečím neistý, opýtaj sa — presne na to sú tu kolegovia aj tvoj manažér.'],
        ['name' => '0. mesiac', 'icon' => '🌱', 'duration_days' => 30, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => '500 € DP, ak do konca mesiaca splníš: registráciu v NBS (sektor poistenie/zaistenie), e-learning, základné školenia (Prvé kroky v UNIQA, Úvod do predaja, IT, Autá), 100 kontaktov v CRM+ a min. 15 ponúk spolu v Unipoint (Život, Autá, Majetok).', 'support_text' => 'Prvý mesiac je o učení sa veľa nového naraz. Je úplne normálne, že si na začiatku neistý — nikto od teba nečaká, že to vieš hneď. Manažér aj skúsenejší kolegovia ti radi pomôžu, stačí sa ozvať.'],
        ['name' => 'I. mesiac', 'icon' => '🧭', 'duration_days' => 30, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €. Tracker nájdeš nižšie pri Modeli zapracovania.', 'support_text' => 'Ak máš pocit, že iní to majú jednoduchšie, nemajú — každý si prešiel rovnakou krivkou učenia. Pýtaj sa toľko, koľko potrebuješ, nie je to znak slabosti.'],
        ['name' => 'II. mesiac', 'icon' => '💬', 'duration_days' => 30, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €.', 'support_text' => 'Blok Predaj je o skutočnom rozhovore s klientom — analýza potrieb, argumentácia, zvládanie námietok, uzatváracie techniky. Netreba to zvládnuť dokonale hneď, tieto zručnosti sa budujú praxou. Ak chceš niečo nacvičiť nanečisto, kolegovia aj manažér radi pomôžu.'],
        ['name' => 'III. mesiac', 'icon' => '❤️', 'duration_days' => 30, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €.', 'support_text' => 'Životné poistenie je srdcom tejto práce a je úplne prirodzené, že práve pri ňom máš najviac otázok. Nie si v tom sám — kolegovia aj manažér ťa podržia.'],
        ['name' => 'IV. mesiac', 'icon' => '🏠', 'duration_days' => 30, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €.', 'support_text' => 'Majetkové poistenie je technickejšia oblasť a prvé ponuky bývajú pomalšie — to je v poriadku. Radšej sa spýtaj vopred, než aby si sa s tým trápil sám.'],
        ['name' => 'V. mesiac', 'icon' => '🎓', 'duration_days' => 30, 'duration_months' => 1, 'is_ongoing' => false, 'reward_text' => 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €. Do konca 6. mesiaca MZ treba spolu min. 5 000 PB, inak nasleduje vyradenie z Modelu zapracovania.', 'support_text' => 'Posledný krok pred maturitou. Ver si — dostal si sa sem vlastnou prácou. A ak sa niečo nepodarí na prvý pokus, nie je to koniec sveta.'],
        ['name' => 'VI.–XII. mesiac', 'icon' => '📈', 'duration_days' => 210, 'duration_months' => 7, 'is_ongoing' => false, 'reward_text' => '6. mesiac ešte podľa produkcie (min. 5 000 PB spolu za 0.–6. mesiac). Od 7. mesiaca DP podľa statusu: FIT 500 € · STD 750 € · TOP 1 000 € mesačne.', 'support_text' => 'Školenia sú za tebou — teraz ide hlavne o pravidelnosť. Status FIT/STD/TOP sa vyhodnocuje priebežne, takže sa oplatí sledovať ho každý mesiac.'],
        ['name' => 'XIII.–XXIV. mesiac', 'icon' => '🏆', 'duration_days' => 360, 'duration_months' => 12, 'is_ongoing' => false, 'reward_text' => 'DP podľa statusu: FIT 300 € · STD 500 € · TOP 700 € mesačne.', 'support_text' => 'Posledná časť Modelu zapracovania. Drž si svoj status a dodatková provízia ide s ním — nič nové sa už neučíš, len pokračuješ v tom, čo už vieš.'],
        ['name' => 'Priebežne', 'icon' => '♾️', 'duration_days' => 0, 'duration_months' => 0, 'is_ongoing' => true, 'reward_text' => '', 'support_text' => 'Bežná práca poradcu, ktorá pokračuje stále, nezávisle od času vo fáze.'],
    ];
}

/**
 * Predvolené materiály (referenčné kroky) Cesty nováčika — zdieľané medzi
 * lokálnym SQLite seedom (dbInitSqlite) aj produkčnou migráciou
 * (sql/017_onboarding_and_events.sql má rovnaký zoznam natvrdo v SQL, keďže
 * produkcia sa nemigruje z PHP kódu). Odkedy koncept nemá per-krokové
 * odškrtávanie, ide už len o referenčný obsah k danej fáze.
 */
function dbOnboardingSeedSteps(): array {
    return [
        // Prevzaté a skrátené z oficiálnej "Karty výkonnosti a rozvoja VFA 2025"
        // (UNIQA onboarding VFA) — len kroky, ktoré si nováčik sám odškrtáva.
        // Vynechané: rozpis učiva jednotlivých kurzov (samostatná príloha) a
        // tabuľka bodov/provízií na 24 mesiacov (iný účel, nie checklist).
        ['phase' => 'Pred nástupom', 'title' => 'Podpis zmluvy o obchodnom zastúpení', 'description' => '', 'link_url' => null],
        ['phase' => 'Pred nástupom', 'title' => 'Registrácia e-learning UNIQA', 'description' => '', 'link_url' => null],
        ['phase' => 'Pred nástupom', 'title' => 'IT systémy — pridelenie prístupových práv', 'description' => 'Albert, UNIHUB, HCL.', 'link_url' => null],
        ['phase' => 'Pred nástupom', 'title' => 'Registrácia e-learning SLASPO', 'description' => 'Podľa podpísanej zmluvy o OZ a návodu na e-learning.', 'link_url' => null],
        ['phase' => 'Pred nástupom', 'title' => 'OFV/SLASPO štúdium pre sektory', 'description' => 'Poistenie a zaistenie — pred vstupným školením. SDS, DDS a Kapitálový trh (KT) — do konca 0. mesiaca.', 'link_url' => null],
        ['phase' => 'Pred nástupom', 'title' => 'Povinné kurzy UNIQA Studio (e-learning)', 'description' => 'Ochrana osobných údajov (GDPR), Compliance, Fraud management, Informačná bezpečnosť.', 'link_url' => null],
        ['phase' => 'Pred nástupom', 'title' => 'UNIHUB — ochutnávka', 'description' => 'Predstavenie systému na dojednávanie a servis poistných zmlúv klienta.', 'link_url' => null],

        ['phase' => '0. mesiac', 'title' => 'Prvé kroky v UNIQA — úvodné školenie', 'description' => 'Poisťovacia abeceda, informácie o spoločnosti, firemná kultúra, systém vzdelávania, moja vízia v UNIQA.', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'Štart I. Životné poistenie — povinné samoštúdium pred štartom', 'description' => 'Život&Radosť.', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'Štart I. Životné poistenie — aktívna účasť a samoštúdium po štarte', 'description' => 'Prezenčne, 1 deň. Život&Radosť a pripoistenia — parametre, výhody, práca v UNIHUB.', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'IT systémy — školenie a nastavenie', 'description' => 'UNIHUB, Albert, UNIQA Studio, UNIPOINT, HCL. Tréning kalkulácie ponúk.', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'Štart II. Autá — samoštúdium a príprava ponuky pred kurzom', 'description' => '', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'Štart II. Autá — aktívna účasť a samoštúdium po štarte', 'description' => 'Online. PZP a KASKO — technické parametre a spôsob dojednania.', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'Databáza klientov — min. 100 kontaktov v CRM', 'description' => '', 'link_url' => null],
        ['phase' => '0. mesiac', 'title' => 'Produkčné body sa počítajú aj v 0. mesiaci', 'description' => 'Ak sa ti v 0. mesiaci podarí vygenerovať produkčné body, nepremrhajú sa — pripočítajú sa k 1. mesiacu MZ.', 'link_url' => null],

        ['phase' => 'I. mesiac', 'title' => 'Štart III. Úvod do predaja — aktívna účasť a samoštúdium', 'description' => 'Prezenčne, 1 deň. Podmienka: min. 300 bodov za školenia. Prehľad produktov, filozofia životného poistenia, telefonovanie a zvládanie námietok, analýza potrieb klienta.', 'link_url' => null],
        ['phase' => 'I. mesiac', 'title' => 'Štart IV. On-line majetok — samoštúdium, účasť a samoštúdium po štarte', 'description' => 'Online, 1 deň. Domov a bezpečie — technické parametre, terminológia, kalkulácia ponuky.', 'link_url' => null],
        ['phase' => 'I. mesiac', 'title' => 'Štart V. Poistenie osôb — samoštúdium, účasť a samoštúdium po štarte', 'description' => 'Prezenčne/online, 1 deň. Cestovné poistenie, pohrebné náklady, Uniqáčik.', 'link_url' => null],
        ['phase' => 'I. mesiac', 'title' => 'Štart VI. Dôchodky — samoštúdium, účasť a samoštúdium po štarte', 'description' => 'Prezenčne, 1 deň. Starobné dôchodkové sporenie — SDS, DDS.', 'link_url' => null],

        ['phase' => 'II. mesiac', 'title' => 'Blok I. Predaj — splniť podmienky pred blokom', 'description' => 'Absolvovanie všetkých školení z 0.-3. mesiaca, zopakovanie produktov životného a neživotného poistenia, produkcia 4000 Pb.', 'link_url' => null],
        ['phase' => 'II. mesiac', 'title' => 'Blok I. Predaj — aktívna účasť a samoštúdium', 'description' => 'Prezenčne, 3 dni. Vstup do sveta klienta, analýza potrieb, efektívna argumentácia, riešenie námietok, uzatváracie techniky, príprava na maturitu.', 'link_url' => null],

        ['phase' => 'III. mesiac', 'title' => 'UNIPOINT — príprava pred Blokom II', 'description' => 'Životné poistenie, SDS, Tempo — opakovanie produktov a základné informácie.', 'link_url' => null],
        ['phase' => 'III. mesiac', 'title' => 'Blok II. Životné poistenie, SDS, PF — splniť podmienky pred blokom', 'description' => 'Absolvovanie všetkých školení z 0.-2. mesiaca, zopakovanie nastavenia poistných súm v ŽP, produkcia 3000 Pb.', 'link_url' => null],
        ['phase' => 'III. mesiac', 'title' => 'Blok II. Životné poistenie — aktívna účasť a samoštúdium po bloku', 'description' => 'Prezenčne, 3 dni. Filozofia a zmysel životného poistenia, finančná matematika, nastavenie poistných súm v UNIPOINT, SDS, Tempo, investovanie.', 'link_url' => null],

        ['phase' => 'IV. mesiac', 'title' => 'UNIPOINT — povinné štúdium pred Blokom Majetkové poistenie', 'description' => 'Domov a bezpečie.', 'link_url' => null],
        ['phase' => 'IV. mesiac', 'title' => 'Blok III. Majetkové poistenie — splniť podmienky pred blokom', 'description' => 'Účasť na Štarte IV, produkcia 1500 Pb, min. 600 bodov za školenia.', 'link_url' => null],
        ['phase' => 'IV. mesiac', 'title' => 'Blok III. Majetkové poistenie — aktívna účasť a samoštúdium po bloku', 'description' => 'Prezenčne, 3 dni. Filozofia majetkového poistenia, technické parametre D&B, návrh správnych poistných súm, práca v UNIPOINT a CRM.', 'link_url' => null],

        ['phase' => 'V. mesiac', 'title' => 'Príprava na maturitu', 'description' => 'Štúdium produktovej časti maturity vrátane skúšobných testov.', 'link_url' => null],
        ['phase' => 'V. mesiac', 'title' => 'Maturita', 'description' => 'Podmienka: absolvovanie všetkých školení z 0.-4. mesiaca. Overenie produktových znalostí a predajných zručností.', 'link_url' => null],

        // Zvyšok Modelu zapracovania (mesiace 6.–24.) nemá vlastné školenia,
        // len priebežné bonusové kritériá — viď sql/028_onboarding_month_ranges.sql.
        ['phase' => 'VI.–XII. mesiac', 'title' => 'Priebežný status a dodatková provízia', 'description' => 'DP podľa statusu: FIT 500 € · STD 750 € · TOP 1 000 € mesačne (7.–12. mesiac MZ). Pozri si aktuálny status a podrobnosti v Modeli zapracovania.', 'link_url' => '/model-zapracovania/'],
        ['phase' => 'VI.–XII. mesiac', 'title' => 'Skúška MATURITA — termíny', 'description' => 'Ak si MATURITU nezložil do konca 6. mesiaca, DP za 7.–12. mesiac sa kráti o 50 %. Ak nie ani do konca 9. mesiaca, nasleduje automatické trvalé vyradenie z Modelu zapracovania.', 'link_url' => null],
        ['phase' => 'VI.–XII. mesiac', 'title' => 'Vrátenie DP pri ukončení zmluvy', 'description' => 'Ak zmluvu o obchodnom zastúpení ukončíš v tomto období (7.–24. mesiac MZ), vraciaš 50 % vyplatenej DP za posledných 12 mesiacov.', 'link_url' => null],
        ['phase' => 'XIII.–XXIV. mesiac', 'title' => 'Priebežný status a dodatková provízia', 'description' => 'DP podľa statusu: FIT 300 € · STD 500 € · TOP 700 € mesačne (13.–24. mesiac MZ). Pozri si aktuálny status a podrobnosti v Modeli zapracovania.', 'link_url' => '/model-zapracovania/'],
        ['phase' => 'XIII.–XXIV. mesiac', 'title' => 'Posledná šanca na MATURITU', 'description' => 'Ak program adaptácie a skúšku MATURITA nezložíš do konca 13. mesiaca od nástupu, spolupráca sa ukončuje.', 'link_url' => null],
    ];
}

/**
 * Predvolené pravidlá Budgetových zliav — zdieľané medzi lokálnym SQLite
 * seedom (dbInitSqlite) aj produkčnou migráciou (sql/026_budget_rules.sql
 * má rovnaký zoznam natvrdo v SQL, keďže produkcia sa nemigruje z PHP kódu).
 */
function dbBudgetSeedRules(): array {
    return [
        ['category' => 'auto', 'title' => 'Rozsah zľavy', 'body' => 'Zľavy sa môžu poskytovať v rozsahu 5 % – 20 %.', 'badge_text' => null, 'badge_color' => 'none', 'sort_order' => 0],
        ['category' => 'auto', 'title' => 'Zľava do 100 €', 'body' => 'Automaticky schvaľuje regionálna asistentka, pokiaľ škodovosť klienta nepresiahne 50 % (asistentka to vždy skontroluje). Ak je škodovosť vyššia, posudzuje Daniel Jurčík.', 'badge_text' => 'Asistentka · pri vyššej škodovosti Daniel Jurčík', 'badge_color' => 'both', 'sort_order' => 1],
        ['category' => 'auto', 'title' => 'Zľava nad 100 €', 'body' => 'Vždy schvaľuje Daniel Jurčík. Do poznámky treba vždy uviesť, prečo pýtame takú zľavu a aké poistné zmluvy klient u nás má — nestačí napísať iba „viaczmluvný klient“. Dôležité je, aby mal zmluvy ako Majetok, Život, Dôchodok alebo Tempo (nie CP alebo iné auto).', 'badge_text' => 'Schvaľuje: Daniel Jurčík', 'badge_color' => 'daniel', 'sort_order' => 2],
        ['category' => 'auto', 'title' => 'Spoluúčasť a budgetová zľava', 'body' => 'Fix 80 € (0 € spoluúčasť v autorizovanom servise) — budgetová zľava sa nebude schvaľovať. Fix 200 € — budgetová zľava sa bude schvaľovať.', 'badge_text' => null, 'badge_color' => 'none', 'sort_order' => 3],
        ['category' => 'auto', 'title' => 'Nízke poistné na PZP', 'body' => 'Ak na PZP vychádza poistné menej ako 100 €, budgetová zľava bude zamietnutá. Netýka sa prívesných vozíkov a motocyklov.', 'badge_text' => null, 'badge_color' => 'none', 'sort_order' => 4],
        ['category' => 'auto', 'title' => 'UW zľava pri vysokej poistnej sume', 'body' => 'Pri poistnej sume nad 100 000 € je možné opätovne požiadať o UW zľavu.', 'badge_text' => null, 'badge_color' => 'none', 'sort_order' => 5],
        ['category' => 'majetok', 'title' => 'Maximálna zľava z budgetu', 'body' => 'Maximálna zľava z budgetu je 10 % (pokiaľ nebolo možné dať akčnú zľavu).', 'badge_text' => 'Schvaľuje: asistentka', 'badge_color' => 'asist', 'sort_order' => 0],
        ['category' => 'majetok', 'title' => 'Súbeh s akčnou zľavou', 'body' => 'Pri použití akčnej zľavy 10 % (aktuálne prebieha kampaň) je maximálna možná budgetová zľava 5 %.', 'badge_text' => 'Schvaľuje: asistentka', 'badge_color' => 'asist', 'sort_order' => 1],
        ['category' => 'majetok', 'title' => 'Rozsah zľavy', 'body' => 'Zľavy sa môžu poskytovať v rozsahu 5 % – 20 %. Zľavy vyššie ako 10 % sa budú odsúhlasovať iba v prípadoch nižšie.', 'badge_text' => 'Vyššie ako 10 %: vždy Daniel Jurčík', 'badge_color' => 'daniel', 'sort_order' => 2],
        ['category' => 'majetok', 'title' => 'Zľava vyššia ako 10 %', 'body' => 'Napr. povodňové zóny, konkurenčná ponuka, VIP klient, klient si naraz poisťuje viac nehnuteľností… Dôvod treba napísať do poznámky — schvaľuje sa individuálne podľa toho, čo klient u nás má poistené, podobne ako pri autopoistení.', 'badge_text' => 'Schvaľuje: Daniel Jurčík', 'badge_color' => 'daniel', 'sort_order' => 3],
    ];
}

function dbBudgetSeedTableRows(): array {
    return [
        ['label' => 'Fix 80 €', 'effect_text' => 'cca +30 % prirážka', 'polarity' => 'neg', 'sort_order' => 0],
        ['label' => 'Fix 200 €', 'effect_text' => 'cca +17 % prirážka', 'polarity' => 'neg', 'sort_order' => 1],
        ['label' => '5 %, max 200 €', 'effect_text' => 'cca +3 % prirážka', 'polarity' => 'neg', 'sort_order' => 2],
        ['label' => 'Fix 400 €', 'effect_text' => 'cca −18 % (zľava z poistného, akoby zľava z budgetu)', 'polarity' => 'pos', 'sort_order' => 3],
        ['label' => 'Spoluúčasť mladého vodiča', 'effect_text' => 'zníženie poistného o desiatky €', 'polarity' => 'pos', 'sort_order' => 4],
    ];
}
