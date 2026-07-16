-- Obľúbené nástroje na Domov (hviezdička pri nástroji, JSON zoznam slugov
-- na poradcovi — rovnaký princíp ako existujúci stĺpec disabled_tools).
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_advisors ADD COLUMN favorite_tools TEXT NULL;
