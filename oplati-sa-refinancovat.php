<?php
/**
 * Oplatí sa mi refinancovať? — break-even prepočet pri refinancovaní
 * hypotéky: mesačná úspora novej sadzby vs. náklady na prechod (poplatok
 * za predčasné splatenie + náklady na nové dojednanie), za koľko mesiacov
 * sa prechod reálne vráti. Prístup VÝHRADNE pre is_owner=1 — rieši len
 * hypotéky sám, ostatní poradcovia to nepotrebujú (rovnaký vzor ako
 * refinancny-radar.php/nabor.php/znalostna-baza.php). Čisto klientská
 * kalkulačka (žiadny zápis do DB) — voliteľne ponúkne aktuálne sadzby
 * z Refinančného Radaru na predvyplnenie novej sadzby.
 */
require_once __DIR__ . '/db.php';

$advisorId = curAdvisorId();
$stmt = db()->prepare('SELECT * FROM formulare_advisors WHERE id = ? AND is_owner = 1 AND active = 1');
$stmt->execute([$advisorId]);
$me = $stmt->fetch();
if (!$me) { header('Location: /'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$rates = [];
try {
    $rates = db()->query('SELECT * FROM formulare_refi_rates ORDER BY fixation, rate ASC')->fetchAll();
} catch (Throwable $e) { /* tabuľka môže byť ešte prázdna */ }
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Oplatí sa mi refinancovať?</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="/assets/theme-init.js"></script>
<link rel="stylesheet" href="/assets/panel.css?v=14">
<style>
  .rf-verdict{margin-top:16px; padding:16px 18px; border-radius:var(--radius-lg); font-weight:700; font-size:14.5px;}
  .rf-verdict.good{background:var(--good-soft,#ecfdf5); color:var(--good,#059669);}
  .rf-verdict.bad{background:var(--err-soft,#fff1f2); color:var(--err,#e11d48);}
  .rf-verdict small{display:block; font-weight:500; font-size:12.5px; margin-top:4px; opacity:.85;}
</style>
</head><body>
<header class="topbar">
  <div class="tb-title">
    <h1>Oplatí sa mi refinancovať?</h1>
    <p>Break-even prepočet pri zmene banky · žiadne dáta sa neukladajú · viditeľné len tebe</p>
  </div>
  <div class="tb-actions">
    <a class="pillbtn" href="/nastroje.php">← Späť na nástroje</a>
  </div>
</header>

<main class="content">

  <div class="card">
    <h3>Vstupy</h3>
    <p style="margin:-6px 0 16px; font-size:12.5px; color:var(--muted);">
      Prepočet je orientačný — presné podmienky (poplatky, doba vybavenia) sa vždy overia priamo v banke.
    </p>
    <div class="add-form" style="display:flex; flex-wrap:wrap; gap:10px;">
      <input id="principal" type="number" min="0" step="100" placeholder="Zostatok istiny (€)">
      <input id="oldRate" type="number" min="0" step="0.01" placeholder="Súčasná sadzba (% p.a.)">
      <input id="newRate" type="number" min="0" step="0.01" placeholder="Nová sadzba (% p.a.)">
      <?php if ($rates): ?>
      <select id="rateSuggest">
        <option value="">— alebo vyber z Refinančného Radaru —</option>
        <?php foreach ($rates as $r): ?>
        <option value="<?= h($r['rate']) ?>"><?= h($r['bank']) ?> · <?= h($r['fixation']) ?> · <?= number_format((float)$r['rate'], 2, ',', ' ') ?> %</option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <input id="years" type="number" min="1" step="1" placeholder="Zostávajúca doba splácania (roky)">
      <input id="penaltyFee" type="number" min="0" step="10" placeholder="Poplatok za predčasné splatenie (€)">
      <input id="setupFee" type="number" min="0" step="10" placeholder="Náklady na nové dojednanie (€)">
    </div>
  </div>

  <div class="card">
    <h3>Výsledok</h3>
    <table>
      <tr><th>Položka</th><th>Hodnota</th></tr>
      <tr><td data-label="Položka">Mesačná splátka — súčasná sadzba</td><td data-label="Hodnota" id="rOldMonthly">—</td></tr>
      <tr><td data-label="Položka">Mesačná splátka — nová sadzba</td><td data-label="Hodnota" id="rNewMonthly">—</td></tr>
      <tr><td data-label="Položka">Mesačná úspora</td><td data-label="Hodnota" id="rMonthlySaving">—</td></tr>
      <tr><td data-label="Položka">Celkové náklady na prechod</td><td data-label="Hodnota" id="rTotalCost">—</td></tr>
      <tr><td data-label="Položka">Návratnosť (break-even)</td><td data-label="Hodnota" id="rBreakEven">—</td></tr>
      <tr><td data-label="Položka">Čistá úspora za zostávajúcu dobu</td><td data-label="Hodnota" id="rNetSaving">—</td></tr>
    </table>
    <div class="rf-verdict" id="verdict" style="display:none;"></div>
  </div>

</main>
<script>
const $ = id => document.getElementById(id);
function num(v){ const n = Number(v); return isFinite(n) && n >= 0 ? n : 0; }
function eur(n){ return Math.round(n).toLocaleString('sk-SK') + ' €'; }

/* Mesačná splátka anuitného úveru: M = P*r*(1+r)^n / ((1+r)^n - 1), r = mesačná sadzba, n = počet mesiacov. */
function monthlyPayment(principal, annualRatePct, months){
  if (principal <= 0 || months <= 0) return 0;
  const r = annualRatePct / 100 / 12;
  if (Math.abs(r) < 1e-9) return principal / months;
  const factor = Math.pow(1 + r, months);
  return principal * r * factor / (factor - 1);
}

function compute(){
  const principal = num($('principal').value);
  const oldRate = num($('oldRate').value);
  const newRate = num($('newRate').value);
  const years = num($('years').value);
  const months = Math.round(years * 12);
  const penaltyFee = num($('penaltyFee').value);
  const setupFee = num($('setupFee').value);

  const oldMonthly = monthlyPayment(principal, oldRate, months);
  const newMonthly = monthlyPayment(principal, newRate, months);
  const monthlySaving = oldMonthly - newMonthly;
  const totalCost = penaltyFee + setupFee;
  const breakEvenMonths = monthlySaving > 0 ? totalCost / monthlySaving : Infinity;
  const netSaving = monthlySaving * months - totalCost;

  $('rOldMonthly').textContent = principal > 0 && years > 0 ? eur(oldMonthly) + '/mes.' : '—';
  $('rNewMonthly').textContent = principal > 0 && years > 0 ? eur(newMonthly) + '/mes.' : '—';
  $('rMonthlySaving').textContent = principal > 0 && years > 0 ? (monthlySaving >= 0 ? '+' : '') + eur(monthlySaving) + '/mes.' : '—';
  $('rTotalCost').textContent = eur(totalCost);
  $('rBreakEven').textContent = isFinite(breakEvenMonths) ? Math.ceil(breakEvenMonths) + ' mesiacov' : '—';
  $('rNetSaving').textContent = principal > 0 && years > 0 ? (netSaving >= 0 ? '+' : '') + eur(netSaving) : '—';

  const verdict = $('verdict');
  if (principal <= 0 || years <= 0 || (oldRate <= 0 && newRate <= 0)) {
    verdict.style.display = 'none';
    return;
  }
  verdict.style.display = 'block';
  if (monthlySaving <= 0) {
    verdict.className = 'rf-verdict bad';
    verdict.innerHTML = 'Nová sadzba nie je nižšia — refinancovanie sa pri týchto číslach neoplatí.'
      + '<small>Mesačná splátka by sa nezmenšila alebo by sa zväčšila.</small>';
  } else if (breakEvenMonths <= months) {
    verdict.className = 'rf-verdict good';
    verdict.innerHTML = 'Oplatí sa — náklady na prechod sa vrátia za ' + Math.ceil(breakEvenMonths) + ' mesiacov.'
      + '<small>Čistá úspora za zostávajúcu dobu splácania: ' + eur(netSaving) + '.</small>';
  } else {
    verdict.className = 'rf-verdict bad';
    verdict.innerHTML = 'Hraničné — návratnosť (' + Math.ceil(breakEvenMonths) + ' mesiacov) presahuje zostávajúcu dobu splácania.'
      + '<small>Pri kratšej zostávajúcej dobe sa prechod nestihne oplatiť.</small>';
  }
}

['principal', 'oldRate', 'newRate', 'years', 'penaltyFee', 'setupFee'].forEach(function(id){
  $(id).addEventListener('input', compute);
});
const rateSuggest = $('rateSuggest');
if (rateSuggest) {
  rateSuggest.addEventListener('change', function(){
    if (this.value) { $('newRate').value = this.value; compute(); }
  });
}
compute();
</script>
<script src="/assets/shell.js?v=13"></script>
</body></html>
