<?php
/**
 * Verejná stránka pre klienta — potvrdenie navrhnutého termínu cez token
 * z e-mailu (odosiela ho leady.php pri akcii "Navrhnúť termín"). Bez
 * gate cookie, vyňatá v .htaccess — rovnaký princíp ako api/*-intake.php.
 */
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

$token = trim((string)($_GET['token'] ?? ''));
$message = 'Neplatný odkaz na potvrdenie termínu.';
$ok = false;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
    $stmt = db()->prepare('SELECT * FROM formulare_bookings WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $booking = $stmt->fetch();
    if ($booking) {
        $status = (string)($booking['status'] ?? '');
        $name = (string)($booking['name'] ?? '');
        $email = (string)($booking['email'] ?? '');
        $topic = (string)($booking['topic'] ?? '');
        $confirmedDate = trim((string)($booking['confirmed_date'] ?? ''));
        $confirmedTime = trim((string)($booking['confirmed_time'] ?? ''));

        if ($status === 'confirmed') {
            $ok = true;
            $message = 'Tento termín už bol potvrdený.';
        } elseif ($status === 'proposed') {
            if ($confirmedDate === '') { $confirmedDate = (string)($booking['preferred_date'] ?? ''); }
            if ($confirmedTime === '') { $confirmedTime = (string)($booking['preferred_time'] ?? ''); }

            db()->prepare('UPDATE formulare_bookings SET status = ?, confirmed_date = ?, confirmed_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute(['confirmed', $confirmedDate ?: null, $confirmedTime ?: null, $booking['id']]);

            sendPortalMail(
                $email,
                '✅ Termín konzultácie je potvrdený | VMfin',
                "Dobrý deň, $name,\n\nďakujem za potvrdenie termínu.\nVaša konzultácia k téme $topic je potvrdená na $confirmedDate o $confirmedTime.\n\nS pozdravom,\nVladimír Moščák\nVMfin"
            );
            sendPortalMail(
                'vmfin@vmfin.sk',
                "✅ Klient potvrdil termín: $name",
                "Klient potvrdil navrhnutý termín.\n\nMeno: $name\nEmail: $email\nTéma: $topic\nPotvrdený termín: $confirmedDate o $confirmedTime\n\nSpravovať: https://portal.vmfin.sk/leady.php?tab=rezervacie",
                $email,
                $name
            );

            $ok = true;
            $message = 'Ďakujem, termín bol úspešne potvrdený.';
        } else {
            $message = 'Tento odkaz už nie je možné použiť.';
        }
    }
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Potvrdenie termínu | VMfin</title>
    <style>
        body{margin:0;font-family:Inter,system-ui,sans-serif;background:#f5f6f8;color:#111827}
        .wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
        .card{max-width:520px;width:100%;background:#fff;border:1px solid #eef0f3;border-radius:16px;padding:26px;box-shadow:0 20px 44px -20px rgba(16,24,40,.24)}
        h1{margin:0 0 10px;font-size:26px}
        p{margin:0 0 16px;line-height:1.6;color:#4b5563}
        .ok{color:#059669}
        .bad{color:#e11d48}
        a.btn{display:inline-block;margin-top:8px;padding:11px 16px;border-radius:10px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:600}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1><?= $ok ? 'Potvrdené' : 'Nepodarilo sa potvrdiť' ?></h1>
        <p class="<?= $ok ? 'ok' : 'bad' ?>"><?= h($message) ?></p>
        <a class="btn" href="https://vmfin.sk/">Späť na vmfin.sk</a>
    </div>
</div>
</body>
</html>
