-- Leady — malý CRM priamo v Portáli (nahrádza starú evidenciu v admin.vmfin.sk,
-- Finančný svet tam už len presmerúva sem). Výhradne pre ownera, rovnaká
-- zásada ako formulare_recruit_candidates (nábor).
CREATE TABLE IF NOT EXISTS formulare_leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  source VARCHAR(32) NOT NULL DEFAULT 'manual',
  message TEXT,
  status VARCHAR(32) NOT NULL DEFAULT 'novy',
  note TEXT,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_leads_status (status),
  FOREIGN KEY (created_by) REFERENCES formulare_advisors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
