<?php
/**
 * Verejný endpoint (mimo brány) — klient otvorí odkaz s ?token=...&tool=...
 * Vráti identitu poradcu + meno klienta + prípadné už uložené dáta (rozpracované).
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$token = (string)($_GET['token'] ?? '');
$tool = (string)($_GET['tool'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token) || $tool === '') {
    echo json_encode(['ok' => false]); exit;
}

$stmt = db()->prepare(
    'SELECT cl.client_label, cl.form_data, cl.status, cl.expires_at,
            a.name AS advisor_name, a.org AS advisor_org, a.email AS advisor_email, a.phone AS advisor_phone
     FROM formulare_client_links cl JOIN formulare_advisors a ON a.id = cl.advisor_id
     WHERE cl.token = ? AND cl.tool = ?'
);
$stmt->execute([$token, $tool]);
$row = $stmt->fetch();

if (!$row || ($row['expires_at'] && strtotime($row['expires_at']) < time())) {
    echo json_encode(['ok' => false]); exit;
}

echo json_encode([
    'ok' => true,
    'clientLabel' => $row['client_label'],
    'formData' => $row['form_data'] ? json_decode($row['form_data']) : null,
    'advisor' => [
        'name' => $row['advisor_name'],
        'org' => $row['advisor_org'],
        'email' => $row['advisor_email'],
        'phone' => $row['advisor_phone'],
    ],
]);
