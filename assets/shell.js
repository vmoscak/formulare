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
    formulare: '<path d="M8 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2"/><rect x="4" y="7" width="12" height="14" rx="2"/>',
    pomocky: '<rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/>',
    docs: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="14" y2="17"/>',
    admin: '<path d="M12 2l7 4v6c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6z"/>',
    nabor: '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    kb: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    news: '<path d="M3 11v3a1 1 0 0 0 1 1h2l4 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 8a4 4 0 0 1 0 8"/><path d="M17.5 5.5a8 8 0 0 1 0 13"/>',
    home: '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
  };

  // Aktuálne účinná téma — explicitný prepínač (data-theme) má prednosť,
  // inak systémová voľba (prefers-color-scheme).
  function effectiveTheme() {
    var explicit = document.documentElement.getAttribute('data-theme');
    if (explicit === 'dark' || explicit === 'light') return explicit;
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  }
  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('theme', t); } catch (e) {}
    var btn = document.getElementById('themeToggle');
    if (btn) btn.innerHTML = svg(t === 'dark' ? ICONS.sun : ICONS.moon);
  }

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

  function render(adv, toolGroups) {
    var path = location.pathname;
    var isDocs = /moje-dokumenty/.test(path);
    var isAdmin = /admin\.php/.test(path);
    var isNabor = /nabor\.php/.test(path);
    var isKb = /znalostna-baza/.test(path);
    var isNews = /novinky\.php/.test(path);
    var isHome = /uvod\.php/.test(path);

    // Ktorá z troch záložiek (Nástroje/Formuláre/Pomôcky) je aktívna: buď sme
    // priamo na jej prehľadovej stránke, alebo na stránke konkrétneho nástroja
    // — vtedy sa skupina zistí zo slugu cesty cez mapu z api/tool-groups.php.
    var currentGroup = null;
    if (/\/nastroje\.php/.test(path)) currentGroup = 'nastroje';
    else if (/\/formulare\.php/.test(path)) currentGroup = 'formulare';
    else if (/\/pomocky\.php/.test(path)) currentGroup = 'pomocky';
    else if (!isDocs && !isAdmin && !isNabor && !isKb && !isNews && !isHome) {
      var slug = (path.split('/').filter(Boolean)[0]) || '';
      currentGroup = (toolGroups && toolGroups[slug]) || 'nastroje';
    }

    var NAV = [
      { key: 'home', icon: ICONS.home, href: '/uvod.php', label: 'Domov', active: isHome },
      { key: 'nastroje', icon: ICONS.tools, href: '/nastroje.php', label: 'Nástroje', active: currentGroup === 'nastroje' },
      { key: 'formulare', icon: ICONS.formulare, href: '/formulare.php', label: 'Formuláre', active: currentGroup === 'formulare' },
      { key: 'pomocky', icon: ICONS.pomocky, href: '/pomocky.php', label: 'Pomôcky', active: currentGroup === 'pomocky' },
      { key: 'docs', icon: ICONS.docs, href: '/moje-dokumenty.php', label: 'Moje dokumenty', active: isDocs }
    ];
    // Admin ikona sa zobrazí len poradcovi s is_admin=1 (server-side to aj
    // tak stráži admin.php samotné — toto je len viditeľnosť v navigácii).
    if (adv.is_admin) {
      NAV.push({ key: 'admin', icon: ICONS.admin, href: '/admin.php', label: 'Admin', active: isAdmin });
    }
    // Náborová zóna aj znalostná báza — viditeľné VÝHRADNE pre is_owner (nie
    // každý admin), obe stránky si to aj tak strážia server-side rovnako prísne.
    if (adv.is_owner) {
      NAV.push({ key: 'nabor', icon: ICONS.nabor, href: '/nabor.php', label: 'Nábor', active: isNabor });
      NAV.push({ key: 'kb', icon: ICONS.kb, href: '/znalostna-baza.php', label: 'Znalostná báza', active: isKb });
      NAV.push({ key: 'news', icon: ICONS.news, href: '/novinky.php', label: 'Novinky', active: isNews });
    }

    var css =
      '#appRail{position:fixed;left:0;top:0;bottom:0;width:72px;background:var(--paper,#fff);border-right:1px solid var(--border,#eef0f3);' +
      'display:flex;flex-direction:column;align-items:center;padding:18px 0;z-index:60;font-family:\'Inter\',-apple-system,\'Segoe UI\',Roboto,sans-serif;}' +
      '#appRail .rlogo{width:40px;height:40px;border-radius:12px;background:var(--accent,#4f46e5);color:#fff;display:flex;align-items:center;justify-content:center;' +
      'box-shadow:0 6px 16px -4px rgba(79,70,229,.5);margin-bottom:26px;flex-shrink:0;}' +
      '#appRail nav{flex:1;display:flex;flex-direction:column;gap:8px;align-items:center;width:100%;}' +
      '#appRail a.ri{position:relative;width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;' +
      'color:var(--label,#98a2b3);text-decoration:none;transition:color .15s,background .15s,transform .12s ease;}' +
      '#appRail a.ri:hover{color:var(--ink-2,#4b5563);background:var(--desk,#f5f6f8);transform:scale(1.07);}' +
      '#appRail a.ri:active{transform:scale(.94);}' +
      '#appRail a.ri.on{color:var(--accent,#4f46e5);background:var(--accent-soft,#eef2ff);}' +
      '#appRail a.ri.on::before{content:"";position:absolute;left:-14px;top:50%;transform:translateY(-50%);width:4px;height:22px;border-radius:0 4px 4px 0;background:var(--accent,#4f46e5);' +
      'transition:height .15s ease;}' +
      '#appRail a.ri .tip,#appRail button.ri .tip{position:absolute;left:56px;top:50%;transform:translateY(-50%);background:#111827;color:#fff;font-size:12px;font-weight:500;' +
      'padding:6px 10px;border-radius:8px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;box-shadow:0 8px 20px -6px rgba(0,0,0,.4);z-index:2;}' +
      '#appRail a.ri:hover .tip,#appRail button.ri:hover .tip{opacity:1;}' +
      '#appRail button.ri{position:relative;width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;' +
      'color:var(--label,#98a2b3);background:none;border:none;cursor:pointer;transition:color .15s,background .15s,transform .12s ease;font:inherit;}' +
      '#appRail button.ri:hover{color:var(--ink-2,#4b5563);background:var(--desk,#f5f6f8);transform:scale(1.07);}' +
      '#appRail button.ri:active{transform:scale(.94);}' +
      '#appRail .rbot{margin-top:auto;display:flex;flex-direction:column;align-items:center;gap:10px;}' +
      '#appRail .ravatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;' +
      'font-size:13px;font-weight:600;color:#fff;text-decoration:none;box-shadow:0 4px 10px -4px rgba(16,24,40,.4);}' +
      'body.has-rail{padding-left:72px;}' +
      '@media(max-width:720px){#appRail{display:none;}body.has-rail{padding-left:0;}}';

    var navHtml = NAV.map(function (n) {
      return '<a class="ri' + (n.active ? ' on' : '') + '" href="' + n.href + '">' + svg(n.icon) +
        '<span class="tip">' + esc(n.label) + '</span></a>';
    }).join('');

    var color = /^#[0-9a-fA-F]{6}$/.test(adv.color || '') ? adv.color : '#4f46e5';
    var themeIcon = effectiveTheme() === 'dark' ? ICONS.sun : ICONS.moon;

    var html =
      '<div id="appRail">' +
        '<a class="rlogo" href="/uvod.php" title="Formuláre">' + svg(ICONS.logo) + '</a>' +
        '<nav>' + navHtml + '</nav>' +
        '<div class="rbot">' +
          '<button type="button" class="ri" id="themeToggle" title="Prepnúť tmavý/svetlý režim">' + svg(themeIcon) +
          '<span class="tip">Tmavý/svetlý režim</span></button>' +
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

    document.getElementById('themeToggle').addEventListener('click', function () {
      setTheme(effectiveTheme() === 'dark' ? 'light' : 'dark');
    });
  }

  function boot() {
    fetch('/api/whoami.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (adv) {
        if (!(adv && adv.id)) return;
        var path = location.pathname;
        var isSpecialPage = /moje-dokumenty|admin\.php|nabor\.php|znalostna-baza|novinky\.php|uvod\.php/.test(path);
        var isGroupOverview = /\/(nastroje|formulare|pomocky)\.php/.test(path);
        // Na stránke konkrétneho nástroja (nie prehľad, nie iná sekcia)
        // potrebujeme mapu slug->skupina, aby sa zvýraznila správna záložka.
        if (isSpecialPage || isGroupOverview) { render(adv, null); return; }
        fetch('/api/tool-groups.php', { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : {}; })
          .then(function (map) { render(adv, map || {}); })
          .catch(function () { render(adv, {}); });
      })
      .catch(function () { /* bez lišty — appka funguje normálne */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
