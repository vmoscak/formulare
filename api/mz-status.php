<?php
/**
 * Uloženie dosiahnutého statusu (FIT/STD/TOP) za jeden mesiac Modelu
 * zapracovania pre aktuálne prihláseného poradcu. Bez gatovania na
 * is_owner — každý si sleduje vlastný postup. POST {month, status}
 * status='' zmaže záznam (návrat na "zatiaľ nevybraté").
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(403); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$month = (int)($input['month'] ?? 0);
$status = (string)($input['status'] ?? '');

if ($month < 6 || $month > 24) { http_response_code(400); echo '{"ok":false}'; exit; }
if ($status !== '' && !in_array($status, ['fit', 'std', 'top'], true)) { http_response_code(400); echo '{"ok":false}'; exit; }

if ($status === '') {
    db()->prepare('DELETE FROM formulare_mz_status WHERE advisor_id = ? AND month_number = ?')
        ->execute([$advisorId, $month]);
} else {
    $upd = db()->prepare('UPDATE formulare_mz_status SET status = ?, updated_at = ? WHERE advisor_id = ? AND month_number = ?');
    $upd->execute([$status, date('Y-m-d H:i:s'), $advisorId, $month]);
    if ($upd->rowCount() === 0) {
        try {
            db()->prepare('INSERT INTO formulare_mz_status (advisor_id, month_number, status) VALUES (?, ?, ?)')
                ->execute([$advisorId, $month, $status]);
        } catch (Throwable $e) { /* súbežný insert - v poriadku, UPDATE už prebehol inde */ }
    }
}

echo json_encode(['ok' => true]);
