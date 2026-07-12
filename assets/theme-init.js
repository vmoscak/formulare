/**
 * Aplikuje uloženú voľbu témy (tmavý/svetlý režim) HNEĎ, pred vykreslením
 * stránky — aby nebola vidieť krátka záblesk nesprávnej témy. Musí byť
 * načítaný v <head>, pred CSS. Prepínač je v assets/shell.js (ľavá lišta).
 *
 * Zároveň pridá triedu "preload" na <html> (skrýva <body> cez CSS v
 * ui.css/panel.css) a po vykreslení ju o snímku neskôr odstráni — jemný
 * fade-in namiesto "výskoku" obsahu pri každom prekliknutí medzi stránkami.
 */
(function () {
  try {
    var t = localStorage.getItem('theme');
    if (t === 'dark' || t === 'light') {
      document.documentElement.setAttribute('data-theme', t);
    }
  } catch (e) { /* localStorage nedostupný (súkromné okno a pod.) — použije sa systémová téma */ }

  document.documentElement.classList.add('preload');
  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      document.documentElement.classList.remove('preload');
    });
  });
})();
