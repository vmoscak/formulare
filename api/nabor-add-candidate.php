<?php
/**
 * Rýchle pridanie kandidáta na nábor priamo z mapy/registra (nabor.php) —
 * prepája inak úplne oddelené nabor.php (register NBS) a
 * nabor-kandidati.php (vlastná evidencia). POST {name, note}.
 * Ak kandidát s rovnakým menom už existuje, nič nezdvojuje a len to oznámi.
 * Prístup VÝHRADNE pre is_owner (rovnaká zásada ako nabor.php/nabor-kandidati.php).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT id FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
if (!$stmt->fetch()) { http_response_code(403); echo '{"ok":false}'; exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim((string)($input['name'] ?? ''));
$note = trim((string)($input['note'] ?? ''));
if ($name === '') { http_response_code(400); echo '{"ok":false,"error":"Chýba meno."}'; exit; }

$dupStmt = db()->prepare('SELECT id FROM formulare_recruit_candidates WHERE name = ? LIMIT 1');
$dupStmt->execute([$name]);
if ($dupStmt->fetch()) {
    echo json_encode(['ok' => true, 'duplicate' => true]);
    exit;
}

db()->prepare('INSERT INTO formulare_recruit_candidates (name, initiator, status, note, created_by) VALUES (?, ?, ?, ?, ?)')
    ->execute([$name, 'ja', 'novy', $note, $advisorId]);

echo json_encode(['ok' => true, 'duplicate' => false]);
