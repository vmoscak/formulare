-- Okres SR odvodený z PSČ (pre filter podľa okresu v Prešovskom/Košickom
-- kraji) — dopočítava sa pri importe (registryImport v db.php) z
-- psc-okres.php, nie ručne. Po tejto migrácii spusti znova import na
-- /nabor.php, aby sa existujúce záznamy dopočítali.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_registry_entities ADD COLUMN okres VARCHAR(40) NULL AFTER region;
ALTER TABLE formulare_registry_entities ADD KEY idx_okres (okres);
