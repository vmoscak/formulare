-- Sledovanie spustených migrácií — základ pre "Databázové migrácie" panel
-- v admin.php, kde ide odteraz spúšťať NOVÉ migrácie priamo z appky namiesto
-- ručného kopírovania do phpMyAdmin. Túto JEDNU migráciu treba, ironicky,
-- spustiť ešte ručne — je to posledná, ktorú tak treba urobiť.
--
-- Po spustení otvor Admin → Databázové migrácie a tlačidlom "Označiť ako už
-- spustené" potvrď všetky súbory 001–035 (appka nemôže vedieť, čo si už
-- reálne spustil v minulosti — musí to potvrdiť človek, ktorý má prístup
-- k phpMyAdmin). Od migrácie 037 vyššie už môžeš používať tlačidlo "Spustiť"
-- priamo v appke.

CREATE TABLE IF NOT EXISTS formulare_schema_migrations (
  filename VARCHAR(160) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  applied_by VARCHAR(120) NOT NULL DEFAULT '',
  note VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
