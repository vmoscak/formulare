-- Kedy sa daný záznam (IČO) prvýkrát objavil v importe — na odlíšenie
-- "nových od posledného importu" na mape (zelená bodka namiesto modrej).
-- Pri reimporte (registryImport v db.php) sa prevezme zo starého záznamu,
-- ak IČO už predtým existovalo; ak je nové, nastaví sa na čas tohto importu.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).
--
-- Druhý riadok (UPDATE) je zámerne súčasťou tejto migrácie: bez neho by
-- prvý import PO nej ukázal úplne všetko ako "nové" (nemáme staršiu
-- históriu na porovnanie). Nastavením first_seen_at na starý dátum (mimo
-- akéhokoľvek reálneho importu) sa všetko, čo existuje TERAZ, ostane
-- "existujúce" (modré) a zelené budú naozaj len budúce, skutočne nové záznamy.

ALTER TABLE formulare_registry_entities ADD COLUMN first_seen_at DATETIME NULL AFTER imported_at;
UPDATE formulare_registry_entities SET first_seen_at = '2000-01-01 00:00:00' WHERE first_seen_at IS NULL;
