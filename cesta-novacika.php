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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isOwner && isset($_POST['add'])) {
        $phase = trim((string)($_POST['phase'] ?? ''));
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
        $phase = trim((string)($_POST['phase'] ?? ''));
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

$steps = db()->query('SELECT * FROM formulare_onboarding_steps ORDER BY sort_order, id')->fetchAll();

$doneStepIds = [];
if ($steps) {
    $prog = db()->prepare('SELECT step_id FROM formulare_onboarding_progress WHERE advisor_id = ?');
    $prog->execute([$advisorId]);
    $doneStepIds = array_map('intval', array_column($prog->fetchAll(), 'step_id'));
}

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
$currentPhaseName = $currentPhaseName ?? null;

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
    'Pred nástupom' => 'Papierovanie a školenia na začiatku vyzerajú ako veľa — a aj je to veľa. Netreba to zvládnuť dokonale na prvýkrát. Ak si niečím neistý, opýtaj sa — presne na to sú tu kolegovia aj tvoj manažér.',
    '0. mesiac' => 'Prvý mesiac je o učení sa veľa nového naraz. Je úplne normálne, že si na začiatku neistý — nikto od teba nečaká, že to vieš hneď. Manažér aj skúsenejší kolegovia ti radi pomôžu, stačí sa ozvať.',
    'I. mesiac' => 'Ak máš pocit, že iní to majú jednoduchšie, nemajú — každý si prešiel rovnakou krivkou učenia. Pýtaj sa toľko, koľko potrebuješ, nie je to znak slabosti.',
    'II. mesiac' => 'Blok Predaj je o skutočnom rozhovore s klientom — analýza potrieb, argumentácia, zvládanie námietok, uzatváracie techniky. Netreba to zvládnuť dokonale hneď, tieto zručnosti sa budujú praxou. Ak chceš niečo nacvičiť nanečisto, kolegovia aj manažér radi pomôžu.',
    'III. mesiac' => 'Životné poistenie je srdcom tejto práce a najviac otázok je pri ňom úplne prirodzené. Nie si v tom sám — kolegovia aj manažér ťa podržia.',
    'IV. mesiac' => 'Majetkové poistenie je technickejšia oblasť a prvé ponuky bývajú pomalšie — to je v poriadku. Radšej sa spýtaj vopred, než sa s tým trápiť sám.',
    'V. mesiac' => 'Posledný krok pred maturitou. Ver si — dostal si sa sem vlastnou prácou. A ak sa niečo nepodarí na prvý pokus, nie je to koniec sveta.',
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

  .ob-next-card{display:flex; align-items:center; gap:14px; background:linear-gradient(135deg, var(--accent-soft), var(--paper)); border:1px solid var(--accent-line); flex-wrap:wrap;}
  .ob-next-icon{font-size:24px; flex-shrink:0;}
  .ob-next-body{flex:1; min-width:160px;}
  .ob-next-label{font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--accent-ink);}
  .ob-next-title{font-size:14.5px; font-weight:600; color:var(--ink); margin-top:3px;}
  .ob-next-done{display:flex; align-items:center; gap:10px;}
  .ob-next-done .ob-next-title{color:var(--good);}

  .ob-trail{display:flex; align-items:center; gap:0; overflow-x:auto; padding:4px 2px 12px; margin:-4px 0 4px;}
  .ob-trail-item{display:flex; align-items:center; gap:7px; flex-shrink:0; cursor:pointer; padding:6px 10px; border-radius:999px; border:none; background:none; font:inherit; transition:transform .15s ease, background .15s ease;}
  .ob-trail-item:hover{background:var(--desk); transform:translateY(-1px);}
  .ob-trail-dot{width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:700; flex-shrink:0;}
  .ob-trail-name{font-size:12px; font-weight:600; white-space:nowrap;}
  .ob-trail-line{width:20px; height:2px; background:var(--border); flex-shrink:0; transition:background .3s ease;}
  .ob-trail-line.filled{background:var(--good);}
  .ob-trail-item.status-done .ob-trail-dot{background:var(--good); color:#fff;}
  .ob-trail-item.status-done .ob-trail-name{color:var(--muted);}
  .ob-trail-item.status-current .ob-trail-dot{background:var(--accent); color:#fff; animation:obDotPulse 2.2s ease-in-out infinite;}
  .ob-trail-item.status-current .ob-trail-name{color:var(--ink);}
  .ob-trail-item.status-upcoming .ob-trail-dot{background:var(--desk); color:var(--muted); border:1px solid var(--border);}
  .ob-trail-item.status-upcoming .ob-trail-name{color:var(--muted);}
  @keyframes obDotPulse{0%,100%{box-shadow:0 0 0 4px var(--accent-soft);}50%{box-shadow:0 0 0 8px transparent;}}

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

  .ob-phase-support{display:flex; gap:8px; align-items:flex-start; font-size:12.5px; color:var(--ink-2); line-height:1.5;
    background:var(--desk); border-radius:var(--radius-md); padding:9px 11px; margin:0 0 10px;}
  .ob-phase-support .ob-support-emoji{flex-shrink:0;}

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
  <div class="card ob-trail">
    <?php $i = 0; $prevStatus = null; foreach ($phaseList as $phaseName => $st): if ($i > 0): ?><span class="ob-trail-line<?= $prevStatus === 'done' ? ' filled' : '' ?>"></span><?php endif; ?>
    <button type="button" class="ob-trail-item status-<?= $st['status'] ?>" onclick="obJumpPhase(<?= $st['idx'] ?>)">
      <span class="ob-trail-dot"><?= $st['status'] === 'done' ? '✓' : ($st['idx'] + 1) ?></span>
      <span class="ob-trail-name"><?= h($phaseName) ?></span>
    </button>
    <?php $i++; $prevStatus = $st['status']; endforeach; ?>
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
    <h3>Osnova</h3>
    <?php if (!$phases): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span class="es-title">Zatiaľ žiadne kroky</span>
        <span class="es-sub"><?= $isOwner ? 'Pridaj prvý krok osnovy nižšie.' : 'Owner sem zatiaľ nič nepridal.' ?></span>
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
      <?php foreach ($phaseSteps as $s): $isDone = in_array((int)$s['id'], $doneStepIds, true); $tip = $OB_TOOLTIPS[$s['title']] ?? null; ?>
      <div class="ob-step<?= $isDone ? ' done' : '' ?>" id="ob-step-<?= (int)$s['id'] ?>" data-step-id="<?= (int)$s['id'] ?>" data-phase-idx="<?= $st['idx'] ?>">
        <input type="checkbox" <?= $isDone ? 'checked' : '' ?> data-toggle-step="<?= (int)$s['id'] ?>">
        <div class="ob-step-body">
          <div class="ob-step-title"><?= h($s['title']) ?><?php if ($tip): ?><span class="ob-info" tabindex="0">i<span class="ob-info-bubble"><?= h($tip) ?></span></span><?php endif; ?></div>
          <?php if ($s['description']): ?><div class="ob-step-desc"><?= h($s['description']) ?></div><?php endif; ?>
        </div>
        <div class="ob-step-actions">
          <?php if ($s['link_url']): ?><a class="toggle-btn" href="<?= h($s['link_url']) ?>" target="_blank">Otvoriť</a><?php endif; ?>
          <?php if ($isOwner): ?>
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
        <input type="text" name="phase" value="<?= h($s['phase']) ?>" placeholder="Fáza (napr. Deň 1)" required>
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
      </details>
    <?php endforeach; ?>
  </div>

  <?php if ($isOwner): ?>
  <div class="card">
    <h3>Pridať krok</h3>
    <form method="post" class="ob-add-form">
      <input type="hidden" name="add" value="1">
      <div class="ob-add-row">
        <input type="text" name="phase" placeholder="Fáza (napr. Deň 1, Týždeň 1, Mesiac 1)" required>
        <input type="text" name="title" placeholder="Názov kroku" required>
      </div>
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
function obJumpPhase(idx) {
  var el = document.getElementById('ob-phase-' + idx);
  if (!el) return;
  el.open = true;
  el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function obJumpStep(phaseIdx, stepId) {
  var phase = document.getElementById('ob-phase-' + phaseIdx);
  if (phase) phase.open = true;
  var row = document.getElementById('ob-step-' + stepId);
  if (!row) return;
  row.scrollIntoView({ behavior: 'smooth', block: 'center' });
  row.classList.add('ob-highlight');
  setTimeout(function () { row.classList.remove('ob-highlight'); }, 1600);
}
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
    if (phaseDone === phaseSteps.length) {
      var badgeEl = document.getElementById('ob-phase-badge-' + phaseIdx);
      if (badgeEl) badgeEl.textContent = '✓';
      var phaseEl = document.getElementById('ob-phase-' + phaseIdx);
      if (phaseEl) phaseEl.classList.add('status-done');
    }
  }
});
</script>
<script src="/assets/shell.js?v=19"></script>
</body></html>
