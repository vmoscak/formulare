<?php
/**
 * Cesta nováčika — onboarding checklist pre nových poradcov v tíme, s odkazmi
 * rovno na konkrétne nástroje appky. Fázy sa zobrazujú ako "cesta" (trail +
 * prstenec postupu + odporúčaný ďalší krok), aby bolo vizuálne jasné, koľko
 * je hotovo a kam ísť ďalej.
 *
 * Prístup: owner (spravuje osnovu, priraďuje/odoberá nováčikov, vidí
 * a upravuje všetko) ALEBO poradca, ktorému owner priradil onboarding
 * (onboarding_started_at IS NOT NULL) — ten vidí len checklist a odškrtáva
 * si vlastný postup, bez možnosti niečo pridať/upraviť/zmazať.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
$isOwner = $me && !empty($me['is_owner']);
$isOnboarding = $me && !empty($me['onboarding_started_at']);
if (!$me || (!$isOwner && !$isOnboarding)) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function advisorInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// Fáza sa v add/edit formulároch vyberá z roletky existujúcich fáz (+ "Nová fáza…").
// Vyrieši sa buď na vybranú existujúcu hodnotu, alebo na ručne napísaný nový názov.
function obResolvePhase(): string {
    $select = trim((string)($_POST['phase_select'] ?? ''));
    if ($select === '__new__') return trim((string)($_POST['phase_new'] ?? ''));
    return $select;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isOwner && isset($_POST['add'])) {
        $phase = obResolvePhase();
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        if ($phase !== '' && $title !== '') {
            $maxSort = (int)db()->query('SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps')->fetchColumn();
            db()->prepare('INSERT INTO formulare_onboarding_steps (phase, title, description, link_url, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$phase, $title, $description, $linkUrl !== '' ? $linkUrl : null, $maxSort + 1]);
        }
    } elseif ($isOwner && isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $phase = obResolvePhase();
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        if ($id && $phase !== '' && $title !== '') {
            db()->prepare('UPDATE formulare_onboarding_steps SET phase = ?, title = ?, description = ?, link_url = ? WHERE id = ?')
                ->execute([$phase, $title, $description, $linkUrl !== '' ? $linkUrl : null, $id]);
        }
    } elseif ($isOwner && isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        db()->prepare('DELETE FROM formulare_onboarding_progress WHERE step_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM formulare_onboarding_steps WHERE id = ?')->execute([$id]);
    } elseif ($isOwner && isset($_POST['move_id'])) {
        $id = (int)$_POST['move_id'];
        $dir = (string)($_POST['direction'] ?? '');
        $stmt = db()->prepare('SELECT id, phase, sort_order FROM formulare_onboarding_steps WHERE id = ?');
        $stmt->execute([$id]);
        $step = $stmt->fetch();
        if ($step) {
            $sibStmt = db()->prepare('SELECT id, sort_order FROM formulare_onboarding_steps WHERE phase = ? ORDER BY sort_order, id');
            $sibStmt->execute([$step['phase']]);
            $siblings = $sibStmt->fetchAll();
            $pos = null;
            foreach ($siblings as $i => $sib) { if ((int)$sib['id'] === $id) { $pos = $i; break; } }
            if ($pos !== null) {
                $swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
                if (isset($siblings[$swapPos])) {
                    $other = $siblings[$swapPos];
                    db()->prepare('UPDATE formulare_onboarding_steps SET sort_order = ? WHERE id = ?')->execute([$other['sort_order'], $id]);
                    db()->prepare('UPDATE formulare_onboarding_steps SET sort_order = ? WHERE id = ?')->execute([$step['sort_order'], $other['id']]);
                }
            }
        }
    } elseif ($isOwner && isset($_POST['assign_advisor_id'])) {
        $id = (int)$_POST['assign_advisor_id'];
        if ($id) {
            // Nový cyklus onboardingu — prípadný predošlý dátum dokončenia (z minulého
            // priradenia) sa vynuluje, nech "História absolventov" odráža aktuálny beh.
            db()->prepare('UPDATE formulare_advisors SET onboarding_started_at = ?, onboarding_completed_at = NULL WHERE id = ? AND is_owner = 0')
                ->execute([date('Y-m-d H:i:s'), $id]);
        }
    } elseif ($isOwner && isset($_POST['unassign_advisor_id'])) {
        $id = (int)$_POST['unassign_advisor_id'];
        if ($id) {
            // onboarding_completed_at sa zámerne NEmaže — je to trvalý záznam v Histórii
            // absolventov, aj keď onboarding_started_at a rozpracovaný postup zmiznú.
            db()->prepare('UPDATE formulare_advisors SET onboarding_started_at = NULL WHERE id = ?')->execute([$id]);
            db()->prepare('DELETE FROM formulare_onboarding_progress WHERE advisor_id = ?')->execute([$id]);
        }
    }
    header('Location: /cesta-novacika.php');
    exit;
}

// "Priebežne" je špeciálna fáza — bežná práca poradcu, ktorá sa nikdy
// "nedokončí" (žiadne zaškrtávanie), preto sa vyníma z osnovy/percenta/trailu
// a zobrazuje sa ako samostatný zoznam na konci stránky.
const OB_ONGOING_PHASE = 'Priebežne';

$allSteps = db()->query('SELECT * FROM formulare_onboarding_steps ORDER BY sort_order, id')->fetchAll();
$steps = array_values(array_filter($allSteps, fn($s) => $s['phase'] !== OB_ONGOING_PHASE));
$ongoingSteps = array_values(array_filter($allSteps, fn($s) => $s['phase'] === OB_ONGOING_PHASE));
// Zoznam všetkých existujúcich fáz (vrátane Priebežne) pre roletku vo formulároch —
// nech ju owner vie znova vybrať, keď pridáva ďalšiu priebežnú úlohu.
$allPhaseNames = array_values(array_unique(array_column($allSteps, 'phase')));

$doneStepIds = [];
if ($steps) {
    $prog = db()->prepare('SELECT step_id FROM formulare_onboarding_progress WHERE advisor_id = ?');
    $prog->execute([$advisorId]);
    $doneStepIds = array_map('intval', array_column($prog->fetchAll(), 'step_id'));
}

// Model zapracovania — mesačný tracker statusu (FIT/STD/TOP), na kvartálny
// doplatok DP vo fázach VI.–XII. a XIII.–XXIV. mesiac (viď obRenderMzTracker()).
$mzStatusMap = [];
try {
    $mzStmt = db()->prepare('SELECT month_number, status FROM formulare_mz_status WHERE advisor_id = ?');
    $mzStmt->execute([$advisorId]);
    foreach ($mzStmt->fetchAll() as $row) { $mzStatusMap[(int)$row['month_number']] = $row['status']; }
} catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }

// Zoskupenie podľa fázy, v poradí prvého výskytu (rešpektuje sort_order).
$phases = [];
foreach ($steps as $s) {
    $phases[$s['phase']][] = $s;
}

$totalSteps = count($steps);
$doneCount = count($doneStepIds);
$pct = $totalSteps > 0 ? round($doneCount / $totalSteps * 100) : 0;

// Stav každej fázy ("cesta") — prvá nedokončená fáza je "current" (tam sa
// nováčik práve nachádza), fázy pred ňou "done", fázy po nej "upcoming".
// Slúži na trail, prstenec a odporúčaný ďalší krok nižšie.
$phaseList = [];
$phaseIndexByName = [];
$idx = 0;
$foundCurrent = false;
foreach ($phases as $phaseName => $phaseSteps) {
    $total = count($phaseSteps);
    $done = 0;
    foreach ($phaseSteps as $s) { if (in_array((int)$s['id'], $doneStepIds, true)) $done++; }
    $complete = $total > 0 && $done === $total;
    if (!$complete && !$foundCurrent) {
        $status = 'current';
        $foundCurrent = true;
    } elseif ($complete) {
        $status = 'done';
    } else {
        $status = 'upcoming';
    }
    $phaseList[$phaseName] = ['idx' => $idx, 'total' => $total, 'done' => $done, 'status' => $status];
    $phaseIndexByName[$phaseName] = $idx;
    if ($status === 'current') { $currentPhaseName = $phaseName; }
    $idx++;
}

// Nováčik (nie owner) vidí vždy len jednu fázu ako kartu — owner naďalej
// dostáva celý zoznam naraz, s tlačidlom na náhľad pohľadu nováčika
// (?view=novice), aby vedel skontrolovať, ako to vyzerá bez toho, aby sám bol
// priradený ako nováčik.
$novicePreview = $isOwner && isset($_GET['view']) && $_GET['view'] === 'novice';
$cardMode = !$isOwner || $novicePreview;
$currentPhaseName = $currentPhaseName ?? null;

$selectedPhaseIdx = (isset($_GET['phase']) && $_GET['phase'] !== '') ? (int)$_GET['phase'] : null;
if ($selectedPhaseIdx === null && $cardMode && $phaseList) {
    // Bez explicitného ?phase= v móde kariet naskočí rovno na aktuálnu fázu
    // (alebo poslednú, ak je všetko hotové) — nie na prehľad všetkého.
    $selectedPhaseIdx = $currentPhaseName !== null ? $phaseIndexByName[$currentPhaseName] : (count($phaseList) - 1);
}
$selectedPhaseName = null;
if ($selectedPhaseIdx !== null) {
    $selectedPhaseName = array_search($selectedPhaseIdx, $phaseIndexByName, true);
    if ($selectedPhaseName === false) { $selectedPhaseName = null; $selectedPhaseIdx = null; }
}
$maxPhaseIdx = count($phaseList) - 1;

// Prvý neodškrtnutý krok naprieč celou osnovou — "Ďalší krok" callout.
$nextStep = null;
foreach ($steps as $s) {
    if (!in_array((int)$s['id'], $doneStepIds, true)) { $nextStep = $s; break; }
}

// Krátky motivačný odkaz podľa rozostupu percenta — drží "wow" pocit pri
// odškrtávaní, rovnaká logika sa prepočíta aj na klientovi v JS (viď nižšie).
function obMotivation(int $pct): string {
    if ($pct >= 100) return 'Hotovo! 🎉';
    if ($pct >= 67) return 'Už len kúsok! 🔥';
    if ($pct >= 34) return 'Skvelý postup! 💪';
    if ($pct >= 1) return 'Pekný štart! 🙌';
    return 'Poďme na to! 🚀';
}

// Doplňujúce info k vybraným krokom (bublinové okno na hover) — skrátená
// "Obsahová náplň kurzu" z Karty výkonnosti a rozvoja VFA 2025. Naviazané na
// presný názov kroku (nie na ID), lebo ide o statický referenčný text mimo DB —
// ak owner názov kroku premenuje, bublina pri ňom jednoducho zmizne.
$OB_TOOLTIPS = [
    'Prvé kroky v UNIQA — úvodné školenie' => 'Poisťovacia abeceda • UNIQA — informácie o spoločnosti • Firemná kultúra UNIQA • Systém vzdelávania v UNIQA • Moja vízia v UNIQA.',
    'Štart I. Životné poistenie — aktívna účasť a samoštúdium po štarte' => 'Život & Radosť — technické parametre, výhody a úžitok pre klienta, práca v UNIHUB. Pripoistenia — parametre, výhody a úžitok pre klienta, práca v UNIHUB.',
    'IT systémy — školenie a nastavenie' => 'Overenie prihlásenia do IT systémov • Práca v prostredí UNIPOINT, Albert, CRM • Tréning kalkulácie ponúk.',
    'Štart II. Autá — aktívna účasť a samoštúdium po štarte' => 'KASKO — technické parametre, výhody a úžitok pre klienta, spôsob dojednania + obhliadka. PZP — technické parametre, výhody pre klienta, spôsob dojednania.',
    'Štart III. Úvod do predaja — aktívna účasť a samoštúdium' => 'Prehľad produktov UNIQA • Filozofia životného poistenia • Telefonovanie a zvládanie námietok • Analýza potrieb klienta — úvod, ukážka, tréning.',
    'Štart IV. On-line majetok — samoštúdium, účasť a samoštúdium po štarte' => 'Technické parametre produktu Domov a bezpečie • Terminológia • Kalkulácia ponuky.',
    'Štart V. Poistenie osôb — samoštúdium, účasť a samoštúdium po štarte' => 'UNIQÁČIK — technické parametre, výhody a úžitok pre klienta, príprava ponuky. Cestovné poistenie a pohrebné náklady — technické parametre, príprava ponuky, výhody a úžitok pre klienta.',
    'Štart VI. Dôchodky — samoštúdium, účasť a samoštúdium po štarte' => 'Starobné dôchodkové sporenie: SDS, DDS — technické parametre, spôsob dojednania.',
    'Blok III. Majetkové poistenie — aktívna účasť a samoštúdium po bloku' => 'Filozofia majetkového poistenia • Technické parametre D&B + asistenčné služby — výhody a úžitok pre klienta • Návrh správnych poistných súm a kalkulácia pre klienta, práca v UNIPOINT a CRM.',
    'Blok II. Životné poistenie — aktívna účasť a samoštúdium po bloku' => 'Filozofia a zmysel životného poistenia — prípadové štúdie • Pravidlá poistenia + finančná matematika • Hra UNIQATNY život • Nastavenie poistných súm ŽP v UNIPOINT a práca v CRM • SDS, Tempo, investovanie, príprava ponúk.',
    'Blok I. Predaj — aktívna účasť a samoštúdium' => 'Vstup do sveta klienta • Analýza potrieb klienta • Efektívna argumentácia pri prezentácii, gradácia argumentácie • Námietky a ich riešenie • Uzatváracie techniky, získavanie odporúčaní, premostenie na servis • Príprava na maturitnú skúšku.',
    'Maturita' => 'Overenie produktových znalostí a predajných zručností.',
];

// Krátky podporný odkaz na začiatku každej fázy — pripomienka, že nováčik
// v tom nie je sám a má sa na koho obrátiť.
$OB_PHASE_SUPPORT = [
    'Pred nástupom' => 'Papierovanie a školenia na začiatku vyzerajú ako veľa — a naozaj toho je veľa. Netreba to zvládnuť dokonale na prvýkrát. Ak si niečím neistý, opýtaj sa — presne na to sú tu kolegovia aj tvoj manažér.',
    '0. mesiac' => 'Prvý mesiac je o učení sa veľa nového naraz. Je úplne normálne, že si na začiatku neistý — nikto od teba nečaká, že to vieš hneď. Manažér aj skúsenejší kolegovia ti radi pomôžu, stačí sa ozvať.',
    'I. mesiac' => 'Ak máš pocit, že iní to majú jednoduchšie, nemajú — každý si prešiel rovnakou krivkou učenia. Pýtaj sa toľko, koľko potrebuješ, nie je to znak slabosti.',
    'II. mesiac' => 'Blok Predaj je o skutočnom rozhovore s klientom — analýza potrieb, argumentácia, zvládanie námietok, uzatváracie techniky. Netreba to zvládnuť dokonale hneď, tieto zručnosti sa budujú praxou. Ak chceš niečo nacvičiť nanečisto, kolegovia aj manažér radi pomôžu.',
    'III. mesiac' => 'Životné poistenie je srdcom tejto práce a je úplne prirodzené, že práve pri ňom máš najviac otázok. Nie si v tom sám — kolegovia aj manažér ťa podržia.',
    'IV. mesiac' => 'Majetkové poistenie je technickejšia oblasť a prvé ponuky bývajú pomalšie — to je v poriadku. Radšej sa spýtaj vopred, než aby si sa s tým trápil sám.',
    'V. mesiac' => 'Posledný krok pred maturitou. Ver si — dostal si sa sem vlastnou prácou. A ak sa niečo nepodarí na prvý pokus, nie je to koniec sveta.',
    'VI.–XII. mesiac' => 'Školenia sú za tebou — teraz ide hlavne o pravidelnosť. Status FIT/STD/TOP sa vyhodnocuje priebežne, takže sa oplatí sledovať ho každý mesiac.',
    'XIII.–XXIV. mesiac' => 'Posledná časť Modelu zapracovania. Drž si svoj status a dodatková provízia ide s ním — nič nové sa už neučíš, len pokračuješ v tom, čo už vieš.',
];

// Ľudsky čitateľný odstup od poslednej aktivity — pre ownera nižšie, aby vedel
// bez otvárania profilu, kto je "bez pohybu" a treba sa mu ozvať.
function obRelativeTime(?string $ts): string {
    if (!$ts) return 'zatiaľ žiadna aktivita';
    $diff = time() - strtotime($ts);
    if ($diff < 3600) return 'pred chvíľou';
    if ($diff < 86400) return 'dnes';
    $days = (int)floor($diff / 86400);
    if ($days === 1) return 'včera';
    if ($days < 14) return 'pred ' . $days . ' dňami';
    $weeks = (int)floor($days / 7);
    return 'pred ' . $weeks . ' ' . ($weeks === 1 ? 'týždňom' : 'týždňami');
}

// Vykreslenie krokov jednej fázy (checkbox, tooltip, owner akcie, inline edit
// formulár) — zdieľané medzi celkovým prehľadom osnovy a stránkou jednej fázy.
function obRenderSteps(array $phaseSteps, int $phaseIdx, array $doneStepIds, array $OB_TOOLTIPS, bool $isOwner, array $allPhaseNames, bool $cardMode = false): void {
    foreach ($phaseSteps as $sIdx => $s) {
        $isDone = in_array((int)$s['id'], $doneStepIds, true);
        $tip = $OB_TOOLTIPS[$s['title']] ?? null;
        $isFirstInPhase = $sIdx === 0;
        $isLastInPhase = $sIdx === count($phaseSteps) - 1;
        ?>
      <div class="ob-step<?= $isDone ? ' done' : '' ?><?= $cardMode ? ' ob-step-card' : '' ?>" id="ob-step-<?= (int)$s['id'] ?>" data-step-id="<?= (int)$s['id'] ?>" data-phase-idx="<?= $phaseIdx ?>" style="--i:<?= $sIdx ?>;">
        <input type="checkbox" <?= $isDone ? 'checked' : '' ?> data-toggle-step="<?= (int)$s['id'] ?>">
        <div class="ob-step-body">
          <div class="ob-step-title"><?= h($s['title']) ?><?php if ($tip): ?><span class="ob-info" tabindex="0">i<span class="ob-info-bubble"><?= h($tip) ?></span></span><?php endif; ?></div>
          <?php if ($s['description']): ?><div class="ob-step-desc"><?= h($s['description']) ?></div><?php endif; ?>
        </div>
        <div class="ob-step-actions">
          <?php if ($s['link_url']): ?><a class="toggle-btn" href="<?= h($s['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
          <?php if ($isOwner): ?>
          <?php if (!$isFirstInPhase): ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="move_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="toggle-btn" title="Posunúť hore">↑</button>
          </form>
          <?php endif; ?>
          <?php if (!$isLastInPhase): ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="move_id" value="<?= (int)$s['id'] ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="toggle-btn" title="Posunúť dole">↓</button>
          </form>
          <?php endif; ?>
          <button type="button" class="toggle-btn" onclick="obEdit(<?= (int)$s['id'] ?>)">Upraviť</button>
          <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento krok?');">
            <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="toggle-btn">Zmazať</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isOwner): ?>
      <form method="post" class="kb-edit" id="ob-edit-<?= (int)$s['id'] ?>" style="display:none; margin-bottom:12px;">
        <input type="hidden" name="edit_id" value="<?= (int)$s['id'] ?>">
        <select name="phase_select" onchange="obPhaseSelectChange(this)" required>
          <?php foreach ($allPhaseNames as $ph): ?>
            <option value="<?= h($ph) ?>" <?= $ph === $s['phase'] ? 'selected' : '' ?>><?= h($ph) ?></option>
          <?php endforeach; ?>
          <option value="__new__">+ Nová fáza…</option>
        </select>
        <input type="text" name="phase_new" placeholder="Názov novej fázy" style="display:none;">
        <input type="text" name="title" value="<?= h($s['title']) ?>" placeholder="Názov kroku" required>
        <textarea name="description" rows="2" placeholder="Popis (nepovinné)"><?= h($s['description']) ?></textarea>
        <input type="text" name="link_url" value="<?= h((string)$s['link_url']) ?>" placeholder="Odkaz (nepovinné, napr. /financna-medzera/)">
        <div style="display:flex; gap:8px;">
          <button type="submit" class="pillbtn solid">Uložiť</button>
          <button type="button" class="pillbtn" onclick="obCancel(<?= (int)$s['id'] ?>)">Zrušiť</button>
        </div>
      </form>
      <?php endif; ?>
        <?php
    }
}

/** DP (dodatková provízia) v EUR pre daný mesiac MZ a status. */
function mzDpAmount(int $month, string $status): int {
    $table = $month <= 12
        ? ['fit' => 500, 'std' => 750, 'top' => 1000]
        : ['fit' => 300, 'std' => 500, 'top' => 700];
    return $table[$status] ?? 0;
}

const MZ_STATUS_LABELS = ['fit' => 'FIT', 'std' => '⭐ STD', 'top' => '🏆 TOP'];
const MZ_QUARTERS = [[7, 8, 9], [10, 11, 12], [13, 14, 15], [16, 17, 18], [19, 20, 21], [22, 23, 24]];

/** Súčet DP, ktoré poradca doteraz reálne získal — pri uzatvorených kvartáloch
 * (všetky 3 mesiace vyplnené) počíta s doplatkom na status 3. mesiaca. */
function mzTotalEarned(array $mzStatusMap): int {
    $total = 0;
    if (!empty($mzStatusMap[6])) $total += mzDpAmount(6, $mzStatusMap[6]);
    foreach (MZ_QUARTERS as $q) {
        $statuses = array_map(fn($m) => $mzStatusMap[$m] ?? null, $q);
        if (!in_array(null, $statuses, true)) {
            $final = $statuses[2];
            foreach ($q as $m) $total += mzDpAmount($m, $final);
        } else {
            foreach ($q as $i => $m) { if ($statuses[$i]) $total += mzDpAmount($m, $statuses[$i]); }
        }
    }
    return $total;
}

/**
 * Mesačný tracker statusu (FIT/STD/TOP) — vykresľuje sa len vo fázach
 * "VI.–XII. mesiac" a "XIII.–XXIV. mesiac". Mesiac 6 je samostatná karta
 * bez prepočtu (kvartál 4.–6. nie je celý sledovaný), zvyšok sú plné
 * kvartály s live prepočtom kumulatívneho doplatku DP (JS mzSelectStatus()).
 */
function obRenderMzTracker(string $phaseName, array $mzStatusMap): void {
    $quartersByPhase = [
        'VI.–XII. mesiac' => [[7, 8, 9], [10, 11, 12]],
        'XIII.–XXIV. mesiac' => [[13, 14, 15], [16, 17, 18], [19, 20, 21], [22, 23, 24]],
    ];
    if (!isset($quartersByPhase[$phaseName])) return;
    $totalEarned = mzTotalEarned($mzStatusMap);
    ?>
    <div class="mz-tracker-title">📊 Mesačný tracker statusu</div>
    <div class="mz-summary-bar">
      <div class="mz-summary-icon">💰</div>
      <div class="mz-summary-text">
        <div class="mz-summary-label">Doteraz získané DP z Modelu zapracovania</div>
        <div class="mz-summary-value mz-total-value"><?= number_format($totalEarned, 0, ',', ' ') ?> €</div>
      </div>
    </div>
    <?php if ($phaseName === 'VI.–XII. mesiac'): $sel6 = $mzStatusMap[6] ?? null; ?>
    <div class="mz-card mz-card-single">
      <div class="mz-card-head">
        <span class="mz-card-month">6. mesiac</span>
        <span class="mz-card-badge">Koniec kvartálu</span>
      </div>
      <p class="mz-card-note">Mesiace 4.–5. nie sú súčasťou trackera, doplatok preto tu nevieme dopočítať — sleduj si ho v CRM.</p>
      <div class="mz-status-picker" data-month="6">
        <?php foreach (MZ_STATUS_LABELS as $sKey => $sLabel): $amt = mzDpAmount(6, $sKey); ?>
        <button type="button" class="mz-status-btn mz-status-btn-<?= $sKey ?><?= $sel6 === $sKey ? ' is-selected' : '' ?>" data-status="<?= $sKey ?>" onclick="mzSelectStatus(this)"><?= $sLabel ?><span class="mz-status-amt"><?= $amt ?> €</span></button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php foreach ($quartersByPhase[$phaseName] as $months): ?>
    <div class="mz-card mz-card-quarter">
      <div class="mz-card-head">
        <span class="mz-card-month">Kvartál <?= $months[0] ?>.–<?= $months[2] ?>. mesiac</span>
      </div>
      <div class="mz-quarter-months">
        <?php foreach ($months as $i => $m): $isFinal = $i === 2; $selM = $mzStatusMap[$m] ?? null; ?>
        <div class="mz-month-col<?= $isFinal ? ' mz-month-col-final' : '' ?>">
          <div class="mz-month-label"><?= $m ?>. mesiac<?= $isFinal ? ' 🎯' : '' ?></div>
          <div class="mz-status-picker" data-month="<?= $m ?>">
            <?php foreach (MZ_STATUS_LABELS as $sKey => $sLabel): $amt = mzDpAmount($m, $sKey); ?>
            <button type="button" class="mz-status-btn mz-status-btn-<?= $sKey ?><?= $selM === $sKey ? ' is-selected' : '' ?>" data-status="<?= $sKey ?>" onclick="mzSelectStatus(this)"><?= $sLabel ?><span class="mz-status-amt"><?= $amt ?> €</span></button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mz-doplatok" id="mz-doplatok-<?= implode('-', $months) ?>"></div>
    </div>
    <?php endforeach; ?>
    <?php
}

// Pre ownera: zoznam ostatných aktívnych poradcov (na priradenie/odobratie)
// spolu s ich vlastným postupom, ak už majú onboarding spustený — vrátane
// poslednej aktivity, aby bolo na prvý pohľad vidieť, kto "stojí" a treba sa
// mu ozvať, bez nutnosti otvárať profil každého jedného zvlášť.
$teamAdvisors = [];
if ($isOwner) {
    $teamAdvisors = db()->query(
        "SELECT id, name, color, onboarding_started_at FROM formulare_advisors WHERE is_owner = 0 AND active = 1 ORDER BY name"
    )->fetchAll();
    if ($teamAdvisors && $totalSteps > 0) {
        $progAll = db()->query('SELECT advisor_id, COUNT(*) AS c, MAX(done_at) AS last_done FROM formulare_onboarding_progress GROUP BY advisor_id')->fetchAll();
        $progByAdvisor = [];
        foreach ($progAll as $p) { $progByAdvisor[$p['advisor_id']] = $p; }
        foreach ($teamAdvisors as &$ta) {
            $ta['doneCount'] = (int)($progByAdvisor[$ta['id']]['c'] ?? 0);
            $ta['lastDone'] = $progByAdvisor[$ta['id']]['last_done'] ?? null;
            $lastRef = $ta['lastDone'] ?? $ta['onboarding_started_at'];
            $daysSince = $lastRef ? floor((time() - strtotime($lastRef)) / 86400) : 0;
            $ta['stalled'] = !empty($ta['onboarding_started_at']) && $ta['doneCount'] < $totalSteps && $daysSince >= 10;
        }
        unset($ta);
    }
}

// História absolventov — trvalý zoznam (dátum dokončenia sa nemaže ani po
// odobratí priradenia), aby zostala stopa o tom, kto onboarding už zvládol.
$graduates = [];
if ($isOwner) {
    $graduates = db()->query(
        "SELECT name, color, onboarding_completed_at FROM formulare_advisors WHERE is_owner = 0 AND active = 1 AND onboarding_completed_at IS NOT NULL ORDER BY onboarding_completed_at DESC"
    )->fetchAll();
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Cesta nováčika</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=27">
<style>
  .ob-hero{position:relative; overflow:hidden; display:flex; align-items:center; gap:22px; flex-wrap:wrap;
    background:linear-gradient(135deg, var(--accent) 0%, var(--accent-ink) 100%); border:none; color:#fff;}
  .ob-hero::before,.ob-hero::after{content:''; position:absolute; border-radius:50%; background:#fff; opacity:.14; pointer-events:none;}
  .ob-hero::before{width:240px; height:240px; top:-110px; right:-70px;}
  .ob-hero::after{width:130px; height:130px; bottom:-80px; right:140px;}
  .ob-ring{--pct:0; width:104px; height:104px; border-radius:50%; flex-shrink:0; position:relative; z-index:1;
    background:conic-gradient(#fff calc(var(--pct) * 3.6deg), rgba(255,255,255,.28) 0deg);
    transition:transform .4s ease;}
  .ob-ring.pulse{animation:obRingPulse .55s ease;}
  @keyframes obRingPulse{0%{transform:scale(1);}40%{transform:scale(1.1);}100%{transform:scale(1);}}
  .ob-ring::after{content:''; position:absolute; inset:9px; border-radius:50%; background:var(--accent-ink);}
  .ob-ring-label{position:absolute; inset:9px; border-radius:50%; z-index:1; display:flex; flex-direction:column; align-items:center; justify-content:center;}
  .ob-ring-pct{font-size:21px; font-weight:800; color:#fff; line-height:1.1;}
  .ob-ring-sub{font-size:9.5px; color:rgba(255,255,255,.8); text-transform:uppercase; letter-spacing:.04em; margin-top:1px;}
  .ob-progress-info{flex:1; min-width:180px; position:relative; z-index:1;}
  .ob-progress-info h4{margin:0 0 4px; font-size:16px; color:#fff;}
  .ob-progress-info p{margin:0; font-size:12.5px; color:rgba(255,255,255,.88);}
  .ob-progress-badge{display:inline-block; margin-top:9px; padding:4px 12px; border-radius:999px; background:rgba(255,255,255,.2);
    font-size:11.5px; font-weight:700; letter-spacing:.02em; color:#fff;}

  .ob-next-card{display:flex; align-items:center; gap:16px; background:linear-gradient(135deg, var(--accent-soft), var(--paper)); border:1px solid var(--accent-line); flex-wrap:wrap; position:relative; overflow:hidden;}
  .ob-next-card::before{content:''; position:absolute; width:150px; height:150px; border-radius:50%; background:var(--accent); opacity:.06; top:-60px; right:-46px; pointer-events:none;}
  .ob-next-icon{font-size:21px; flex-shrink:0; width:46px; height:46px; border-radius:50%; background:var(--paper); border:1px solid var(--accent-line); display:flex; align-items:center; justify-content:center; position:relative; z-index:1;}
  .ob-next-body{flex:1; min-width:160px; position:relative; z-index:1;}
  .ob-next-label{font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--accent-ink);}
  .ob-next-title{font-size:14.5px; font-weight:600; color:var(--ink); margin-top:3px;}
  .ob-next-done{display:flex; align-items:center; gap:10px;}
  .ob-next-done .ob-next-title{color:var(--good); position:relative; z-index:1;}

  .ob-journey-card{padding:20px 8px 6px;}
  .ob-journey-scroll{overflow-x:auto; padding:2px 0 0;}
  .ob-journey-svg{display:block; margin:0 auto;}
  .oj-track{fill:none; stroke:var(--border); stroke-width:6; stroke-linecap:round; stroke-linejoin:round;}
  .oj-progress{fill:none; stroke:var(--good); stroke-width:6; stroke-linecap:round; stroke-linejoin:round;}
  .oj-stop{cursor:pointer; text-decoration:none; outline-offset:4px;}
  .oj-dot{fill:var(--paper); stroke:var(--border); stroke-width:2.5; transition:stroke-width .15s ease;}
  .oj-stop:hover .oj-dot{stroke-width:3.5;}
  .oj-dot-label{font-size:13px; font-weight:800; fill:var(--muted);}
  .oj-name{font-size:10.5px; font-weight:600; fill:var(--muted);}
  .oj-stop.status-done .oj-dot{fill:var(--good); stroke:var(--good);}
  .oj-stop.status-done .oj-dot-label{fill:#fff;}
  .oj-stop.status-current .oj-dot{fill:var(--accent); stroke:var(--accent);}
  .oj-stop.status-current .oj-dot-label{fill:#fff;}
  .oj-stop.status-current .oj-name{fill:var(--ink); font-weight:700;}
  .oj-stop.status-upcoming .oj-dot{stroke-dasharray:3 3;}
  .oj-pulse{fill:none; stroke:var(--accent); stroke-width:2.5; opacity:.55; transform-box:fill-box; transform-origin:center;
    animation:ojPulseRing 2.2s ease-out infinite;}
  @keyframes ojPulseRing{0%{transform:scale(1); opacity:.55;}100%{transform:scale(1.7); opacity:0;}}

  .ob-phase{border:none; border-left:3px solid transparent; margin:0 0 6px; border-radius:0 var(--radius-md) var(--radius-md) 0; transition:background .2s ease;}
  .ob-phase.status-current{border-left-color:var(--accent); background:var(--accent-soft);}
  .ob-phase.status-done{border-left-color:var(--good);}
  .ob-phase-summary{list-style:none; cursor:pointer; display:flex; align-items:center; gap:10px; padding:11px 8px; border-radius:var(--radius-md); user-select:none;}
  .ob-phase-summary::-webkit-details-marker{display:none;}
  .ob-phase-summary::marker{content:'';}
  .ob-phase-summary:hover{background:var(--desk);}
  .ob-phase-badge{width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0;}
  .ob-phase.status-done .ob-phase-badge{background:var(--good-soft); color:var(--good);}
  .ob-phase.status-current .ob-phase-badge{background:var(--accent); color:#fff; animation:obBadgePulse 2.2s ease-in-out infinite;}
  .ob-phase.status-upcoming .ob-phase-badge{background:var(--desk); color:var(--muted); border:1px solid var(--border);}
  @keyframes obBadgePulse{0%,100%{box-shadow:0 0 0 0 var(--accent-soft);}50%{box-shadow:0 0 0 6px transparent;}}
  .ob-phase-name{flex:1; font-size:13px; font-weight:700; color:var(--ink); text-transform:uppercase; letter-spacing:.03em;}
  .ob-phase-count{font-size:12px; color:var(--muted); font-weight:600; flex-shrink:0;}
  .ob-phase.status-upcoming .ob-phase-summary{opacity:.72;}
  .ob-phase-body{padding:0 8px 8px 42px;}
  .ob-phase-nav{display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:14px;}
  .ob-osnova-head{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;}
  .ob-preview-banner{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 14px; margin-bottom:16px; border-radius:var(--radius-md); background:var(--accent-soft); color:var(--accent); font-size:13px; font-weight:600;}

  .ob-phase-hero{display:flex; align-items:center; gap:20px; background:linear-gradient(135deg, var(--accent-soft), var(--paper)); border:1px solid var(--accent-line); border-radius:var(--radius-md); padding:20px; margin-bottom:16px; position:relative; overflow:hidden;}
  .ob-phase-hero::before{content:''; position:absolute; width:180px; height:180px; border-radius:50%; background:var(--accent); opacity:.07; top:-70px; right:-60px; pointer-events:none;}
  .ob-phase-ring{--pct:0; width:76px; height:76px; border-radius:50%; flex-shrink:0; position:relative; z-index:1;
    background:conic-gradient(var(--accent) calc(var(--pct) * 3.6deg), var(--accent-line) 0deg); transition:background .5s ease, transform .4s ease;}
  .ob-phase-ring.pulse{animation:obRingPulse .55s ease;}
  .ob-phase-ring::after{content:''; position:absolute; inset:6px; border-radius:50%; background:var(--paper);}
  .ob-phase-ring-label{position:absolute; inset:6px; z-index:1; display:flex; align-items:center; justify-content:center; font-size:19px; font-weight:800; color:var(--accent-ink);}
  .ob-phase-hero-body{position:relative; z-index:1; min-width:0;}
  .ob-phase-eyebrow{font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--accent-ink); margin:0 0 4px;}
  .ob-phase-hero-title{font-size:21px; font-weight:800; letter-spacing:-.01em; color:var(--ink); margin:0 0 4px;}
  .ob-phase-hero-count{font-size:12.5px; color:var(--muted); margin:0;}
  .ob-phase-toast{position:absolute; top:14px; right:16px; background:var(--good); color:#fff; font-size:12px; font-weight:700;
    padding:6px 12px; border-radius:999px; z-index:2; opacity:0; transform:translateY(-6px) scale(.92); pointer-events:none;
    transition:opacity .3s ease, transform .3s ease;}
  .ob-phase-toast.show{opacity:1; transform:translateY(0) scale(1);}

  @keyframes obStepIn{from{opacity:0; transform:translateY(8px);} to{opacity:1; transform:translateY(0);}}
  .ob-step.ob-step-card{border:1px solid var(--border); border-bottom:1px solid var(--border); border-radius:var(--radius-md); padding:12px 14px; margin-bottom:10px;
    animation:obStepIn .35s ease both; animation-delay:calc(var(--i, 0) * 60ms);
    transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;}
  .ob-step.ob-step-card:last-child{margin-bottom:0; border-bottom:1px solid var(--border);}
  .ob-step.ob-step-card:hover{transform:translateY(-2px); border-color:var(--accent-line); box-shadow:0 6px 16px rgba(0,0,0,.06);}
  .ob-step.ob-step-card.done{background:var(--good-soft); border-color:var(--good);}

  .ob-phase-support{display:flex; gap:8px; align-items:flex-start; font-size:12.5px; color:var(--ink-2); line-height:1.5;
    background:var(--desk); border-radius:var(--radius-md); padding:9px 11px; margin:0 0 10px;}
  .ob-phase-support .ob-support-emoji{flex-shrink:0;}

  .mz-tracker-title{font-size:14.5px; font-weight:700; color:var(--ink); margin:22px 0 12px; padding-top:18px; border-top:1px solid var(--border);}

  .mz-summary-bar{display:flex; align-items:center; gap:12px; background:linear-gradient(135deg, var(--accent-soft), var(--good-soft));
    border:1px solid var(--accent-line); border-radius:var(--radius-xl); padding:14px 18px; margin-bottom:16px;}
  .mz-summary-icon{font-size:26px; line-height:1; flex-shrink:0;}
  .mz-summary-label{font-size:11.5px; font-weight:600; color:var(--ink-2); text-transform:uppercase; letter-spacing:.03em;}
  .mz-summary-value{font-size:22px; font-weight:800; color:var(--accent-ink); letter-spacing:-.01em; margin-top:1px;
    transition:transform .25s ease;}
  .mz-summary-value.mz-bump{animation:mzBump .4s ease;}
  @keyframes mzBump{0%{transform:scale(1);} 40%{transform:scale(1.12);} 100%{transform:scale(1);}}

  .mz-card{border:1px solid var(--border); border-radius:var(--radius-xl); padding:16px 18px; margin-bottom:12px; background:var(--desk);}
  .mz-card-head{display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px;}
  .mz-card-month{font-size:13.5px; font-weight:700; color:var(--ink);}
  .mz-card-badge{font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:999px; background:var(--accent-soft); color:var(--accent-ink); flex-shrink:0;}
  .mz-card-note{font-size:12px; color:var(--muted); margin:0 0 12px; line-height:1.5;}
  .mz-status-picker{display:flex; gap:6px;}
  .mz-status-btn{flex:1; padding:9px 4px; border:1.5px solid var(--border); border-radius:var(--radius-md); background:var(--paper);
    font-size:12px; font-weight:700; color:var(--ink-2); cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:2px;
    transition:border-color .15s ease, background .15s ease, transform .1s ease;}
  .mz-status-btn .mz-status-amt{font-size:10px; font-weight:600; color:var(--muted);}
  .mz-status-btn:hover{transform:translateY(-1px);}
  .mz-status-btn:active{transform:translateY(0) scale(.97);}

  .mz-status-btn-fit{border-color:var(--accent-line); background:var(--accent-soft);}
  .mz-status-btn-fit:hover{border-color:var(--accent);}
  .mz-status-btn-fit.is-selected{border-color:var(--accent); background:var(--accent); color:#fff;}
  .mz-status-btn-fit.is-selected .mz-status-amt{color:rgba(255,255,255,.85);}

  .mz-status-btn-std{border-color:var(--amber-soft); background:var(--amber-soft);}
  .mz-status-btn-std:hover{border-color:var(--amber);}
  .mz-status-btn-std.is-selected{border-color:var(--amber); background:var(--amber); color:#fff;}
  .mz-status-btn-std.is-selected .mz-status-amt{color:rgba(255,255,255,.85);}

  .mz-status-btn-top{border-color:var(--good-soft); background:var(--good-soft);}
  .mz-status-btn-top:hover{border-color:var(--good);}
  .mz-status-btn-top.is-selected{border-color:var(--good); background:var(--good); color:#fff; transform:translateY(-1px) scale(1.02);}
  .mz-status-btn-top.is-selected .mz-status-amt{color:rgba(255,255,255,.85);}

  .mz-quarter-months{display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px;}
  .mz-month-label{font-size:10.5px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.02em; margin-bottom:6px; text-align:center;}
  .mz-month-col-final .mz-month-label{color:var(--accent-ink);}
  .mz-doplatok{border-radius:var(--radius-md); padding:10px 12px; font-size:12.5px; text-align:center; background:var(--paper); border:1px dashed var(--border); line-height:1.5;}
  .mz-doplatok strong{font-weight:800;}
  .mz-doplatok-win{color:var(--good); font-weight:700; background:var(--good-soft); border:1px solid var(--accent-line); font-size:13px; padding:12px;}
  .mz-doplatok-flat{color:var(--ink-2);}
  .mz-doplatok-hint{color:var(--muted);}
  .mz-doplatok-hint strong{color:var(--ink-2);}
  @media(max-width:560px){.mz-quarter-months{grid-template-columns:1fr;} .mz-month-col{border-bottom:1px solid var(--border); padding-bottom:10px;} .mz-month-col:last-child{border-bottom:none; padding-bottom:0;} .mz-summary-value{font-size:19px;}}

  .ob-info{position:relative; display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px;
    border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); font-size:10px; font-weight:700; cursor:help;
    margin-left:6px; flex-shrink:0; border:1px solid var(--accent-line); vertical-align:middle;}
  .ob-info:hover, .ob-info:focus{background:var(--accent); color:#fff; border-color:var(--accent); outline:none;}
  .ob-info-bubble{position:absolute; left:50%; bottom:calc(100% + 8px); transform:translateX(-50%) translateY(4px);
    width:240px; max-width:min(240px, 78vw); background:var(--ink); color:#fff; font-size:11.5px; font-weight:400; line-height:1.5;
    padding:8px 10px; border-radius:8px; text-align:left; opacity:0; pointer-events:none; transition:opacity .15s ease, transform .15s ease; z-index:20; box-shadow:var(--shadow-md);}
  .ob-info-bubble::after{content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:var(--ink);}
  .ob-info:hover .ob-info-bubble, .ob-info:focus .ob-info-bubble{opacity:1; transform:translateX(-50%) translateY(0);}

  .ob-step{display:flex; align-items:flex-start; gap:12px; padding:12px 4px; border-bottom:1px solid var(--border); transition:background .25s ease;}
  .ob-step:last-child{border-bottom:none;}
  .ob-step.ob-highlight{background:var(--accent-soft); border-radius:var(--radius-md);}
  .ob-step input[type=checkbox]{appearance:none; -webkit-appearance:none; width:22px; height:22px; margin-top:0; flex-shrink:0;
    border:2px solid var(--line-strong); border-radius:50%; cursor:pointer; position:relative; transition:background .2s ease, border-color .2s ease;}
  .ob-step input[type=checkbox]:hover{border-color:var(--accent);}
  .ob-step input[type=checkbox]:checked{background:var(--good); border-color:var(--good); animation:obCheckPop .3s ease;}
  .ob-step input[type=checkbox]:checked::after{content:''; position:absolute; left:6px; top:2px; width:5px; height:10px;
    border:solid #fff; border-width:0 2px 2px 0; transform:rotate(45deg);}
  @keyframes obCheckPop{0%{transform:scale(.65);}60%{transform:scale(1.18);}100%{transform:scale(1);}}
  .ob-ongoing-dot{width:22px; height:22px; flex-shrink:0; margin-top:0; display:flex; align-items:center; justify-content:center;
    font-size:20px; line-height:1; color:var(--accent);}
  .ob-step-body{flex:1; min-width:0;}
  .ob-step-title{font-size:14px; font-weight:600; color:var(--ink);}
  .ob-step.done .ob-step-title{color:var(--muted); text-decoration:line-through;}
  .ob-step-desc{font-size:12.5px; color:var(--muted); line-height:1.5; margin-top:2px;}
  .ob-step-actions{display:flex; align-items:center; gap:6px; flex-shrink:0;}
  .ob-add-form{display:flex; flex-direction:column; gap:10px;}
  .ob-add-row{display:grid; grid-template-columns:1fr 2fr; gap:10px;}
  .ob-team-row{display:flex; align-items:center; gap:12px; padding:12px 4px; border-bottom:1px solid var(--border);}
  .ob-team-row:last-child{border-bottom:none;}
  .ob-team-ini{width:34px; height:34px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12.5px; font-weight:600; flex-shrink:0;}
  .ob-team-body{flex:1; min-width:0;}
  .ob-team-name{font-size:13.5px; font-weight:600; color:var(--ink);}
  .ob-team-status{font-size:12px; color:var(--muted); margin-top:1px;}
  .ob-team-bar-track{width:100%; height:5px; border-radius:999px; background:var(--desk); overflow:hidden; margin:6px 0 3px;}
  .ob-team-bar-fill{height:100%; background:var(--accent); border-radius:999px;}
  .ob-team-stalled{display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:700; color:var(--amber); background:var(--amber-soft); padding:2px 8px; border-radius:999px; margin-top:5px;}
  .ob-grad-badge{font-size:18px; flex-shrink:0;}
  @media(max-width:720px){ .ob-add-row{grid-template-columns:1fr;} }

  .ob-confetti{position:fixed; inset:0; pointer-events:none; z-index:9999; overflow:hidden;}
  .ob-confetti span{position:absolute; top:-12px; width:8px; height:14px; opacity:.9; border-radius:2px; animation:obConfettiFall linear forwards;}
  @keyframes obConfettiFall{0%{transform:translateY(0) rotate(0deg); opacity:1;}100%{transform:translateY(110vh) rotate(560deg); opacity:.35;}}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Cesta nováčika</h1>
    <p><?= $isOwner ? 'Onboarding checklist pre nových poradcov · spravuješ osnovu a priraďuješ nováčikov' : 'Tvoj postup pri zaučovaní — odškrtávaj, ako napredúvaš' ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card ob-hero">
    <div class="ob-ring" id="obRing" style="--pct:<?= (int)$pct ?>;">
      <div class="ob-ring-label">
        <div class="ob-ring-pct" id="obRingPct"><?= (int)$pct ?>%</div>
        <div class="ob-ring-sub">hotovo</div>
      </div>
    </div>
    <div class="ob-progress-info">
      <h4 id="obProgressHeading"><?= $doneCount ?> z <?= $totalSteps ?> krokov dokončených</h4>
      <p><?php if ($currentPhaseName !== null): ?>Aktuálna fáza: <?= h($currentPhaseName) ?><?php elseif ($totalSteps > 0): ?>Celá osnova je dokončená — skvelá práca!<?php else: ?>Osnova zatiaľ nie je pripravená.<?php endif; ?></p>
      <span class="ob-progress-badge" id="obMotivation"><?= h(obMotivation((int)$pct)) ?></span>
    </div>
  </div>

  <?php if ($totalSteps > 0): ?>
  <div class="card ob-journey-card">
    <?php
      $jSpacing = 96; $jAmp = 20; $jMidY = 44; $jPad = 30;
      $jN = count($phaseList);
      $jWidth = $jPad * 2 + $jSpacing * max(0, $jN - 1);
      $jHeight = 122;
      $jPoints = [];
      foreach ($phaseList as $jName => $jSt) {
          $jPoints[] = ['x' => $jPad + $jSt['idx'] * $jSpacing, 'y' => $jMidY + sin($jSt['idx'] * 1.1) * $jAmp, 'name' => $jName, 'st' => $jSt];
      }
      $jPathD = ''; $jSegLen = [];
      foreach ($jPoints as $k => $jp) {
          $jPathD .= ($k === 0 ? 'M' : 'L') . round($jp['x'], 1) . ',' . round($jp['y'], 1) . ' ';
          if ($k > 0) {
              $dx = $jp['x'] - $jPoints[$k - 1]['x']; $dy = $jp['y'] - $jPoints[$k - 1]['y'];
              $jSegLen[] = sqrt($dx * $dx + $dy * $dy);
          }
      }
      $jTotalLen = array_sum($jSegLen);
      $jProgressLen = 0;
      foreach ($jPoints as $k => $jp) {
          if ($k === count($jPoints) - 1) break;
          if ($jp['st']['status'] === 'done') { $jProgressLen += $jSegLen[$k]; }
          elseif ($jp['st']['status'] === 'current') {
              $segPct = $jp['st']['total'] > 0 ? ($jp['st']['done'] / $jp['st']['total']) : 0;
              $jProgressLen += $jSegLen[$k] * $segPct;
              break;
          } else { break; }
      }
    ?>
    <div class="ob-journey-scroll">
      <svg viewBox="0 0 <?= $jWidth ?> <?= $jHeight ?>" width="<?= $jWidth ?>" height="<?= $jHeight ?>" class="ob-journey-svg" role="img" aria-label="Cesta naprieč fázami onboardingu">
        <?php if ($jN > 1): ?>
        <path d="<?= trim($jPathD) ?>" class="oj-track" />
        <path d="<?= trim($jPathD) ?>" class="oj-progress" style="stroke-dasharray:<?= round($jTotalLen, 1) ?>; stroke-dashoffset:<?= round($jTotalLen - $jProgressLen, 1) ?>;" />
        <?php endif; ?>
        <?php foreach ($jPoints as $jp): $jSt = $jp['st']; ?>
        <a href="?phase=<?= $jSt['idx'] ?><?= $novicePreview ? '&view=novice' : '' ?>" class="oj-stop status-<?= $jSt['status'] ?>" aria-label="<?= h($jp['name']) ?> (<?= $jSt['done'] ?>/<?= $jSt['total'] ?>)">
          <?php if ($jSt['status'] === 'current'): ?><circle cx="<?= $jp['x'] ?>" cy="<?= $jp['y'] ?>" r="15" class="oj-pulse" /><?php endif; ?>
          <circle cx="<?= $jp['x'] ?>" cy="<?= $jp['y'] ?>" r="15" class="oj-dot" />
          <text x="<?= $jp['x'] ?>" y="<?= $jp['y'] ?>" class="oj-dot-label" text-anchor="middle" dominant-baseline="central"><?= $jSt['status'] === 'done' ? '✓' : ($jSt['idx'] + 1) ?></text>
          <text x="<?= $jp['x'] ?>" y="<?= $jp['y'] + 30 ?>" class="oj-name" text-anchor="middle"><?= h($jp['name']) ?></text>
        </a>
        <?php endforeach; ?>
      </svg>
    </div>
  </div>

  <?php if ($nextStep): $npIdx = $phaseIndexByName[$nextStep['phase']]; ?>
  <div class="card ob-next-card">
    <span class="ob-next-icon">🚀</span>
    <div class="ob-next-body">
      <div class="ob-next-label">Ďalší krok</div>
      <div class="ob-next-title"><?= h($nextStep['title']) ?></div>
    </div>
    <button type="button" class="pillbtn solid" onclick="obJumpStep(<?= $npIdx ?>, <?= (int)$nextStep['id'] ?>)">Pokračovať →</button>
  </div>
  <?php else: ?>
  <div class="card ob-next-card ob-next-done">
    <span class="ob-next-title">🎉 Celá cesta nováčika je dokončená — gratulujeme!</span>
    <button type="button" class="pillbtn solid" id="certBtn" data-advisor-name="<?= h($me['name']) ?>" data-total-steps="<?= (int)$totalSteps ?>" data-phase-count="<?= count($phases) ?>">Stiahnuť certifikát</button>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($isOwner): ?>
  <div class="card">
    <h3>Priradiť nováčikovi</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Priradený poradca uvidí Cestu nováčika vo svojej ľavej lište aj pripomienku na Domov — vidí len checklist, osnovu upravuješ výhradne ty.
    </p>
    <?php if (!$teamAdvisors): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <span class="es-title">Zatiaľ žiadni ďalší poradcovia</span>
        <span class="es-sub">Pridaj poradcu v Admin sekcii, potom mu sem vieš priradiť onboarding.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($teamAdvisors as $ta): $assigned = !empty($ta['onboarding_started_at']); $taPct = ($assigned && $totalSteps > 0) ? round((int)($ta['doneCount'] ?? 0) / $totalSteps * 100) : 0; ?>
    <div class="ob-team-row">
      <span class="ob-team-ini" style="background:<?= h($ta['color']) ?>;"><?= h(advisorInitials($ta['name'])) ?></span>
      <div class="ob-team-body">
        <div class="ob-team-name"><?= h($ta['name']) ?></div>
        <?php if ($assigned): ?>
          <div class="ob-team-bar-track"><div class="ob-team-bar-fill" style="width:<?= $taPct ?>%;"></div></div>
          <div class="ob-team-status"><?= (int)($ta['doneCount'] ?? 0) ?>/<?= $totalSteps ?> · <?= $taPct ?> % · posledná aktivita: <?= h(obRelativeTime($ta['lastDone'] ?? null)) ?></div>
          <?php if (!empty($ta['stalled'])): ?><span class="ob-team-stalled">⚠️ Bez pohybu</span><?php endif; ?>
        <?php else: ?>
          <div class="ob-team-status">Zatiaľ nepriradené</div>
        <?php endif; ?>
      </div>
      <form method="post" style="margin:0;">
        <input type="hidden" name="<?= $assigned ? 'unassign_advisor_id' : 'assign_advisor_id' ?>" value="<?= (int)$ta['id'] ?>">
        <button type="submit" class="toggle-btn"><?= $assigned ? 'Odobrať' : 'Priradiť' ?></button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($isOwner && $graduates): ?>
  <div class="card">
    <h3>História absolventov</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Kto už onboarding dokončil — záznam zostáva aj po odobratí priradenia.
    </p>
    <?php foreach ($graduates as $g): ?>
    <div class="ob-team-row">
      <span class="ob-team-ini" style="background:<?= h($g['color']) ?>;"><?= h(advisorInitials($g['name'])) ?></span>
      <div class="ob-team-body">
        <div class="ob-team-name"><?= h($g['name']) ?></div>
        <div class="ob-team-status">Dokončil(a) <?= h((new DateTime($g['onboarding_completed_at']))->format('j.n.Y')) ?></div>
      </div>
      <span class="ob-grad-badge">🎓</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <?php if (!$cardMode): ?>
    <div class="ob-osnova-head">
      <h3 style="margin:0;">Osnova</h3>
      <a class="pillbtn" href="?view=novice">👀 Náhľad nováčika</a>
    </div>
    <?php if (!$phases): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span class="es-title">Zatiaľ žiadne kroky</span>
        <span class="es-sub">Pridaj prvý krok osnovy nižšie.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($phases as $phaseName => $phaseSteps): $st = $phaseList[$phaseName]; $phaseOpen = $isOwner || $st['status'] !== 'done'; ?>
      <details class="ob-phase status-<?= $st['status'] ?>" id="ob-phase-<?= $st['idx'] ?>" <?= $phaseOpen ? 'open' : '' ?>>
        <summary class="ob-phase-summary">
          <span class="ob-phase-badge" id="ob-phase-badge-<?= $st['idx'] ?>"><?= $st['status'] === 'done' ? '✓' : ($st['idx'] + 1) ?></span>
          <span class="ob-phase-name"><?= h($phaseName) ?></span>
          <span class="ob-phase-count" id="ob-phase-count-<?= $st['idx'] ?>"><?= $st['done'] ?>/<?= $st['total'] ?></span>
        </summary>
        <div class="ob-phase-body">
      <?php if (isset($OB_PHASE_SUPPORT[$phaseName])): ?>
      <div class="ob-phase-support"><span class="ob-support-emoji">🤝</span><span><?= h($OB_PHASE_SUPPORT[$phaseName]) ?></span></div>
      <?php endif; ?>
      <?php obRenderSteps($phaseSteps, $st['idx'], $doneStepIds, $OB_TOOLTIPS, $isOwner, $allPhaseNames); ?>
      <?php obRenderMzTracker($phaseName, $mzStatusMap); ?>
        </div>
      </details>
    <?php endforeach; ?>
    <?php else: $vp = $novicePreview ? '&view=novice' : ''; ?>
    <?php if ($novicePreview): ?>
    <div class="ob-preview-banner">
      <span>👀 Náhľad pohľadu, aký vidí priradený nováčik.</span>
      <a class="pillbtn" href="?">← Späť na správu osnovy</a>
    </div>
    <?php endif; ?>
    <?php if ($selectedPhaseName === null): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span class="es-title">Zatiaľ žiadne kroky</span>
        <span class="es-sub">Owner sem zatiaľ nič nepridal.</span>
      </div>
    <?php else: $phaseSteps = $phases[$selectedPhaseName]; $st = $phaseList[$selectedPhaseName]; ?>
    <div class="ob-phase-nav">
      <?php if ($selectedPhaseIdx > 0): ?><a class="pillbtn" href="?phase=<?= $selectedPhaseIdx - 1 ?><?= $vp ?>">← Predchádzajúca</a><?php else: ?><span></span><?php endif; ?>
      <?php if ($isOwner): ?><a class="pillbtn" href="?">Celá osnova</a><?php else: ?><span></span><?php endif; ?>
      <?php if ($selectedPhaseIdx < $maxPhaseIdx): ?><a class="pillbtn" href="?phase=<?= $selectedPhaseIdx + 1 ?><?= $vp ?>">Ďalšia fáza →</a><?php else: ?><span></span><?php endif; ?>
    </div>
    <?php $phasePct = $st['total'] > 0 ? round($st['done'] / $st['total'] * 100) : 0; ?>
    <div class="ob-phase-hero">
      <span class="ob-phase-toast" id="ob-phase-toast-<?= $st['idx'] ?>">🎉 Fáza dokončená!</span>
      <div class="ob-phase-ring" id="ob-phase-ring-<?= $st['idx'] ?>" style="--pct:<?= (int)$phasePct ?>;">
        <div class="ob-phase-ring-label" id="ob-phase-badge-<?= $st['idx'] ?>"><?= $st['status'] === 'done' ? '✓' : ($st['idx'] + 1) ?></div>
      </div>
      <div class="ob-phase-hero-body">
        <p class="ob-phase-eyebrow">Fáza <?= $st['idx'] + 1 ?> z <?= count($phaseList) ?></p>
        <h3 class="ob-phase-hero-title"><?= h($selectedPhaseName) ?></h3>
        <p class="ob-phase-hero-count"><span id="ob-phase-count-<?= $st['idx'] ?>"><?= $st['done'] ?>/<?= $st['total'] ?></span> krokov hotových</p>
      </div>
    </div>
    <?php if (isset($OB_PHASE_SUPPORT[$selectedPhaseName])): ?>
    <div class="ob-phase-support"><span class="ob-support-emoji">🤝</span><span><?= h($OB_PHASE_SUPPORT[$selectedPhaseName]) ?></span></div>
    <?php endif; ?>
    <?php obRenderSteps($phaseSteps, $st['idx'], $doneStepIds, $OB_TOOLTIPS, $isOwner, $allPhaseNames, true); ?>
    <?php obRenderMzTracker($selectedPhaseName, $mzStatusMap); ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($ongoingSteps || $isOwner): ?>
  <div class="card">
    <h3>Priebežne</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Bežná práca poradcu, ktorá pokračuje stále — bez odškrtávania, mimo percenta postupu vyššie.
    </p>
    <?php if (!$ongoingSteps): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg>
        <span class="es-title">Zatiaľ žiadne priebežné úlohy</span>
        <span class="es-sub">Pridaj prvú nižšie — vyber fázu "Priebežne" (alebo napíš novú).</span>
      </div>
    <?php endif; ?>
    <?php foreach ($ongoingSteps as $oIdx => $s): $tip = $OB_TOOLTIPS[$s['title']] ?? null; $isFirstOngoing = $oIdx === 0; $isLastOngoing = $oIdx === count($ongoingSteps) - 1; ?>
    <div class="ob-step ob-step-ongoing ob-step-card" id="ob-step-<?= (int)$s['id'] ?>" style="--i:<?= $oIdx ?>;">
      <span class="ob-ongoing-dot" aria-hidden="true">•</span>
      <div class="ob-step-body">
        <div class="ob-step-title"><?= h($s['title']) ?><?php if ($tip): ?><span class="ob-info" tabindex="0">i<span class="ob-info-bubble"><?= h($tip) ?></span></span><?php endif; ?></div>
        <?php if ($s['description']): ?><div class="ob-step-desc"><?= h($s['description']) ?></div><?php endif; ?>
      </div>
      <div class="ob-step-actions">
        <?php if ($s['link_url']): ?><a class="toggle-btn" href="<?= h($s['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
        <?php if ($isOwner): ?>
        <?php if (!$isFirstOngoing): ?>
        <form method="post" style="margin:0;">
          <input type="hidden" name="move_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="direction" value="up">
          <button type="submit" class="toggle-btn" title="Posunúť hore">↑</button>
        </form>
        <?php endif; ?>
        <?php if (!$isLastOngoing): ?>
        <form method="post" style="margin:0;">
          <input type="hidden" name="move_id" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="direction" value="down">
          <button type="submit" class="toggle-btn" title="Posunúť dole">↓</button>
        </form>
        <?php endif; ?>
        <button type="button" class="toggle-btn" onclick="obEdit(<?= (int)$s['id'] ?>)">Upraviť</button>
        <form method="post" style="margin:0;" onsubmit="return confirm('Naozaj zmazať tento krok?');">
          <input type="hidden" name="delete_id" value="<?= (int)$s['id'] ?>">
          <button type="submit" class="toggle-btn">Zmazať</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($isOwner): ?>
    <form method="post" class="kb-edit" id="ob-edit-<?= (int)$s['id'] ?>" style="display:none; margin-bottom:12px;">
      <input type="hidden" name="edit_id" value="<?= (int)$s['id'] ?>">
      <select name="phase_select" onchange="obPhaseSelectChange(this)" required>
        <?php foreach ($allPhaseNames as $ph): ?>
          <option value="<?= h($ph) ?>" <?= $ph === $s['phase'] ? 'selected' : '' ?>><?= h($ph) ?></option>
        <?php endforeach; ?>
        <option value="__new__">+ Nová fáza…</option>
      </select>
      <input type="text" name="phase_new" placeholder="Názov novej fázy" style="display:none;">
      <input type="text" name="title" value="<?= h($s['title']) ?>" placeholder="Názov kroku" required>
      <textarea name="description" rows="2" placeholder="Popis (nepovinné)"><?= h($s['description']) ?></textarea>
      <input type="text" name="link_url" value="<?= h((string)$s['link_url']) ?>" placeholder="Odkaz (nepovinné, napr. /financna-medzera/)">
      <div style="display:flex; gap:8px;">
        <button type="submit" class="pillbtn solid">Uložiť</button>
        <button type="button" class="pillbtn" onclick="obCancel(<?= (int)$s['id'] ?>)">Zrušiť</button>
      </div>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($isOwner): ?>
  <div class="card">
    <h3>Pridať krok</h3>
    <form method="post" class="ob-add-form">
      <input type="hidden" name="add" value="1">
      <div class="ob-add-row">
        <select name="phase_select" onchange="obPhaseSelectChange(this)" required>
          <option value="">— Vyber fázu —</option>
          <?php foreach ($allPhaseNames as $ph): ?>
            <option value="<?= h($ph) ?>"><?= h($ph) ?></option>
          <?php endforeach; ?>
          <option value="__new__">+ Nová fáza…</option>
        </select>
        <input type="text" name="title" placeholder="Názov kroku" required>
      </div>
      <input type="text" name="phase_new" placeholder="Názov novej fázy" style="display:none;">
      <textarea name="description" rows="2" placeholder="Popis (nepovinné)"></textarea>
      <input type="text" name="link_url" placeholder="Odkaz (nepovinné, napr. /financna-medzera/)">
      <button type="submit" class="pillbtn solid" style="align-self:start; width:max-content;">Pridať krok</button>
    </form>
  </div>
  <?php endif; ?>

</main>
<script>
function obEdit(id) {
  document.getElementById('ob-step-' + id).style.display = 'none';
  document.getElementById('ob-edit-' + id).style.display = 'flex';
  document.getElementById('ob-edit-' + id).style.flexDirection = 'column';
  document.getElementById('ob-edit-' + id).style.gap = '10px';
}
function obCancel(id) {
  document.getElementById('ob-step-' + id).style.display = 'flex';
  document.getElementById('ob-edit-' + id).style.display = 'none';
}
function obPhaseSelectChange(sel) {
  var form = sel.closest('form');
  var textInput = form.querySelector('input[name=phase_new]');
  if (!textInput) return;
  if (sel.value === '__new__') {
    textInput.style.display = 'block';
    textInput.required = true;
    textInput.focus();
  } else {
    textInput.style.display = 'none';
    textInput.required = false;
    textInput.value = '';
  }
}
var OB_CARD_MODE = <?= $cardMode ? 'true' : 'false' ?>;
var OB_VIEW_PARAM = <?= $novicePreview ? "'&view=novice'" : "''" ?>;
function obJumpStep(phaseIdx, stepId) {
  if (OB_CARD_MODE) {
    location.href = '?phase=' + phaseIdx + OB_VIEW_PARAM + '#ob-step-' + stepId;
    return;
  }
  var phase = document.getElementById('ob-phase-' + phaseIdx);
  if (phase) phase.open = true;
  var row = document.getElementById('ob-step-' + stepId);
  if (!row) return;
  row.scrollIntoView({ behavior: 'smooth', block: 'center' });
  row.classList.add('ob-highlight');
  setTimeout(function () { row.classList.remove('ob-highlight'); }, 1600);
}
document.addEventListener('DOMContentLoaded', function () {
  if (location.hash.indexOf('#ob-step-') !== 0) return;
  var row = document.querySelector(location.hash);
  if (!row) return;
  row.classList.add('ob-highlight');
  setTimeout(function () { row.classList.remove('ob-highlight'); }, 1600);
});
function obMotivationText(pct) {
  if (pct >= 100) return 'Hotovo! 🎉';
  if (pct >= 67) return 'Už len kúsok! 🔥';
  if (pct >= 34) return 'Skvelý postup! 💪';
  if (pct >= 1) return 'Pekný štart! 🙌';
  return 'Poďme na to! 🚀';
}
function obConfetti() {
  var colors = ['#ffffff', '#fde68a', '#a7f3d0', '#bfdbfe', '#fbcfe8'];
  var wrap = document.createElement('div');
  wrap.className = 'ob-confetti';
  for (var i = 0; i < 44; i++) {
    var s = document.createElement('span');
    s.style.left = (Math.random() * 100) + 'vw';
    s.style.background = colors[Math.floor(Math.random() * colors.length)];
    s.style.animationDuration = (2 + Math.random() * 1.5) + 's';
    s.style.animationDelay = (Math.random() * 0.4) + 's';
    wrap.appendChild(s);
  }
  document.body.appendChild(wrap);
  setTimeout(function () { wrap.remove(); }, 4000);
}

function obEscapeHtml(x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

function obBuildCertificateHtml(name, totalSteps, phaseCount) {
  var dateStr = new Date().toLocaleDateString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric' });
  var css = "\n"
    + "@font-face { font-family:'DejaVu Sans'; src:url('vendor/dompdf/lib/fonts/DejaVuSans.ttf'); }\n"
    + "@font-face { font-family:'DejaVu Sans'; font-weight:bold; src:url('vendor/dompdf/lib/fonts/DejaVuSans-Bold.ttf'); }\n"
    + "@font-face { font-family:'DejaVu Serif'; src:url('vendor/dompdf/lib/fonts/DejaVuSerif.ttf'); }\n"
    + "@font-face { font-family:'DejaVu Serif'; font-weight:bold; src:url('vendor/dompdf/lib/fonts/DejaVuSerif-Bold.ttf'); }\n"
    + "@font-face { font-family:'DejaVu Serif'; font-style:italic; src:url('vendor/dompdf/lib/fonts/DejaVuSerif-Italic.ttf'); }\n"
    + "* { box-sizing:border-box; }\n"
    + "body { margin:0; padding:0; font-family:'DejaVu Sans',sans-serif; color:#20242b; background:#fff; }\n"
    + ".cert-outer { border:0.75pt solid #c7d2fe; border-radius:3mm; padding:3mm; margin-top:8mm; }\n"
    + ".cert-border { border:1.5pt solid #4f46e5; border-radius:2mm; padding:16mm 14mm 14mm; text-align:center; }\n"
    + ".cert-badge { width:20mm; height:20mm; border-radius:50%; background:#4f46e5; border:2pt solid #c7d2fe; margin:0 auto 6mm; position:relative; }\n"
    + ".cert-badge-check { position:absolute; left:7mm; top:6mm; width:5mm; height:9mm; border-right:1.4pt solid #fff; border-bottom:1.4pt solid #fff; transform:rotate(40deg); }\n"
    + ".cert-kicker { font-size:9.5pt; letter-spacing:2.5pt; text-transform:uppercase; color:#4f46e5; font-weight:bold; margin-bottom:5mm; }\n"
    + ".cert-title { font-family:'DejaVu Serif',serif; font-size:26pt; font-weight:bold; color:#1f2333; margin-bottom:6mm; }\n"
    + ".cert-ornament { width:50mm; margin:0 auto 8mm; border-collapse:collapse; }\n"
    + ".cert-ornament td { padding:0; }\n"
    + ".cert-ornament .cert-line { height:0.75pt; background:#c7d2fe; }\n"
    + ".cert-ornament .cert-dot { width:8pt; text-align:center; }\n"
    + ".cert-ornament .cert-dot span { display:inline-block; width:4pt; height:4pt; border-radius:50%; background:#4f46e5; }\n"
    + ".cert-sub { font-family:'DejaVu Serif',serif; font-style:italic; font-size:11.5pt; color:#666; margin-bottom:7mm; }\n"
    + ".cert-name { font-family:'DejaVu Serif',serif; font-size:21pt; font-weight:bold; color:#4f46e5; margin-bottom:8mm; }\n"
    + ".cert-body { font-size:11pt; line-height:1.7; color:#3a3f4a; margin:0 auto 8mm; padding:0 6mm; max-width:130mm; }\n"
    + ".cert-stats { font-size:10pt; color:#4f46e5; font-weight:bold; letter-spacing:.3pt; margin-bottom:14mm; }\n"
    + ".cert-footer { width:100%; margin-top:4mm; border-collapse:collapse; }\n"
    + ".cert-footer td { width:50%; text-align:center; vertical-align:top; padding:0; }\n"
    + ".cert-footer-line { width:42mm; height:0.75pt; background:#d6dae3; margin:0 auto 3mm; }\n"
    + ".cert-footer-label { font-size:9pt; color:#8a8f9c; }\n"
    + ".cert-footer-value { font-size:10pt; color:#333; font-weight:bold; margin-top:1mm; }\n"
    + "@page{ margin:18mm; }\n";
  return '<!DOCTYPE html><html lang="sk"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
    + '<title>Certifikát — Cesta nováčika</title><style>' + css + '</style></head><body>'
    + '<div class="cert-outer"><div class="cert-border">'
    + '<div class="cert-badge"><div class="cert-badge-check"></div></div>'
    + '<div class="cert-kicker">Certifikát o dokončení</div>'
    + '<div class="cert-title">Cesta nováčika</div>'
    + '<table class="cert-ornament"><tr><td class="cert-line"></td><td class="cert-dot"><span></span></td><td class="cert-line"></td></tr></table>'
    + '<div class="cert-sub">Tento certifikát potvrdzuje, že</div>'
    + '<div class="cert-name">' + obEscapeHtml(name) + '</div>'
    + '<div class="cert-body">úspešne absolvoval(a) celý adaptačný program v UNIQA — od podpisu zmluvy, cez všetky vzdelávacie bloky, až po maturitnú skúšku.</div>'
    + '<div class="cert-stats">' + (totalSteps || 0) + ' krokov &middot; ' + (phaseCount || 0) + ' fáz osnovy</div>'
    + '<table class="cert-footer"><tr>'
    + '<td><div class="cert-footer-line"></div><div class="cert-footer-label">Dátum dokončenia</div><div class="cert-footer-value">' + dateStr + '</div></td>'
    + '<td><div class="cert-footer-line"></div><div class="cert-footer-label">Program</div><div class="cert-footer-value">Onboarding VFA</div></td>'
    + '</tr></table>'
    + '</div></div></body></html>';
}

function obDoCertificate() {
  var btn = document.getElementById('certBtn');
  if (!btn) return;
  var name = btn.dataset.advisorName;
  var totalSteps = +btn.dataset.totalSteps || 0;
  var phaseCount = +btn.dataset.phaseCount || 0;
  var orig = btn.textContent;
  btn.textContent = 'Generujem…'; btn.disabled = true;
  var html = obBuildCertificateHtml(name, totalSteps, phaseCount);
  fetch('/pdf.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ html: html, filename: 'Certifikat_Cesta_novacika' })
  })
  .then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.blob();
  })
  .then(function (blob) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'Certifikat_Cesta_novacika.pdf';
    document.body.appendChild(a); a.click();
    setTimeout(function () { document.body.removeChild(a); URL.revokeObjectURL(url); }, 1000);
    btn.textContent = orig; btn.disabled = false;
  })
  .catch(function (e) {
    console.error(e);
    btn.textContent = orig; btn.disabled = false;
    alert('Chyba pri generovaní PDF: ' + e.message);
  });
}
var certBtnEl = document.getElementById('certBtn');
if (certBtnEl) certBtnEl.addEventListener('click', obDoCertificate);

var OB_TOTAL = <?= $totalSteps ?>;
document.addEventListener('change', function(e){
  if (!e.target.matches('input[type=checkbox][data-toggle-step]')) return;
  var stepId = +e.target.dataset.toggleStep;
  var done = e.target.checked;
  var row = document.getElementById('ob-step-' + stepId);
  row.classList.toggle('done', done);

  fetch('/api/onboarding-toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ stepId: stepId, done: done })
  }).catch(function(){});

  var doneNow = document.querySelectorAll('input[data-toggle-step]:checked').length;
  var pct = OB_TOTAL > 0 ? Math.round(doneNow / OB_TOTAL * 100) : 0;
  var ring = document.getElementById('obRing');
  if (ring) {
    ring.style.setProperty('--pct', pct);
    ring.classList.remove('pulse');
    void ring.offsetWidth;
    ring.classList.add('pulse');
  }
  var ringPct = document.getElementById('obRingPct');
  if (ringPct) ringPct.textContent = pct + '%';
  var heading = document.getElementById('obProgressHeading');
  if (heading) heading.textContent = doneNow + ' z ' + OB_TOTAL + ' krokov dokončených';
  var motivation = document.getElementById('obMotivation');
  if (motivation) motivation.textContent = obMotivationText(pct);

  if (done && OB_TOTAL > 0 && doneNow === OB_TOTAL) obConfetti();

  var phaseIdx = row.dataset.phaseIdx;
  if (phaseIdx !== undefined) {
    var phaseSteps = document.querySelectorAll('.ob-step[data-phase-idx="' + phaseIdx + '"] input[type=checkbox]');
    var phaseDone = 0;
    phaseSteps.forEach(function (cb) { if (cb.checked) phaseDone++; });
    var countEl = document.getElementById('ob-phase-count-' + phaseIdx);
    if (countEl) countEl.textContent = phaseDone + '/' + phaseSteps.length;
    var phaseRing = document.getElementById('ob-phase-ring-' + phaseIdx);
    if (phaseRing) {
      phaseRing.style.setProperty('--pct', phaseSteps.length > 0 ? Math.round(phaseDone / phaseSteps.length * 100) : 0);
      phaseRing.classList.remove('pulse');
      void phaseRing.offsetWidth;
      phaseRing.classList.add('pulse');
    }
    if (phaseDone === phaseSteps.length) {
      var badgeEl = document.getElementById('ob-phase-badge-' + phaseIdx);
      if (badgeEl) badgeEl.textContent = '✓';
      var phaseEl = document.getElementById('ob-phase-' + phaseIdx);
      if (phaseEl) phaseEl.classList.add('status-done');
      if (done && phaseSteps.length > 0) {
        var toastEl = document.getElementById('ob-phase-toast-' + phaseIdx);
        if (toastEl) {
          toastEl.classList.add('show');
          setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
        }
      }
    }
  }
});

var MZ_STATUS = <?= json_encode($mzStatusMap, JSON_UNESCAPED_UNICODE) ?>;
var MZ_QUARTERS = [[7,8,9],[10,11,12],[13,14,15],[16,17,18],[19,20,21],[22,23,24]];
function mzAmount(month, status) {
  if (!status) return 0;
  var table = month <= 12 ? { fit: 500, std: 750, top: 1000 } : { fit: 300, std: 500, top: 700 };
  return table[status] || 0;
}
function mzComputeTotal() {
  var total = MZ_STATUS[6] ? mzAmount(6, MZ_STATUS[6]) : 0;
  MZ_QUARTERS.forEach(function (q) {
    var statuses = q.map(function (m) { return MZ_STATUS[m]; });
    if (statuses.every(Boolean)) {
      var finalStatus = statuses[2];
      q.forEach(function (m) { total += mzAmount(m, finalStatus); });
    } else {
      q.forEach(function (m, i) { total += mzAmount(m, statuses[i]); });
    }
  });
  return total;
}
function mzRenderTotal(bump) {
  var value = mzComputeTotal();
  document.querySelectorAll('.mz-total-value').forEach(function (el) {
    el.textContent = value.toLocaleString('sk-SK') + ' €';
    if (bump) {
      el.classList.remove('mz-bump');
      void el.offsetWidth;
      el.classList.add('mz-bump');
    }
  });
}
function mzUpdateQuarterDisplay(month, fromClick) {
  var quarter = null;
  for (var i = 0; i < MZ_QUARTERS.length; i++) {
    if (MZ_QUARTERS[i].indexOf(month) !== -1) { quarter = MZ_QUARTERS[i]; break; }
  }
  if (!quarter) return;
  var el = document.getElementById('mz-doplatok-' + quarter.join('-'));
  if (!el) return;

  var statuses = quarter.map(function (m) { return MZ_STATUS[m]; });
  var filled = statuses.filter(Boolean).length;
  var maxTotal = quarter.reduce(function (s, m) { return s + mzAmount(m, 'top'); }, 0);

  if (filled < 3) {
    if (filled === 0) {
      el.className = 'mz-doplatok mz-doplatok-hint';
      el.textContent = 'Vyplň status za mesiace tohto kvartálu — priebežne ti tu ukážeme aj potenciálny doplatok.';
      return;
    }
    var partialTotal = 0;
    quarter.forEach(function (m, i) { partialTotal += mzAmount(m, statuses[i]); });
    var potential = maxTotal - partialTotal;
    el.className = 'mz-doplatok mz-doplatok-hint';
    if (potential > 0) {
      el.innerHTML = 'Zatiaľ za kvartál: <strong>' + partialTotal + ' €</strong> · pri statuse TOP až <strong>' + maxTotal + ' €</strong> (potenciál +' + potential + ' € 🚀)';
    } else {
      el.innerHTML = 'Zatiaľ za kvartál: <strong>' + partialTotal + ' €</strong> — už si na maxime, drž to! 🏆';
    }
    return;
  }

  var finalStatus = statuses[2];
  var actualTotal = 0, upgradedTotal = 0;
  for (var j = 0; j < quarter.length; j++) {
    actualTotal += mzAmount(quarter[j], statuses[j]);
    upgradedTotal += mzAmount(quarter[j], finalStatus);
  }
  var diff = upgradedTotal - actualTotal;

  if (diff > 0) {
    el.className = 'mz-doplatok mz-doplatok-win';
    el.innerHTML = '🎉 Doplatok DP za kvartál: <strong>+' + diff + ' €</strong> (za 3. mesiac si dosiahol status ' + finalStatus.toUpperCase() + ', doplatia ti rozdiel za celý kvartál).';
    if (fromClick && typeof obConfetti === 'function') obConfetti();
  } else {
    el.className = 'mz-doplatok mz-doplatok-flat';
    el.innerHTML = 'Kvartál uzatvorený na statuse ' + finalStatus.toUpperCase() + ' — spolu <strong>' + actualTotal + ' €</strong> DP.';
  }
}
function mzSelectStatus(btn) {
  var picker = btn.closest('.mz-status-picker');
  var month = +picker.dataset.month;
  var status = btn.dataset.status;
  var wasSelected = btn.classList.contains('is-selected');
  var newStatus = wasSelected ? '' : status;

  picker.querySelectorAll('.mz-status-btn').forEach(function (b) { b.classList.remove('is-selected'); });
  if (!wasSelected) btn.classList.add('is-selected');

  if (newStatus) MZ_STATUS[month] = newStatus; else delete MZ_STATUS[month];
  mzUpdateQuarterDisplay(month, true);
  mzRenderTotal(true);

  fetch('/api/mz-status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ month: month, status: newStatus })
  }).catch(function () {});
}
document.querySelectorAll('.mz-doplatok[id^="mz-doplatok-"]').forEach(function (el) {
  var months = el.id.replace('mz-doplatok-', '').split('-').map(Number);
  mzUpdateQuarterDisplay(months[2]);
});
</script>
<script src="/assets/shell.js?v=20"></script>
</body></html>
