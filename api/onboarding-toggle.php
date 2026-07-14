<?php
/**
 * Prepnutie stavu jedného kroku Cesty nováčika (hotovo/nehotovo) pre
 * aktuálne prihláseného poradcu. Zámerne bez gatovania na is_owner —
 * keď sa stránka raz otvorí pre celý tím, tento endpoint už nemusí meniť.
 * POST {stepId, done}
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(403); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$stepId = (int)($input['stepId'] ?? 0);
$done = !empty($input['done']);

if ($stepId <= 0) { http_response_code(400); echo '{"ok":false}'; exit; }

if ($done) {
    try {
        db()->prepare('INSERT INTO formulare_onboarding_progress (advisor_id, step_id) VALUES (?, ?)')
            ->execute([$advisorId, $stepId]);
    } catch (Throwable $e) { /* už označené - UNIQUE constraint, v poriadku */ }

    // Prvý moment, keď poradca odškrtne úplne poslednú chýbajúcu položku osnovy,
    // sa natrvalo uloží ako dátum dokončenia — zostáva v "Histórii absolventov"
    // aj keď ho owner neskôr odoberie z priradenia alebo sa osnova zväčší.
    $totalSteps = (int)db()->query('SELECT COUNT(*) FROM formulare_onboarding_steps')->fetchColumn();
    $doneStmt = db()->prepare('SELECT COUNT(*) FROM formulare_onboarding_progress WHERE advisor_id = ?');
    $doneStmt->execute([$advisorId]);
    $doneCount = (int)$doneStmt->fetchColumn();
    if ($totalSteps > 0 && $doneCount >= $totalSteps) {
        db()->prepare('UPDATE formulare_advisors SET onboarding_completed_at = COALESCE(onboarding_completed_at, ?) WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $advisorId]);
    }
} else {
    db()->prepare('DELETE FROM formulare_onboarding_progress WHERE advisor_id = ? AND step_id = ?')
        ->execute([$advisorId, $stepId]);
}

echo json_encode(['ok' => true]);
