<?php
/**
 * Vyplní oficiálny UNIQA formulár "Zmena správcu zmluvy na žiadosť klienta"
 * (Príloha 3) — na rozdiel od ../pdf.php (Dompdf, HTML → PDF) tu ide o
 * PRESNÝ pretlač textu na naskenovanú/normalizovanú predlohu (template.pdf)
 * pomocou FPDI + tFPDF. Súradnice polí sú prevzaté priamo z AcroForm polí
 * originálneho PDF (v bodoch, ľavý horný roh) a prepočítané na mm.
 *
 * KDE ČO UPRAVIŤ:
 *   • Predloha .............. template.pdf (nemeniť inak než cez qpdf normalizáciu)
 *   • Súradnice polí ......... nižšie, pole $FIELDS / funkcia rowRects()
 */
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
    // Vendorovaná tFPDF/FPDI knižnica (písaná pre staršie PHP) hlási na PHP 8.4
    // neškodné upozornenia (dynamické vlastnosti, mazanie starého cache
    // súboru cez @unlink, ktorý nemusí existovať) — nesmú zhodiť request.
    if (strpos($errfile, '/vendor/setasign/') !== false) return true;
    if ($errno === E_DEPRECATED || $errno === E_NOTICE || $errno === E_STRICT) return true;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Tfpdf\Fpdi;

function jsonError(string $msg, int $code = 400): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function s($v): string { return trim((string)($v ?? '')); }
function pt2mm(float $pt): float { return $pt * 25.4 / 72; }

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) jsonError('Neplatný vstup');

$client   = $input['client'] ?? [];
$newAdmin = $input['newAdmin'] ?? [];
$rows     = is_array($input['rows'] ?? null) ? $input['rows'] : [];
$reason   = s($input['reason'] ?? '');
$signDateRaw = s($input['signDate'] ?? '');
$signPlace   = s($input['signPlace'] ?? '');
$advisor  = $input['advisor'] ?? [];

if (s($client['name'] ?? '') === '') jsonError('Chýba meno a priezvisko / názov klienta.');

$filledRows = array_values(array_filter($rows, function($r){ return s($r['contractNo'] ?? '') !== ''; }));
if (!$filledRows) jsonError('Zadaj aspoň jedno číslo zmluvy na prevod.');
$filledRows = array_slice($filledRows, 0, 5);

foreach ($filledRows as $r) {
    if (s($newAdmin['name'] ?? '') === '') jsonError('Chýba meno/názov nového správcu.');
    if (s($newAdmin['acquisitionNo'] ?? '') === '') jsonError('Chýba získateľské číslo nového správcu (SFA/VFA).');
    if (s($newAdmin['nbsNo'] ?? '') === '') jsonError('Chýba registračné číslo nového správcu v NBS.');
    break;
}

$signDate = '';
if ($signDateRaw !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $signDateRaw);
    if ($d) $signDate = $d->format('d.m.Y');
}

// -----------------------------------------------------------------------
// Súradnice polí (pt, ľavý horný roh) — prevzaté z AcroForm predlohy.
// -----------------------------------------------------------------------
$FIELDS = [
    'clientName'         => [209.8, 98.6, 560.4, 112.8],
    'clientRepresented'  => [209.8, 111.9, 560.4, 126.0],
    'clientAddress'      => [209.8, 125.9, 560.4, 140.0],
    'clientRcIco'        => [79.4, 141.0, 382.8, 155.2],
    'clientPhone'        => [423.1, 140.5, 560.4, 154.6],
    'clientEmail'        => [79.4, 154.5, 560.4, 168.7],
    'reason'             => [40.0, 483.5, 555.0, 512.0],
    'signDate'           => [102.3, 524.3, 190.2, 538.4],
    'signPlace'          => [257.1, 524.3, 427.1, 538.4],
    'advisorName'        => [107.3, 598.8, 555.6, 612.9],
    'advisorEmail'       => [107.3, 613.0, 555.6, 627.2],
    'advisorCompany'     => [107.3, 627.0, 555.6, 641.2],
];

// Riadky zmlúv (5 na predlohe). Stĺpce sú konzistentné naprieč riadkami
// podľa X-ovej pozície (názvy polí v zdrojovom AcroForme sú v riadku 4
// nekonzistentné/preklepnuté, preto sa spoliehame na pozíciu, nie meno).
$ROW_RECTS = [
    1 => [
        'checks' => ['zp'=>[41.4,310.8], 'nzp'=>[77.9,310.7], 'pof'=>[41.3,320.6], 'sds'=>[77.9,321.2], 'dds'=>[41.1,330.6]],
        'name' => [208.3, 313.0, 340.2, 341.3],
        'contractNo' => [110.2, 326.8, 206.6, 341.0],
        'acquisitionNo' => [340.2, 326.9, 416.7, 341.1],
        'personalNo' => [418.1, 326.8, 493.2, 341.0],
        'nbsNo' => [494.6, 326.8, 555.8, 341.0],
    ],
    2 => [
        'checks' => ['zp'=>[41.2,342.4], 'nzp'=>[77.7,342.3], 'pof'=>[41.1,352.2], 'sds'=>[77.7,352.8], 'dds'=>[41.2,362.2]],
        'name' => [208.3, 344.4, 340.2, 372.6],
        'contractNo' => [110.2, 358.1, 206.6, 372.3],
        'acquisitionNo' => [340.2, 357.5, 416.7, 371.7],
        'personalNo' => [418.1, 357.4, 493.2, 371.6],
        'nbsNo' => [494.6, 358.4, 555.6, 371.3],
    ],
    3 => [
        'checks' => ['zp'=>[41.2,373.3], 'nzp'=>[77.7,373.2], 'pof'=>[41.1,383.1], 'sds'=>[77.6,383.7], 'dds'=>[40.9,393.1]],
        'name' => [208.3, 375.5, 340.2, 403.7],
        'contractNo' => [110.8, 389.5, 207.2, 403.7],
        'acquisitionNo' => [340.2, 389.6, 416.7, 403.8],
        'personalNo' => [418.1, 389.5, 493.2, 403.7],
        'nbsNo' => [494.6, 389.5, 555.6, 403.7],
    ],
    4 => [
        'checks' => ['zp'=>[41.4,404.7], 'nzp'=>[78.0,404.5], 'pof'=>[41.4,414.5], 'sds'=>[77.9,415.1], 'dds'=>[41.2,424.4]],
        'name' => [208.3, 406.2, 340.2, 434.4],
        'contractNo' => [110.5, 420.2, 206.8, 434.4],
        'acquisitionNo' => [340.2, 420.3, 416.7, 434.5],
        'personalNo' => [418.1, 420.2, 493.2, 434.4],
        'nbsNo' => [494.6, 420.2, 555.6, 434.4],
    ],
    5 => [
        'checks' => ['zp'=>[41.4,435.7], 'nzp'=>[78.0,435.6], 'pof'=>[41.3,445.6], 'sds'=>[77.9,446.2], 'dds'=>[41.1,455.5]],
        'name' => [208.3, 437.6, 340.2, 465.8],
        'contractNo' => [110.8, 451.6, 207.1, 465.8],
        'acquisitionNo' => [340.2, 451.7, 416.7, 465.9],
        'personalNo' => [418.1, 451.6, 493.2, 465.8],
        'nbsNo' => [494.6, 451.6, 555.6, 465.8],
    ],
];

$pdf = new Fpdi();
$pdf->setSourceFile(__DIR__ . '/template.pdf');
$tplId = $pdf->importPage(1);
$size = $pdf->getTemplateSize($tplId);
$pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
$pdf->useTemplate($tplId);
$pdf->SetAutoPageBreak(false);

$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);
$pdf->SetTextColor(15, 15, 15);

function writeCell(Fpdi $pdf, array $rect, string $text, float $size = 9, bool $bold = false): void {
    if ($text === '') return;
    $x0 = pt2mm($rect[0]); $y0 = pt2mm($rect[1]);
    $x1 = pt2mm($rect[2]); $y1 = pt2mm($rect[3]);
    $pdf->SetFont('DejaVu', $bold ? 'B' : '', $size);
    // jemné zvislé odsadenie od hornej hrany bunky
    $pdf->SetXY($x0 + 0.5, $y0 + 0.6);
    $pdf->Cell($x1 - $x0 - 1, $y1 - $y0, $text, 0, 0, 'L');
}

// Počet riadkov, na ktoré by sa text zalomil pri danej šírke bunky a fonte
// (rovnaký algoritmus ako interné MultiCell/_dochunksplit, len bez kreslenia).
function countWrappedLines(Fpdi $pdf, float $widthMm, string $text): int {
    $words = preg_split('/\s+/', trim($text));
    $lines = 1; $lineText = '';
    foreach ($words as $w) {
        $candidate = $lineText === '' ? $w : $lineText . ' ' . $w;
        if ($pdf->GetStringWidth($candidate) > $widthMm && $lineText !== '') {
            $lines++; $lineText = $w;
        } else {
            $lineText = $candidate;
        }
    }
    return max(1, $lines);
}

// Viacriadkové pole s automatickým zmenšením písma, aby sa text zmestil do
// výšky bunky na predlohe (napr. "Dôvod zmeny" má na formulári len pár mm).
function writeMulti(Fpdi $pdf, array $rect, string $text, float $size = 8.3): void {
    if ($text === '') return;
    $x0 = pt2mm($rect[0]); $y0 = pt2mm($rect[1]);
    $x1 = pt2mm($rect[2]); $y1 = pt2mm($rect[3]);
    $widthMm = $x1 - $x0 - 1;
    $heightMm = $y1 - $y0;

    $fs = $size;
    while ($fs >= 6.0) {
        $pdf->SetFont('DejaVu', '', $fs);
        $lineH = ($fs / 72 * 25.4) * 1.15;
        $lines = countWrappedLines($pdf, $widthMm, $text);
        if ($lines * $lineH <= $heightMm || $fs <= 6.0) break;
        $fs -= 0.4;
    }
    $lineH = ($fs / 72 * 25.4) * 1.15;
    $pdf->SetXY($x0 + 0.5, $y0 + 0.3);
    $pdf->MultiCell($widthMm, $lineH, $text, 0, 'L');
}

function writeCheck(Fpdi $pdf, array $xy): void {
    $x = pt2mm($xy[0]); $y = pt2mm($xy[1]);
    $pdf->SetFont('DejaVu', 'B', 8.3);
    $pdf->SetXY($x, $y);
    $pdf->Cell(3.0, 3.0, 'X', 0, 0, 'C');
}

// --- Žiadateľ - klient ---------------------------------------------------
writeCell($pdf, $FIELDS['clientName'], s($client['name'] ?? ''), 9.5);
writeCell($pdf, $FIELDS['clientRepresented'], s($client['representedBy'] ?? ''), 9);
writeCell($pdf, $FIELDS['clientAddress'], s($client['address'] ?? ''), 9);
writeCell($pdf, $FIELDS['clientRcIco'], s($client['rcIco'] ?? ''), 9);
writeCell($pdf, $FIELDS['clientPhone'], s($client['phone'] ?? ''), 9);
writeCell($pdf, $FIELDS['clientEmail'], s($client['email'] ?? ''), 9);

// --- Riadky zmlúv ---------------------------------------------------------
$newAdminName = s($newAdmin['name'] ?? '');
$acquisitionNo = s($newAdmin['acquisitionNo'] ?? '');
$personalNo = s($newAdmin['personalNo'] ?? '');
$nbsNo = s($newAdmin['nbsNo'] ?? '');

foreach ($filledRows as $i => $row) {
    $rr = $ROW_RECTS[$i + 1];
    $contractNo = s($row['contractNo'] ?? '');
    if ($contractNo === '') continue;

    foreach (['zp','nzp','pof','sds','dds'] as $ck) {
        if (!empty($row[$ck])) writeCheck($pdf, $rr['checks'][$ck]);
    }
    writeMulti($pdf, $rr['name'], $newAdminName, 8.3);
    writeCell($pdf, $rr['contractNo'], $contractNo, 8.6);
    writeCell($pdf, $rr['acquisitionNo'], $acquisitionNo, 8.6);
    writeCell($pdf, $rr['personalNo'], $personalNo, 8.6);
    writeCell($pdf, $rr['nbsNo'], $nbsNo, 8.6);
}

// --- Dôvod zmeny ------------------------------------------------------
writeMulti($pdf, $FIELDS['reason'], $reason, 8.6);

// --- Podpisy ------------------------------------------------------------
writeCell($pdf, $FIELDS['signDate'], $signDate, 9);
writeCell($pdf, $FIELDS['signPlace'], $signPlace, 9);

// --- Žiadosť klienta prevzal (sprostredkovateľ) --------------------------
writeCell($pdf, $FIELDS['advisorName'], s($advisor['name'] ?? ''), 9);
writeCell($pdf, $FIELDS['advisorEmail'], s($advisor['email'] ?? ''), 9);
writeCell($pdf, $FIELDS['advisorCompany'], s($advisor['company'] ?? ''), 9);

$filename = 'Zmena_spravcu_zmluvy_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $client['name'] ?? 'klient');
$pdf->Output('D', $filename . '.pdf');
