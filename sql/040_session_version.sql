-- Okamžité odhlásenie poradcu ownerom/adminom. Cookie cur_advisor teraz
-- v sebe nesie číslo verzie prihlásenia — zvýšením session_version v DB sa
-- všetky doteraz vydané cookie danému poradcovi okamžite stanú neplatnými
-- (bez čakania na ich 24-hodinové vypršanie).
ALTER TABLE formulare_advisors ADD COLUMN session_version INT NOT NULL DEFAULT 0;
