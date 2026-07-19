-- Cesta nováčika — nový koncept "Mapa cesty a odmeny". Fázy sú teraz
-- samostatné dátové entity s dĺžkou trvania (dni) namiesto skupiny krokov
-- odvodenej len z reťazca `phase`. Postup fázou je automatický podľa
-- uplynutého času od formulare_advisors.onboarding_started_at — appka už
-- nikoho nekontroluje krok po kroku, len ukazuje, čo nováčika čaká a čo
-- za to dostane (Model zapracovania). formulare_onboarding_steps ostáva
-- ako zoznam referenčných materiálov (odkazy/popisy) per fáza — bez
-- per-poradcovho odškrtávania (formulare_onboarding_progress sa už
-- nepoužíva, tabuľka ostáva v DB nedotknutá kvôli historickým dátam).
-- Spustiť RUČNE v phpMyAdmin.

CREATE TABLE IF NOT EXISTS formulare_onboarding_phases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(10) NOT NULL DEFAULT '📍',
  sort_order INT NOT NULL DEFAULT 0,
  duration_days INT NOT NULL DEFAULT 30,
  is_ongoing TINYINT(1) NOT NULL DEFAULT 0,
  reward_text TEXT NOT NULL,
  support_text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE formulare_onboarding_steps ADD COLUMN phase_id INT NULL AFTER phase;

INSERT INTO formulare_onboarding_phases (name, icon, sort_order, duration_days, is_ongoing, reward_text, support_text) VALUES
('Pred nástupom', '📋', 0, 7, 0, '', 'Papierovanie a školenia na začiatku vyzerajú ako veľa — a naozaj toho je veľa. Netreba to zvládnuť dokonale na prvýkrát. Ak si niečím neistý, opýtaj sa — presne na to sú tu kolegovia aj tvoj manažér.'),
('0. mesiac', '🌱', 1, 30, 0, '', 'Prvý mesiac je o učení sa veľa nového naraz. Je úplne normálne, že si na začiatku neistý — nikto od teba nečaká, že to vieš hneď. Manažér aj skúsenejší kolegovia ti radi pomôžu, stačí sa ozvať.'),
('I. mesiac', '🧭', 2, 30, 0, '', 'Ak máš pocit, že iní to majú jednoduchšie, nemajú — každý si prešiel rovnakou krivkou učenia. Pýtaj sa toľko, koľko potrebuješ, nie je to znak slabosti.'),
('II. mesiac', '💬', 3, 30, 0, '', 'Blok Predaj je o skutočnom rozhovore s klientom — analýza potrieb, argumentácia, zvládanie námietok, uzatváracie techniky. Netreba to zvládnuť dokonale hneď, tieto zručnosti sa budujú praxou. Ak chceš niečo nacvičiť nanečisto, kolegovia aj manažér radi pomôžu.'),
('III. mesiac', '❤️', 4, 30, 0, '', 'Životné poistenie je srdcom tejto práce a je úplne prirodzené, že práve pri ňom máš najviac otázok. Nie si v tom sám — kolegovia aj manažér ťa podržia.'),
('IV. mesiac', '🏠', 5, 30, 0, '', 'Majetkové poistenie je technickejšia oblasť a prvé ponuky bývajú pomalšie — to je v poriadku. Radšej sa spýtaj vopred, než aby si sa s tým trápil sám.'),
('V. mesiac', '🎓', 6, 30, 0, '', 'Posledný krok pred maturitou. Ver si — dostal si sa sem vlastnou prácou. A ak sa niečo nepodarí na prvý pokus, nie je to koniec sveta.'),
('VI.–XII. mesiac', '📈', 7, 210, 0, 'DP podľa statusu: FIT 500 € · STD 750 € · TOP 1 000 € mesačne.', 'Školenia sú za tebou — teraz ide hlavne o pravidelnosť. Status FIT/STD/TOP sa vyhodnocuje priebežne, takže sa oplatí sledovať ho každý mesiac.'),
('XIII.–XXIV. mesiac', '🏆', 8, 360, 0, 'DP podľa statusu: FIT 300 € · STD 500 € · TOP 700 € mesačne.', 'Posledná časť Modelu zapracovania. Drž si svoj status a dodatková provízia ide s ním — nič nové sa už neučíš, len pokračuješ v tom, čo už vieš.'),
('Priebežne', '♾️', 9, 0, 1, '', 'Bežná práca poradcu, ktorá pokračuje stále, nezávisle od času vo fáze.');

-- Ak by v produkcii existovala vlastná fáza pridaná ownerom mimo tohto
-- zoznamu (iný názov ako vyššie), vytvorí sa pre ňu záznam s predvolenými
-- hodnotami, nech žiadny materiál neostane bez fázy.
INSERT INTO formulare_onboarding_phases (name, icon, sort_order, duration_days, is_ongoing, reward_text, support_text)
SELECT DISTINCT os.phase, '📍', 100 + os.id, 30, 0, '', ''
FROM formulare_onboarding_steps os
LEFT JOIN formulare_onboarding_phases op ON op.name = os.phase
WHERE op.id IS NULL;

UPDATE formulare_onboarding_steps os
JOIN formulare_onboarding_phases op ON op.name = os.phase
SET os.phase_id = op.id
WHERE os.phase_id IS NULL;
