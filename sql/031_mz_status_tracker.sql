-- Model zapracovania: mesačný tracker statusu (FIT/STD/TOP) namiesto
-- plochých krokov "zaznamenaj status" z migrácie 030 — teraz interaktívny
-- výber s výpočtom kvartálneho doplatku DP priamo v Ceste nováčika.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

-- Ak si migráciu 030 už spustil, tento krok odstráni ploché "X. mesiac"
-- kroky (bezpečné spustiť aj keď 030 nikdy nebežala — jednoducho nič nezmaže).
DELETE FROM formulare_onboarding_steps
WHERE phase IN ('VI.–XII. mesiac', 'XIII.–XXIV. mesiac')
  AND (title LIKE '%zaznamenaj dosiahnutý status%' OR title LIKE '%kvartál sa uzatvára%' OR title = 'Kvartálny doplatok DP — ako funguje' OR title = '24. mesiac — posledný mesiac MZ');

CREATE TABLE IF NOT EXISTS formulare_mz_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  advisor_id INT NOT NULL,
  month_number INT NOT NULL,
  status VARCHAR(10) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_advisor_month (advisor_id, month_number),
  KEY idx_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
