-- Chýbajúce indexy pre časté dopyty "moje dokumenty / moje odkazy zoradené
-- podľa dátumu" (moje-dokumenty.php, admin.php, odporúčania na Domove).
-- Doteraz mal advisor_id index len cez FK, ale ORDER BY stĺpec dátumu
-- musel appka dopočítať filesortom. Pri stovkách/tisícoch záznamov na
-- poradcu to začne byť citeľné. Spustiť RUČNE v phpMyAdmin.

ALTER TABLE formulare_generated_documents
  ADD INDEX idx_gendocs_advisor_date (advisor_id, generated_at);

ALTER TABLE formulare_client_links
  ADD INDEX idx_clientlinks_advisor_date (advisor_id, created_at);
