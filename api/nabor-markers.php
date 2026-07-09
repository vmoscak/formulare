<?php
/**
 * Body pre mapu náborovej zóny (JSON) — súradnice sú na úrovni obce (stred
 * PSČ), nie presná ulica, pozri psc-suradnice.php. Rozsah kategórií je
 * natrvalo len na viazaných a podriadených finančných agentov a rozsah
 * krajov natrvalo len na NABOR_ACTIVE_REGIONS (rovnako ako nabor.php),
 * rovnaké filtre (q, sector, parent, region, okres) v query stringu.
 * Prístup VÝHRADNE pre is_owner (rovnaká kontrola ako nabor.php).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT id FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
if (!$stmt->fetch()) { http_response_code(403); echo '[]'; exit; }

$q = trim((string)($_GET['q'] ?? ''));
$fCategories = array_values(array_intersect(array_map('trim', (array)($_GET['cat'] ?? [])), AGENT_CATEGORIES));
$fSector = trim((string)($_GET['sector'] ?? ''));
$fParent = trim((string)($_GET['parent'] ?? ''));
$fRegion = trim((string)($_GET['region'] ?? ''));
$fOkres = trim((string)($_GET['okres'] ?? ''));
$activeCategories = $fCategories ?: AGENT_CATEGORIES;
// Kraj zúžený na jeden z povolených, ak je zvolený a platný — inak oba.
$activeRegions = ($fRegion !== '' && in_array($fRegion, NABOR_ACTIVE_REGIONS, true)) ? [$fRegion] : NABOR_ACTIVE_REGIONS;

$where = ['lat IS NOT NULL', 'lon IS NOT NULL'];
$params = [];
$where[] = '(' . implode(' OR ', array_fill(0, count($activeCategories), 'categories LIKE ?')) . ')';
foreach ($activeCategories as $c) { $params[] = '%"' . $c . '"%'; }
$where[] = '(' . implode(' OR ', array_fill(0, count($activeRegions), 'region = ?')) . ')';
foreach ($activeRegions as $r) { $params[] = $r; }
if ($q !== '') { $where[] = '(name LIKE ? OR ico LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
if ($fSector !== '') { $where[] = 'sectors LIKE ?'; $params[] = '%"' . $fSector . '"%'; }
if ($fParent !== '') { $where[] = 'parent_names LIKE ?'; $params[] = '%"' . $fParent . '"%'; }
if ($fOkres !== '') { $where[] = 'okres = ?'; $params[] = $fOkres; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$out = [];
try {
    $stmt = db()->prepare("SELECT ico, name, city, region, lat, lon, sectors, parent_names, raw_json
        FROM formulare_registry_entities $whereSql");
    $stmt->execute($params);
    while ($r = $stmt->fetch()) {
        $licenses = json_decode((string)$r['raw_json'], true);
        $cats = [];
        if (is_array($licenses)) {
            foreach ($licenses as $item) {
                if (!is_array($item) || empty($item['scope'])) continue;
                $cats[] = trim($item['scope'] . (!empty($item['valid_from']) ? ' (od ' . $item['valid_from'] . ')' : ''));
            }
        }
        $sectors = json_decode((string)$r['sectors'], true);
        $parents = json_decode((string)$r['parent_names'], true);
        $out[] = [
            'ico' => $r['ico'],
            'name' => $r['name'],
            'city' => $r['city'],
            'region' => $r['region'],
            'lat' => (float)$r['lat'],
            'lon' => (float)$r['lon'],
            'cats' => $cats,
            'sectors' => is_array($sectors) ? $sectors : [],
            'parents' => is_array($parents) ? $parents : [],
        ];
    }
} catch (Throwable $e) { /* prázdna tabuľka -> prázdny zoznam */ }

echo json_encode($out, JSON_UNESCAPED_UNICODE);
