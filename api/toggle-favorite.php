<?php
/**
 * Prepnutie obľúbeného nástroja (hviezdička) pre aktuálne prihláseného
 * poradcu. Ukladá sa ako JSON zoznam slugov na formulare_advisors.favorite_tools
 * (rovnaký princíp ako disabled_tools v admin.php). POST {slug}
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(403); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$slug = trim((string)($input['slug'] ?? ''));
if ($slug === '') { http_response_code(400); echo '{"ok":false}'; exit; }

$stmt = db()->prepare('SELECT favorite_tools FROM formulare_advisors WHERE id = ?');
$stmt->execute([$advisorId]);
$row = $stmt->fetch();

$favs = [];
if ($row && !empty($row['favorite_tools'])) {
    $decoded = json_decode($row['favorite_tools'], true);
    if (is_array($decoded)) $favs = $decoded;
}

$isFav = in_array($slug, $favs, true);
if ($isFav) {
    $favs = array_values(array_diff($favs, [$slug]));
} else {
    $favs[] = $slug;
}

db()->prepare('UPDATE formulare_advisors SET favorite_tools = ? WHERE id = ?')
    ->execute([json_encode($favs), $advisorId]);

echo json_encode(['ok' => true, 'favorited' => !$isFav]);
