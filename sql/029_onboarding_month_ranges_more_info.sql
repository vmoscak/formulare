-- Cesta nováčika: doplnenie viac informácií do posledných dvoch fáz
-- (VI.–XII. mesiac, XIII.–XXIV. mesiac) — MATURITA termíny a vrátenie DP
-- pri ukončení zmluvy, priamo z Modelu zapracovania 2026.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

SET @base := (SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps);

INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES
('VI.–XII. mesiac', 'Skúška MATURITA — termíny', 'Ak si MATURITU nezložil do konca 6. mesiaca, DP za 7.–12. mesiac sa kráti o 50 %. Ak nie ani do konca 9. mesiaca, nasleduje automatické trvalé vyradenie z Modelu zapracovania.', NULL, @base + 1),
('VI.–XII. mesiac', 'Vrátenie DP pri ukončení zmluvy', 'Ak zmluvu o obchodnom zastúpení ukončíš v tomto období (7.–24. mesiac MZ), vraciaš 50 % vyplatenej DP za posledných 12 mesiacov.', NULL, @base + 2),
('XIII.–XXIV. mesiac', 'Posledná šanca na MATURITU', 'Ak program adaptácie a skúšku MATURITA nezložíš do konca 13. mesiaca od nástupu, spolupráca sa ukončuje.', NULL, @base + 3);
