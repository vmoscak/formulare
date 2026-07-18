<?php
/**
 * Presporiadanie obľúbených nástrojov (drag & drop na Domov) — ukladá nové
 * poradie do rovnakého stĺpca ako toggle-favorite.php (favorite_tools).
 * POST {slugs: [...]}. Endpoint slúži VÝHRADNE na presporiadanie — smie
 * obsahovať len slugy, ktoré už medzi obľúbenými poradcu sú, nič nepridáva
 * ani neuberá.
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(403); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$slugs = is_array($input['slugs'] ?? null) ? $input['slugs'] : [];

$stmt = db()->prepare('SELECT favorite_tools FROM formulare_advisors WHERE id = ?');
$stmt->execute([$advisorId]);
$row = $stmt->fetch();

$current = [];
if ($row && !empty($row['favorite_tools'])) {
    $decoded = json_decode($row['favorite_tools'], true);
    if (is_array($decoded)) $current = $decoded;
}

$newOrder = [];
foreach ($slugs as $s) {
    $s = (string)$s;
    if (in_array($s, $current, true) && !in_array($s, $newOrder, true)) $newOrder[] = $s;
}
// Ak by v poslanom poradí chýbal niektorý pôvodný slug (napr. race condition
// s pridaním obľúbeného na inej záložke), pripojí sa na koniec, nech sa nestratí.
foreach ($current as $s) {
    if (!in_array($s, $newOrder, true)) $newOrder[] = $s;
}

db()->prepare('UPDATE formulare_advisors SET favorite_tools = ? WHERE id = ?')
    ->execute([json_encode($newOrder), $advisorId]);

echo json_encode(['ok' => true]);
