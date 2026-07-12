# Nápady na ďalší rozvoj

Priebežný zoznam nápadov na appku Formuláre/Portál — nezáväzný backlog, nie
záväzný plán. Zoradené tematicky, nie podľa priority. Škrtaj/zaškrtávaj podľa
toho, čo sa rozhodneme spraviť.

---

## ✅ Už hotové

- [x] **Modulárny spínač (Feature Toggles)** — zap./vyp. jednotlivých nástrojov
  per poradca cez admin zónu. (Presne tvoj tip na koniec zoznamu — už funguje.)
- [x] **Náborová zóna** — register agentov (NBS), filter podľa kategórie/kraja,
  interaktívna mapa s presným aj približným geokódovaním. Len pre teba.
- [x] **Znalostná báza v „Ľudskej reči"** — interné FAQ s vyhľadávaním a
  kopírovaním na jeden klik. Zatiaľ tiež len pre teba, zobrazenie ostatným
  poradcom sa ešte doladí.
- [x] **Generátor „Finančnej kartičky prvej pomoci“** — cesta C (SMS/WhatsApp
  text) aj plnohodnotné PDF s prehľadom poistiek a núdzových kontaktov.
  Fyzická/QR-vCard cesta (A/B) zatiaľ nie.
- [x] **Checklisty podľa typu škody do SMS** — výber typu škody (auto,
  domácnosť, krádež, úraz, zodpovednosť), hotový zoznam dokladov ako PDF
  alebo SMS/WhatsApp text.
- [x] **Generátor „5-hviezdičkovej prosby“ do WhatsAppu** — text s odkazom na
  Google/Facebook recenziu, odkaz sa pamätá v prehliadači.
- [x] **„Warm-Intro“ WhatsApp Generátor** — priateľská prvá správa pre nový
  odporúčaný kontakt.
- [x] **Tmavý režim (Dark Mode)** — prepínač v ľavej lište, uložený v
  prehliadači, funguje naprieč celou appkou.
- [x] **Kartička „Odporučte ma priateľom“** — poďakovanie, kontakt na
  poradcu a priestor na odporúčanie (meno/telefón priateľa) — PDF aj
  SMS/WhatsApp text.
- [x] **Žiadosť o výplatu poistného plnenia** — formálna žiadosť o
  vyplatenie plnenia z poistnej udalosti na IBAN žiadateľa.
- [x] **Dizajnové mikro-interakcie** — jemné animácie pri výbere
  dlaždíc, stlačení tlačidiel, nástupe kariet a hoveri na ľavej lište
  (rešpektuje prefers-reduced-motion).
- [x] **Vysvetlivky pre klienta** — krátky jednostránkový „slovník
  pojmov“ (spoluúčasť, čakacia doba, franšíza a pod.) ako PDF/text.
- [x] **Interaktívny ťahák „Čo pýtať od klienta“** — živý zoznam otázok
  podľa situácie klienta na odškrtávanie počas stretnutia.
- [x] **Editor noviniek** — pridávaš len ty (novinky.php); „dôležité“
  ostáva navrchu.
- [x] **Domov (úvodná záložka po prihlásení)** — nová stránka uvod.php,
  prvá záložka v ľavej lište. Zobrazuje banner noviniek (presunutý sem
  z index.php) a veľké karty na Nástroje/Formuláre/Pomôcky v novom,
  jemnejšom dizajne (biele karty, farebný pruh hore, kruhová ikona) —
  odlišnom od farebných gradientových kariet jednotlivých nástrojov.
- [x] **Refinančný Radar** (sledovač sadzieb) — ručne udržiavaný
  prehľad hypotekárnych sadzieb podľa banky/fixácie, s viditeľným
  „stáří" každého záznamu. Zámerne bez akéhokoľvek automatického
  sťahovania (rozhodnutie: pri číslach pre klienta sa nedá spoliehať
  na niečo, čo sa môže potichu pokaziť). Viditeľné len pre teba —
  hypotéky riešiš sám, poradcovia to nepotrebujú.
- [x] **Copy-Paste zóna pre poradcu** — osobné rýchle textové šablóny;
  na rozdiel od Znalostnej bázy (zdieľaná, len owner) tu je dátovo
  pripravené, aby si každý poradca videl a upravoval len svoje vlastné
  texty. Zatiaľ viditeľné len pre teba (owner), kým sa overí užitočnosť
  — potom sa sprístupní všetkým jednou zmenou podmienky.
- [x] **Interaktívna „Pyramída istoty“** — klikacia stupňovitá pyramída
  (Ochrana → Rezerva → Budovanie cieľov → Zhodnocovanie), pri každej
  vrstve krátke vysvetlenie a kontrolný zoznam na odškrtávanie počas
  rozhovoru s klientom. Voliteľné meno klienta a export do PDF.
- [x] **„Latte Faktor“** — kalkulačka malých pravidelných výdavkov
  (napr. káva, predplatné): naživo prepočíta, o koľko by suma narástla
  pri pravidelnom investovaní namiesto minutia (zvolený počet rokov,
  očakávané ročné zhodnotenie). Voliteľné meno klienta a export do PDF.
- [x] **Simulátor dvoch extrémov** — vedľa seba dva krajné scenáre
  (dlhý život/dožitie vs. náhla strata príjmu zajtra), pri každom
  kontrolný zoznam na odškrtávanie počas rozhovoru. Voliteľné meno
  klienta a export do PDF.
- [x] **Argument Builder** — vyber produkt a (voliteľne) typ klienta,
  poskladá sa zoznam odporúčaných argumentov na jeho predstavenie —
  opak Ťaháku „čo pýtať“ (toto je čo povedať). Odškrtávanie počas
  rozhovoru, export do PDF.
- [x] **Vybavovač námietok** — opak Argument Builderu: vyber typickú
  námietku klienta („je to drahé“, „musím si to premyslieť“...),
  appka ukáže odporúčané reakcie. Interný nástroj pre poradcu, nie na
  odovzdanie klientovi — bez PDF, len kopírovanie pre vlastné poznámky.
- [x] **„Prvých 30 dní“** — kartička pre klienta hneď po podpise: čo
  teraz nasleduje, kedy začína platiť krytie, ako postupovať pri
  poistnej udalosti. Vyber produkt, voliteľné meno klienta, PDF aj
  text na SMS/WhatsApp, kontakt poradcu sa predvyplní automaticky.
- [x] **Emailové pozdravy** — narodeniny, Vianoce/Nový rok, výročie
  zmluvy. Tlačidlo skopíruje NAFORMÁTOVANÝ (grafický) e-mail priamo
  do schránky (nie len text) — vloží sa Ctrl+V rovno do interného
  e-mailového klienta (Outlook a pod.) so zachovaným vzhľadom.
  Šablóna je zámerne „Outlook-safe" (tabuľkový layout, inline štýly).
- [x] **Sprievodca podľa životnej udalosti** — meta-nástroj, nestavia
  nový obsah, len prepája existujúce nástroje do odporúčaného
  poradia podľa udalosti klienta (svadba, dieťa, kúpa bytu, zmena
  zamestnania, koniec fixácie hypotéky). Krok naviazaný na hypotéku
  sa bez majiteľa zobrazí len ako poznámka bez odkazu.
- [x] **„Oplatí sa mi refinancovať?“** — break-even prepočet pri
  zmene banky: mesačná úspora novej sadzby vs. náklady na prechod
  (poplatok za predčasné splatenie + nové dojednanie), za koľko
  mesiacov sa to vráti. Vie predvyplniť sadzbu z Refinančného
  Radaru. Viditeľné len pre teba — hypotéky riešiš sám.
- [x] **Grafické a vizuálne vylepšenia naprieč appkou** —
  (1) vlastné toast notifikácie (`assets/toast.js`) namiesto natívneho
  `alert()` na chybové/validačné hlášky vo všetkých nástrojoch;
  (2) kruhový progress namiesto textovej pilulky na Pyramíde istoty;
  (3) jednotný farebný pruh v hlavičke každého generovaného PDF
  (farba podľa farby nástroja, bez loga — len akcentový pruh);
  (4) krajšie prázdne stavy (ikona + priateľská hláška) v Moje
  dokumenty a Znalostnej báze; (5) jemný loading shimmer na ľavej
  lište namiesto náhleho "výskoku" pri pomalšom pripojení.
- [x] **Rebranding na „Portál"** — appka sa už nevolá „Formuláre",
  premenované všade, kde ide o názov appky (titulky, wordmark, päta
  prihlasovacej obrazovky, tooltip loga). Záložka „Formuláre" v ľavej
  lište (zmluvy/žiadosti) ostala — je to legitímny názov kategórie
  dokumentov, nie brand appky.
- [x] **Ďalšie grafické doladenie** — (1) vlastný favicon (indigo
  štvorec s fajkou, `favicon.ico` + `assets/favicon.svg`); (2) jemný
  fade-in pri načítaní každej stránky namiesto tvrdého "výskoku"
  obsahu; (3) plynulý crossfade farieb pri prepínaní tmavý/svetlý
  režim; (4) „shine" efekt — svetelný pruh prechádzajúci cez kartu
  nástroja pri prejdení myšou; (5) ikona zámku pri štítku „Len pre
  teba"/„Admin" na kartách Domov — vizuálne, nielen textovo.
- [x] **Tretie kolo grafického doladenia** — (1) jemný tieň pod
  hornou lištou po odscrollovaní obsahu; (2) vlastný focus-outline
  v brand farbe pri navigácii klávesnicou (namiesto prehliadačového
  default); (3) tenký scrollbar ladený s motívom (svetlý/tmavý);
  (4) farebný akcent na riadku tabuľky pri prejdení myšou (Moje
  dokumenty, Znalostná báza, Admin); (5) "count-up" animácia
  hlavných výsledných čísel v "Oplatí sa mi refinancovať?"
  (mesačná úspora, čistá úspora) — nabehnú plynulo namiesto
  okamžitého prepočtu.
- [x] **Poistný semafor** — rýchly vizuálny audit krytia na 1 klik:
  úmrtie, invalidita, kritické choroby, trvalé následky, dlhodobá PN
  a finančná rezerva ako farebné svetlo (červená/oranžová/zelená)
  podľa pomeru existujúce krytie / odporúčaná suma. Rovnaká základná
  metodika ako Kalkulačka finančnej medzery, zjednodušená bez
  záväzkov a detí. PDF aj kopírovanie zhrnutia.
- [x] **Simulátor krátenia plnenia** — princíp pomerného plnenia pri
  podpoistení majetku (Plnenie = Škoda × Poistná suma / Skutočná
  hodnota): vedľa seba ukáže, koľko by klient reálne dostal pri
  terajšej podpoistenej sume oproti správne nastavenej. Argumentačný
  nástroj pri námietke „to mi stačí“. PDF aj kopírovanie zhrnutia.

---

## Kalkulačky pre klienta

- [ ] **Kalkulačka „Živnostník (SZČO) na PN-ke“**
- [ ] **Hypo-kalkulačka pre optimalizátorov** (S.R.O. a Živnosť)
- [ ] **Kalkulačka príjmových cieľov** (Reverse-engineering úspechu)
- [ ] **Mini-kalkulačka pre sociálne siete** (Lead Magnet)
- [ ] **Kalkulačka „Zvýšenie platu vs. Zamestnanecký benefit“**
- [ ] **Finančný Index Ochrany (FIO Skóre)** — jedno súhrnné číslo/skóre za
  celú domácnosť (na rozdiel od Poistného semaforu, ktorý ukazuje 6
  oblastí samostatne)
- [ ] **Porovnávač „Čo sa stane, ak...“** (scenáre životných rizík)
- [ ] **Kalkulačka „Štart do života pre dieťa“**
- [ ] **„Bancassurance Killer“** (bankové poistenie úveru vs. UNIQA)
- [ ] **„Obchádzač notára“** (kalkulačka dedičského konania a zmrazených účtov) —
  vhodné pre bonitnejších klientov, seniorov, predaj III. piliera/ŽP/investícií
- [ ] **„Dôchodkový Tacho-meter“** (presný vek odchodu do dôchodku) — odložené,
  čaká sa na presnú tabuľku dôchodkového veku podľa ročníka od Sociálnej poisťovne
- [ ] **„Crash Test“ nehnuteľnosti** (indexačný radar)
- [ ] **Matica životných udalostí** (Life-Event Asistent)
- [ ] **„Before / After“ Slider** (finančný röntgen) — posuvník na porovnanie
  fotiek "pred/po", aplikovaný na financie klienta
- [ ] **„Semafor čakacích dôb“** (vizuálna časová os pre nové poistky)

## Generátory dokumentov a kariet

- [ ] **Generátor „Finančnej kartičky prvej pomoci“** — zostáva: A) fyzická
  cesta, B) digitálna QR-vCard cesta
- [ ] **Generátor sprievodných prehlásení k likvidácii**
- [ ] **Žiadosť o zmenu oprávnenej osoby**
- [ ] **Rýchle linky a QR kódy** (zbierka)
- [ ] **Generátor „Rodinného Trezoru“** (núdzový manuál pre pozostalých)
- [ ] **Generátor bezpečného linku na zber dát** — appka už má tokénový
  klientsky odkaz, ale len pre 2 nástroje (Kalkulačka finančnej medzery,
  Aké poistenie potrebujem); premyslieť, či rozšíriť alebo urobiť
  samostatný všeobecný formulár *(odložené, dorozmyslieť)*

## Nástroje a produktivita poradcu

- [ ] **„Tichý radar“ pre poradcov** (pripomienkovač aktivít)
- [ ] **Šablóny na riešenie „Zásekov“** (trablšúting) — rýchle texty na
  vyžiadanie dokladov, keď proces v poisťovni zastane
- [ ] **60-sekundový „Zápisník po stretnutí“**
- [ ] **Hlasový zápisník z auta** (AI, moderná produktivita)
- [ ] **„Hypo-Refinance Autopilot“** s notifikáciou pre poradcu

## Manažérske a tímové funkcie

- [ ] **Most medzi Hypotékou a Poistením** (manažérska funkcia — synergia)
- [ ] **Smart Lead Routing** (inteligentné prerozdeľovanie kontaktov)
- [ ] **Modul „Klientske výročia a narodeniny“** (automaty na vzťahy)
- [ ] **Pripomienkovač „Happiness Check“**
- [ ] **Zdieľaná nástenka „Best Practices“** (tímové víťazstvá)
- [ ] **Automatický zber dát cez CRON** (plná automatizácia — dôchodky)
- [ ] **Modul „Lokálny Partner Hub“** (vzájomný referral systém)
- [ ] **Sezónny marketingový kalendár** (autopilot na kampane)
- [ ] **„Sieň slávy“** a **Osobný „Provízny simulátor“** *(odložené — pozri nižšie)*

## Marketing a získavanie klientov

- [ ] **„Rodinná mapa“** (generačný marketing, demografický cross-sell)
- [ ] **Výročný „Zostrih zmlúv“** (automatický pre-servis existujúcich klientov)

## B2B / firemný segment

- [ ] **„Audit firiem“** (B2B minikatalóg pre konateľov a SZČO)
- [ ] **One-Pager „Ako vytiahnuť peniaze z firmy do súkromia cez UNIQA“**

---

## Odložené (nenasadzovať zatiaľ)

- **„Sieň slávy“ / Provízny simulátor** — zámerne **nie ako súťaženie**
  medzi poradcami, ale skôr ako **uznanie výsledkov** (napr. tichá pochvala/
  míľnik, nie verejný rebríček). Koncept si necháme premyslieť nanovo, kým
  sa k tomu vrátime — zatiaľ sa nestavia.
- **CRM-lite / centrálna databáza klientov** (a na nej postavené: história
  stretnutí per klient, história škodových udalostí, segmentácia/tagovanie
  klientov) — súčasný stav: klientske údaje sa spracúvajú cez CRM od
  Microsoftu v UNIQA a cez MyPort/Finportal (hypotéky, cez BEplan) —
  spracovanie tam robí firma, nie ty osobne, čo je výhoda (menšia osobná
  zodpovednosť). Vlastná databáza klientov v appke Portál by túto
  výhodu stratila — stal by si sa ty osobne prevádzkovateľom tých údajov,
  vrátane potreby získať a evidovať súhlas so spracovaním osobných údajov
  od každého klienta zvlášť. Zatiaľ sa do toho nejde — appka ostáva pri
  nástrojoch a jednorazových dokumentoch, nie systém záznamov o klientoch.

---

## Poznámky k implementácii

- **Pravidlo (od 7/2026):** nové nástroje sa poradcom nezobrazujú
  automaticky naschvál — po nasadení si ich v admin.php **sám ručne
  vypneš** pre ostatných (existujúci mechanizmus `disabled_tools`, pár
  klikov). Žiadne SQL na toto už netreba generovať — to bolo zbytočný
  krok navyše.
- **Pravidlo (od 7/2026):** appka Portál nie je systém záznamov o
  klientoch a ani sa ním zámerne nemá stať. Klientske údaje reálne
  spracúva CRM od Microsoftu (UNIQA) a MyPort/Finportal cez BEplan
  (hypotéky) — zodpovednosť za spracovanie je tam na firme, nie na tebe
  osobne. Nová databáza klientov v tejto appke (aj čiastková, napr. len
  história stretnutí) by túto zodpovednosť presunula na teba, vrátane
  nutnosti získať súhlas so spracovaním osobných údajov od každého
  klienta. Appka preto ostáva pri nástrojoch a jednorazových dokumentoch
  (PDF s menom klienta sa negeneruje ako trvalá evidencia, len na
  stiahnutie/odovzdanie) — nie pri centrálnej evidencii.
