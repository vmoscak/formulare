<?php
require_once __DIR__ . '/db.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

set_exception_handler(function($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    // Nikdy neposielať $e->getMessage()/getFile()/getLine() klientovi — môžu
    // prezradiť cesty na serveri alebo obsah dát. Detail nech si pozrie admin
    // v PHP error logu hostingu, sem ide len generická správa.
    echo json_encode(['error' => 'Generovanie PDF zlyhalo. Skús to znova.']);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// pdf.php je vedome mimo brány (klientske odkazy ho potrebujú bez prihlásenia),
// takže je vystavený celému internetu — jednoduchý limit počtu generovaní za
// minútu (zdieľaná formulare_login_throttle tabuľka) ako poistka proti zneužitiu
// servera na CPU-náročné generovanie cudzieho HTML.
function pdfRateLimitOk(int $maxPerMinute = 40): bool {
    try {
        $pdo = db();
        $scope = 'pdf_' . date('YmdHi');
        $stmt = $pdo->prepare('SELECT fail_count FROM formulare_login_throttle WHERE scope = ?');
        $stmt->execute([$scope]);
        $count = (int)($stmt->fetch()['fail_count'] ?? 0) + 1;
        $pdo->prepare('REPLACE INTO formulare_login_throttle (scope, fail_count, locked_until) VALUES (?, ?, NULL)')
            ->execute([$scope, $count]);
        // Príležitostné upratanie starých minútových vedierok (netreba pri každom requeste).
        if (random_int(1, 20) === 1) {
            $pdo->prepare("DELETE FROM formulare_login_throttle WHERE scope LIKE 'pdf\\_%' ESCAPE '\\\\' AND scope < ?")
                ->execute(['pdf_' . date('YmdHi', time() - 300)]);
        }
        return $count <= $maxPerMinute;
    } catch (Throwable $e) { return true; }
}
if (!pdfRateLimitOk()) { http_response_code(429); echo json_encode(['error' => 'Príliš veľa požiadaviek, skús o chvíľu.']); exit; }

// Text listov generovaných v appke má rádovo desiatky KB — 3 MB je veľkorysá
// hranica, ktorá stopne len zámerné zahltenie servera obrovským telom.
$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 3 * 1024 * 1024) { http_response_code(413); echo json_encode(['error' => 'Vstup je príliš veľký.']); exit; }

$input = json_decode($rawInput, true);
if (empty($input['html'])) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Missing html']);
    exit;
}

$baseDir  = __DIR__;
$tmpDir   = $baseDir . '/tmp';
$fontDir  = $baseDir . '/tmp/fonts';

if (!is_dir($tmpDir))  mkdir($tmpDir,  0775, true);
if (!is_dir($fontDir)) mkdir($fontDir, 0775, true);

// Skopíruj predvolené fonty Dompdf do fontDir
$srcFonts = $baseDir . '/vendor/dompdf/lib/fonts';
if (is_dir($srcFonts)) {
    foreach (glob($srcFonts . '/*.{ttf,ufm,afm,json}', GLOB_BRACE) as $f) {
        $dest = $fontDir . '/' . basename($f);
        if (!file_exists($dest)) @copy($f, $dest);
    }
    $distJson = $srcFonts . '/installed-fonts.dist.json';
    $instJson = $fontDir  . '/installed-fonts.json';
    if (file_exists($distJson) && !file_exists($instJson)) @copy($distJson, $instJson);
}

require_once $baseDir . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->setDefaultFont('DejaVu Sans');
$options->setIsHtml5ParserEnabled(true);
$options->setIsRemoteEnabled(false);
$options->setTempDir($tmpDir);
$options->setFontDir($fontDir);
$options->setFontCache($fontDir);
$options->setChroot($baseDir);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($input['html'], 'UTF-8');
// A4: 595.28 x 841.89 pt, margins in pt (1mm = 2.8346pt)
// 25mm=70.87pt, 22mm=62.36pt, 28mm=79.37pt
$dompdf->setPaper([0, 0, 595.28, 841.89], 'portrait');
$dompdf->render();

$filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $input['filename'] ?? 'vypoved') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
