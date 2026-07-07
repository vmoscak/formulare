/**
 * Zdieľané pomocné funkcie pre poradcovský panel (klientske odkazy,
 * vygenerované dokumenty) — používajú financna-medzera aj wizard-poistenie.
 * Musí byť verejne dostupný aj bez gate cookie (viď .htaccess), keďže ho
 * načítava aj klientska stránka otvorená cez jedinečný token.
 */

function escapeHtml(x){ return String(x).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

/* Zaloguje vygenerovaný dokument (poradcovský aj klientsky) — fire-and-forget,
   nesmie nikdy zablokovať samotné stiahnutie PDF pri zlyhaní. */
function logDocument(tool, clientLabel, formData, clientToken){
  try{
    fetch('../api/log-document.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        tool: tool,
        clientLabel: clientLabel,
        formData: formData,
        token: clientToken || undefined,
      })
    }).catch(()=>{});
  }catch(e){}
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
