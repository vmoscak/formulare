/* ============================================================================
   FORMULÁRE — spoločná ľavá ikonová lišta (app rail)
   ----------------------------------------------------------------------------
   Vloží fixnú ľavú lištu s navigáciou na všetky stránky, kde je prihlásený
   poradca. Zisťuje sa cez /api/whoami.php — ak vráti prázdno (klient otvorený
   cez token, nezvolený poradca, brána), lišta sa NEZOBRAZÍ. Vďaka tomu klienti
   nikdy nevidia poradcovskú navigáciu.

   Lišta je position:fixed, takže nenarúša existujúci layout — telu sa len
   pridá ľavý padding (trieda .has-rail). Štýly aj Inter font sú vložené tu,
   aby lišta vyzerala rovnako na každej stránke bez ohľadu na jej CSS.

   KDE ČO UPRAVIŤ:
     • Položky navigácie ..... pole NAV nižšie
     • Vzhľad lišty .......... reťazec CSS nižšie
========================================================================== -->
*/
(function () {
  'use strict';

  // Inter font (rovnaký ako v predlohe) — s fallbackom na systémové písmo.
  try {
    var f = document.createElement('link');
    f.rel = 'stylesheet';
    f.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';
    document.head.appendChild(f);
  } catch (e) { /* bez Interu ostane systémové písmo */ }

  var ICONS = {
    logo: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
    tools: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    docs: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/>',
    admin: '<path d="M12 2l7 4v6c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6z"/>',
  };

  function svg(path) {
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
      'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
  }

  function initials(name) {
    var parts = (name || '').trim().split(/\s+/);
    var a = parts[0] ? parts[0][0] : '';
    var b = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (a + b).toUpperCase() || '?';
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function render(adv) {
    var path = location.pathname;
    var isDocs = /moje-dokumenty/.test(path);
    var isAdmin = /admin\.php/.test(path);
    var isTools = !isDocs && !isAdmin; // nástroje aj samotná stránka nastroje.php

    var NAV = [
      { key: 'tools', icon: ICONS.tools, href: '/nastroje.php', label: 'Nástroje', active: isTools },
      { key: 'docs', icon: ICONS.docs, href: '/moje-dokumenty.php', label: 'Moje dokumenty', active: isDocs }
    ];
    // Admin ikona sa zobrazí len poradcovi s is_admin=1 (server-side to aj
    // tak stráži admin.php samotné — toto je len viditeľnosť v navigácii).
    if (adv.is_admin) {
      NAV.push({ key: 'admin', icon: ICONS.admin, href: '/admin.php', label: 'Admin', active: isAdmin });
    }

    var css =
      '#appRail{position:fixed;left:0;top:0;bottom:0;width:72px;background:#fff;border-right:1px solid #eef0f3;' +
      'display:flex;flex-direction:column;align-items:center;padding:18px 0;z-index:60;font-family:\'Inter\',-apple-system,\'Segoe UI\',Roboto,sans-serif;}' +
      '#appRail .rlogo{width:40px;height:40px;border-radius:12px;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;' +
      'box-shadow:0 6px 16px -4px rgba(79,70,229,.5);margin-bottom:26px;flex-shrink:0;}' +
      '#appRail nav{flex:1;display:flex;flex-direction:column;gap:8px;align-items:center;width:100%;}' +
      '#appRail a.ri{position:relative;width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;' +
      'color:#98a2b3;text-decoration:none;transition:color .15s,background .15s;}' +
      '#appRail a.ri:hover{color:#4b5563;background:#f5f6f8;}' +
      '#appRail a.ri.on{color:#4f46e5;background:#eef2ff;}' +
      '#appRail a.ri.on::before{content:"";position:absolute;left:-14px;top:50%;transform:translateY(-50%);width:4px;height:22px;border-radius:0 4px 4px 0;background:#4f46e5;}' +
      '#appRail a.ri .tip{position:absolute;left:56px;top:50%;transform:translateY(-50%);background:#111827;color:#fff;font-size:12px;font-weight:500;' +
      'padding:6px 10px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;box-shadow:0 8px 20px -6px rgba(0,0,0,.4);z-index:2;}' +
      '#appRail a.ri:hover .tip{opacity:1;}' +
      '#appRail .rbot{margin-top:auto;display:flex;flex-direction:column;align-items:center;gap:14px;}' +
      '#appRail .ravatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;' +
      'font-size:13px;font-weight:600;color:#fff;text-decoration:none;box-shadow:0 4px 10px -4px rgba(16,24,40,.4);}' +
      'body.has-rail{padding-left:72px;}' +
      '@media(max-width:720px){#appRail{display:none;}body.has-rail{padding-left:0;}}';

    var navHtml = NAV.map(function (n) {
      return '<a class="ri' + (n.active ? ' on' : '') + '" href="' + n.href + '">' + svg(n.icon) +
        '<span class="tip">' + esc(n.label) + '</span></a>';
    }).join('');

    var color = /^#[0-9a-fA-F]{6}$/.test(adv.color || '') ? adv.color : '#4f46e5';

    var html =
      '<div id="appRail">' +
        '<a class="rlogo" href="/nastroje.php" title="Formuláre">' + svg(ICONS.logo) + '</a>' +
        '<nav>' + navHtml + '</nav>' +
        '<div class="rbot">' +
          '<a class="ravatar" href="/" title="' + esc(adv.name || '') + ' — zmeniť poradcu" ' +
          'style="background:' + color + ';">' + esc(initials(adv.name)) + '</a>' +
        '</div>' +
      '</div>';

    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    var wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.insertBefore(wrap.firstChild, document.body.firstChild);
    document.body.classList.add('has-rail');
  }

  function boot() {
    fetch('/api/whoami.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (adv) {
        if (adv && adv.id) render(adv);
      })
      .catch(function () { /* bez lišty — appka funguje normálne */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
