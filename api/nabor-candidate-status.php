<?php
/**
 * Rýchla zmena stavu kandidáta na nábor (Kanban drag/select v
 * nabor-kandidati.php) — bez nutnosti otvárať celý edit formulár.
 * POST {id, status}. Prístup výhradne pre is_owner.
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT id FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
if (!$stmt->fetch()) { http_response_code(403); echo '{"ok":false}'; exit; }

const RK_VALID_STATUSES = ['novy', 'oslovene', 'zaujem', 'stretnutie', 'pripojil', 'odmietol', 'stratil'];

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($input['id'] ?? 0);
$status = (string)($input['status'] ?? '');
if (!$id || !in_array($status, RK_VALID_STATUSES, true)) { http_response_code(400); echo '{"ok":false}'; exit; }

db()->prepare('UPDATE formulare_recruit_candidates SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
    ->execute([$status, $id]);

echo json_encode(['ok' => true]);
