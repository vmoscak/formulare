/* ============================================================================
   FORMULÁRE — zdieľané toast notifikácie
   ----------------------------------------------------------------------------
   Nahrádza natívny alert() vlastnou, štýlovanou notifikáciou (zošmyknutie
   sprava hore, farebne odlíšená podľa typu, automaticky zmizne). Self-contained
   ako shell.js — vlastné CSS aj DOM sa vložia pri prvom volaní, žiadna závislosť
   na ui.css/panel.css, takže funguje na oboch dizajnových systémoch appky.

   POUŽITIE:  showToast('Text správy');
              showToast('Text správy', 'error');   // 'error' | 'success' | 'info' (predvolené)
========================================================================== */
(function () {
  'use strict';

  var container = null;

  function ensureContainer() {
    if (container) return container;
    var css = document.createElement('style');
    css.textContent =
      '#toastStack{position:fixed;top:18px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:10px;' +
      'max-width:min(360px,calc(100vw - 36px));font-family:\'Inter\',-apple-system,\'Segoe UI\',Roboto,sans-serif;}' +
      '.toast-item{display:flex;align-items:flex-start;gap:10px;background:var(--paper,#fff);color:var(--ink,#111827);' +
      'border:1px solid var(--border,#eef0f3);border-left:4px solid #4f46e5;border-radius:10px;padding:12px 14px;' +
      'box-shadow:0 14px 32px -10px rgba(0,0,0,.28);font-size:13px;line-height:1.5;white-space:pre-line;' +
      'transform:translateX(120%);opacity:0;transition:transform .25s cubic-bezier(.22,1,.36,1),opacity .25s ease;}' +
      '.toast-item.show{transform:translateX(0);opacity:1;}' +
      '.toast-item.error{border-left-color:#e11d48;}' +
      '.toast-item.success{border-left-color:#059669;}' +
      '.toast-item.info{border-left-color:#4f46e5;}' +
      '.toast-ic{flex-shrink:0;width:20px;height:20px;margin-top:1px;}' +
      '.toast-close{flex-shrink:0;margin-left:auto;cursor:pointer;color:var(--muted,#9aa1ad);font-size:16px;line-height:1;' +
      'background:none;border:none;padding:0 0 0 8px;}' +
      '.toast-close:hover{color:var(--ink,#111827);}' +
      '@media(max-width:480px){#toastStack{left:12px;right:12px;top:12px;max-width:none;}}';
    document.head.appendChild(css);

    var stack = document.createElement('div');
    stack.id = 'toastStack';
    document.body.appendChild(stack);
    container = stack;
    return container;
  }

  var ICONS = {
    error: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
  };

  function svg(type) {
    var color = type === 'error' ? '#e11d48' : (type === 'success' ? '#059669' : '#4f46e5');
    return '<svg class="toast-ic" viewBox="0 0 24 24" fill="none" stroke="' + color + '" stroke-width="2" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[type] || ICONS.info) + '</svg>';
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  window.showToast = function (message, type, durationMs) {
    type = (type === 'error' || type === 'success') ? type : 'info';
    durationMs = durationMs || 5000;
    var stack = ensureContainer();

    var item = document.createElement('div');
    item.className = 'toast-item ' + type;
    item.innerHTML = svg(type) + '<span>' + esc(message) + '</span>' +
      '<button type="button" class="toast-close" aria-label="Zavrieť">&times;</button>';
    stack.appendChild(item);

    requestAnimationFrame(function () { item.classList.add('show'); });

    var timer = setTimeout(remove, durationMs);
    function remove() {
      clearTimeout(timer);
      item.classList.remove('show');
      setTimeout(function () { if (item.parentNode) item.parentNode.removeChild(item); }, 260);
    }
    item.querySelector('.toast-close').addEventListener('click', remove);
  };
})();
