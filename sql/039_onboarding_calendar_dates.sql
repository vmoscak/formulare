-- Cesta nováčika: kalendárové plánovanie fáz namiesto pevného počtu dní.
-- Nováčik vždy nastupuje k 1. v mesiaci — onboarding_start_date je tento
-- reálny dátum nástupu (nepovinné pole; bez neho fázy bežia po starom,
-- pevným počtom dní od aktivácie). duration_months je dĺžka fázy v celých
-- kalendárnych mesiacoch, používaná len keď má nováčik nastavený nástup.
ALTER TABLE formulare_advisors ADD COLUMN onboarding_start_date DATE NULL AFTER onboarding_started_at;

ALTER TABLE formulare_onboarding_phases ADD COLUMN duration_months INT NOT NULL DEFAULT 1 AFTER duration_days;

UPDATE formulare_onboarding_phases
SET duration_months = CASE WHEN is_ongoing = 1 THEN 0 ELSE GREATEST(1, ROUND(duration_days / 30)) END;
