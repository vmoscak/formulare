-- Priradenie Cesty nováčika konkrétnemu poradcovi (nie len owner-ovi).
-- Spustiť RUČNE v phpMyAdmin.

ALTER TABLE formulare_advisors ADD COLUMN onboarding_started_at DATETIME NULL;

-- Oprava znenia prvého kroku z migrácie 017 (login netreba spomínať —
-- kým sa poradca neprihlási, checklist beztak nevidí).
UPDATE formulare_onboarding_steps
   SET title = 'Prehľad appky',
       description = 'Prejdi si tri hlavné záložky (Nástroje / Formuláre / Pomôcky) a pozri sa do ľavej lišty — nemusíš si nič zapamätať, len vedieť, kde čo nájsť.'
 WHERE title = 'Prihlásenie a prehľad appky';
