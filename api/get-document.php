<?php
/**
 * Vráti uložený vygenerovaný dokument (nástroj, meno klienta, dáta formulára)
 * na opätovné otvorenie a vygenerovanie PDF. Vlastník dokumentu ho vidí vždy,
 * admin (is_admin=1) môže otvoriť ktorýkoľvek dokument naprieč poradcami.
 * GET ?id=123
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(401); echo '{}'; exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo '{}'; exit; }

$stmt = db()->prepare('SELECT id, advisor_id, tool, client_label, form_data FROM formulare_generated_documents WHERE id = ?');
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); echo '{}'; exit; }

if ((int)$doc['advisor_id'] !== $advisorId) {
    $me = db()->prepare('SELECT is_admin FROM formulare_advisors WHERE id = ?');
    $me->execute([$advisorId]);
    $me = $me->fetch();
    if (!$me || !$me['is_admin']) { http_response_code(403); echo '{}'; exit; }
}

echo json_encode([
    'tool' => $doc['tool'],
    'client_label' => $doc['client_label'],
    'form_data' => $doc['form_data'],
]);
