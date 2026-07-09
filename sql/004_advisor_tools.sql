-- Zap./vyp. jednotlivých nástrojov per poradca (admin.php). Hodnota je JSON
-- pole vypnutých slugov, napr. ["vypoved-poistenia","preberaci-protokol"].
-- NULL/prázdne = nič nie je vypnuté (nový nástroj automaticky vidí každý).
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_advisors ADD COLUMN disabled_tools TEXT NULL AFTER pin_hash;
