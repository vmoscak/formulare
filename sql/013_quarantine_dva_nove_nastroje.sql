-- Jednorazová dátová úprava (nie schéma) — podľa pravidla z 7/2026: nové
-- nástroje sa pri pridaní rovno vypnú pre všetkých okrem teba (owner).
-- Týka sa: "Vysvetlivky pre klienta" a "Ťahák „Čo pýtať od klienta“".
-- Ostatných poradcov zapneš postupne v admin.php.
--
-- Spustiť RUČNE v phpMyAdmin.

UPDATE formulare_advisors
SET disabled_tools = JSON_ARRAY_APPEND(
  COALESCE(disabled_tools, JSON_ARRAY()),
  '$',
  'vysvetlivky-pre-klienta'
)
WHERE (is_owner IS NULL OR is_owner != 1)
  AND (
    disabled_tools IS NULL
    OR JSON_SEARCH(disabled_tools, 'one', 'vysvetlivky-pre-klienta') IS NULL
  );

UPDATE formulare_advisors
SET disabled_tools = JSON_ARRAY_APPEND(
  COALESCE(disabled_tools, JSON_ARRAY()),
  '$',
  'tahak-co-pytat-od-klienta'
)
WHERE (is_owner IS NULL OR is_owner != 1)
  AND (
    disabled_tools IS NULL
    OR JSON_SEARCH(disabled_tools, 'one', 'tahak-co-pytat-od-klienta') IS NULL
  );
