<?php
/**
 * Cesta nováčika — koncept "Mapa cesty a odmeny". Toto NIE JE kontrolný
 * nástroj pre ownera (žiadne odškrtávanie jednotlivých krokov, žiadne
 * sledovanie "kto zaostáva") — je to informačná a motivačná stránka pre
 * samotného poradcu: čo ho čaká (mapa fáz) a čo za to dostane (Model
 * zapracovania). Postup fázou je automatický podľa uplynutého času od
 * formulare_advisors.onboarding_started_at, nie podľa manuálneho plnenia.
 *
 * Prístup: owner (spravuje fázy a ich materiály, priraďuje/odoberá
 * nováčikov) ALEBO poradca, ktorému owner priradil onboarding
 * (onboarding_started_at IS NOT NULL) — ten vidí len svoju cestu, bez
 * možnosti čokoľvek upraviť.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
$isOwner = $me && !empty($me['is_owner']);
$isOnboarding = $me && !empty($me['onboarding_started_at']);
if (!$me || (!$isOwner && !$isOnboarding)) { header('Location: /'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfCheck()) { http_response_code(403); exit('Neplatný CSRF token — obnov stránku a skús to znova.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isOwner && isset($_POST['add_phase'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? '')) ?: '📍';
        $isOngoing = isset($_POST['is_ongoing']) ? 1 : 0;
        $duration = max(0, (int)($_POST['duration_days'] ?? 30));
        $durationMonths = max(0, (int)($_POST['duration_months'] ?? 1));
        $rewardText = trim((string)($_POST['reward_text'] ?? ''));
        $supportText = trim((string)($_POST['support_text'] ?? ''));
        if ($name !== '') {
            $maxSort = (int)db()->query('SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_phases')->fetchColumn();
            try {
                db()->prepare('INSERT INTO formulare_onboarding_phases (name, icon, sort_order, duration_days, duration_months, is_ongoing, reward_text, support_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$name, $icon, $maxSort + 1, $duration, $durationMonths, $isOngoing, $rewardText, $supportText]);
            } catch (Throwable $e) { /* duplicitný názov fázy — ignorované */ }
        }
    } elseif ($isOwner && isset($_POST['edit_phase_id'])) {
        $id = (int)$_POST['edit_phase_id'];
        $name = trim((string)($_POST['name'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? '')) ?: '📍';
        $isOngoing = isset($_POST['is_ongoing']) ? 1 : 0;
        $duration = max(0, (int)($_POST['duration_days'] ?? 30));
        $durationMonths = max(0, (int)($_POST['duration_months'] ?? 1));
        $rewardText = trim((string)($_POST['reward_text'] ?? ''));
        $supportText = trim((string)($_POST['support_text'] ?? ''));
        if ($id && $name !== '') {
            db()->prepare('UPDATE formulare_onboarding_phases SET name = ?, icon = ?, duration_days = ?, duration_months = ?, is_ongoing = ?, reward_text = ?, support_text = ? WHERE id = ?')
                ->execute([$name, $icon, $duration, $durationMonths, $isOngoing, $rewardText, $supportText, $id]);
        }
    } elseif ($isOwner && isset($_POST['delete_phase_id'])) {
        $id = (int)$_POST['delete_phase_id'];
        db()->prepare('DELETE FROM formulare_onboarding_steps WHERE phase_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM formulare_onboarding_phases WHERE id = ?')->execute([$id]);
    } elseif ($isOwner && isset($_POST['move_phase_id'])) {
        $id = (int)$_POST['move_phase_id'];
        $dir = (string)($_POST['direction'] ?? '');
        $all = db()->query('SELECT id, sort_order FROM formulare_onboarding_phases ORDER BY sort_order, id')->fetchAll();
        $pos = null;
        foreach ($all as $i => $row) { if ((int)$row['id'] === $id) { $pos = $i; break; } }
        if ($pos !== null) {
            $swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
            if (isset($all[$swapPos])) {
                $other = $all[$swapPos];
                db()->prepare('UPDATE formulare_onboarding_phases SET sort_order = ? WHERE id = ?')->execute([$other['sort_order'], $id]);
                db()->prepare('UPDATE formulare_onboarding_phases SET sort_order = ? WHERE id = ?')->execute([$all[$pos]['sort_order'], $other['id']]);
            }
        }
    } elseif ($isOwner && isset($_POST['add_material'])) {
        $phaseId = (int)($_POST['phase_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        if ($phaseId && $title !== '') {
            $msStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM formulare_onboarding_steps WHERE phase_id = ?');
            $msStmt->execute([$phaseId]);
            $maxSort = (int)$msStmt->fetchColumn();
            db()->prepare('INSERT INTO formulare_onboarding_steps (phase, phase_id, title, description, link_url, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute(['', $phaseId, $title, $description, $linkUrl !== '' ? $linkUrl : null, $maxSort + 1]);
        }
    } elseif ($isOwner && isset($_POST['edit_material_id'])) {
        $id = (int)$_POST['edit_material_id'];
        $phaseId = (int)($_POST['phase_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $linkUrl = trim((string)($_POST['link_url'] ?? ''));
        if ($id && $phaseId && $title !== '') {
            db()->prepare('UPDATE formulare_onboarding_steps SET phase_id = ?, title = ?, description = ?, link_url = ? WHERE id = ?')
                ->execute([$phaseId, $title, $description, $linkUrl !== '' ? $linkUrl : null, $id]);
        }
    } elseif ($isOwner && isset($_POST['delete_material_id'])) {
        $id = (int)$_POST['delete_material_id'];
        db()->prepare('DELETE FROM formulare_onboarding_steps WHERE id = ?')->execute([$id]);
    } elseif ($isOwner && isset($_POST['move_material_id'])) {
        $id = (int)$_POST['move_material_id'];
        $dir = (string)($_POST['direction'] ?? '');
        $stmt2 = db()->prepare('SELECT id, phase_id, sort_order FROM formulare_onboarding_steps WHERE id = ?');
        $stmt2->execute([$id]);
        $mat = $stmt2->fetch();
        if ($mat) {
            $sibStmt = db()->prepare('SELECT id, sort_order FROM formulare_onboarding_steps WHERE phase_id = ? ORDER BY sort_order, id');
            $sibStmt->execute([$mat['phase_id']]);
            $siblings = $sibStmt->fetchAll();
            $pos = null;
            foreach ($siblings as $i => $sib) { if ((int)$sib['id'] === $id) { $pos = $i; break; } }
            if ($pos !== null) {
                $swapPos = $dir === 'up' ? $pos - 1 : $pos + 1;
                if (isset($siblings[$swapPos])) {
                    $other = $siblings[$swapPos];
                    db()->prepare('UPDATE formulare_onboarding_steps SET sort_order = ? WHERE id = ?')->execute([$other['sort_order'], $id]);
                    db()->prepare('UPDATE formulare_onboarding_steps SET sort_order = ? WHERE id = ?')->execute([$mat['sort_order'], $other['id']]);
                }
            }
        }
    } elseif ($isOwner && isset($_POST['assign_advisor_id'])) {
        $id = (int)$_POST['assign_advisor_id'];
        if ($id) {
            // Dátum nástupu (nepovinný) — reálny prvý deň "0. mesiaca", zvyčajne
            // 1. v mesiaci. Bez neho fázy bežia po starom (pevný počet dní).
            $startDateRaw = trim((string)($_POST['start_date'] ?? ''));
            $startDate = null;
            if ($startDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateRaw)) $startDate = $startDateRaw;
            // Nový cyklus onboardingu — prípadný predošlý dátum dokončenia
            // (z minulého priradenia) sa vynuluje, nech "História absolventov"
            // odráža aktuálny beh.
            db()->prepare('UPDATE formulare_advisors SET onboarding_started_at = ?, onboarding_start_date = ?, onboarding_completed_at = NULL WHERE id = ? AND is_owner = 0')
                ->execute([date('Y-m-d H:i:s'), $startDate, $id]);
        }
    } elseif ($isOwner && isset($_POST['unassign_advisor_id'])) {
        $id = (int)$_POST['unassign_advisor_id'];
        if ($id) {
            db()->prepare('UPDATE formulare_advisors SET onboarding_started_at = NULL, onboarding_start_date = NULL WHERE id = ?')->execute([$id]);
        }
    }
    header('Location: /cesta-novacika.php');
    exit;
}

// -- Fázy a ich materiály --
// try/catch: formulare_onboarding_phases je nová tabuľka (koncept "Mapa cesty
// a odmeny") — kým sa na produkcii nespustí sql/033_onboarding_roadmap_concept.sql
// (RUČNE v phpMyAdmin), radšej zobrazí prázdnu osnovu než tvrdú chybu.
$migrationPending = false;
$allPhases = [];
try {
    $allPhases = db()->query('SELECT * FROM formulare_onboarding_phases ORDER BY sort_order, id')->fetchAll();
} catch (Throwable $e) { $migrationPending = true; }
$phases = array_values(array_filter($allPhases, fn($p) => empty($p['is_ongoing'])));
$ongoingPhases = array_values(array_filter($allPhases, fn($p) => !empty($p['is_ongoing'])));

$allMaterials = [];
try {
    $allMaterials = db()->query('SELECT * FROM formulare_onboarding_steps ORDER BY sort_order, id')->fetchAll();
} catch (Throwable $e) { $migrationPending = true; }
$materialsByPhase = [];
foreach ($allMaterials as $m) {
    if (!empty($m['phase_id'])) $materialsByPhase[(int)$m['phase_id']][] = $m;
}

// Doplňujúce info k vybraným materiálom (bublinové okno na hover) — skrátená
// "Obsahová náplň kurzu" z Karty výkonnosti a rozvoja VFA 2025. Naviazané na
// presný názov materiálu (nie na ID), lebo ide o statický referenčný text.
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

// -- Kto a čo sa má zobraziť: novicova "cesta" (mapa + odmeny) vs. ownerova
// administrácia (fázy, materiály, tím). Owner si vie cestu vždy prezrieť
// cez ?view=novice — buď generickú šablónu (voliteľne s ?day=N na simuláciu
// iného dňa), alebo priamo pohľad konkrétneho priradeného nováčika (?as=ID),
// so všetkými jeho reálnymi dátumami a stavom Modelu zapracovania.
$novicePreview = $isOwner && isset($_GET['view']) && $_GET['view'] === 'novice';
$previewAdvisor = null;
if ($novicePreview && isset($_GET['as'])) {
    $pStmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 0 AND active = 1 AND onboarding_started_at IS NOT NULL');
    $pStmt->execute([(int)$_GET['as']]);
    $previewAdvisor = $pStmt->fetch() ?: null;
}
$viewerIsNovice = $isOnboarding || $novicePreview;
// V "preview as" móde sa všetky nováčikove dáta (fázy, MZ, checklist) čítajú
// pod jeho ID, nie pod ID prihláseného ownera.
$viewerAdvisorId = $previewAdvisor ? (int)$previewAdvisor['id'] : $advisorId;

// Kalendárový režim (fázy naviazané na reálne mesiace) sa použije pre
// skutočne prihláseného nováčika ALEBO pre "preview as" konkrétneho
// priradeného nováčika — obaja majú nastavený dátum nástupu. Generický
// náhľad "?view=novice&day=N" (bez ?as=) ostáva na starom (deň-based) móde,
// lebo nie je naviazaný na žiadny reálny záznam poradcu.
if ($isOnboarding) {
    $elapsedDays = obElapsedDays($me['onboarding_started_at']);
    $scheduledPhases = obScheduleFor($phases, $me['onboarding_started_at'], $me['onboarding_start_date'] ?? null);
    $currentMzMonth = obCalendarMzMonth($me['onboarding_start_date'] ?? null) ?? min(24, intdiv($elapsedDays, 30));
} elseif ($previewAdvisor) {
    $elapsedDays = obElapsedDays($previewAdvisor['onboarding_started_at']);
    $scheduledPhases = obScheduleFor($phases, $previewAdvisor['onboarding_started_at'], $previewAdvisor['onboarding_start_date'] ?? null);
    $currentMzMonth = obCalendarMzMonth($previewAdvisor['onboarding_start_date'] ?? null) ?? min(24, intdiv($elapsedDays, 30));
} elseif ($novicePreview) {
    $elapsedDays = isset($_GET['day']) ? max(0, (int)$_GET['day']) : 0;
    $scheduledPhases = obLegacySchedule($phases);
    $currentMzMonth = min(24, intdiv($elapsedDays, 30));
} else {
    $elapsedDays = 0;
    $scheduledPhases = obLegacySchedule($phases);
    $currentMzMonth = 0;
}
$totalDurationDays = $scheduledPhases ? (int)end($scheduledPhases)['end_day'] : 0;

$phasesWithStatus = obPhaseStatuses($scheduledPhases, $elapsedDays);
$phaseById = [];
foreach ($phasesWithStatus as $p) { $phaseById[(int)$p['id']] = $p; }

$currentPhase = null;
foreach ($phasesWithStatus as $p) { if ($p['status'] === 'current') { $currentPhase = $p; break; } }
$isGraduated = $totalDurationDays > 0 && $elapsedDays >= $totalDurationDays;

$selectedPhaseId = isset($_GET['phase']) ? (int)$_GET['phase'] : null;
if ($selectedPhaseId === null || !isset($phaseById[$selectedPhaseId])) {
    if ($currentPhase) { $selectedPhaseId = (int)$currentPhase['id']; }
    elseif ($isGraduated && $phasesWithStatus) { $selectedPhaseId = (int)end($phasesWithStatus)['id']; }
    elseif ($phasesWithStatus) { $selectedPhaseId = (int)$phasesWithStatus[0]['id']; }
    else { $selectedPhaseId = null; }
}
$selectedPhase = $selectedPhaseId !== null ? ($phaseById[$selectedPhaseId] ?? null) : null;

/** DP (dodatková provízia) v EUR pre daný mesiac MZ a status. */
function mzDpAmount(int $month, string $status): int {
    $table = $month <= 12
        ? ['fit' => 500, 'std' => 750, 'top' => 1000]
        : ['fit' => 300, 'std' => 500, 'top' => 700];
    return $table[$status] ?? 0;
}

// Mesiace 1.–6. sa hodnotia podľa mesačnej produkcie (PB), nie podľa statusu
// FIT/STD/TOP — ten platí až od 7. mesiaca. Sumy sa zhodou okolností
// zhodujú (500/750/1000 €), preto sa interne ukladajú pod rovnaké kľúče
// fit/std/top — mení sa len zobrazený popisok tlačidla.
const MZ_STATUS_LABELS = ['fit' => 'FIT', 'std' => '⭐ STD', 'top' => '🏆 TOP'];
const MZ_PB_LABELS = ['fit' => '1 200 PB', 'std' => '2 400 PB', 'top' => '3 600 PB'];
const MZ_PB_POINTS = ['fit' => 1200, 'std' => 2400, 'top' => 3600];
// Kumulatívne prahy za CELÝ kvartál (súčet PB za 3 mesiace) — presne 3×
// mesačný prah (3×1200/2400/3600). Používa sa len pri kvartáloch 1.–6.
// mesiaca na určenie doplatku (mesiace 7.–24. sa dopočítavajú podľa
// statusu dosiahnutého v 3. mesiaci, nie podľa súčtu).
const MZ_QUARTER_PB_THRESHOLDS = ['fit' => 3600, 'std' => 7200, 'top' => 10800];
const MZ_QUARTERS = [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10, 11, 12], [13, 14, 15], [16, 17, 18], [19, 20, 21], [22, 23, 24]];

/** Status, na ktorý sa dopočíta celý uzatvorený kvartál. Mesiace 1.–6.
 * (PB): podľa súčtu bodov za celý kvartál vs. MZ_QUARTER_PB_THRESHOLDS.
 * Mesiace 7.–24. (FIT/STD/TOP): podľa statusu dosiahnutého v 3. mesiaci. */
function mzQuarterFinalStatus(array $q, array $statuses): string {
    if ($q[0] <= 6) {
        $sum = array_sum(array_map(fn($s) => MZ_PB_POINTS[$s] ?? 0, $statuses));
        if ($sum >= MZ_QUARTER_PB_THRESHOLDS['top']) return 'top';
        if ($sum >= MZ_QUARTER_PB_THRESHOLDS['std']) return 'std';
        return 'fit';
    }
    return $statuses[2];
}

/** Súčet DP, ktoré poradca doteraz reálne získal. 0. mesiac je samostatný
 * flat bonus (500 €, mimo kvartálov). Pri uzatvorených kvartáloch (všetky
 * 3 mesiace vyplnené) sa porovná súčet jednotlivých mesačných volieb
 * s dopočítaným kvartálnym pásmom (mzQuarterFinalStatus) a použije sa VYŠŠIA
 * hodnota — kvartálne prepočítanie je len bonusový doplatok, nikdy nesmie
 * znížiť DP pod to, čo už poradca vyplnením jednotlivých mesiacov dosiahol. */
function mzTotalEarned(array $mzStatusMap): int {
    $total = 0;
    if (!empty($mzStatusMap[0])) $total += mzDpAmount(0, $mzStatusMap[0]);
    foreach (MZ_QUARTERS as $q) {
        $statuses = array_map(fn($m) => $mzStatusMap[$m] ?? null, $q);
        if (!in_array(null, $statuses, true)) {
            $final = mzQuarterFinalStatus($q, $statuses);
            $naive = 0; $blended = 0;
            foreach ($q as $i => $m) {
                $naive += mzDpAmount($m, $statuses[$i]);
                $blended += mzDpAmount($m, $final);
            }
            $total += max($naive, $blended);
        } else {
            foreach ($q as $i => $m) { if ($statuses[$i]) $total += mzDpAmount($m, $statuses[$i]); }
        }
    }
    return $total;
}

/**
 * Súhrnná lišta s celkovým doteraz získaným DP — samostatná, aby sa dala
 * zobraziť staticky pri každej fáze (napr. v karte "Čo za to dostaneš"),
 * nezávisle od toho, kde sa nachádza plný tracker Modelu zapracovania.
 */
function obRenderMzTotalBar(array $mzStatusMap): void {
    $totalEarned = mzTotalEarned($mzStatusMap);
    ?>
    <div class="mz-summary-bar">
      <div class="mz-summary-icon">💰</div>
      <div class="mz-summary-text">
        <div class="mz-summary-label">Doteraz získané DP z Modelu zapracovania</div>
        <div class="mz-summary-value mz-total-value"><?= number_format($totalEarned, 0, ',', ' ') ?> €</div>
      </div>
    </div>
    <?php
}

/**
 * "Čo za to dostaneš" — celý Model zapracovania naraz (0.–24. mesiac),
 * nezávisle od aktuálne prezeranej fázy. Ukazuje sa ako náhľad odmien od
 * prvého dňa, nielen keď už na ne prišiel rad. Podľa oficiálneho dokumentu
 * "Model zapracovania 2026": 0. mesiac = flat 500 € pri splnení vstupných
 * podmienok, 1.–6. mesiac = podľa produkčných bodov, 7.–24. mesiac = podľa
 * statusu FIT/STD/TOP.
 */
function obRenderRewards(array $mzStatusMap, int $currentMzMonth, bool $readOnly = false): void {
    $ro = $readOnly ? 'disabled title="Náhľad je len na čítanie"' : '';
    $sel0 = $mzStatusMap[0] ?? null;
    ?>
    <div class="mz-card mz-card-single">
      <div class="mz-card-head">
        <span class="mz-card-month">0. mesiac</span>
        <span class="mz-card-badge">Vstupné podmienky</span>
      </div>
      <p class="mz-card-note">Registrácia v NBS, e-learning, základné školenia, 100 kontaktov v CRM+ a min. 15 ponúk v Unipoint — jednorazovo 500 €.</p>
      <div class="mz-status-picker" data-month="0">
        <button type="button" class="mz-status-btn mz-status-btn-fit<?= $sel0 === 'fit' ? ' is-selected' : '' ?>" data-status="fit" onclick="mzSelectStatus(this)" <?= $ro ?>>✓ Podmienky splnené<span class="mz-status-amt"><?= mzDpAmount(0, 'fit') ?> €</span></button>
      </div>
    </div>
    <?php foreach (MZ_QUARTERS as $months):
        $labels = $months[0] <= 6 ? MZ_PB_LABELS : MZ_STATUS_LABELS;
        $statuses = array_map(fn($m) => $mzStatusMap[$m] ?? null, $months);
        $filled = count(array_filter($statuses));
        $isFuture = $months[0] > $currentMzMonth + 1;
        $key = implode('-', $months);
        $hint = $filled === 3 ? 'Uzatvorený ✓' : ($filled > 0 ? $filled . '/3 vyplnené' : ($isFuture ? 'Zatiaľ neaktuálne' : 'Čaká na vyplnenie'));
        $hintClass = $filled === 3 ? ' mz-quarter-hint-done' : ($filled > 0 ? ' mz-quarter-hint-partial' : '');
    ?>
    <details class="mz-card mz-card-quarter" <?= $isFuture ? '' : 'open' ?>>
      <summary class="mz-card-head">
        <span class="mz-card-month">Kvartál <?= $months[0] ?>.–<?= $months[2] ?>. mesiac</span>
        <span class="mz-quarter-hint<?= $hintClass ?>" id="mz-quarter-hint-<?= $key ?>"><?= h($hint) ?></span>
        <span class="mz-quarter-chevron" aria-hidden="true">▾</span>
      </summary>
      <div class="mz-quarter-months">
        <?php foreach ($months as $i => $m): $isFinal = $i === 2; $selM = $mzStatusMap[$m] ?? null; ?>
        <div class="mz-month-col<?= $isFinal ? ' mz-month-col-final' : '' ?>">
          <div class="mz-month-label"><?= $m ?>. mesiac<?= $isFinal ? ' 🎯' : '' ?></div>
          <div class="mz-status-picker" data-month="<?= $m ?>">
            <?php foreach ($labels as $sKey => $sLabel): $amt = mzDpAmount($m, $sKey); ?>
            <button type="button" class="mz-status-btn mz-status-btn-<?= $sKey ?><?= $selM === $sKey ? ' is-selected' : '' ?>" data-status="<?= $sKey ?>" onclick="mzSelectStatus(this)" <?= $ro ?>><?= $sLabel ?><span class="mz-status-amt"><?= $amt ?> €</span></button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mz-doplatok" id="mz-doplatok-<?= $key ?>"></div>
    </details>
    <?php endforeach; ?>
    <?php
}

$mzStatusMap = [];
if ($viewerIsNovice) {
    try {
        $mzStmt = db()->prepare('SELECT month_number, status FROM formulare_mz_status WHERE advisor_id = ?');
        $mzStmt->execute([$viewerAdvisorId]);
        foreach ($mzStmt->fetchAll() as $row) { $mzStatusMap[(int)$row['month_number']] = $row['status']; }
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }
}

// Osobné odškrtávanie materiálov — čisto pre nováčika samotného, nech si vie
// značiť, čo už spravil. NIE je to nástroj na kontrolu ownerom: nikde sa
// nezobrazuje súhrnne za tím, neovplyvňuje postup fázou (ten ide ďalej len
// podľa uplynutého času). V "preview as" móde sa zobrazí presne to, čo má
// odškrtnuté reálny nováčik — owner si tak vie overiť jeho pohľad.
$doneStepIds = [];
if ($viewerIsNovice) {
    try {
        $doneStmt = db()->prepare('SELECT step_id FROM formulare_onboarding_progress WHERE advisor_id = ?');
        $doneStmt->execute([$viewerAdvisorId]);
        $doneStepIds = array_map('intval', array_column($doneStmt->fetchAll(), 'step_id'));
    } catch (Throwable $e) { /* tabuľka ešte nemusí existovať */ }
}

// -- Pre ownera: tím a história absolventov --
$teamAdvisors = [];
$graduates = [];
if ($isOwner) {
    $teamAdvisors = db()->query(
        "SELECT id, name, color, onboarding_started_at, onboarding_start_date, onboarding_completed_at FROM formulare_advisors WHERE is_owner = 0 AND active = 1 ORDER BY name"
    )->fetchAll();
    foreach ($teamAdvisors as &$ta) {
        $taElapsed = obElapsedDays($ta['onboarding_started_at']);
        $ta['elapsedDays'] = $taElapsed;
        // Každý nováčik môže mať vlastný dátum nástupu — rozvrh fáz sa preto
        // počíta osobitne pre každého, nie zdieľane z jedného $phasesWithStatus.
        $taSchedule = $ta['onboarding_started_at'] ? obScheduleFor($phases, $ta['onboarding_started_at'], $ta['onboarding_start_date'] ?? null) : $phases;
        $taTotalDurationDays = $taSchedule ? (int)end($taSchedule)['end_day'] : 0;
        $taCurrent = null;
        if (!empty($ta['onboarding_started_at'])) {
            foreach (obPhaseStatuses($taSchedule, $taElapsed) as $tp) { if ($tp['status'] === 'current') { $taCurrent = $tp; break; } }
        }
        $ta['currentPhase'] = $taCurrent;
        $ta['isGraduated'] = !empty($ta['onboarding_started_at']) && $taTotalDurationDays > 0 && $taElapsed >= $taTotalDurationDays;
        // Lazy dopočítanie a uloženie dátumu absolvovania — spustí sa samo
        // pri prvom zobrazení tejto stránky ownerovi po tom, čo niekto reálne
        // prekročí celkovú dĺžku cesty. Vďaka tomu "História absolventov"
        // prežije aj prípadné neskoršie odobratie priradenia.
        if ($ta['isGraduated'] && empty($ta['onboarding_completed_at'])) {
            $gradDate = date('Y-m-d H:i:s', strtotime($ta['onboarding_started_at']) + $taTotalDurationDays * 86400);
            db()->prepare('UPDATE formulare_advisors SET onboarding_completed_at = ? WHERE id = ?')->execute([$gradDate, $ta['id']]);
            $ta['onboarding_completed_at'] = $gradDate;
        }
    }
    unset($ta);

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
<link rel="stylesheet" href="<?= asset('fonts.css') ?>">
<script src="<?= asset('theme-init.js') ?>"></script>
<link rel="stylesheet" href="<?= asset('panel.css') ?>">
<style>
  /* ============================================================
     Cesta nováčika — späť vo farbách portálu (štandardné tokeny appky:
     --paper/--accent/--ink..., svetlý/tmavý režim podľa appky). Zmenilo sa
     len rozloženie (jeden stĺpec namiesto rozdelených okien) a materiály
     majú opäť checkbox — čisto pre osobný prehľad nováčika, nie pre
     sledovanie ownerom (žiadny report z toho nikam nejde).
  ============================================================ */
  .ob-hero{position:relative; overflow:hidden; display:flex; align-items:center; gap:22px; flex-wrap:wrap;
    background:linear-gradient(135deg, var(--accent) 0%, var(--accent-ink) 100%); border:none; color:#fff;}
  .ob-hero::before,.ob-hero::after{content:''; position:absolute; border-radius:50%; background:#fff; opacity:.14; pointer-events:none;}
  .ob-hero::before{width:240px; height:240px; top:-110px; right:-70px;}
  .ob-hero::after{width:130px; height:130px; bottom:-80px; right:140px;}
  .ob-ring{--pct:0; width:96px; height:96px; border-radius:50%; flex-shrink:0; position:relative; z-index:1;
    background:conic-gradient(#fff calc(var(--pct) * 3.6deg), rgba(255,255,255,.28) 0deg);}
  .ob-ring::after{content:''; position:absolute; inset:9px; border-radius:50%; background:var(--accent-ink);}
  .ob-ring-label{position:absolute; inset:9px; border-radius:50%; z-index:1; display:flex; align-items:center; justify-content:center;}
  .ob-ring-icon{font-size:28px; line-height:1;}
  .ob-progress-info{flex:1; min-width:180px; position:relative; z-index:1;}
  .ob-progress-info h4{margin:0 0 4px; font-size:16px; color:#fff;}
  .ob-progress-info p{margin:0; font-size:12.5px; color:rgba(255,255,255,.88);}
  .ob-day-badge{display:inline-block; margin-top:9px; padding:4px 12px; border-radius:999px; background:rgba(255,255,255,.2);
    font-size:11.5px; font-weight:700; letter-spacing:.02em; color:#fff;}

  .obt-grad{text-align:center; padding:36px 20px;}
  .obt-grad-emoji{font-size:44px; margin-bottom:10px;}
  .obt-grad-title{font-size:19px; font-weight:800; color:var(--ink); margin-bottom:6px;}
  .obt-grad-sub{font-size:13px; color:var(--muted);}

  /* ---- Cesta (trasa) ---- */
  .obt-map-title{font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin-bottom:16px;}
  .obt-route-svg{width:100%; height:auto; display:block; overflow:visible;}
  .obr-track{stroke:var(--border); stroke-width:2;}
  .obr-progress{stroke:var(--accent); stroke-width:2.5;}
  .obr-stop{cursor:pointer; text-decoration:none; outline-offset:5px;}
  .obr-dot{fill:var(--paper); stroke:var(--muted); stroke-width:2; transition:stroke-width .15s ease;}
  .obr-stop:hover .obr-dot{stroke-width:3;}
  .obr-stop.status-done .obr-dot{fill:var(--accent); stroke:var(--accent);}
  .obr-stop.status-current .obr-dot{fill:var(--paper); stroke:var(--accent); stroke-width:3;}
  .obr-stop.status-upcoming .obr-dot{stroke-dasharray:2.5 2.5;}
  .obr-name{font-size:10px; fill:var(--muted);}
  .obr-name.current{fill:var(--ink); font-weight:700;}
  .obr-plane{font-size:12px; fill:var(--accent);}
  .obr-pulse{fill:none; stroke:var(--accent); stroke-width:2; opacity:.55; transform-box:fill-box; transform-origin:center;
    animation:obrPulseRing 2.2s ease-out infinite;}
  @keyframes obrPulseRing{0%{transform:scale(1); opacity:.55;}100%{transform:scale(1.8); opacity:0;}}

  /* ---- Detail fázy (dva stĺpce: materiály + odmena) ---- */
  .obt-phase-nav{display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:14px;}
  .obt-detail{display:grid; grid-template-columns:1.3fr 1fr; gap:18px; align-items:start;}
  @media(max-width:720px){.obt-detail{grid-template-columns:1fr;}}
  .obt-eyebrow-sm{font-size:10.5px; letter-spacing:.1em; text-transform:uppercase; color:var(--accent-ink); margin:0 0 6px;}
  .obt-panel-count{font-size:12.5px; color:var(--muted); margin:0 0 16px;}
  .obt-support{display:flex; gap:8px; font-size:12.5px; color:var(--ink-2); line-height:1.6; background:var(--desk);
    border-radius:9px; padding:10px 12px; margin:0 0 14px;}
  .obt-reward{background:var(--accent-soft); border-color:var(--accent-line);}
  .obt-reward-label{font-size:13px; font-weight:700; color:var(--accent-ink); margin-bottom:10px;}
  .obt-reward-note{font-size:13px; color:var(--ink-2); line-height:1.6;}

  .ob-material{display:flex; align-items:flex-start; gap:12px; padding:11px 4px; border-bottom:1px solid var(--border);}
  .ob-material input[type=checkbox]{appearance:none; -webkit-appearance:none; width:20px; height:20px; margin-top:1px; flex-shrink:0;
    border:2px solid var(--line-strong); border-radius:50%; cursor:pointer; position:relative; transition:background .2s ease, border-color .2s ease;}
  .ob-material input[type=checkbox]:hover{border-color:var(--accent);}
  .ob-material input[type=checkbox]:checked{background:var(--good); border-color:var(--good);}
  .ob-material input[type=checkbox]:checked::after{content:''; position:absolute; left:5px; top:1px; width:5px; height:9px;
    border:solid #fff; border-width:0 2px 2px 0; transform:rotate(45deg);}
  .ob-material.done .ob-material-title{color:var(--muted); text-decoration:line-through;}
  .ob-material:last-child{border-bottom:none;}
  .ob-material-dot{width:8px; height:8px; border-radius:50%; background:var(--accent); margin-top:6px; flex-shrink:0;}
  .ob-material-body{flex:1; min-width:0;}
  .ob-material-title{font-size:14px; font-weight:600; color:var(--ink);}
  .ob-material-desc{font-size:12.5px; color:var(--muted); line-height:1.5; margin-top:2px;}
  .ob-material-actions{display:flex; align-items:center; gap:6px; flex-shrink:0;}

  .ob-info{position:relative; display:inline-flex; align-items:center; justify-content:center; width:16px; height:16px;
    border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); font-size:10px; font-weight:700; cursor:help;
    margin-left:6px; flex-shrink:0; border:1px solid var(--accent-line); vertical-align:middle;}
  .ob-info:hover, .ob-info:focus{background:var(--accent); color:#fff; border-color:var(--accent); outline:none;}
  .ob-info-bubble{position:absolute; left:50%; bottom:calc(100% + 8px); transform:translateX(-50%) translateY(4px);
    width:240px; max-width:min(240px, 78vw); background:var(--ink); color:#fff; font-size:11.5px; font-weight:400; line-height:1.5;
    padding:8px 10px; border-radius:8px; text-align:left; opacity:0; pointer-events:none; transition:opacity .15s ease, transform .15s ease; z-index:20; box-shadow:var(--shadow-md);}
  .ob-info-bubble::after{content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:var(--ink);}
  .ob-info:hover .ob-info-bubble, .ob-info:focus .ob-info-bubble{opacity:1; transform:translateX(-50%) translateY(0);}

  .mz-tracker-title{font-size:14.5px; font-weight:700; color:var(--ink); margin:0 0 12px;}
  .mz-summary-bar{display:flex; align-items:center; gap:12px; background:linear-gradient(135deg, var(--accent-soft), var(--good-soft));
    border:1px solid var(--accent-line); border-radius:var(--radius-xl); padding:14px 18px; margin-top:14px; margin-bottom:16px;}
  .mz-summary-icon{font-size:26px; line-height:1; flex-shrink:0;}
  .mz-summary-label{font-size:11.5px; font-weight:600; color:var(--ink-2); text-transform:uppercase; letter-spacing:.03em;}
  .mz-summary-value{font-size:22px; font-weight:800; color:var(--accent-ink); letter-spacing:-.01em; margin-top:1px; transition:transform .25s ease;}
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
  .mz-card-quarter{padding:0;}
  .mz-card-quarter > summary.mz-card-head{list-style:none; cursor:pointer; padding:16px 18px; margin-bottom:0; border-radius:var(--radius-xl);}
  .mz-card-quarter > summary.mz-card-head::-webkit-details-marker{display:none;}
  .mz-card-quarter > summary.mz-card-head::marker{content:'';}
  .mz-card-quarter > summary.mz-card-head:hover{background:var(--paper);}
  .mz-card-quarter[open] > summary.mz-card-head{border-radius:var(--radius-xl) var(--radius-xl) 0 0;}
  .mz-quarter-hint{font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:999px; background:var(--paper); color:var(--muted); flex-shrink:0; white-space:nowrap; border:1px solid var(--border);}
  .mz-quarter-hint-partial{background:var(--amber-soft); color:var(--amber); border-color:var(--amber-soft);}
  .mz-quarter-hint-done{background:var(--good-soft); color:var(--good); border-color:var(--good-soft);}
  .mz-quarter-chevron{font-size:11px; color:var(--muted); transition:transform .2s ease; flex-shrink:0;}
  .mz-card-quarter[open] .mz-quarter-chevron{transform:rotate(180deg);}
  .mz-card-quarter > .mz-quarter-months{padding:0 18px; margin-top:2px;}
  .mz-card-quarter > .mz-doplatok{margin:0 18px 16px;}
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

  .ob-preview-banner{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 14px; margin-bottom:16px; border-radius:var(--radius-md); background:var(--accent-soft); color:var(--accent); font-size:13px; font-weight:600;}

  .ob-manage-phase{border:none; border-left:3px solid var(--border); margin:0 0 6px; border-radius:0 var(--radius-md) var(--radius-md) 0;}
  .ob-manage-phase.is-ongoing{border-left-color:var(--accent);}
  .ob-manage-summary{list-style:none; cursor:pointer; display:flex; align-items:center; gap:10px; padding:11px 8px; border-radius:var(--radius-md); user-select:none;}
  .ob-manage-summary::-webkit-details-marker{display:none;}
  .ob-manage-summary::marker{content:'';}
  .ob-manage-summary:hover{background:var(--desk);}
  .ob-manage-icon{width:30px; height:30px; border-radius:50%; background:var(--accent-soft); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;}
  .ob-manage-name{flex:1; font-size:13.5px; font-weight:700; color:var(--ink);}
  .ob-manage-duration{font-size:12px; color:var(--muted); font-weight:600; flex-shrink:0;}
  .ob-manage-body{padding:4px 8px 14px 46px;}
  .ob-manage-actions{display:flex; align-items:center; gap:6px; flex-shrink:0;}
  .ob-phase-edit-form,.ob-material-edit-form{display:none; flex-direction:column; gap:10px; margin:8px 0 16px; padding:12px; background:var(--desk); border-radius:var(--radius-md);}
  .ob-phase-add-row{display:grid; grid-template-columns:60px 1fr 110px 110px; gap:10px;}
  .ob-material-add-row{display:grid; grid-template-columns:1fr 1fr; gap:10px;}
  @media(max-width:640px){.ob-phase-add-row,.ob-material-add-row{grid-template-columns:1fr;}}
  .ob-ongoing-check{display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--ink-2);}

  .ob-team-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:12px;}
  .ob-team-card{border:1px solid var(--border); border-radius:var(--radius-lg); padding:14px; display:flex; flex-direction:column; gap:10px;
    transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;}
  .ob-team-card:hover{transform:translateY(-2px); border-color:var(--accent-line); box-shadow:0 8px 20px rgba(0,0,0,.07);}
  .ob-team-card-top{display:flex; align-items:center; gap:10px;}
  .ob-team-ini-lg{width:42px; height:42px; border-radius:50%; color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; flex-shrink:0;}
  .ob-team-card-name{font-size:14px; font-weight:700; color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
  .ob-team-card-sub{font-size:11.5px; color:var(--muted); margin-top:1px;}
  .ob-team-card-foot{margin-top:auto;}
  .ob-grad-badge{font-size:18px; flex-shrink:0;}
  @media(max-width:720px){ .ob-phase-add-row,.ob-material-add-row{grid-template-columns:1fr;} }

  .ob-confetti{position:fixed; inset:0; pointer-events:none; z-index:9999; overflow:hidden;}
  .ob-confetti span{position:absolute; top:-12px; width:8px; height:14px; opacity:.9; border-radius:2px; animation:obConfettiFall linear forwards;}
  @keyframes obConfettiFall{0%{transform:translateY(0) rotate(0deg); opacity:1;}100%{transform:translateY(110vh) rotate(560deg); opacity:.35;}}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Cesta nováčika</h1>
    <p><?= $isOwner && !$novicePreview ? 'Mapa cesty a odmien pre nových poradcov · spravuješ obsah a priraďuješ nováčikov' : 'Čo ťa čaká a čo za to dostaneš' ?></p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <?php if ($migrationPending): ?>
  <div class="section">
    <div class="card" style="background:var(--rose-soft); border-color:#fbd0d5;">
      <h3 style="margin-top:0;">⚠️ Chýba databázová migrácia</h3>
      <p style="margin:0; font-size:13px; color:var(--ink-2);">
        Táto stránka potrebuje novú tabuľku <code>formulare_onboarding_phases</code> — spusti <code>sql/033_onboarding_roadmap_concept.sql</code> ručne v phpMyAdmin, potom stránku obnov.
      </p>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($novicePreview): ?>
  <div class="section">
    <div class="ob-preview-banner">
      <span>👀 <?= $previewAdvisor ? 'Presne to, čo teraz vidí ' . h($previewAdvisor['name']) . '.' : 'Náhľad všeobecnej šablóny' . (isset($_GET['day']) ? ' (deň ' . (int)$_GET['day'] . ')' : '') . ' — nie je naviazaný na konkrétneho nováčika.' ?></span>
      <a class="pillbtn" href="/cesta-novacika.php">← Späť na správu</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($viewerIsNovice): ?>

  <?php if ($isGraduated): ?>
  <div class="card obt-grad" id="obGradCard" data-graduated="1" data-advisor-id="<?= (int)$advisorId ?>">
    <div class="obt-grad-emoji">🎉</div>
    <div class="obt-grad-title">Prešiel/-la si celou Cestou nováčika!</div>
    <div class="obt-grad-sub">Model zapracovania a odmeny nižšie ostávajú naďalej k dispozícii.</div>
  </div>
  <?php else: ?>
  <div class="card ob-hero">
    <div class="ob-ring" style="--pct:<?= $totalDurationDays > 0 ? min(100, round($elapsedDays / $totalDurationDays * 100)) : 0 ?>;">
      <div class="ob-ring-label"><span class="ob-ring-icon"><?= $currentPhase['icon'] ?? '📍' ?></span></div>
    </div>
    <div class="ob-progress-info">
      <h4>Deň <?= $elapsedDays + 1 ?> z tvojho onboardingu</h4>
      <p><?= $currentPhase ? 'Aktuálna fáza: ' . h($currentPhase['name']) : 'Osnova zatiaľ nie je pripravená.' ?></p>
      <span class="ob-day-badge"><?= $totalDurationDays > 0 ? min(100, round($elapsedDays / $totalDurationDays * 100)) : 0 ?> % cesty za sebou</span>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($phasesWithStatus): ?>
  <div class="card">
    <div class="obt-map-title">Cesta</div>
    <?php
      $jSpacing = 96; $jPad = 62; $jY = 30;
      $jN = count($phasesWithStatus);
      $jWidth = $jPad * 2 + $jSpacing * max(0, $jN - 1);
      $jHeight = 62;
      $jPoints = [];
      foreach ($phasesWithStatus as $jIdx => $jp) {
          $jPoints[] = ['x' => $jPad + $jIdx * $jSpacing, 'y' => $jY, 'phase' => $jp];
      }
      $jTotalLen = $jN > 1 ? $jPoints[$jN - 1]['x'] - $jPoints[0]['x'] : 0;
      $jProgressLen = 0;
      foreach ($jPoints as $k => $jp) {
          if ($k === count($jPoints) - 1) break;
          $segLen = $jPoints[$k + 1]['x'] - $jp['x'];
          $jph = $jp['phase'];
          if ($jph['status'] === 'done') { $jProgressLen += $segLen; }
          elseif ($jph['status'] === 'current') {
              $segPct = $jph['duration_days'] > 0 ? min(1, max(0, ($elapsedDays - $jph['start_day']) / $jph['duration_days'])) : 0;
              $jProgressLen += $segLen * $segPct;
              break;
          } else { break; }
      }
      $vp = $novicePreview ? '&view=novice' . ($previewAdvisor ? '&as=' . (int)$previewAdvisor['id'] : (isset($_GET['day']) ? '&day=' . (int)$_GET['day'] : '')) : '';
    ?>
    <div class="ob-journey-scroll">
      <svg viewBox="0 0 <?= $jWidth ?> <?= $jHeight ?>" width="<?= $jWidth ?>" height="<?= $jHeight ?>" class="obt-route-svg" role="img" aria-label="Letová trasa naprieč fázami onboardingu">
        <?php if ($jN > 1): ?>
        <line x1="<?= $jPad ?>" y1="<?= $jY ?>" x2="<?= $jPad + $jTotalLen ?>" y2="<?= $jY ?>" class="obr-track" />
        <line x1="<?= $jPad ?>" y1="<?= $jY ?>" x2="<?= $jPad + $jProgressLen ?>" y2="<?= $jY ?>" class="obr-progress" />
        <?php endif; ?>
        <?php foreach ($jPoints as $jp): $jph = $jp['phase']; ?>
        <a href="?phase=<?= (int)$jph['id'] ?><?= $vp ?>" class="obr-stop status-<?= $jph['status'] ?>" aria-label="<?= h($jph['name']) ?>">
          <?php if ($jph['status'] === 'current'): ?>
          <circle cx="<?= $jp['x'] ?>" cy="<?= $jp['y'] ?>" r="9" class="obr-pulse" />
          <text x="<?= $jp['x'] ?>" y="<?= $jp['y'] - 13 ?>" text-anchor="middle" class="obr-plane" transform="rotate(90 <?= $jp['x'] ?> <?= $jp['y'] - 13 ?>)">✈</text>
          <?php endif; ?>
          <circle cx="<?= $jp['x'] ?>" cy="<?= $jp['y'] ?>" r="5.5" class="obr-dot" />
          <text x="<?= $jp['x'] ?>" y="<?= $jp['y'] + 20 ?>" class="obr-name<?= $jph['status'] === 'current' ? ' current' : '' ?>" text-anchor="middle"><?= h($jph['name']) ?></text>
        </a>
        <?php endforeach; ?>
      </svg>
    </div>
  </div>

  <?php if ($selectedPhase): $sp = $selectedPhase; ?>
  <div class="obt-phase-nav">
    <?php $spIdxList = array_column($phasesWithStatus, 'id'); $spPos = array_search((int)$sp['id'], $spIdxList, true); ?>
    <?php if ($spPos > 0): ?><a class="pillbtn" href="?phase=<?= (int)$phasesWithStatus[$spPos - 1]['id'] ?><?= $vp ?>">← Predchádzajúca</a><?php else: ?><span></span><?php endif; ?>
    <?php if ($spPos !== false && $spPos < count($phasesWithStatus) - 1): ?><a class="pillbtn" href="?phase=<?= (int)$phasesWithStatus[$spPos + 1]['id'] ?><?= $vp ?>">Ďalšia fáza →</a><?php else: ?><span></span><?php endif; ?>
  </div>
  <div class="obt-detail">
    <div class="card">
      <p class="obt-eyebrow-sm">Fáza <?= ($spPos !== false ? $spPos + 1 : '?') ?> z <?= count($phasesWithStatus) ?><?= $sp['status'] === 'current' ? ' · práve tu si' : ($sp['status'] === 'done' ? ' · za tebou' : ' · príde neskôr') ?></p>
      <h3><?= $sp['status'] === 'done' ? '✓ ' : $sp['icon'] . ' ' ?><?= h($sp['name']) ?></h3>
      <p class="obt-panel-count">
        <?php if ($sp['status'] === 'current'): ?>Deň <?= $elapsedDays - $sp['start_day'] + 1 ?> z <?= $sp['duration_days'] ?> v tejto fáze
        <?php elseif ($sp['status'] === 'done'): ?>Táto fáza trvala <?= $sp['duration_days'] ?> dní
        <?php else: ?>Začína sa o <?= $sp['start_day'] - $elapsedDays ?> dní
        <?php endif; ?>
      </p>
      <?php if ($sp['support_text']): ?>
      <div class="obt-support"><span>🤝</span><span><?= h($sp['support_text']) ?></span></div>
      <?php endif; ?>
      <?php $mats = $materialsByPhase[(int)$sp['id']] ?? []; ?>
      <?php if ($mats): ?>
      <?php foreach ($mats as $m): $tip = $OB_TOOLTIPS[$m['title']] ?? null; $isDone = in_array((int)$m['id'], $doneStepIds, true); ?>
      <div class="ob-material<?= $isDone ? ' done' : '' ?>" id="ob-mat-<?= (int)$m['id'] ?>">
        <input type="checkbox" <?= $isDone ? 'checked' : '' ?> data-toggle-step="<?= (int)$m['id'] ?>" aria-label="Označiť ako splnené" <?= $novicePreview ? 'disabled title="Náhľad je len na čítanie"' : '' ?>>
        <div class="ob-material-body">
          <div class="ob-material-title"><?= h($m['title']) ?><?php if ($tip): ?><span class="ob-info" tabindex="0">i<span class="ob-info-bubble"><?= h($tip) ?></span></span><?php endif; ?></div>
          <?php if ($m['description']): ?><div class="ob-material-desc"><?= h($m['description']) ?></div><?php endif; ?>
        </div>
        <?php if ($m['link_url']): ?><a class="toggle-btn" href="<?= h($m['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <p style="font-size:12.5px; color:var(--muted);">K tejto fáze zatiaľ nie sú žiadne materiály.</p>
      <?php endif; ?>
    </div>
    <div class="card obt-reward">
      <div class="obt-reward-label">💰 Čo za to dostaneš</div>
      <?php if ($sp['reward_text']): ?>
      <div class="obt-reward-note"><?= h($sp['reward_text']) ?></div>
      <?php else: ?>
      <div class="obt-reward-note">Text sa ešte dopĺňa.</div>
      <?php endif; ?>
      <?php obRenderMzTotalBar($mzStatusMap); ?>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="card">
    <h3 class="mz-tracker-title">💰 Čo za to dostaneš — Model zapracovania</h3>
    <?php obRenderRewards($mzStatusMap, $currentMzMonth, $novicePreview); ?>
  </div>

  <?php
    $ongoingMaterials = [];
    foreach ($ongoingPhases as $op) { foreach (($materialsByPhase[(int)$op['id']] ?? []) as $m) { $ongoingMaterials[] = $m; } }
  ?>
  <?php if ($ongoingMaterials): ?>
  <div class="card">
    <h3>Priebežne</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">Bežná práca poradcu, ktorá pokračuje stále — mimo časovej osi vyššie.</p>
    <?php foreach ($ongoingMaterials as $m): $tip = $OB_TOOLTIPS[$m['title']] ?? null; ?>
    <div class="ob-material">
      <span class="ob-material-dot" aria-hidden="true"></span>
      <div class="ob-material-body">
        <div class="ob-material-title"><?= h($m['title']) ?><?php if ($tip): ?><span class="ob-info" tabindex="0">i<span class="ob-info-bubble"><?= h($tip) ?></span></span><?php endif; ?></div>
        <?php if ($m['description']): ?><div class="ob-material-desc"><?= h($m['description']) ?></div><?php endif; ?>
      </div>
      <?php if ($m['link_url']): ?><a class="toggle-btn" href="<?= h($m['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php endif; // $viewerIsNovice ?>

  <?php if ($isOwner && !$novicePreview): ?>

  <div class="card">
    <div class="ob-osnova-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:4px;">
      <h3 style="margin:0;">Fázy a materiály</h3>
      <a class="pillbtn" href="?view=novice">👀 Náhľad nováčika</a>
    </div>
    <p style="margin:8px 0 16px; font-size:12.5px; color:var(--muted);">
      Poradca postupuje fázami automaticky podľa uplynutého času od nástupu — nič sa tu ručne neodškrtáva. Tu upravuješ len obsah (dĺžku fáz, popisy, odmeny a materiály).
    </p>
    <?php if (!$allPhases): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span class="es-title">Zatiaľ žiadne fázy</span>
        <span class="es-sub">Pridaj prvú fázu nižšie.</span>
      </div>
    <?php endif; ?>
    <?php foreach ($allPhases as $pIdx => $p): $pMats = $materialsByPhase[(int)$p['id']] ?? []; $isFirstPhase = $pIdx === 0; $isLastPhase = $pIdx === count($allPhases) - 1; ?>
    <details class="ob-manage-phase<?= $p['is_ongoing'] ? ' is-ongoing' : '' ?>">
      <summary class="ob-manage-summary">
        <span class="ob-manage-icon"><?= $p['icon'] ?></span>
        <span class="ob-manage-name"><?= h($p['name']) ?></span>
        <span class="ob-manage-duration"><?= $p['is_ongoing'] ? 'priebežná' : ($p['duration_months'] ?? 0) . ' mes. (' . (int)$p['duration_days'] . ' dní bez kalendára)' ?> · <?= count($pMats) ?> materiálov</span>
        <span class="ob-manage-actions">
          <?php if (!$isFirstPhase): ?>
          <form method="post" style="margin:0; display:inline;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="move_phase_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="toggle-btn" title="Posunúť hore">↑</button></form>
          <?php endif; ?>
          <?php if (!$isLastPhase): ?>
          <form method="post" style="margin:0; display:inline;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="move_phase_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="toggle-btn" title="Posunúť dole">↓</button></form>
          <?php endif; ?>
          <button type="button" class="toggle-btn" onclick="event.preventDefault(); obPhaseEdit(<?= (int)$p['id'] ?>, this)">Upraviť</button>
          <form method="post" style="margin:0; display:inline;" onsubmit="return confirm('Naozaj zmazať túto fázu aj jej materiály?');"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="delete_phase_id" value="<?= (int)$p['id'] ?>"><button type="submit" class="toggle-btn">Zmazať</button></form>
        </span>
      </summary>
      <div class="ob-manage-body">
        <form method="post" class="ob-phase-edit-form" id="ob-phase-edit-<?= (int)$p['id'] ?>">
          <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="edit_phase_id" value="<?= (int)$p['id'] ?>">
          <div class="ob-phase-add-row">
            <input type="text" name="icon" value="<?= h($p['icon']) ?>" placeholder="Ikona" maxlength="8">
            <input type="text" name="name" value="<?= h($p['name']) ?>" placeholder="Názov fázy" required>
            <input type="number" name="duration_months" value="<?= (int)($p['duration_months'] ?? 1) ?>" min="0" placeholder="Dĺžka (mesiacov)" title="Dĺžka v kalendárnych mesiacoch — používa sa, keď má nováčik nastavený dátum nástupu.">
            <input type="number" name="duration_days" value="<?= (int)$p['duration_days'] ?>" min="0" placeholder="Dĺžka (dni)" title="Dĺžka v dňoch — záložný režim bez dátumu nástupu (aj pre samotnú fázu Pred nástupom).">
          </div>
          <label class="ob-ongoing-check"><input type="checkbox" name="is_ongoing" <?= $p['is_ongoing'] ? 'checked' : '' ?>> Priebežná fáza (mimo časovej osi, dĺžka sa ignoruje)</label>
          <textarea name="support_text" rows="2" placeholder="Podporný odkaz (motivačná poznámka pre nováčika)"><?= h($p['support_text']) ?></textarea>
          <textarea name="reward_text" rows="2" placeholder="Odmena — čo za túto fázu dostane (nepovinné)"><?= h($p['reward_text']) ?></textarea>
          <div style="display:flex; gap:8px;">
            <button type="submit" class="pillbtn solid">Uložiť</button>
            <button type="button" class="pillbtn" onclick="obPhaseCancel(<?= (int)$p['id'] ?>)">Zrušiť</button>
          </div>
        </form>

        <?php foreach ($pMats as $mIdx => $m): $isFirstMat = $mIdx === 0; $isLastMat = $mIdx === count($pMats) - 1; ?>
        <div class="ob-material" id="ob-mat-row-<?= (int)$m['id'] ?>">
          <span class="ob-material-dot" aria-hidden="true"></span>
          <div class="ob-material-body">
            <div class="ob-material-title"><?= h($m['title']) ?></div>
            <?php if ($m['description']): ?><div class="ob-material-desc"><?= h($m['description']) ?></div><?php endif; ?>
          </div>
          <div class="ob-material-actions">
            <?php if ($m['link_url']): ?><a class="toggle-btn" href="<?= h($m['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
            <?php if (!$isFirstMat): ?>
            <form method="post" style="margin:0; display:inline;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="move_material_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="toggle-btn" title="Posunúť hore">↑</button></form>
            <?php endif; ?>
            <?php if (!$isLastMat): ?>
            <form method="post" style="margin:0; display:inline;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="move_material_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="toggle-btn" title="Posunúť dole">↓</button></form>
            <?php endif; ?>
            <button type="button" class="toggle-btn" onclick="obMatEdit(<?= (int)$m['id'] ?>)">Upraviť</button>
            <form method="post" style="margin:0; display:inline;" onsubmit="return confirm('Naozaj zmazať tento materiál?');"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="delete_material_id" value="<?= (int)$m['id'] ?>"><button type="submit" class="toggle-btn">Zmazať</button></form>
          </div>
        </div>
        <form method="post" class="ob-material-edit-form" id="ob-mat-edit-<?= (int)$m['id'] ?>">
          <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="edit_material_id" value="<?= (int)$m['id'] ?>">
          <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
          <input type="text" name="title" value="<?= h($m['title']) ?>" placeholder="Názov materiálu" required>
          <textarea name="description" rows="2" placeholder="Popis (nepovinné)"><?= h($m['description']) ?></textarea>
          <input type="text" name="link_url" value="<?= h((string)$m['link_url']) ?>" placeholder="Odkaz (nepovinné)">
          <div style="display:flex; gap:8px;">
            <button type="submit" class="pillbtn solid">Uložiť</button>
            <button type="button" class="pillbtn" onclick="obMatCancel(<?= (int)$m['id'] ?>)">Zrušiť</button>
          </div>
        </form>
        <?php endforeach; ?>

        <form method="post" class="ob-material-add-row" style="margin-top:12px;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="add_material" value="1">
          <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
          <input type="text" name="title" placeholder="Nový materiál — názov" required>
          <input type="text" name="link_url" placeholder="Odkaz (nepovinné)">
          <textarea name="description" rows="2" placeholder="Popis (nepovinné)" style="grid-column:1 / -1;"></textarea>
          <button type="submit" class="pillbtn" style="align-self:start; width:max-content;">+ Pridať materiál</button>
        </form>
      </div>
    </details>
    <?php endforeach; ?>

    <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
      <h4 style="margin:0 0 10px; font-size:13.5px;">Pridať fázu</h4>
      <form method="post" style="display:flex; flex-direction:column; gap:10px;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="add_phase" value="1">
        <div class="ob-phase-add-row">
          <input type="text" name="icon" placeholder="📍" maxlength="8">
          <input type="text" name="name" placeholder="Názov fázy" required>
          <input type="number" name="duration_months" value="1" min="0" placeholder="Dĺžka (mesiacov)" title="Dĺžka v kalendárnych mesiacoch — používa sa, keď má nováčik nastavený dátum nástupu.">
          <input type="number" name="duration_days" value="30" min="0" placeholder="Dĺžka (dni)" title="Dĺžka v dňoch — záložný režim bez dátumu nástupu.">
        </div>
        <label class="ob-ongoing-check"><input type="checkbox" name="is_ongoing"> Priebežná fáza (mimo časovej osi)</label>
        <textarea name="support_text" rows="2" placeholder="Podporný odkaz (nepovinné)"></textarea>
        <textarea name="reward_text" rows="2" placeholder="Odmena (nepovinné)"></textarea>
        <button type="submit" class="pillbtn solid" style="align-self:start; width:max-content;">+ Pridať fázu</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>Priradiť nováčikovi</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Priradený poradca uvidí Cestu nováčika vo svojej ľavej lište — vidí len svoju mapu a odmeny, obsah upravuješ výhradne ty.
    </p>
    <?php if (!$teamAdvisors): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <span class="es-title">Zatiaľ žiadni ďalší poradcovia</span>
        <span class="es-sub">Pridaj poradcu v Admin sekcii, potom mu sem vieš priradiť onboarding.</span>
      </div>
    <?php endif; ?>
    <?php if ($teamAdvisors): ?>
    <div class="ob-team-grid">
    <?php foreach ($teamAdvisors as $ta): $assigned = !empty($ta['onboarding_started_at']); ?>
    <div class="ob-team-card">
      <div class="ob-team-card-top">
        <span class="ob-team-ini-lg" style="background:<?= h($ta['color']) ?>;"><?= h(advisorInitials($ta['name'])) ?></span>
        <div style="min-width:0;">
          <div class="ob-team-card-name"><?= h($ta['name']) ?></div>
          <div class="ob-team-card-sub">
            <?php if (!$assigned): ?>Zatiaľ nepriradené
            <?php elseif ($ta['isGraduated']): ?>🎓 Cesta dokončená
            <?php else: ?>Deň <?= $ta['elapsedDays'] + 1 ?> · <?= $ta['currentPhase'] ? h($ta['currentPhase']['icon'] . ' ' . $ta['currentPhase']['name']) : '—' ?>
            <?php endif; ?>
          </div>
          <?php if ($assigned && !empty($ta['onboarding_start_date'])): ?>
          <div class="ob-team-card-sub" style="opacity:.75;">Nástup <?= h((new DateTime($ta['onboarding_start_date']))->format('j.n.Y')) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="ob-team-card-foot">
        <form method="post" style="margin:0; display:flex; gap:6px; align-items:center; flex-wrap:wrap;"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
          <input type="hidden" name="<?= $assigned ? 'unassign_advisor_id' : 'assign_advisor_id' ?>" value="<?= (int)$ta['id'] ?>">
          <?php if (!$assigned): ?>
          <input type="date" name="start_date" value="<?= h(date('Y-m-01', strtotime('first day of next month'))) ?>" title="Dátum nástupu (1. v mesiaci) — nepovinné, bez neho pôjdu fázy po starom (pevný počet dní)">
          <?php endif; ?>
          <?php if ($assigned): ?>
          <a class="toggle-btn" href="?view=novice&as=<?= (int)$ta['id'] ?>" title="Zobraziť presne to, čo teraz vidí <?= h($ta['name']) ?>">👀 Zobraziť ako</a>
          <?php endif; ?>
          <button type="submit" class="toggle-btn"><?= $assigned ? 'Odobrať' : 'Priradiť' ?></button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($graduates): ?>
  <div class="card">
    <h3>História absolventov</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Kto už celú cestu dokončil — záznam zostáva aj po odobratí priradenia.
    </p>
    <?php foreach ($graduates as $g): ?>
    <div class="ob-team-row" style="display:flex; align-items:center; gap:12px; padding:12px 4px; border-bottom:1px solid var(--border);">
      <span class="ob-team-ini-lg" style="background:<?= h($g['color']) ?>;"><?= h(advisorInitials($g['name'])) ?></span>
      <div style="flex:1;">
        <div class="ob-team-card-name"><?= h($g['name']) ?></div>
        <div class="ob-team-card-sub">Dokončil(a) <?= h((new DateTime($g['onboarding_completed_at']))->format('j.n.Y')) ?></div>
      </div>
      <span class="ob-grad-badge">🎓</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php endif; // $isOwner && !$novicePreview ?>

</main>
<script>
function obPhaseEdit(id, btn) {
  var details = btn && btn.closest('details');
  if (details) details.open = true;
  document.getElementById('ob-phase-edit-' + id).style.display = 'flex';
}
function obPhaseCancel(id) { document.getElementById('ob-phase-edit-' + id).style.display = 'none'; }
function obMatEdit(id) {
  document.getElementById('ob-mat-row-' + id).style.display = 'none';
  document.getElementById('ob-mat-edit-' + id).style.display = 'flex';
}
function obMatCancel(id) {
  document.getElementById('ob-mat-row-' + id).style.display = 'flex';
  document.getElementById('ob-mat-edit-' + id).style.display = 'none';
}

function obConfetti() {
  var colors = ['#ffffff', '#fde68a', '#a7f3d0', '#bfdbfe', '#fbcfe8'];
  var wrap = document.createElement('div');
  wrap.className = 'ob-confetti';
  for (var i = 0; i < 60; i++) {
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
(function () {
  var gradCard = document.getElementById('obGradCard');
  if (!gradCard) return;
  var key = 'obGraduatedConfetti_' + gradCard.dataset.advisorId;
  if (localStorage.getItem(key)) return;
  localStorage.setItem(key, '1');
  obConfetti();
})();

// Osobné odškrtávanie materiálov — len vizuálny/osobný stav pre nováčika,
// neovplyvňuje postup fázou ani sa nikde nezobrazuje ownerovi súhrnne.
document.addEventListener('change', function (e) {
  if (!e.target.matches('input[type=checkbox][data-toggle-step]')) return;
  var stepId = +e.target.dataset.toggleStep;
  var done = e.target.checked;
  var row = document.getElementById('ob-mat-' + stepId);
  if (row) row.classList.toggle('done', done);
  fetch('/api/onboarding-toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ stepId: stepId, done: done })
  }).catch(function () {});
});

var MZ_STATUS = <?= json_encode($mzStatusMap, JSON_UNESCAPED_UNICODE) ?>;
var MZ_QUARTERS = [[1,2,3],[4,5,6],[7,8,9],[10,11,12],[13,14,15],[16,17,18],[19,20,21],[22,23,24]];
var MZ_PB_POINTS = { fit: 1200, std: 2400, top: 3600 };
var MZ_PB_LABELS = { fit: '1 200 PB', std: '2 400 PB', top: '3 600 PB' };
var MZ_QUARTER_PB_THRESHOLDS = { fit: 3600, std: 7200, top: 10800 };
function mzAmount(month, status) {
  if (!status) return 0;
  var table = month <= 12 ? { fit: 500, std: 750, top: 1000 } : { fit: 300, std: 500, top: 700 };
  return table[status] || 0;
}
function mzQuarterFinalStatus(quarter, statuses) {
  if (quarter[0] <= 6) {
    var sum = statuses.reduce(function (s, st) { return s + (MZ_PB_POINTS[st] || 0); }, 0);
    if (sum >= MZ_QUARTER_PB_THRESHOLDS.top) return 'top';
    if (sum >= MZ_QUARTER_PB_THRESHOLDS.std) return 'std';
    return 'fit';
  }
  return statuses[2];
}
function mzComputeTotal() {
  var total = MZ_STATUS[0] ? mzAmount(0, MZ_STATUS[0]) : 0;
  MZ_QUARTERS.forEach(function (q) {
    var statuses = q.map(function (m) { return MZ_STATUS[m]; });
    if (statuses.every(Boolean)) {
      var finalStatus = mzQuarterFinalStatus(q, statuses);
      var naive = 0, blended = 0;
      q.forEach(function (m, i) { naive += mzAmount(m, statuses[i]); blended += mzAmount(m, finalStatus); });
      total += Math.max(naive, blended);
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

  var hintEl = document.getElementById('mz-quarter-hint-' + quarter.join('-'));
  if (hintEl) {
    hintEl.textContent = filled === 3 ? 'Uzatvorený ✓' : (filled > 0 ? filled + '/3 vyplnené' : 'Čaká na vyplnenie');
    hintEl.className = 'mz-quarter-hint' + (filled === 3 ? ' mz-quarter-hint-done' : (filled > 0 ? ' mz-quarter-hint-partial' : ''));
  }

  var isPbQuarter = quarter[0] <= 6;

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
      var potentialHint = isPbQuarter
        ? 'pri súčte nad ' + MZ_QUARTER_PB_THRESHOLDS.top.toLocaleString('sk-SK') + ' b za celý kvartál'
        : 'pri statuse TOP';
      el.innerHTML = 'Zatiaľ za kvartál: <strong>' + partialTotal + ' €</strong> · ' + potentialHint + ' až <strong>' + maxTotal + ' €</strong> (potenciál +' + potential + ' € 🚀)';
    } else {
      el.innerHTML = 'Zatiaľ za kvartál: <strong>' + partialTotal + ' €</strong> — už si na maxime, drž to! 🏆';
    }
    return;
  }

  // Kvartálne prepočítanie je len bonusový doplatok navyše k tomu, čo už
  // poradca vyplnením jednotlivých mesiacov dosiahol — nikdy ho nesmie znížiť.
  var finalStatus = mzQuarterFinalStatus(quarter, statuses);
  var actualTotal = 0, blendedTotal = 0;
  for (var j = 0; j < quarter.length; j++) {
    actualTotal += mzAmount(quarter[j], statuses[j]);
    blendedTotal += mzAmount(quarter[j], finalStatus);
  }
  var finalTotal = Math.max(actualTotal, blendedTotal);
  var diff = finalTotal - actualTotal;
  var pbSum = isPbQuarter ? statuses.reduce(function (s, st) { return s + (MZ_PB_POINTS[st] || 0); }, 0) : 0;

  if (diff > 0) {
    el.className = 'mz-doplatok mz-doplatok-win';
    var winReason = isPbQuarter
      ? 'spolu si za kvartál dosiahol ' + pbSum.toLocaleString('sk-SK') + ' b, doplatia ti rozdiel na pásmo ' + MZ_PB_LABELS[finalStatus] + '/mesiac za celý kvartál'
      : 'za 3. mesiac si dosiahol status ' + finalStatus.toUpperCase() + ', doplatia ti rozdiel za celý kvartál';
    el.innerHTML = '🎉 Doplatok DP za kvartál: <strong>+' + diff + ' €</strong> (' + winReason + ').';
    if (fromClick) obConfetti();
  } else {
    el.className = 'mz-doplatok mz-doplatok-flat';
    el.innerHTML = 'Kvartál uzatvorený — spolu <strong>' + finalTotal + ' €</strong> DP (súčet tvojich mesačných volieb).';
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
<script src="<?= asset('shell.js') ?>"></script>
</body></html>
