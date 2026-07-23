<?php
/**
 * Príjem rezervácií z vmfin.sk (web/booking.php) — server-server volanie,
 * rovnaký vzor ako api/leads-intake.php (HMAC podpis, žiadna gate cookie).
 * Token na klientske potvrdenie termínu sa generuje tu, nie na strane
 * odosielateľa — nedôverujeme externe dodanému tokenu pre bezpečnostne
 * citlivú vec (kto vie token, vie zmeniť stav rezervácie cez booking-confirm.php).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

function bkiError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { bkiError('Nepovolená metóda.', 405); }
if (!defined('LEADS_API_SECRET') || LEADS_API_SECRET === '') { bkiError('API nie je nakonfigurované.', 503); }

$raw = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_X_LEADS_SIGNATURE'] ?? '');
$expected = hash_hmac('sha256', $raw, LEADS_API_SECRET);
if ($sig === '' || !hash_equals($expected, $sig)) { bkiError('Neplatný podpis.', 401); }

$data = json_decode($raw, true);
if (!is_array($data)) { bkiError('Neplatný vstup.'); }

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { bkiError('Chýba meno alebo platný e-mail.'); }
$phone = trim((string)($data['phone'] ?? ''));
$topic = trim((string)($data['topic'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$meetingType = in_array((string)($data['meeting_type'] ?? ''), ['online', 'osobne'], true) ? (string)$data['meeting_type'] : 'online';
$prefDate = trim((string)($data['preferred_date'] ?? ''));
$prefTime = trim((string)($data['preferred_time'] ?? ''));
if ($prefDate === '' || $prefTime === '') { bkiError('Chýba preferovaný termín.'); }
$altDate = trim((string)($data['alt_date'] ?? '')) ?: null;
$altTime = trim((string)($data['alt_time'] ?? '')) ?: null;

$token = bin2hex(random_bytes(32));

$ins = db()->prepare(
    'INSERT INTO formulare_bookings (name, email, phone, topic, message, meeting_type, preferred_date, preferred_time, alt_date, alt_time, status, token)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([$name, $email, $phone, $topic, $message, $meetingType, $prefDate, $prefTime, $altDate, $altTime, 'pending', $token]);

echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId()]);
