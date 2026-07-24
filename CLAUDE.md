# Portál (formulare) — prehľad pre budúce Claude session

Interný pracovný nástroj poistného poradcu UNIQA Vladimíra Moščáka.
Beží na **portal.vmfin.sk** (za spoločnou "bránou" — jedno heslo pre
všetkých poradcov, `brana.php`). PHP 8.4 + MySQL, žiadny frontend
framework, nasadenie cez GitHub Actions (`.github/workflows/deploy.yml`,
push do `master` = live FTP deploy na Websupport, so syntax checkom a
smoke testom pred deployom).

Čo appka rieši: nástroje/kalkulačky pre klientov, generátor dokumentov
(plnomocenstvá, žiadosti, čestné vyhlásenia), Kontakty (CRM leadov),
Nábor, Tímový kalendár/prehľad, onboarding nováčikov, znalostná báza.
Podrobnejšie nápady/backlog viď `NAPADY.md`.

## Vzťah k druhému systému — vmoscak/vmfin-web

Tento repozitár (Portál) je **úplne samostatný** od `vmoscak/vmfin-web`
(web.vmfin.sk + admin.vmfin.sk — Vladimírove ostatné projekty MATERIA,
WEBIDO, e-shop). Iný repozitár, iná DB, iné prihlásenie. Admin.vmfin.sk
funguje ako vstupný "hub" (dashboard s vizualizáciou uzlov), z ktorého
sa poradca preklikáva aj sem, ale nie je to jeden integrovaný systém.

- **SSO/spoločné prihlásenie medzi Portálom a admin.vmfin.sk zatiaľ
  neexistuje** — plánované do budúcna, zámerne odložené (rozhodnuté
  7/2026 pri architektonickom review oboch systémov). Neimplementovať
  bez explicitného zadania.
- **Kontakty (leady.php v tomto repe) ≠ Firmy (companies.php v
  vmfin-web/sub/admin)** — toto je najdôležitejšia hranica v celom
  systéme, nerozbíjaj ju bez opýtania:
  - **Kontakty** = klienti Finančného sveta (poistenie cez UNIQA).
    **Nikdy sa im nefakturuje** priamo z tohto systému.
  - **Firmy** (vo vmfin-web) = fakturačné subjekty pre MATERIA, WEBIDO
    alebo nezávislé projekty — kŕmi faktúry/CP/DL/ZL v admin.vmfin.sk.
  - Tá istá reálna osoba sa môže objaviť v oboch zoznamoch úplne
    nezávisle (napr. poistný klient sa neskôr stane aj zákazníkom
    MATERIA) — to je v poriadku a **netreba to prepájať ani
    deduplikovať naprieč systémami**. Sú to zámerne dve oddelené
    databázy pre dva odlišné typy vzťahu (provízia od UNIQA vs. vlastná
    faktúra).
- **GDPR poznámka:** appka bola dlho zámerne NEmala byť systém záznamov
  o klientoch (zodpovednosť za spracovanie osobných údajov mala ostať
  na UNIQA/MyPort, nie na Vladimírovi osobne). Toto rozhodnutie bolo
  7/2026 vedome zrušené — Kontakty sa rozširujú o IČO/adresu/zmluvy.
  Detail a dôvod zrušenia viď `NAPADY.md` → "Poznámky k implementácii".
  Ak niekedy navrhuješ ďalšie rozšírenie osobných údajov v Kontaktoch,
  priprav sa na to, že to má reálny GDPR dôsledok (súhlasy klientov) —
  neber to ako bezvýznamnú zmenu schémy.

## Časté omyly z minulých session (nerob ich znova)

- Statické `*/index.html` stránky nástrojov (financna-analyza,
  wizard-poistenie a ďalších ~40) **nie sú PHP** a nemôžu volať
  `asset()` z `db.php`. Zdieľané assety (`shell.js`, `ui.css`,
  `advisor-panel.js`, `toast.js`) tam majú ručne verziované `?v=NN`,
  ktoré sa synchronizuje automaticky pri deployi cez
  `scripts/sync-static-asset-versions.php` (zavolané z CI pred FTP
  mirrorom). Ak meníš tieto zdieľané assety, over si, že sync skript
  beží — nespoliehaj sa na to, že si niekto ručne zvýši číslo.
- Viacero nástrojov (`invoices.php`-štýl stránky cez `zone-head.php`
  partial) **už majú** breadcrumb naspäť cez `$ZONE_CRUMBS` — pred
  pridávaním vlastného "← Späť" odkazu skontroluj `zone-head.php`
  vzor (grep `ZONE_CRUMBS`), nie len či existuje `<h1>`/`back-link`.
