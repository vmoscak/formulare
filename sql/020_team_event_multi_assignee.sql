-- Viacnásobné priradenie udalosti v Tímovom kalendári (2-3 kolegom naraz,
-- napr. "obchodníci + ja"). Nahrádza jediný stĺpec assigned_advisor_id,
-- ktorý ostáva v tabuľke len kvôli starým riadkom — appka ho už nečíta.
-- Spustiť RUČNE v phpMyAdmin.

CREATE TABLE IF NOT EXISTS formulare_team_event_assignees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  advisor_id INT NOT NULL,
  UNIQUE KEY uniq_event_advisor (event_id, advisor_id),
  KEY idx_event (event_id),
  KEY idx_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Presunie doterajšie jednotlivé priradenia (ak si už nejaké pridal cez
-- predošlú verziu kalendára) do novej tabuľky, nič sa nestratí.
INSERT IGNORE INTO formulare_team_event_assignees (event_id, advisor_id)
SELECT id, assigned_advisor_id FROM formulare_team_events WHERE assigned_advisor_id IS NOT NULL;
