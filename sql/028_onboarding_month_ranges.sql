-- Cesta nováčika: dve nové fázy na konci trasy, pokrývajúce zvyšok Modelu
-- zapracovania (mesiace 6.–24.) — bez konkrétnych školení, len odkaz na
-- priebežný status a výšku DP podľa dosiahnutého statusu.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

SET @base := (SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps);

INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES
('VI.–XII. mesiac', 'Priebežný status a dodatková provízia', 'DP podľa statusu: FIT 500 € · STD 750 € · TOP 1 000 € mesačne (7.–12. mesiac MZ). Pozri si aktuálny status a podrobnosti v Modeli zapracovania.', '/model-zapracovania/', @base + 1),
('XIII.–XXIV. mesiac', 'Priebežný status a dodatková provízia', 'DP podľa statusu: FIT 300 € · STD 500 € · TOP 700 € mesačne (13.–24. mesiac MZ). Pozri si aktuálny status a podrobnosti v Modeli zapracovania.', '/model-zapracovania/', @base + 2);
