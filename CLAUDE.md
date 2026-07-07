# Formuláre — poradcovský portál VMfin

Statický web (HTML+CSS+vanilla JS na jednotlivé nástroje, PHP len na zdieľaný backend) pre poradcov vo VMfin. Repo: `vmoscak/formulare`, pracovná vetva `claude/issues-2-creation-ifhwha` (merguje sa do `master`, ktorý má auto-deploy na Websupport cez GitHub Actions).

Komunikácia vždy po slovensky. Vladimír Moščák edituje web priamo cez Claude — kóduje, commituje a nasadzuje sám (žiadny Cursor, žiadne prompty na kopírovanie).

## Čo je hotové a nasadené (živé na produkcii)

- **Brána na celú doménu** — jedna zdieľaná fráza (`brana.php` + `.htaccess` cookie gate), klienti sem nechodia priamo.
- **Poradcovský portál:**
  - `/` — len dlaždice poradcov (farebný avatar s iniciálami), klik nastaví `cur_advisor` cookie a presmeruje na `/nastroje.php`.
  - `/nastroje.php` — všetky nástroje, personalizované podľa prihláseného poradcu.
  - `admin.php` — prehľad pre majiteľa (zoznam poradcov, história dokumentov, klientske odkazy), prístup len pre `is_admin=1`.
- **DB** (MySQL, zdieľaná s hlavným VMfin webom) — tabuľky s prefixom `formulare_` (`formulare_advisors`, `formulare_client_links`, `formulare_generated_documents`). Migrácia `sql/002_advisor_color.sql` pridala stĺpec `color` + 4 nových poradcov + farby všetkým piatim — **overené 2026-07-07, funguje bez chýb** (lokálne aj po overení admin.php).
- **5 poradcov s vlastnou farbou:** Vladimír Moščák (modrá), Miroslava Vaňová (tyrkysová), Milan Haluška (jantárová), Kamil Polivčak (fialová), Ľuboš Šimčisko (ružová). Farba sa dá zmeniť cez color picker v `admin.php`.
- **Nástroje `financna-medzera` a `wizard-poistenie`:**
  - Dual režim Poradca/Klient, jedinečné klientske odkazy (`?token=...`) s uloženými rozpracovanými dátami.
  - Klientske PDF je maskované (žiadne presné eurá, len kvalitatívne hodnotenie) — poradcovské PDF má plné čísla.
  - PDF výstup je vo farbe konkrétneho poradcu (hlavička, CTA, zvýraznenia).
  - Animácie zjednotené s hlavnou stránkou (hover glow vo farbe poradcu, fade-in na `admin.php`).

## Explicitne mimo rozsahu / odložené

- Výpočtová metodika (`computeGaps()`) — zatiaľ zostáva DIME-podobný placeholder, Vladimír povedal "výpočty necháme na koniec".
- Ďalšie farebné doladenie výstupov — chce doladiť neskôr, netreba iniciovať sám.
- E-maily/telefóny 4 nových poradcov — vedome nechce dopĺňať teraz ("Zvyšok nebudeme dávať").
- Navrhnuté, ale neschválené: e-mailové notifikácie pri vyplnení klientskeho odkazu, expirácia odkazov (`expires_at` stĺpec existuje, nikde sa nenastavuje), filter/vyhľadávanie v `admin.php`.

## Prevádzkové poznámky

- Bez priameho prístupu k produkčnej DB ani k `formulare.vmfin.sk` (proxy blokuje externé domény) — všetko sa testuje lokálne pred nasadením.
- **Lokálne testovanie:** `config.local.php` (vzor v `config.sample.php`) s `DB_DSN` = `sqlite:...tmp/local.sqlite` — schéma sa vytvorí automaticky pri prvom pripojení cez `dbInitSqlite()` v `db.php`. Prihlásenie ako poradca/admin ide len cez flow `/?adv=<id>` (nastaví `cur_advisor` cookie) — priame nastavenie cookie cez JS pri navigácii nefunguje spoľahlivo. `.htaccess` brána (`gate_auth` cookie) sa v `php -S` neuplatňuje (mod_rewrite nefunguje), takže lokálne ju netreba riešiť.
- Štandardný postup: commit → push na `claude/issues-2-creation-ifhwha` → PR → merge do `master` → auto-deploy. Nasadzovanie je povolené bez pýtania sa vopred.
