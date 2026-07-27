-- Kategórie pre Aktuálne postupy (predtým Znalostná báza) — farebné
-- rozlíšenie podľa témy (životné poistenie, poistenie auta, ...) a základný
-- filter v UI. Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce
-- migrácie).
ALTER TABLE formulare_knowledge_base ADD COLUMN category VARCHAR(32) NOT NULL DEFAULT 'vseobecne';
ALTER TABLE formulare_knowledge_base ADD INDEX idx_kb_category (category);
