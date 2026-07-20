<?php
/**
 * Vráti mená klientov, ktorých prihlásený poradca naposledy zadal v danom
 * nástroji — na doplnenie autocomplete (datalist) pri opakovanom vypĺňaní
 * mena, nech ho nemusí písať odznova. GET ?tool=<slug>
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(401); echo '{"clients":[]}'; exit; }

$tool = (string)($_GET['tool'] ?? '');
if (!preg_match('/^[a-z0-9-]+$/', $tool)) { echo '{"clients":[]}'; exit; }

$stmt = db()->prepare(
    "SELECT client_label FROM formulare_generated_documents
     WHERE advisor_id = ? AND tool = ? AND client_label != 'Klient' AND client_label != ''
     GROUP BY client_label ORDER BY MAX(generated_at) DESC LIMIT 8"
);
$stmt->execute([$advisorId, $tool]);
$clients = array_column($stmt->fetchAll(), 'client_label');

echo json_encode(['clients' => $clients], JSON_UNESCAPED_UNICODE);
