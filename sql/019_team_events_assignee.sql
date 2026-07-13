-- Tímový kalendár: priradenie udalosti konkrétnemu kolegovi (farebne podľa
-- jeho farby, rovnako ako iniciálky v appke). NULL = celý tím. Spustiť
-- RUČNE v phpMyAdmin.

ALTER TABLE formulare_team_events ADD COLUMN assigned_advisor_id INT NULL;
