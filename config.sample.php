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

// Hlavný PIN brány (brana.php) — 4-miestne číslo, napr. '4821' — a hodnota
// cookie, ktorú .htaccess overuje. Osobné PIN kódy jednotlivých poradcov sa
// nastavujú v admin.php a ukladajú hashované v DB (pozri sql/003_advisor_pin.sql).
define('GATE_PASSPHRASE', '0000');
define('GATE_TOKEN', 'nahrad-nahodnym-64-znakovym-retazcom');

// LocationIQ API token (presné geokódovanie v náborovej zóne, len Prešovský
// a Košický kraj) — zaregistruj sa na locationiq.com, token nájdeš v Dashboard.
define('LOCATIONIQ_TOKEN', '');
