-- Poradcovský portál — schéma pre MySQL (Websupport hosting).
-- Spustiť RUČNE RAZ cez Websupport phpMyAdmin (GitHub Actions runner sa k tejto
-- databáze priamo nedostane). Lokálne testovanie používa SQLite s rovnakou
-- štruktúrou — pozri dbInit() v db.php.

CREATE TABLE IF NOT EXISTS formulare_advisors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  org VARCHAR(160) NOT NULL DEFAULT '',
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  color VARCHAR(7) NOT NULL DEFAULT '#1f5fd1',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formulare_client_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token CHAR(64) NOT NULL UNIQUE,
  advisor_id INT NOT NULL,
  tool VARCHAR(40) NOT NULL,
  client_label VARCHAR(160) NOT NULL,
  form_data TEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  claimed_at DATETIME NULL,
  expires_at DATETIME NULL,
  FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formulare_generated_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  advisor_id INT NOT NULL,
  client_link_id INT NULL,
  source VARCHAR(16) NOT NULL DEFAULT 'advisor',
  tool VARCHAR(40) NOT NULL,
  client_label VARCHAR(160) NOT NULL,
  form_data TEXT NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (advisor_id) REFERENCES formulare_advisors(id),
  FOREIGN KEY (client_link_id) REFERENCES formulare_client_links(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
