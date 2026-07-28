-- Prepojenie Rezervácie -> Leady (Kontakty). Poradca môže z prijatej
-- rezervácie stretnutia ručne vytvoriť leada (tlačidlo v leady.php,
-- tab Rezervácie) — lead_id sa uloží späť na rezerváciu, aby sa dalo
-- neskôr poznať, že už bola prevedená, a zobraziť jej stav.
-- Spustiť RUČNE v phpMyAdmin (rovnako ako predchádzajúce migrácie).
ALTER TABLE formulare_bookings ADD COLUMN lead_id INT NULL AFTER token;
ALTER TABLE formulare_bookings ADD KEY idx_bookings_lead_id (lead_id);
ALTER TABLE formulare_bookings ADD CONSTRAINT fk_bookings_lead
  FOREIGN KEY (lead_id) REFERENCES formulare_leads(id) ON DELETE SET NULL;
