-- Aktuálne WiFi heslo (kancelária) — mení sa cca raz za 3 mesiace, doteraz
-- sa písalo len ako Novinka (formulare_news), ktorá časom zapadla medzi
-- ostatné oznamy. Samostatná jednoriadková tabuľka, zobrazená natrvalo na
-- Domov (uvod.php), editovateľná len ownerom/adminom v admin.php.
-- Spustiť RUČNE v phpMyAdmin, alebo cez "Databázové migrácie" v admin.php.
CREATE TABLE IF NOT EXISTS formulare_wifi (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    password VARCHAR(100) NOT NULL DEFAULT '',
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO formulare_wifi (id, password) VALUES (1, '');
