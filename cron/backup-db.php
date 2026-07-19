<?php
/**
 * Denná záloha celej DB do tmp/backups/ (mimo git deploy synchronizácie,
 * mimo verejného HTTP prístupu — rovnaká ochrana ako tmp/php-error.log,
 * viď .htaccess). Čistý PHP export cez PDO (SHOW CREATE TABLE + INSERTy),
 * nie shell_exec('mysqldump ...') — na zdieľanom hostingu často nie je
 * dostupný/povolený a appka by tak závisela od niečoho mimo jej kontroly.
 *
 * Spúšťanie: Websupport "Plánovač úloh" → nastav dennú návštevu URL
 * https://<doména>/cron/backup-db.php?token=<GATE_TOKEN> (rovnaký tajný
 * token ako brána appky). Dá sa spustiť aj z príkazového riadku (CLI).
 * Bez správneho tokenu 403 — tento súbor je zámerne mimo cookie brány
 * (rovnako ako cron/nabor-geocode.php), pretože plánovač nemá cookies.
 *
 * Drží posledných KEEP_DAYS záloh, staršie maže — bez rotácie by tmp/
 * časom narástlo bez kontroly.
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');

$isCli = PHP_SAPI === 'cli';
if (!$isCli && !hash_equals(GATE_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

if (str_starts_with(DB_DSN, 'sqlite:')) {
    echo "Lokálny SQLite dev — záloha sa netýka (backup je len pre produkčný MySQL).\n";
    exit;
}

const KEEP_DAYS = 14;

$backupDir = __DIR__ . '/../tmp/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
    http_response_code(500);
    echo "Nepodarilo sa vytvoriť priečinok na zálohy.\n";
    exit;
}

$pdo = db();
$filename = $backupDir . '/backup-' . date('Y-m-d_His') . '.sql';
$fh = fopen($filename, 'w');
if (!$fh) {
    http_response_code(500);
    echo "Nepodarilo sa vytvoriť súbor zálohy.\n";
    exit;
}

fwrite($fh, "-- Automatická záloha formulare DB — " . date('Y-m-d H:i:s') . "\n");
fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$totalRows = 0;

foreach ($tables as $table) {
    $createRow = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
    $createSql = $createRow['Create Table'] ?? '';
    fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n$createSql;\n\n");

    // Neťahať celú tabuľku naraz do pamäte — unbuffered kurzor + zápis
    // po dávkach, aby to fungovalo aj s tisíckami riadkov na zdieľanom
    // hostingu s obmedzenou pamäťou.
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
    $batch = [];
    $cols = null;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($cols === null) $cols = array_keys($row);
        $vals = array_map(function ($v) use ($pdo) {
            return $v === null ? 'NULL' : $pdo->quote((string)$v);
        }, $row);
        $batch[] = '(' . implode(',', $vals) . ')';
        $totalRows++;
        if (count($batch) >= 200) {
            fwrite($fh, "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        }
    }
    if ($batch) {
        fwrite($fh, "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $batch) . ";\n");
    }
    fwrite($fh, "\n");
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($fh);

$sizeKb = round(filesize($filename) / 1024, 1);

// Rotácia — zmazať zálohy staršie ako KEEP_DAYS.
$deleted = 0;
foreach (glob($backupDir . '/backup-*.sql') ?: [] as $old) {
    if (filemtime($old) < time() - KEEP_DAYS * 86400) {
        @unlink($old);
        $deleted++;
    }
}

echo "OK: " . basename($filename) . " (" . count($tables) . " tabuliek, $totalRows riadkov, {$sizeKb} KB). Zmazaných starých záloh: $deleted.\n";
