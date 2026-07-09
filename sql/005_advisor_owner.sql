-- Vlastník appky (majiteľ) — jediný, kto vidí náborovú zónu. Zámerne oddelené
-- od is_admin: aj keby v budúcnosti pribudol ďalší admin, náborovú zónu
-- neuvidí, kým mu is_owner výslovne nenastavíš.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_advisors ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;

-- Nastav is_owner=1 na svojom riadku (uprav WHERE podľa svojho mena/e-mailu):
-- UPDATE formulare_advisors SET is_owner = 1 WHERE email = 'vmfin@vmfin.sk';
