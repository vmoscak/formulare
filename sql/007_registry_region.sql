-- Kraj SR odvodený z PSČ (pre filter "len východné Slovensko" a pod.) —
-- dopočítava sa pri importe (registryImport v db.php) z psc-kraj.php,
-- nie ručne. Po tejto migrácii spusti znova import na /nabor, aby sa
-- existujúce záznamy dopočítali.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_registry_entities ADD COLUMN region VARCHAR(40) NULL AFTER parent_names;
ALTER TABLE formulare_registry_entities ADD KEY idx_region (region);
