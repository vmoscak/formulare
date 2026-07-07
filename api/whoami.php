<?php
/**
 * Vráti identitu aktuálneho poradcu (podľa cur_advisor cookie) ako JSON,
 * alebo {} ak nie je nastavená / poradca neexistuje / je neaktívny.
 * Volané z nástrojov (financna-medzera, wizard-poistenie) na prepísanie
 * natvrdo napísaného ADVISOR objektu — zlyhanie sa tichoticho ignoruje.
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
if (!$advisorId) { echo '{}'; exit; }

try {
    $stmt = db()->prepare('SELECT id, name, org, email, phone, color FROM formulare_advisors WHERE id = ? AND active = 1');
    $stmt->execute([$advisorId]);
    $advisor = $stmt->fetch();
} catch (Throwable $e) { $advisor = null; }

echo json_encode($advisor ?: (object)[]);
