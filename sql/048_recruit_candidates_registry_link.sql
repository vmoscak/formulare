-- Voliteľné prepojenie kandidáta na nábor (formulare_recruit_candidates) so
-- záznamom v registri NBS (formulare_registry_entities.ico) — kandidát
-- naďalej NEMUSÍ byť v registri, prepojenie je len pomôcka, keď tam je.
-- Zámerne BEZ FOREIGN KEY: registryImport() pri každom reimporte celú
-- tabuľku formulare_registry_entities zmaže a znova naplní (viď db.php),
-- FK constraint by pri reimporte buď zlyhal, alebo (pri ON DELETE SET NULL)
-- ticho odpojil všetkých kandidátov. IČO sa preto ukladá ako voľný string
-- a zobrazenie v nabor-kandidati.php si samo overí, či záznam ešte existuje.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).
ALTER TABLE formulare_recruit_candidates ADD COLUMN registry_ico VARCHAR(20) NULL AFTER email;
ALTER TABLE formulare_recruit_candidates ADD KEY idx_recruit_registry_ico (registry_ico);
