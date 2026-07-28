<?php
/**
 * Rýchle hľadanie v registri NBS (JSON) — pre autocomplete pri voliteľnom
 * prepojení kandidáta na nábor so záznamom v registri (nabor-kandidati.php).
 * Prístup VÝHRADNE pre is_owner (rovnaká kontrola ako nabor.php/nabor-markers.php).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT id FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
if (!$stmt->fetch()) { http_response_code(403); echo '[]'; exit; }

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) { echo '[]'; exit; }

$out = [];
try {
    $stmt = db()->prepare(
        'SELECT ico, name, city FROM formulare_registry_entities WHERE name LIKE ? OR ico LIKE ? ORDER BY name ASC LIMIT 8'
    );
    $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
    $out = $stmt->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }

echo json_encode($out, JSON_UNESCAPED_UNICODE);
