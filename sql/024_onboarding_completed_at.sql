-- História absolventov Cesty nováčika — dátum dokončenia sa uloží trvalo aj po
-- odobratí priradenia, aby owner mal prehľad o tom, kto onboarding dokončil
-- a kedy. Spustiť RUČNE v phpMyAdmin.

ALTER TABLE formulare_advisors ADD COLUMN onboarding_completed_at DATETIME NULL;
