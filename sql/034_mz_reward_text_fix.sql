-- Oprava textov o odmene (reward_text) pri fázach 0.–V. mesiac — pôvodná
-- migrácia 033 ich nechala prázdne, čo pôsobilo, akoby odmeny z Modelu
-- zapracovania začínali až od 6. mesiaca. Podľa oficiálneho dokumentu
-- "Model zapracovania 2026" (UNIQA) DP existuje už od 0. mesiaca (flat
-- 500 € pri splnení vstupných podmienok) a mesiace 1.–6. sú odmeňované
-- podľa produkčných bodov (PB), nie podľa statusu FIT/STD/TOP — ten platí
-- až od 7. mesiaca. Spustiť RUČNE v phpMyAdmin.

UPDATE formulare_onboarding_phases SET
  reward_text = 'Dodatková provízia (DP) z Modelu zapracovania začína od 0. mesiaca (pozri ďalšiu fázu) — táto fáza je len príprava pred jeho štartom.'
WHERE name = 'Pred nástupom';

UPDATE formulare_onboarding_phases SET
  reward_text = '500 € DP, ak do konca mesiaca splníš: registráciu v NBS (sektor poistenie/zaistenie), e-learning, základné školenia (Prvé kroky v UNIQA, Úvod do predaja, IT, Autá), 100 kontaktov v CRM+ a min. 15 ponúk spolu v Unipoint (Život, Autá, Majetok).'
WHERE name = '0. mesiac';

UPDATE formulare_onboarding_phases SET
  reward_text = 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €. Tracker nájdeš nižšie pri Modeli zapracovania.'
WHERE name = 'I. mesiac';

UPDATE formulare_onboarding_phases SET
  reward_text = 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €.'
WHERE name IN ('II. mesiac', 'III. mesiac', 'IV. mesiac');

UPDATE formulare_onboarding_phases SET
  reward_text = 'DP podľa mesačnej produkcie: 1 200 PB → 500 € · 2 400 PB → 750 € · 3 600 PB → 1 000 €. Do konca 6. mesiaca MZ treba spolu min. 5 000 PB, inak nasleduje vyradenie z Modelu zapracovania.'
WHERE name = 'V. mesiac';

UPDATE formulare_onboarding_phases SET
  reward_text = '6. mesiac ešte podľa produkcie (min. 5 000 PB spolu za 0.–6. mesiac). Od 7. mesiaca DP podľa statusu: FIT 500 € · STD 750 € · TOP 1 000 € mesačne.'
WHERE name = 'VI.–XII. mesiac';
