-- Jednorazová dátová úprava (nie schéma): novo pridaný nástroj
-- "Výplata poistného plnenia" sa mal podľa nového pravidla zobraziť najprv
-- len tebe (owner), nie hneď všetkým poradcom — toto ho dodatočne vypne
-- pre všetkých okrem teba. Ostatných zapneš postupne v admin.php.
--
-- Spustiť RUČNE v phpMyAdmin.

UPDATE formulare_advisors
SET disabled_tools = JSON_ARRAY_APPEND(
  COALESCE(disabled_tools, JSON_ARRAY()),
  '$',
  'ziadost-vyplata-poistneho-plnenia'
)
WHERE (is_owner IS NULL OR is_owner != 1)
  AND (
    disabled_tools IS NULL
    OR JSON_SEARCH(disabled_tools, 'one', 'ziadost-vyplata-poistneho-plnenia') IS NULL
  );
