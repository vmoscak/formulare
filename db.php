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
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    // Defenzívne pre už existujúce lokálne SQLite DB založené pred zavedením PIN-u / prepínačov nástrojov.
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN pin_hash TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
    try { $pdo->exec("ALTER TABLE formulare_advisors ADD COLUMN disabled_tools TEXT NULL"); } catch (Throwable $e) { /* stĺpec už existuje */ }
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
}
