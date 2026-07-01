<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_exception_handler(function($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input = json_decode(file_get_contents('php://input'), true);
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
