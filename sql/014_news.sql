-- Novinky (oznamy pre poradcov) — editovať smie výhradne owner, zobrazujú sa
-- ako banner na hlavnej stránke (výber poradcu, pred prihlásením).
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

CREATE TABLE IF NOT EXISTS formulare_news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  important TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
