/**
 * Zdieľané pomocné funkcie pre poradcovský panel (klientske odkazy,
 * vygenerované dokumenty) — používajú financna-medzera aj wizard-poistenie.
 * Musí byť verejne dostupný aj bez gate cookie (viď .htaccess), keďže ho
 * načítava aj klientska stránka otvorená cez jedinečný token.
 */

function escapeHtml(x){ return String(x).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* Zaloguje vygenerovaný dokument (poradcovský aj klientsky) — fire-and-forget,
   nesmie nikdy zablokovať samotné stiahnutie PDF pri zlyhaní.
   isDraft=true: "Uložiť rozpracované" — dokument sa uloží do histórie BEZ
   generovania PDF, len aby sa dal neskôr znova otvoriť a doplniť. */
function logDocument(tool, clientLabel, formData, clientToken, isDraft){
  try{
    fetch('../api/log-document.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        tool: tool,
        clientLabel: clientLabel,
        formData: formData,
        token: clientToken || undefined,
        isDraft: !!isDraft,
      })
    }).catch(()=>{});
  }catch(e){}
}

/* Autocomplete mena klienta z histórie tohto nástroja u tohto poradcu —
   naviaže <datalist> (vytvorí ho, ak treba) na dané pole podľa jeho id, nech
   sa meno pri opakovanom vypĺňaní toho istého nástroja nemusí písať odznova.
   Bez cur_advisor cookie (napr. klientska stránka cez token) endpoint vráti
   prázdny zoznam — tichý no-op. */
function wireClientAutocomplete(tool, inputId){
  var inputEl = document.getElementById(inputId);
  if (!inputEl) return;
  try{
    var dlId = inputId + 'RecentDL';
    var dl = document.getElementById(dlId);
    if (!dl) {
      dl = document.createElement('datalist');
      dl.id = dlId;
      document.body.appendChild(dl);
    }
    inputEl.setAttribute('list', dlId);
    fetch('../api/recent-clients.php?tool=' + encodeURIComponent(tool))
      .then(function(r){ return r.ok ? r.json() : {clients:[]}; })
      .then(function(data){
        var clients = (data && data.clients) || [];
        dl.innerHTML = clients.map(function(c){ return '<option value="' + escapeHtml(c) + '">'; }).join('');
      })
      .catch(function(){});
  }catch(e){}
}

/* Ak URL obsahuje ?loadDoc=<id> (odkaz z "Moje dokumenty" / prehľadu pre
   majiteľa), načíta uložený dokument a rovno spustí generovanie PDF —
   umožňuje sa k už vygenerovanému dokumentu kedykoľvek vrátiť. Pri koncepte
   (is_draft, uložený cez "Uložiť rozpracované") sa PDF negeneruje automaticky
   — len sa doplnia dáta do formulára, nech sa dá pokračovať v rozpracovaní.
   applyFn(formData) — nastaví načítané dáta do stavu formulára a prekreslí.
   generateFn() — funkcia, ktorá spustí generovanie/stiahnutie PDF (doPDF /
   doGeneratePdf). */
async function autoOpenSavedDocument(applyFn, generateFn){
  const id = new URLSearchParams(location.search).get('loadDoc');
  if (!id) return;
  try{
    const r = await fetch('../api/get-document.php?id=' + encodeURIComponent(id));
    if (!r.ok) return;
    const data = await r.json();
    if (!data || !data.form_data) return;
    applyFn(JSON.parse(data.form_data));
    if (!data.is_draft) generateFn();
  }catch(e){ /* ticho ignoruj, formulár ostane prázdny */ }
}

/* Doručí vygenerované PDF používateľovi — na mobile s podporou Web Share API
   (Android Chrome, iOS Safari) otvorí natívne menu "Zdieľať" priamo s PDF
   súborom (WhatsApp/e-mail/Airdrop...), inak (desktop, staršie prehliadače)
   spadne späť na klasické stiahnutie ako doteraz. Natívne "Zdieľať" sa
   obmedzuje len na mobil — novšie desktopové Chrome/Edge (Windows) tiež
   vedia navigator.share, ale tam poradcovia väčšinou chcú PDF rovno
   uložiť, nie otvárať zdieľací dialóg. */
function downloadOrSharePdfBlob(blob, filename){
  var name = filename + '.pdf';
  var isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  try{
    if(isMobile){
      var file = new File([blob], name, { type: 'application/pdf' });
      if(navigator.share && navigator.canShare && navigator.canShare({ files: [file] })){
        navigator.share({ files: [file] }).catch(function(){});
        return;
      }
    }
  }catch(e){ /* File/share nepodporované — pokračuj na stiahnutie nižšie */ }
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a); a.click();
  setTimeout(function(){ document.body.removeChild(a); URL.revokeObjectURL(url); }, 1000);
}

/* Vykreslí QR kód klientskeho odkazu do daného kontajnera — na stretnutí
   klient telefónom naskenuje a rovno vypĺňa, netreba nič posielať/prepisovať.
   Vyžaduje assets/qrcode.js (vendored, žiadne volanie na cudzí server). */
function renderLinkQr(containerId, url){
  const el = document.getElementById(containerId);
  if(!el || typeof qrcode !== 'function') return;
  try{
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    el.innerHTML = qr.createSvgTag(4, 4);
  }catch(e){ /* ticho ignoruj, odkaz aj tak funguje bez QR */ }
}

/* Načíta a vykreslí zoznam (klientske odkazy / vygenerované dokumenty) do
   karty, ktorá je štandardne skrytá — zobrazí sa len ak sú nejaké záznamy.
   opts: { endpoint, tool, cardId, listId, rowHtml(row)->string, onOpen(row) } */
async function loadHistoryList(opts){
  try{
    const r = await fetch(opts.endpoint + '?tool=' + opts.tool);
    const rows = await r.json();
    const card = document.getElementById(opts.cardId);
    const list = document.getElementById(opts.listId);
    if(!Array.isArray(rows) || !rows.length || !card || !list) return;
    card.style.display = 'block';
    list.innerHTML = rows.map(opts.rowHtml).join('');
    list.querySelectorAll('[data-open-id]').forEach(function(btn){
      btn.addEventListener('click', function(){
        const row = rows.find(function(x){ return String(x.id) === btn.dataset.openId; });
        if(row) opts.onOpen(row);
      });
    });
  }catch(e){ /* ticho ignoruj, panel jednoducho zostane skrytý */ }
}
