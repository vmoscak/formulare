<?php
/**
 * Verejný endpoint (mimo brány) — klient odošle vyplnené údaje.
 * POST {token, tool, formData}. Prepíše form_data pri každom odoslaní
 * (klient sa môže k odkazu vrátiť a opraviť si to).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = (string)($input['token'] ?? '');
$tool = (string)($input['tool'] ?? '');
$formData = $input['formData'] ?? null;

if (!preg_match('/^[a-f0-9]{64}$/', $token) || $tool === '' || $formData === null) {
    http_response_code(400); echo '{"ok":false}'; exit;
}

$stmt = db()->prepare('SELECT id, expires_at FROM formulare_client_links WHERE token = ? AND tool = ?');
$stmt->execute([$token, $tool]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); echo '{"ok":false}'; exit; }
if ($row['expires_at'] && strtotime($row['expires_at']) < time()) { http_response_code(410); echo '{"ok":false}'; exit; }

$upd = db()->prepare("UPDATE formulare_client_links SET form_data = ?, status = 'submitted', submitted_at = ? WHERE token = ? AND tool = ?");
$upd->execute([json_encode($formData), date('Y-m-d H:i:s'), $token, $tool]);

echo json_encode(['ok' => true]);
