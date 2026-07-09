<?php
/**
 * Dávkové presné geokódovanie adries náborovej zóny (OpenStreetMap Nominatim)
 * — spúšťané periodicky cez Websupport "Plánovač úloh" (návšteva URL), aby
 * to bežalo na pozadí bez otvoreného prehliadača. Každý beh spracuje jednu
 * malú dávku (viď geocodeBatchProcess v db.php) a rešpektuje limit
 * Nominatim 1 dotaz/sekundu.
 *
 * Autorizácia: buď spustené z príkazového riadku (CLI), alebo cez URL s
 * ?token=<GATE_TOKEN> — rovnaký tajný token ako brána appky, nastav v
 * Plánovači úloh presne URL vrátane tokenu. Bez správneho tokenu 403.
 * .htaccess túto cestu necháva mimo cookie brány (rovnako ako iné verejné
 * endpointy s vlastnou ochranou), pretože cron nemá prehliadačové cookies.
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: text/plain; charset=utf-8');

$isCli = PHP_SAPI === 'cli';
if (!$isCli && !hash_equals(GATE_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo "Chyba: neplatny alebo chybajuci token.\n";
    exit;
}

try {
    $result = geocodeBatchProcess(35);
    echo "Spracovane: {$result['processed']}, najdene: {$result['found']}, "
        . "nenajdene: {$result['not_found']}, zostava: {$result['remaining']}\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Chyba: ' . $e->getMessage() . "\n";
}
