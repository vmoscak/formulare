<?php
/**
 * Zaloguje vygenerovaný dokument (poradcovský aj klientsky) do histórie.
 * Verejný (mimo brány) — volá sa aj z klientskej stránky po odoslaní.
 * POST {tool, clientLabel, formData, token?}
 *   - ak je token platný -> source='client', advisor_id sa odvodí z formulare_client_links
 *   - inak -> source='advisor', advisor_id z cur_advisor cookie
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$tool = (string)($input['tool'] ?? '');
$clientLabel = (string)($input['clientLabel'] ?? '');
$formData = $input['formData'] ?? null;
$token = (string)($input['token'] ?? '');

if ($tool === '' || $formData === null) { http_response_code(400); echo '{"ok":false}'; exit; }

$advisorId = null;
$clientLinkId = null;
$source = 'advisor';

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = db()->prepare('SELECT id, advisor_id FROM formulare_client_links WHERE token = ? AND tool = ?');
    $stmt->execute([$token, $tool]);
    $row = $stmt->fetch();
    if ($row) {
        $advisorId = (int)$row['advisor_id'];
        $clientLinkId = (int)$row['id'];
        $source = 'client';
    }
}
if ($advisorId === null) {
    $advisorId = curAdvisorId();
}
if (!$advisorId) { echo '{"ok":false,"reason":"no_advisor"}'; exit; }

$stmt = db()->prepare(
    'INSERT INTO formulare_generated_documents (advisor_id, client_link_id, source, tool, client_label, form_data)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$advisorId, $clientLinkId, $source, $tool, $clientLabel, json_encode($formData)]);

echo json_encode(['ok' => true]);
