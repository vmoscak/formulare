-- Cesta nováčika (onboarding checklist, zatiaľ len pre owner) + Spoločný
-- kalendár dôležitých termínov (viditeľný pre všetkých, editovať smie
-- výhradne owner). Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce
-- migrácie).

CREATE TABLE IF NOT EXISTS formulare_onboarding_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phase VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  link_url VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formulare_onboarding_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  advisor_id INT NOT NULL,
  step_id INT NOT NULL,
  done_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_advisor_step (advisor_id, step_id),
  KEY idx_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS formulare_team_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_date DATE NOT NULL,
  title VARCHAR(255) NOT NULL,
  note TEXT NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Predvolená osnova Cesty nováčika (Deň 1 / Týždeň 1 / Mesiac 1) — owner si
-- vie kroky kedykoľvek upraviť, pridať alebo zmazať priamo na stránke.
INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES
('Deň 1', 'Prihlásenie a prehľad appky', 'Over si prístup do Portálu, prejdi si tri hlavné záložky (Nástroje / Formuláre / Pomôcky) a pozri sa do ľavej lišty.', '/nastroje.php', 0),
('Deň 1', 'Znalostná báza', 'Prelistuj si interné FAQ a rýchle texty — nemusíš si nič pamätať naspamäť, appka to má pripravené na kopírovanie.', '/znalostna-baza.php', 1),
('Týždeň 1', 'Vyskúšaj Kalkulačku finančnej medzery', 'Prejdi si nanečisto celý formulár aj s výstupom (checklist, PDF) — na testovacích číslach, nie na reálnom klientovi.', '/financna-medzera/', 2),
('Týždeň 1', 'Precvič si Vybavovača námietok', 'Prejdi si typické námietky klientov („je to drahé“, „musím si to premyslieť“...) a odporúčané reakcie.', '/vybavovac-namietok/', 3),
('Týždeň 1', 'Argument Builder', 'Vyskúšaj si poskladať argumentáciu pre 2-3 rôzne typy klientov, nech vidíš, ako appka odporúča postupovať.', '/argument-builder/', 4),
('Týždeň 1', 'Prejdi si Pyramídu istoty', 'Pochop poradie, v akom sa buduje finančná istota klienta — ochrana, rezerva, ciele, zhodnocovanie.', '/pyramida-istoty/', 5),
('Mesiac 1', 'Prvé skutočné stretnutie s klientom', 'Ideálne so skúsenejším kolegom vedľa seba (shadowing) alebo aspoň s jeho spätnou väzbou hneď po stretnutí.', NULL, 6),
('Mesiac 1', 'Prvých 30 dní po podpise', 'Over si, ako appka pripraví kartičku pre klienta hneď po podpise zmluvy — čo nasleduje, kedy začína platiť krytie.', '/prvych-30-dni/', 7),
('Mesiac 1', 'Skús Poistný semafor a Simulátor krátenia plnenia', 'Dva argumentačné nástroje, ktoré sa oplatí mať poruke pri rozhovore o výške krytia.', '/poistny-semafor/', 8),
('Mesiac 1', 'Skontroluj si Moje dokumenty', 'Pozri sa, čo všetko si za prvý mesiac vygeneroval — dobrý spôsob, ako vidieť vlastný pokrok.', '/moje-dokumenty.php', 9);
