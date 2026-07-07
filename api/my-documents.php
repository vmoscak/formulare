<?php
/**
 * Zoznam vygenerovaných dokumentov aktuálneho poradcu pre daný nástroj —
 * umožňuje vrátiť sa k už vygenerovanému PDF (znova načítať jeho dáta).
 * Za bránou (nie je vo verejnom zozname v .htaccess). GET ?tool=financna-medzera
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = isset($_COOKIE['cur_advisor']) ? (int)$_COOKIE['cur_advisor'] : 0;
if (!$advisorId) { echo '[]'; exit; }

$tool = (string)($_GET['tool'] ?? '');
$stmt = db()->prepare(
    'SELECT id, client_label, form_data, generated_at
     FROM formulare_generated_documents WHERE advisor_id = ? AND tool = ? ORDER BY generated_at DESC LIMIT 100'
);
$stmt->execute([$advisorId, $tool]);
echo json_encode($stmt->fetchAll());
