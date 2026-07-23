-- Leady prichádzajúce automaticky z vmfin.sk (api/leads-intake.php) nemajú
-- prihláseného poradcu, ktorý by ich "vytvoril" — created_by preto musí
-- pripúšťať NULL (predtým NOT NULL, sedelo len pre ručne pridané leady).
ALTER TABLE formulare_leads MODIFY created_by INT NULL;
