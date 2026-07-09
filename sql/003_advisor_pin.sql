-- PIN prihlásenie poradcov + globálne priehradenie pokusov o uhádnutie PIN.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).

ALTER TABLE formulare_advisors ADD COLUMN pin_hash VARCHAR(255) NULL AFTER color;

CREATE TABLE IF NOT EXISTS formulare_login_throttle (
  scope VARCHAR(40) PRIMARY KEY,
  fail_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
