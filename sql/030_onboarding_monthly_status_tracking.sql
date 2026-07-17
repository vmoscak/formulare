-- Cesta nováčika: poznámka o prenose PB z 0. mesiaca + mesačné odškrtávacie
-- kroky "zaznamenaj status" pre VI.–XII. a XIII.–XXIV. mesiac, s výraznejším
-- upozornením v poslednom mesiaci každého kvartálu (kumulatívny doplatok DP).
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

SET @base := (SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps);

INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES
('0. mesiac', 'Produkčné body sa počítajú aj v 0. mesiaci', 'Ak sa ti v 0. mesiaci podarí vygenerovať produkčné body, nepremrhajú sa — pripočítajú sa k 1. mesiacu MZ.', NULL, @base + 1),

('VI.–XII. mesiac', 'Kvartálny doplatok DP — ako funguje', 'MZ sa vyhodnocuje aj kumulatívne po každých 3 mesiacoch. Ak niektorý mesiac kvartálu dosiahneš nižší status, ale v poslednom mesiaci kvartálu vyšší, doplatia ti rozdiel DP za celý kvartál — podľa statusu dosiahnutého v treťom mesiaci.', NULL, @base + 2),
('VI.–XII. mesiac', '6. mesiac — kvartál sa uzatvára', 'Posledný mesiac tohto kvartálu. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 1 000 € navyše.', NULL, @base + 3),
('VI.–XII. mesiac', '7. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 4),
('VI.–XII. mesiac', '8. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 5),
('VI.–XII. mesiac', '9. mesiac — kvartál sa uzatvára', 'Posledný mesiac tohto kvartálu. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 1 000 € navyše.', NULL, @base + 6),
('VI.–XII. mesiac', '10. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 7),
('VI.–XII. mesiac', '11. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 8),
('VI.–XII. mesiac', '12. mesiac — kvartál sa uzatvára', 'Posledný mesiac tohto kvartálu. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 1 000 € navyše.', NULL, @base + 9),

('XIII.–XXIV. mesiac', '13. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 10),
('XIII.–XXIV. mesiac', '14. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 11),
('XIII.–XXIV. mesiac', '15. mesiac — kvartál sa uzatvára', 'Posledný mesiac tohto kvartálu. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 800 € navyše.', NULL, @base + 12),
('XIII.–XXIV. mesiac', '16. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 13),
('XIII.–XXIV. mesiac', '17. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 14),
('XIII.–XXIV. mesiac', '18. mesiac — kvartál sa uzatvára', 'Posledný mesiac tohto kvartálu. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 800 € navyše.', NULL, @base + 15),
('XIII.–XXIV. mesiac', '19. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 16),
('XIII.–XXIV. mesiac', '20. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 17),
('XIII.–XXIV. mesiac', '21. mesiac — kvartál sa uzatvára', 'Posledný mesiac tohto kvartálu. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 800 € navyše.', NULL, @base + 18),
('XIII.–XXIV. mesiac', '22. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 19),
('XIII.–XXIV. mesiac', '23. mesiac — zaznamenaj dosiahnutý status', 'Na konci mesiaca si zaznamenaj dosiahnutý status (FIT/STD/TOP).', NULL, @base + 20),
('XIII.–XXIV. mesiac', '24. mesiac — posledný mesiac MZ', 'Posledný mesiac Modelu zapracovania. Ak dosiahneš vyšší status ako v predošlých dvoch mesiacoch, doplatia ti rozdiel za celý kvartál — napr. z FIT na TOP môže znamenať až 800 € navyše.', NULL, @base + 21);
