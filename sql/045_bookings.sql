-- Rezervácie stretnutí — nahrádza vmfin_bookings vo vmfin-web. Rezervačný
-- formulár na vmfin.sk sem posiela nové žiadosti cez api/bookings-intake.php,
-- klient potvrdzuje navrhnutý termín cez token v booking-confirm.php.
CREATE TABLE IF NOT EXISTS formulare_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL DEFAULT '',
  topic VARCHAR(120) NOT NULL DEFAULT '',
  message TEXT,
  meeting_type VARCHAR(16) NOT NULL DEFAULT 'online',
  preferred_date DATE NOT NULL,
  preferred_time VARCHAR(10) NOT NULL,
  alt_date DATE NULL,
  alt_time VARCHAR(10) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  admin_note TEXT,
  confirmed_date DATE NULL,
  confirmed_time VARCHAR(10) NULL,
  token VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bookings_token (token),
  KEY idx_bookings_status (status),
  KEY idx_bookings_preferred_date (preferred_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
