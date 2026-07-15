-- Skupina "Priebežne" v Ceste nováčika — bežná práca poradcu bez odškrtávania
-- (kód ju vynecháva z percenta postupu a trailu, viď cesta-novacika.php).
-- Spustiť RUČNE v phpMyAdmin.

SET @base := (SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps);

INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES
('Priebežne', 'Priebežne budovať kontakty', '', NULL, @base + 1),
('Priebežne', 'Kontaktovať klientov', '', NULL, @base + 2),
('Priebežne', 'Vytvárať ponuky', '', NULL, @base + 3),
('Priebežne', 'Pýtať si odporúčania od spokojných klientov', '', NULL, @base + 4),
('Priebežne', 'Sledovať termíny výročí zmlúv a koniec fixácií u klientov', '', NULL, @base + 5),
('Priebežne', 'Udržiavať si prehľad o produktoch a zmenách v ponuke', '', NULL, @base + 6);
