<?php
/**
 * Finančná analýza — zdieľaná databáza klientov (namiesto len localStorage
 * na jednom PC). Scoped podľa advisor_id, rovnaký vzor ako ostatné /api/*.php
 * (cur_advisor cookie cez curAdvisorId()).
 *
 * GET  ?action=list          -> zoznam klientov (meta pre riadky v tabuľke)
 * GET  ?id=<client_id>       -> plné dáta jedného klienta
 * POST {id, meta, data}      -> uloženie (insert alebo update)
 * POST {action:'delete', id} -> vymazanie
 */
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$advisorId = curAdvisorId();
if (!$advisorId) { http_response_code(403); echo '{"ok":false}'; exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id !== '') {
        $stmt = db()->prepare('SELECT data, updated_at FROM formulare_fa_clients WHERE advisor_id = ? AND client_id = ?');
        $stmt->execute([$advisorId, $id]);
        $row = $stmt->fetch();
        if (!$row) { echo 'null'; exit; }
        echo json_encode(['savedAt' => $row['updated_at'], 'data' => json_decode($row['data'], true) ?: []]);
        exit;
    }

    // followup_date je novší stĺpec (sql/042) — kým sa migrácia nespustí na
    // produkcii, radšej vrátiť zoznam bez neho než celý zoznam klientov zhodiť.
    try {
        $stmt = db()->prepare(
            'SELECT client_id, case_name, a_name, b_name, status, updated_at, followup_date
             FROM formulare_fa_clients WHERE advisor_id = ? ORDER BY updated_at DESC'
        );
        $stmt->execute([$advisorId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $stmt = db()->prepare(
            'SELECT client_id, case_name, a_name, b_name, status, updated_at
             FROM formulare_fa_clients WHERE advisor_id = ? ORDER BY updated_at DESC'
        );
        $stmt->execute([$advisorId]);
        $rows = $stmt->fetchAll();
    }
    $out = array_map(function ($r) {
        return [
            'id' => $r['client_id'],
            'name' => $r['case_name'],
            'a' => $r['a_name'],
            'b' => $r['b_name'],
            'status' => $r['status'],
            'updated' => $r['updated_at'],
            'followup' => $r['followup_date'] ?? null,
        ];
    }, $rows);
    echo json_encode($out);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string)($input['action'] ?? 'save');
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') { http_response_code(400); echo '{"ok":false}'; exit; }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM formulare_fa_clients WHERE advisor_id = ? AND client_id = ?')
            ->execute([$advisorId, $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $meta = is_array($input['meta'] ?? null) ? $input['meta'] : [];
    $data = is_array($input['data'] ?? null) ? $input['data'] : [];
    $caseName = (string)($meta['name'] ?? '');
    $aName = (string)($meta['a'] ?? '');
    $bName = (string)($meta['b'] ?? '');
    $status = (string)($meta['status'] ?? '');
    $json = json_encode($data);
    $now = date('Y-m-d H:i:s');
    $followupRaw = trim((string)($data['followup_date'] ?? ''));
    $followupDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $followupRaw) ? $followupRaw : null;

    $stmt = db()->prepare('SELECT id FROM formulare_fa_clients WHERE advisor_id = ? AND client_id = ?');
    $stmt->execute([$advisorId, $id]);
    $existing = $stmt->fetch();

    // followup_date je novší stĺpec (sql/042) — kým sa migrácia nespustí na
    // produkcii, radšej ulož klienta bez neho (dáta ostávajú v data JSON aj
    // tak) než nech celé uloženie zlyhá na chýbajúcom stĺpci.
    try {
        if ($existing) {
            db()->prepare(
                'UPDATE formulare_fa_clients SET case_name=?, a_name=?, b_name=?, status=?, data=?, followup_date=?, updated_at=? WHERE id=?'
            )->execute([$caseName, $aName, $bName, $status, $json, $followupDate, $now, $existing['id']]);
        } else {
            db()->prepare(
                'INSERT INTO formulare_fa_clients (advisor_id, client_id, case_name, a_name, b_name, status, data, followup_date, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$advisorId, $id, $caseName, $aName, $bName, $status, $json, $followupDate, $now]);
        }
    } catch (Throwable $e) {
        if ($existing) {
            db()->prepare(
                'UPDATE formulare_fa_clients SET case_name=?, a_name=?, b_name=?, status=?, data=?, updated_at=? WHERE id=?'
            )->execute([$caseName, $aName, $bName, $status, $json, $now, $existing['id']]);
        } else {
            db()->prepare(
                'INSERT INTO formulare_fa_clients (advisor_id, client_id, case_name, a_name, b_name, status, data, updated_at)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$advisorId, $id, $caseName, $aName, $bName, $status, $json, $now]);
        }
    }
    echo json_encode(['ok' => true, 'savedAt' => $now]);
    exit;
}

http_response_code(405);
echo '{"ok":false}';
