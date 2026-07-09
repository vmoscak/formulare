-- Register inštitúcií/agentov z NBS (náborová zóna) — dáta sa importujú
-- z JSON súboru nahratého cez FTP, nie ručne cez tento SQL súbor.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

CREATE TABLE IF NOT EXISTS formulare_registry_entities (
  ico VARCHAR(20) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255) NOT NULL DEFAULT '',
  city VARCHAR(120) NOT NULL DEFAULT '',
  zip VARCHAR(10) NOT NULL DEFAULT '',
  country VARCHAR(5) NOT NULL DEFAULT 'SK',
  categories TEXT NULL,
  sectors TEXT NULL,
  parent_names TEXT NULL,
  raw_json LONGTEXT NOT NULL,
  lat DECIMAL(10,7) NULL,
  lon DECIMAL(10,7) NULL,
  geocoded_at DATETIME NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_name (name(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
