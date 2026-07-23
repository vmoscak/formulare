<?php
declare(strict_types=1);

/**
 * Statické index.html stránky nástrojov (financna-analyza/, wizard-poistenie/,
 * ...) nie sú PHP, takže nemôžu volať asset() z db.php a mať tak vždy čerstvý
 * ?v=NN podľa filemtime — verzia bola v nich ručne zapísaná pri vytvorení
 * a odvtedy sa nehýbala. Keďže zdieľané JS/CSS v /assets majú 30-dňový
 * verejný cache (.htaccess), stará verzia = poradca vidí starý shell.js
 * (napr. premenovanie "Dopyty" na "Kontakty" sa do bočnej lišty na týchto
 * stránkach nepremietlo), kým PHP stránky cez asset() vždy ukazujú aktuálny
 * stav. Tento skript to zjednotí: pred každým deployom prepíše ?v=NN vo
 * všetkých statických index.html stránkach na rovnaké číslo (filemtime),
 * aké by vrátil asset() pre PHP stránky.
 *
 * Spúšťa sa automaticky v CI pred deployom (.github/workflows/deploy.yml).
 * Dá sa spustiť aj ručne: php scripts/sync-static-asset-versions.php
 */

$root = dirname(__DIR__);
$assetsDir = $root . '/assets';

// Assety, na ktoré odkazujú statické index.html nástrojov s ?v=NN.
$watched = [];
foreach (glob($assetsDir . '/*.{js,css}', GLOB_BRACE) as $f) {
    $watched[basename($f)] = filemtime($f) ?: 1;
}

$htmlFiles = glob($root . '/*/index.html');
$changed = 0;

foreach ($htmlFiles as $file) {
    $html = file_get_contents($file);
    if ($html === false) {
        continue;
    }
    $orig = $html;

    foreach ($watched as $name => $mtime) {
        $quoted = preg_quote($name, '#');
        $html = preg_replace(
            '#(/assets/' . $quoted . ')\?v=\d+#',
            '$1?v=' . $mtime,
            $html
        );
    }

    if ($html !== $orig) {
        file_put_contents($file, $html);
        $changed++;
        echo 'Aktualizované: ' . substr($file, strlen($root) + 1) . "\n";
    }
}

echo "Hotovo — zmenených súborov: $changed / " . count($htmlFiles) . "\n";
