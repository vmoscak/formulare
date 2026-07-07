<?php
/**
 * Vytvorí jedinečný odkaz pre klienta (POST {tool, clientLabel}).
 * Vyžaduje gate cookie (je za bránou — nie je vo verejnom zozname v .htaccess)
 * a cur_advisor cookie na priradenie odkazu k poradcovi.
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"method"}'; exit; }

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(401); echo '{"error":"no_advisor"}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$tool = (string)($input['tool'] ?? '');
$clientLabel = trim((string)($input['clientLabel'] ?? ''));

$allowedTools = ['financna-medzera', 'wizard-poistenie'];
if (!in_array($tool, $allowedTools, true) || $clientLabel === '') {
    http_response_code(400); echo '{"error":"invalid_input"}'; exit;
}

$token = bin2hex(random_bytes(32));
$stmt = db()->prepare('INSERT INTO formulare_client_links (token, advisor_id, tool, client_label, expires_at) VALUES (?, ?, ?, ?, ?)');
$expires = date('Y-m-d H:i:s', time() + 60 * 86400);
$stmt->execute([$token, $advisorId, $tool, $clientLabel, $expires]);

echo json_encode([
    'token' => $token,
    'url' => "/{$tool}/index.html?token={$token}&klient=1",
]);
