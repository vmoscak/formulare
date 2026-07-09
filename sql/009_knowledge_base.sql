-- Znalostná báza (interné FAQ / rýchle texty) — prispieva ktokoľvek prihlásený,
-- nie je to obmedzené na admina/vlastníka.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

CREATE TABLE IF NOT EXISTS formulare_knowledge_base (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  advisor_id INT NOT NULL,
  advisor_name VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
