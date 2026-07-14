<?php
spl_autoload_register(function ($class) {
    $map = [
        'Dompdf\\'        => __DIR__ . '/dompdf/src/',
        'FontLib\\'       => __DIR__ . '/fontlib/FontLib/',
        'Svg\\'           => __DIR__ . '/svglib/Svg/',
        'Masterminds\\'   => __DIR__ . '/html5/',
        'setasign\\Fpdi\\' => __DIR__ . '/setasign/fpdi/src/',
    ];
    foreach ($map as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) continue;
        $file = $dir . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $len)) . '.php';
        if (file_exists($file)) { require $file; return; }
    }
});
require_once __DIR__ . '/dompdf/lib/Cpdf.php';
require_once __DIR__ . '/setasign/tfpdf/tfpdf.php';
require_once __DIR__ . '/setasign/tfpdf/font/unifont/ttfonts.php';
