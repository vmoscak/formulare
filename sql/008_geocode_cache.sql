-- Trvalá cache presného geokódovania adries (OpenStreetMap Nominatim) —
-- prežije reimport (na rozdiel od formulare_registry_entities, ktorá sa pri
-- každom importe kompletne premaže/znovu naplní). Vďaka tomu sa pri ďalšom
-- mesačnom importe znova geokódujú len skutočne nové/zmenené adresy.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

CREATE TABLE IF NOT EXISTS formulare_geocode_cache (
  address_hash CHAR(32) PRIMARY KEY,
  address VARCHAR(255) NOT NULL,
  lat DECIMAL(10,7) NULL,
  lon DECIMAL(10,7) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
