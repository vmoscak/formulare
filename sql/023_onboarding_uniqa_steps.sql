-- Nahradenie osnovy "Cesta nováčika" pôvodným zjednodušeným obsahom (Deň 1 /
-- Týždeň 1 / Mesiac 1) curovaným výberom z oficiálnej "Karty výkonnosti
-- a rozvoja VFA 2025" (UNIQA onboarding). Spustiť RUČNE v phpMyAdmin.
--
-- POZOR: zmaže existujúce kroky aj rozpracovaný postup (odškrtnuté kroky)
-- všetkých poradcov, ktorí majú onboarding spustený — po tejto migrácii
-- začínajú s novou osnovou od nuly.

DELETE FROM formulare_onboarding_progress;
DELETE FROM formulare_onboarding_steps;

INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES
('Pred nástupom', 'Podpis zmluvy o obchodnom zastúpení', '', NULL, 0),
('Pred nástupom', 'Registrácia e-learning UNIQA', '', NULL, 1),
('Pred nástupom', 'IT systémy — pridelenie prístupových práv', 'Albert, UNIHUB, HCL.', NULL, 2),
('Pred nástupom', 'Registrácia e-learning SLASPO', 'Podľa podpísanej zmluvy o OZ a návodu na e-learning.', NULL, 3),
('Pred nástupom', 'OFV/SLASPO štúdium pre sektory', 'Poistenie a zaistenie — pred vstupným školením. SDS, DDS a Kapitálový trh (KT) — do konca 0. mesiaca.', NULL, 4),
('Pred nástupom', 'Povinné kurzy UNIQA Studio (e-learning)', 'Ochrana osobných údajov (GDPR), Compliance, Fraud management, Informačná bezpečnosť.', NULL, 5),
('Pred nástupom', 'UNIHUB — ochutnávka', 'Predstavenie systému na dojednávanie a servis poistných zmlúv klienta.', NULL, 6),
('0. mesiac', 'Prvé kroky v UNIQA — úvodné školenie', 'Poisťovacia abeceda, informácie o spoločnosti, firemná kultúra, systém vzdelávania, moja vízia v UNIQA.', NULL, 7),
('0. mesiac', 'Štart I. Životné poistenie — povinné samoštúdium pred štartom', 'Život&Radosť.', NULL, 8),
('0. mesiac', 'Štart I. Životné poistenie — aktívna účasť a samoštúdium po štarte', 'Prezenčne, 1 deň. Život&Radosť a pripoistenia — parametre, výhody, práca v UNIHUB.', NULL, 9),
('0. mesiac', 'IT systémy — školenie a nastavenie', 'UNIHUB, Albert, UNIQA Studio, UNIPOINT, HCL. Tréning kalkulácie ponúk.', NULL, 10),
('0. mesiac', 'Štart II. Autá — samoštúdium a príprava ponuky pred kurzom', '', NULL, 11),
('0. mesiac', 'Štart II. Autá — aktívna účasť a samoštúdium po štarte', 'Online. PZP a KASKO — technické parametre a spôsob dojednania.', NULL, 12),
('0. mesiac', 'Databáza klientov — min. 50 kontaktov v CRM', '', NULL, 13),
('I. mesiac', 'Štart III. Úvod do predaja — aktívna účasť a samoštúdium', 'Prezenčne, 1 deň. Podmienka: min. 300 bodov za školenia. Prehľad produktov, filozofia životného poistenia, telefonovanie a zvládanie námietok, analýza potrieb klienta.', NULL, 14),
('I. mesiac', 'Štart IV. On-line majetok — samoštúdium, účasť a samoštúdium po štarte', 'Online, 1 deň. Domov a bezpečie — technické parametre, terminológia, kalkulácia ponuky.', NULL, 15),
('I. mesiac', 'Štart V. Poistenie osôb — samoštúdium, účasť a samoštúdium po štarte', 'Prezenčne/online, 1 deň. Cestovné poistenie, pohrebné náklady, Uniqáčik.', NULL, 16),
('I. mesiac', 'Štart VI. Dôchodky — samoštúdium, účasť a samoštúdium po štarte', 'Prezenčne, 1 deň. Starobné dôchodkové sporenie — SDS, DDS.', NULL, 17),
('II. mesiac', 'UNIPOINT — povinné štúdium pred Blokom Majetkové poistenie', 'Domov a bezpečie.', NULL, 18),
('II. mesiac', 'Blok I. Majetkové poistenie — splniť podmienky pred blokom', 'Účasť na Štarte IV, produkcia 1500 Pb, min. 600 bodov za školenia.', NULL, 19),
('II. mesiac', 'Blok I. Majetkové poistenie — aktívna účasť a samoštúdium po bloku', 'Prezenčne, 3 dni. Filozofia majetkového poistenia, technické parametre D&B, návrh správnych poistných súm, práca v UNIPOINT a CRM.', NULL, 20),
('III. mesiac', 'UNIPOINT — príprava pred Blokom II', 'Životné poistenie, SDS, Tempo — opakovanie produktov a základné informácie.', NULL, 21),
('III. mesiac', 'Blok II. Životné poistenie, SDS, PF — splniť podmienky pred blokom', 'Absolvovanie všetkých školení z 0.-2. mesiaca, zopakovanie nastavenia poistných súm v ŽP, produkcia 3000 Pb.', NULL, 22),
('III. mesiac', 'Blok II. Životné poistenie — aktívna účasť a samoštúdium po bloku', 'Prezenčne, 3 dni. Filozofia a zmysel životného poistenia, finančná matematika, nastavenie poistných súm v UNIPOINT, SDS, Tempo, investovanie.', NULL, 23),
('IV. mesiac', 'Blok III. Predaj — splniť podmienky pred blokom', 'Absolvovanie všetkých školení z 0.-3. mesiaca, zopakovanie produktov životného a neživotného poistenia, produkcia 4000 Pb.', NULL, 24),
('IV. mesiac', 'Blok III. Predaj — aktívna účasť a samoštúdium', 'Prezenčne, 3 dni. Vstup do sveta klienta, analýza potrieb, efektívna argumentácia, riešenie námietok, uzatváracie techniky, príprava na maturitu.', NULL, 25),
('V. mesiac', 'Príprava na maturitu', 'Štúdium produktovej časti maturity vrátane skúšobných testov.', NULL, 26),
('V. mesiac', 'Maturita', 'Podmienka: absolvovanie všetkých školení z 0.-4. mesiaca. Overenie produktových znalostí a predajných zručností.', NULL, 27),
('Priebežne (každý mesiac)', 'Potenciál biznisu — min. 50 kontaktov v CRM', '', NULL, 28),
('Priebežne (každý mesiac)', 'Telefonovanie — min. 25 denne', '', NULL, 29),
('Priebežne (každý mesiac)', 'Stretnutia s klientom — 2-3 denne', '', NULL, 30),
('Priebežne (každý mesiac)', 'Príprava minimálne 10 analýz', '', '/wizard-poistenie/', 31),
('Priebežne (každý mesiac)', 'Kontaktovanie klienta a analýza klientových potrieb', 'Zisti, čo klient potrebuje, a spočítaj presné krytie.', '/financna-medzera/', 32);
