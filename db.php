<?php
/**
 * Jednoduchý PDO singleton. Číta prístupové údaje z config.local.php
 * (lokálne SQLite pri vývoji, MySQL na produkcii — pozri config.sample.php).
 */

require_once __DIR__ . '/config.local.php';

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
 * Ľudsky čitateľný názov nástroja podľa slugu priečinka — používa sa v tabuľkách
 * histórie dokumentov (admin.php, moje-dokumenty.php), kde sa inak zobrazoval
 * surový slug ako "nahrada-skody-zodpovednost".
 */
function toolLabel(string $slug): string {
    static $labels = [
        'wizard-poistenie' => 'Aké poistenie potrebujem',
        'financna-medzera' => 'Kalkulačka finančnej medzery',
        'financna-analyza' => 'Finančná analýza (staršia verzia)',
        'checklist-analyza' => 'Checklist – výstup z analýzy',
        'splnomocnenie' => 'Všeobecné splnomocnenie',
        'vypoved-poistenia' => 'Výpoveď poistnej zmluvy',
        'preberaci-protokol' => 'Preberací protokol',
        'univerzalna-ziadost-zmena' => 'Univerzálna žiadosť o zmenu',
        'nahrada-skody-zodpovednost' => 'Žiadosť o náhradu škody',
        'cestne-vyhlasenie-inej-poistky' => 'Čestné prehlásenie',
        'cestne-vyhlasenie-kupa-veci' => 'Čestné prehlásenie o kúpe veci',
        'suhlas-vyplata-inemu-uctu' => 'Súhlas s výplatou na iný účet',
        'ziadost-vratenie-preplatku' => 'Vrátenie preplatku',
        'odvolanie-zamietnutie-plnenia' => 'Odvolanie voči likvidácii',
        'reklamacia-postup-institucie' => 'Reklamácia / sťažnosť',
        'ziadost-vyplata-poistneho-plnenia' => 'Výplata poistného plnenia',
        'financna-karticka-prvej-pomoci' => 'Finančná kartička prvej pomoci',
        'checklisty-skody' => 'Checklisty podľa typu škody',
        'generator-recenzii' => 'Generátor 5-hviezdičkovej prosby',
        'warm-intro-whatsapp' => 'Warm-Intro WhatsApp generátor',
        'karticka-odporucte-ma' => 'Kartička „Odporučte ma priateľom“',
        'vysvetlivky-pre-klienta' => 'Vysvetlivky pre klienta',
        'tahak-co-pytat-od-klienta' => 'Ťahák „Čo pýtať od klienta“',
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
        is_admin INTEGER NOT NULL DEFAULT 0,
        is_owner INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    // Defenzívne pre už existujúce lokálne SQLite DB založené pred zavedením PIN-u / prepínačov nástrojov / náborovej zóny.
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN pin_hash TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN disabled_tools TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN is_owner INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* stĺpec už existuje */ }
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
        generated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id),
        FOREIGN KEY (client_link_id) REFERENCES formulare_client_links(id)
    )");
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
}
