-- Viacdňové udalosti v Tímovom kalendári — pridáva end_date (koniec rozsahu,
-- NULL/rovnaký ako event_date = jednodňová udalosť, nemení sa správanie
-- existujúcich záznamov). Spustiť RUČNE v phpMyAdmin.

ALTER TABLE formulare_team_events ADD COLUMN end_date DATE NULL AFTER event_date;
UPDATE formulare_team_events SET end_date = event_date WHERE end_date IS NULL;
