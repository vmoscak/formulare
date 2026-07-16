-- Budgetové zľavy — pravidlá editovateľné priamo v appke (owner), keďže sa
-- občas menia (napr. akčná zľava pri majetku neplatí trvalo).
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

CREATE TABLE IF NOT EXISTS formulare_budget_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(20) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  badge_text VARCHAR(255) NULL,
  badge_color VARCHAR(10) NOT NULL DEFAULT 'none',
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formulare_budget_table_rows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(255) NOT NULL,
  effect_text VARCHAR(255) NOT NULL,
  polarity VARCHAR(10) NOT NULL DEFAULT 'neg',
  sort_order INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formulare_budget_meta (
  id INT PRIMARY KEY,
  tip_text TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO formulare_budget_rules (category, title, body, badge_text, badge_color, sort_order) VALUES
('auto', 'Rozsah zľavy', 'Zľavy sa môžu poskytovať v rozsahu 5 % – 20 %.', NULL, 'none', 0),
('auto', 'Zľava do 100 €', 'Automaticky schvaľuje regionálna asistentka, pokiaľ škodovosť klienta nepresiahne 50 % (asistentka to vždy skontroluje). Ak je škodovosť vyššia, posudzuje Daniel Jurčík.', 'Asistentka · pri vyššej škodovosti Daniel Jurčík', 'both', 1),
('auto', 'Zľava nad 100 €', 'Vždy schvaľuje Daniel Jurčík. Do poznámky treba vždy uviesť, prečo pýtame takú zľavu a aké poistné zmluvy klient u nás má — nestačí napísať iba „viaczmluvný klient“. Dôležité je, aby mal zmluvy ako Majetok, Život, Dôchodok alebo Tempo (nie CP alebo iné auto).', 'Schvaľuje: Daniel Jurčík', 'daniel', 2),
('auto', 'Spoluúčasť a budgetová zľava', 'Fix 80 € (0 € spoluúčasť v autorizovanom servise) — budgetová zľava sa nebude schvaľovať. Fix 200 € — budgetová zľava sa bude schvaľovať.', NULL, 'none', 3),
('auto', 'Nízke poistné na PZP', 'Ak na PZP vychádza poistné menej ako 100 €, budgetová zľava bude zamietnutá. Netýka sa prívesných vozíkov a motocyklov.', NULL, 'none', 4),
('auto', 'UW zľava pri vysokej poistnej sume', 'Pri poistnej sume nad 100 000 € je možné opätovne požiadať o UW zľavu.', NULL, 'none', 5),
('majetok', 'Maximálna zľava z budgetu', 'Maximálna zľava z budgetu je 10 % (pokiaľ nebolo možné dať akčnú zľavu).', 'Schvaľuje: asistentka', 'asist', 0),
('majetok', 'Súbeh s akčnou zľavou', 'Pri použití akčnej zľavy 10 % (aktuálne prebieha kampaň) je maximálna možná budgetová zľava 5 %.', 'Schvaľuje: asistentka', 'asist', 1),
('majetok', 'Rozsah zľavy', 'Zľavy sa môžu poskytovať v rozsahu 5 % – 20 %. Zľavy vyššie ako 10 % sa budú odsúhlasovať iba v prípadoch nižšie.', 'Vyššie ako 10 %: vždy Daniel Jurčík', 'daniel', 2),
('majetok', 'Zľava vyššia ako 10 %', 'Napr. povodňové zóny, konkurenčná ponuka, VIP klient, klient si naraz poisťuje viac nehnuteľností… Dôvod treba napísať do poznámky — schvaľuje sa individuálne podľa toho, čo klient u nás má poistené, podobne ako pri autopoistení.', 'Schvaľuje: Daniel Jurčík', 'daniel', 3);

INSERT INTO formulare_budget_table_rows (label, effect_text, polarity, sort_order) VALUES
('Fix 80 €', 'cca +30 % prirážka', 'neg', 0),
('Fix 200 €', 'cca +17 % prirážka', 'neg', 1),
('5 %, max 200 €', 'cca +3 % prirážka', 'neg', 2),
('Fix 400 €', 'cca −18 % (zľava z poistného, akoby zľava z budgetu)', 'pos', 3),
('Spoluúčasť mladého vodiča', 'zníženie poistného o desiatky €', 'pos', 4);

INSERT INTO formulare_budget_meta (id, tip_text) VALUES
(1, 'Spoluúčasť fix 400 € (320 € v autorizovanom servise) je spôsob, ako znížiť poistné pre klienta aj v prípade, že je budget minutý. V niektorých prípadoch to klient bez problémov zoberie.');
