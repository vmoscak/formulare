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
<link rel="stylesheet" href="/assets/fonts.css">
<script src="/assets/theme-init.js"></script>
<script src="/assets/toast.js?v=1"></script>
<link rel="stylesheet" href="/assets/panel.css?v=28">
<style>
  .rf-verdict{margin-top:16px; padding:16px 18px; border-radius:var(--radius-lg); font-weight:700; font-size:14.5px;}
  .rf-verdict.good{background:var(--good-soft,#ecfdf5); color:var(--good,#059669);}
  .rf-verdict.bad{background:var(--err-soft,#fff1f2); color:var(--err,#e11d48);}
  .rf-verdict small{display:block; font-weight:500; font-size:12.5px; margin-top:4px; opacity:.85;}
  .rf-chart-row{margin-bottom:16px;}
  .rf-chart-row:last-child{margin-bottom:0;}
  .rf-chart-label{display:flex; justify-content:space-between; font-size:12.5px; color:var(--muted); margin-bottom:6px;}
  .rf-chart-track{height:24px; border-radius:6px; background:var(--desk,#f5f6f8); overflow:hidden;}
  .rf-chart-fill{height:100%; display:flex; align-items:center; justify-content:flex-end; padding-right:9px;
    color:#fff; font-size:11.5px; font-weight:700; white-space:nowrap; box-sizing:border-box;}
  .rf-chart-fill.old{background:#94a3b8;}
  .rf-chart-fill.new{background:#0891b2;}
  .rf-timeline-track{height:28px; border-radius:8px; overflow:hidden; display:flex;}
  .rf-timeline-track .tl-payback{background:#f59e0b; height:100%;}
  .rf-timeline-track .tl-profit{background:#059669; height:100%;}
  .rf-timeline-labels{display:flex; justify-content:space-between; font-size:11.5px; color:var(--muted); margin-top:8px;}
  .rf-timeline-labels .tl-l-payback{color:#b45309; font-weight:600;}
  .rf-timeline-labels .tl-l-profit{color:#059669; font-weight:600;}
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
      <input id="clientName" placeholder="Meno klienta (nepovinné, pre PDF)">
    </div>
  </div>

  <div class="card" id="chartCard" style="display:none;">
    <h3>Graf</h3>
    <div class="rf-chart-row">
      <div class="rf-chart-label"><span>Mesačná splátka</span></div>
      <div class="rf-chart-track"><div class="rf-chart-fill old" id="barOld" style="width:0%;"></div></div>
    </div>
    <div class="rf-chart-row">
      <div class="rf-chart-label"><span>&nbsp;</span></div>
      <div class="rf-chart-track"><div class="rf-chart-fill new" id="barNew" style="width:0%;"></div></div>
    </div>
    <div class="rf-chart-row" style="margin-top:22px;">
      <div class="rf-chart-label"><span>Časová os návratnosti</span></div>
      <div class="rf-timeline-track">
        <div class="tl-payback" id="tlPayback" style="width:0%;"></div>
        <div class="tl-profit" id="tlProfit" style="width:0%;"></div>
      </div>
      <div class="rf-timeline-labels">
        <span class="tl-l-payback" id="tlPaybackLabel">—</span>
        <span class="tl-l-profit" id="tlProfitLabel">—</span>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Výsledok</h3>
    <div style="margin-bottom:14px;">
      <button type="button" class="pillbtn solid" id="pdfBtn">Stiahnuť PDF</button>
    </div>
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

/* Krátka "count-up" animácia hlavných výsledných čísel — od predchádzajúcej
   hodnoty k novej, nech prepočet po zmene vstupu pôsobí menej trhavo. */
const numAnimState = {};
function animateNumber(el, from, to, prefix, formatFn){
  const key = el.id;
  if (numAnimState[key]) cancelAnimationFrame(numAnimState[key]);
  if (!isFinite(from)) from = to;
  const start = performance.now();
  const dur = 450;
  function step(now){
    const t = Math.min(1, (now - start) / dur);
    const eased = 1 - Math.pow(1 - t, 3);
    const val = from + (to - from) * eased;
    el.textContent = prefix + formatFn(val);
    if (t < 1) numAnimState[key] = requestAnimationFrame(step);
    else delete numAnimState[key];
  }
  numAnimState[key] = requestAnimationFrame(step);
}

/* Mesačná splátka anuitného úveru: M = P*r*(1+r)^n / ((1+r)^n - 1), r = mesačná sadzba, n = počet mesiacov. */
function monthlyPayment(principal, annualRatePct, months){
  if (principal <= 0 || months <= 0) return 0;
  const r = annualRatePct / 100 / 12;
  if (Math.abs(r) < 1e-9) return principal / months;
  const factor = Math.pow(1 + r, months);
  return principal * r * factor / (factor - 1);
}

/* Posledný prepočet — použije sa aj pri generovaní PDF, nech sa nič neprepočítava dvakrát. */
let lastResult = null;

function calc(){
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
  const hasData = principal > 0 && years > 0 && (oldRate > 0 || newRate > 0);

  return { principal, oldRate, newRate, years, months, penaltyFee, setupFee,
    oldMonthly, newMonthly, monthlySaving, totalCost, breakEvenMonths, netSaving, hasData };
}

function renderChart(r){
  const chartCard = $('chartCard');
  if (!r.hasData) { chartCard.style.display = 'none'; return; }
  chartCard.style.display = 'block';

  const maxMonthly = Math.max(r.oldMonthly, r.newMonthly, 1);
  const barOld = $('barOld'), barNew = $('barNew');
  barOld.style.width = Math.max(4, r.oldMonthly / maxMonthly * 100) + '%';
  barOld.textContent = eur(r.oldMonthly) + '/mes.';
  barNew.style.width = Math.max(4, r.newMonthly / maxMonthly * 100) + '%';
  barNew.textContent = eur(r.newMonthly) + '/mes.';

  const paybackMonths = isFinite(r.breakEvenMonths) ? Math.min(r.breakEvenMonths, r.months) : r.months;
  const paybackPct = r.months > 0 ? (paybackMonths / r.months * 100) : 0;
  const profitPct = 100 - paybackPct;
  $('tlPayback').style.width = paybackPct + '%';
  $('tlProfit').style.width = profitPct + '%';
  $('tlPaybackLabel').textContent = isFinite(r.breakEvenMonths)
    ? 'Návratnosť: ' + Math.ceil(r.breakEvenMonths) + ' mes.'
    : 'Nestihne sa vrátiť';
  $('tlProfitLabel').textContent = profitPct > 0 ? 'Čistý zisk: ' + Math.round(profitPct) + ' % doby' : '';
}

const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let prevMonthlySaving = 0, prevNetSaving = 0;
function compute(){
  const r = calc();
  lastResult = r;

  $('rOldMonthly').textContent = r.hasData ? eur(r.oldMonthly) + '/mes.' : '—';
  $('rNewMonthly').textContent = r.hasData ? eur(r.newMonthly) + '/mes.' : '—';
  if (r.hasData && !reduceMotion){
    animateNumber($('rMonthlySaving'), prevMonthlySaving, r.monthlySaving, r.monthlySaving >= 0 ? '+' : '', v => eur(v) + '/mes.');
    animateNumber($('rNetSaving'), prevNetSaving, r.netSaving, r.netSaving >= 0 ? '+' : '', eur);
  } else {
    $('rMonthlySaving').textContent = r.hasData ? (r.monthlySaving >= 0 ? '+' : '') + eur(r.monthlySaving) + '/mes.' : '—';
    $('rNetSaving').textContent = r.hasData ? (r.netSaving >= 0 ? '+' : '') + eur(r.netSaving) : '—';
  }
  prevMonthlySaving = r.hasData ? r.monthlySaving : 0;
  prevNetSaving = r.hasData ? r.netSaving : 0;
  $('rTotalCost').textContent = eur(r.totalCost);
  $('rBreakEven').textContent = isFinite(r.breakEvenMonths) ? Math.ceil(r.breakEvenMonths) + ' mesiacov' : '—';

  renderChart(r);

  const verdict = $('verdict');
  if (!r.hasData) {
    verdict.style.display = 'none';
    return;
  }
  verdict.style.display = 'block';
  if (r.monthlySaving <= 0) {
    verdict.className = 'rf-verdict bad';
    verdict.innerHTML = 'Nová sadzba nie je nižšia — refinancovanie sa pri týchto číslach neoplatí.'
      + '<small>Mesačná splátka by sa nezmenšila alebo by sa zväčšila.</small>';
  } else if (r.breakEvenMonths <= r.months) {
    verdict.className = 'rf-verdict good';
    verdict.innerHTML = 'Oplatí sa — náklady na prechod sa vrátia za ' + Math.ceil(r.breakEvenMonths) + ' mesiacov.'
      + '<small>Čistá úspora za zostávajúcu dobu splácania: ' + eur(r.netSaving) + '.</small>';
  } else {
    verdict.className = 'rf-verdict bad';
    verdict.innerHTML = 'Hraničné — návratnosť (' + Math.ceil(r.breakEvenMonths) + ' mesiacov) presahuje zostávajúcu dobu splácania.'
      + '<small>Pri kratšej zostávajúcej dobe sa prechod nestihne oplatiť.</small>';
  }
}

/* ===========================================================================
   PDF (server-side Dompdf cez zdieľaný /pdf.php, presné A4) — rovnaké grafy
   ako v náhľade, len znovupostavené inline-štýlovou tabuľkovou schémou.
=========================================================================== */
function escapeHtml(x){ return String(x==null?'':x).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function buildReportHtml(r){
  const clientName = ($('clientName').value || '').trim() || 'Klient';
  const today = new Date();
  const SK_MONTHS = ['január','február','marec','apríl','máj','jún','júl','august','september','október','november','december'];
  const dateStr = today.getDate() + '. ' + SK_MONTHS[today.getMonth()] + ' ' + today.getFullYear();

  const maxMonthly = Math.max(r.oldMonthly, r.newMonthly, 1);
  const oldPct = Math.max(4, r.oldMonthly / maxMonthly * 100);
  const newPct = Math.max(4, r.newMonthly / maxMonthly * 100);
  const paybackMonths = isFinite(r.breakEvenMonths) ? Math.min(r.breakEvenMonths, r.months) : r.months;
  const paybackPct = r.months > 0 ? (paybackMonths / r.months * 100) : 0;
  const profitPct = 100 - paybackPct;

  const verdictGood = r.monthlySaving > 0 && r.breakEvenMonths <= r.months;
  const verdictText = r.monthlySaving <= 0
    ? 'Nová sadzba nie je nižšia — refinancovanie sa pri týchto číslach neoplatí.'
    : (verdictGood
      ? 'Oplatí sa — náklady na prechod sa vrátia za ' + Math.ceil(r.breakEvenMonths) + ' mesiacov. Čistá úspora za zostávajúcu dobu: ' + eur(r.netSaving) + '.'
      : 'Hraničné — návratnosť (' + Math.ceil(r.breakEvenMonths) + ' mesiacov) presahuje zostávajúcu dobu splácania.');

  const css = `
    @font-face { font-family:'DejaVu Sans'; src:url('vendor/dompdf/lib/fonts/DejaVuSans.ttf'); }
    @font-face { font-family:'DejaVu Sans'; font-weight:bold; src:url('vendor/dompdf/lib/fonts/DejaVuSans-Bold.ttf'); }
    * { box-sizing:border-box; }
    div,p,span,table,tr,td { margin:0; padding:0; }
    body { margin:0; padding:0; font-family:'DejaVu Sans',sans-serif; font-size:10.5pt; line-height:1.5; color:#20242b; background:#fff; }
    .pdf-topbar { height:3mm; background:#0891b2; border-radius:0.8mm; margin-bottom:6mm; } .doctitle { text-align:center; font-size:16pt; letter-spacing:.5pt; font-weight:bold; padding:7pt 0 2pt; }
    .card-sub { text-align:center; font-size:10.5pt; color:#666; margin-bottom:5mm; }
    .meta { text-align:center; font-size:9pt; color:#8a8a8a; margin-bottom:9mm; padding-bottom:5mm; border-bottom:1pt solid #e5e5e5; }
    .sec-hd { font-weight:bold; font-size:11pt; margin:7mm 0 3mm; }
    .bar-row { margin-bottom:4mm; }
    .bar-label { font-size:9pt; color:#666; margin-bottom:1.5mm; }
    table.bar-track { width:100%; border-collapse:collapse; height:6mm; background:#f1f3f5; border-radius:1mm; }
    table.bar-track td.fill { height:6mm; color:#fff; font-size:8.5pt; font-weight:bold; text-align:right; padding-right:2.5mm; }
    td.fill.old { background:#94a3b8; }
    td.fill.new { background:#0891b2; }
    table.tl-track { width:100%; border-collapse:collapse; height:7mm; margin-top:2mm; }
    table.tl-track td.payback { background:#f59e0b; height:7mm; }
    table.tl-track td.profit { background:#059669; height:7mm; }
    .tl-labels { display:table; width:100%; margin-top:2mm; font-size:8.5pt; }
    .tl-l { display:table-cell; }
    .tl-l.payback { color:#b45309; font-weight:bold; text-align:left; }
    .tl-l.profit { color:#059669; font-weight:bold; text-align:right; }
    table.rows { width:100%; border-collapse:collapse; margin-top:6mm; }
    table.rows td { padding:2.8mm 0; border-bottom:0.5pt solid #eef0f3; font-size:10pt; }
    table.rows td.v { text-align:right; font-weight:bold; }
    .verdict { margin-top:6mm; padding:4mm 5mm; border-radius:2mm; font-size:10pt; font-weight:bold; }
    .verdict.good { background:#ecfdf5; color:#059669; }
    .verdict.bad { background:#fff1f2; color:#e11d48; }
    .foot { font-size:8pt; color:#999; margin-top:8mm; }
    @page{ margin:18mm 18mm 18mm 18mm; }
  `;

  const body = '<div class="pdf-topbar"></div><div class="doctitle">Oplatí sa mi refinancovať?</div>'
    + '<div class="card-sub">Break-even prepočet pri zmene banky</div>'
    + '<div class="meta">' + escapeHtml(clientName) + ' &middot; ' + dateStr + '</div>'
    + '<div class="sec-hd">Mesačná splátka</div>'
    + '<div class="bar-row"><div class="bar-label">Súčasná sadzba (' + r.oldRate + ' %)</div>'
      + '<table class="bar-track"><tr><td class="fill old" style="width:' + oldPct + '%;">' + eur(r.oldMonthly) + '</td><td></td></tr></table></div>'
    + '<div class="bar-row"><div class="bar-label">Nová sadzba (' + r.newRate + ' %)</div>'
      + '<table class="bar-track"><tr><td class="fill new" style="width:' + newPct + '%;">' + eur(r.newMonthly) + '</td><td></td></tr></table></div>'
    + '<div class="sec-hd">Časová os návratnosti</div>'
    + '<table class="tl-track"><tr><td class="payback" style="width:' + paybackPct + '%;"></td><td class="profit" style="width:' + profitPct + '%;"></td></tr></table>'
    + '<div class="tl-labels"><div class="tl-l payback">' + (isFinite(r.breakEvenMonths) ? 'Návratnosť: ' + Math.ceil(r.breakEvenMonths) + ' mes.' : 'Nestihne sa vrátiť') + '</div>'
      + '<div class="tl-l profit">' + (profitPct > 0 ? 'Čistý zisk: ' + Math.round(profitPct) + ' % doby' : '') + '</div></div>'
    + '<table class="rows">'
      + '<tr><td>Mesačná úspora</td><td class="v">' + (r.monthlySaving >= 0 ? '+' : '') + eur(r.monthlySaving) + '/mes.</td></tr>'
      + '<tr><td>Celkové náklady na prechod</td><td class="v">' + eur(r.totalCost) + '</td></tr>'
      + '<tr><td>Čistá úspora za zostávajúcu dobu</td><td class="v">' + (r.netSaving >= 0 ? '+' : '') + eur(r.netSaving) + '</td></tr>'
    + '</table>'
    + '<div class="verdict ' + (verdictGood ? 'good' : 'bad') + '">' + escapeHtml(verdictText) + '</div>'
    + '<div class="foot">Orientačný prepočet, nie záväzná ponuka. Presné podmienky sa vždy overia priamo v banke.</div>';

  return '<!DOCTYPE html><html lang="sk"><head>'
    + '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
    + '<title>Oplatí sa mi refinancovať?</title><style>' + css + '</style></head><body>' + body + '</body></html>';
}

function doPDF(){
  if (!lastResult || !lastResult.hasData) { showToast('Najprv vyplň vstupy — istinu, sadzby a dobu splácania.', 'error'); return; }
  const btn = $('pdfBtn'); const orig = btn.textContent;
  btn.textContent = 'Generujem…'; btn.disabled = true;

  const html = buildReportHtml(lastResult);
  const clientName = ($('clientName').value || '').trim();
  const filename = 'Oplati_sa_refinancovat' + (clientName ? '_' + clientName.replace(/\s+/g,'_') : '');

  fetch('/pdf.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ html: html, filename: filename })
  })
  .then(function(r){
    const ct = r.headers.get('Content-Type') || '';
    if (ct.includes('application/json') || ct.includes('text/')) {
      return r.text().then(function(t){ throw new Error('Server: ' + t); });
    }
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.blob();
  })
  .then(function(blob){
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename + '.pdf';
    document.body.appendChild(a); a.click();
    setTimeout(function(){ document.body.removeChild(a); URL.revokeObjectURL(url); }, 1000);
    btn.textContent = orig; btn.disabled = false;
  })
  .catch(function(e){
    console.error(e);
    btn.textContent = orig; btn.disabled = false;
    showToast('Chyba PDF: ' + e.message, 'error');
  });
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
$('pdfBtn').addEventListener('click', doPDF);
compute();
</script>
<script src="/assets/shell.js?v=22"></script>
</body></html>
