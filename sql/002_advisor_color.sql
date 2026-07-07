-- Farebné rozlíšenie poradcov — spustiť RUČNE v phpMyAdmin (rovnako ako 001_init.sql).
-- Bezpečné spustiť aj opakovane vďaka kontrole existencie stĺpca nižšie nie je potrebná,
-- ak stĺpec ešte neexistuje — v tom prípade spustite len samotný ALTER TABLE.

ALTER TABLE formulare_advisors ADD COLUMN color VARCHAR(7) NOT NULL DEFAULT '#1f5fd1' AFTER phone;

UPDATE formulare_advisors SET color = '#2563eb' WHERE name = 'Vladimír Moščák';
UPDATE formulare_advisors SET color = '#0d9488' WHERE name = 'Miroslava Vaňová';
UPDATE formulare_advisors SET color = '#b45309' WHERE name = 'Milan Haluška';
UPDATE formulare_advisors SET color = '#6d28d9' WHERE name = 'Kamil Polivčak';
UPDATE formulare_advisors SET color = '#be185d' WHERE name = 'Ľuboš Šimčisko';
