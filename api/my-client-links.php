<?php
/**
 * Zoznam klientskych odkazov aktuálneho poradcu (podľa cur_advisor cookie).
 * Za bránou (nie je vo verejnom zozname v .htaccess). GET ?tool=financna-medzera
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
if (!$advisorId) { echo '[]'; exit; }

$tool = (string)($_GET['tool'] ?? '');
$stmt = db()->prepare(
    'SELECT id, client_label, status, form_data, created_at, submitted_at
     FROM formulare_client_links WHERE advisor_id = ? AND tool = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([$advisorId, $tool]);
echo json_encode($stmt->fetchAll());
