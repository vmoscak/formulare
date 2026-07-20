-- Termín ďalšieho stretnutia/kontroly (financna-analyza, sekcia 12) ako
-- samostatný stĺpec — doteraz bol pochovaný len vnútri data (JSON blob),
-- takže sa nedal efektívne vypísať/zoradiť naprieč klientmi. Umožňuje
-- zoznam "Nadchádzajúce kontroly" na Domove aj triedenie v zozname klientov.
ALTER TABLE formulare_fa_clients ADD COLUMN followup_date DATE NULL;
ALTER TABLE formulare_fa_clients ADD INDEX idx_advisor_followup (advisor_id, followup_date);
