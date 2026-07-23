<?php
/**
 * Príjem leadov z vmfin.sk (web/send-mail.php) — server-server volanie,
 * nie z prehliadača, preto žiadna gate_auth/cur_advisor cookie. Namiesto
 * toho HMAC podpis nad telom requestu, zdieľaný secret LEADS_API_SECRET
 * (rovnaká hodnota v oboch repozitároch — formulare aj vmfin-web).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

function ldiError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { ldiError('Nepovolená metóda.', 405); }
if (!defined('LEADS_API_SECRET') || LEADS_API_SECRET === '') { ldiError('API nie je nakonfigurované.', 503); }

$raw = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_X_LEADS_SIGNATURE'] ?? '');
$expected = hash_hmac('sha256', $raw, LEADS_API_SECRET);
if ($sig === '' || !hash_equals($expected, $sig)) { ldiError('Neplatný podpis.', 401); }

$data = json_decode($raw, true);
if (!is_array($data)) { ldiError('Neplatný vstup.'); }

$name = trim((string)($data['name'] ?? ''));
if ($name === '') { ldiError('Chýba meno.'); }
$phone = trim((string)($data['phone'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$source = array_key_exists($data['source'] ?? '', ['manual'=>1,'web_formular'=>1,'odporucanie'=>1,'social'=>1,'ine'=>1])
    ? $data['source'] : 'web_formular';
$message = trim((string)($data['message'] ?? ''));

$id = db()->prepare(
    'INSERT INTO formulare_leads (name, phone, email, source, message, status, created_by) VALUES (?, ?, ?, ?, ?, ?, NULL)'
);
$id->execute([$name, $phone, $email, $source, $message, 'novy']);

echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId()]);
