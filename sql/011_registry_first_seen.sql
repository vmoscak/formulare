-- Kedy sa daný záznam (IČO) prvýkrát objavil v importe — na odlíšenie
-- "nových od posledného importu" na mape (zelená bodka namiesto modrej).
-- Pri reimporte (registryImport v db.php) sa prevezme zo starého záznamu,
-- ak IČO už predtým existovalo; ak je nové, nastaví sa na čas tohto importu.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).
--
-- Pozor: keďže existujúce záznamy zatiaľ nemajú first_seen_at vyplnené,
-- prvý import PO tejto migrácii ukáže úplne všetko ako "nové" (nemáme
-- staršiu históriu na porovnanie) — od druhého importu už to bude presné.

ALTER TABLE formulare_registry_entities ADD COLUMN first_seen_at DATETIME NULL AFTER imported_at;
