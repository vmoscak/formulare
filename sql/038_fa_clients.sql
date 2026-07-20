-- Finančná analýza: zdieľaná databáza klientov (predtým len localStorage/súbory
-- v prehliadači na jednom PC). Jeden riadok = jeden klientský prípad, celé dáta
-- formulára uložené ako JSON (rovnaký princíp ako formulare_generated_documents.form_data).
CREATE TABLE IF NOT EXISTS formulare_fa_clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  advisor_id INT NOT NULL,
  client_id VARCHAR(64) NOT NULL,
  case_name VARCHAR(255) NOT NULL DEFAULT '',
  a_name VARCHAR(255) NOT NULL DEFAULT '',
  b_name VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(32) NOT NULL DEFAULT '',
  data LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_advisor_client (advisor_id, client_id),
  KEY idx_advisor_updated (advisor_id, updated_at),
  FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
