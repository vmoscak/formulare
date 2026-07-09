<?php
/**
 * Mapovanie slug nástroja -> skupina záložky (nastroje/formulare/pomocky),
 * odvodené priamo z tools-registry.php (jediný zdroj pravdy). Volané zo
 * shell.js, aby vedela zvýrazniť správnu záložku aj keď je otvorená
 * konkrétna stránka nástroja (napr. /splnomocnenie/), nie prehľad.
 */
require_once __DIR__ . '/../tools-registry.php';
header('Content-Type: application/json; charset=utf-8');

$out = [];
foreach ($TOOL_CATEGORIES as $cat) {
    $group = $cat['group'] ?? 'nastroje';
    foreach ($cat['tools'] as $t) {
        $out[toolSlug($t['href'])] = $group;
    }
}
echo json_encode($out);
