<?php
/**
 * Vzor konfigurácie — skopíruj na config.local.php a vyplň skutočné hodnoty.
 * config.local.php sa NIKDY necommituje (je v .gitignore). Na produkcii ho
 * generuje GitHub Actions deploy krok zo secrets (pozri .github/workflows/deploy.yml).
 *
 * Lokálne testovanie: DB_DSN môže byť SQLite súbor namiesto MySQL —
 * napr. 'sqlite:' . __DIR__ . '/tmp/local.sqlite'
 */

define('DB_DSN',  'mysql:host=localhost;dbname=formulare;charset=utf8mb4');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

// Zdieľaná bezpečnostná fráza (brana.php) a hodnota cookie, ktorú .htaccess overuje.
define('GATE_PASSPHRASE', 'zmen-ma');
define('GATE_TOKEN', 'nahrad-nahodnym-64-znakovym-retazcom');
