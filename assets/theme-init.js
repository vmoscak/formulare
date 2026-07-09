/**
 * Aplikuje uloženú voľbu témy (tmavý/svetlý režim) HNEĎ, pred vykreslením
 * stránky — aby nebola vidieť krátka záblesk nesprávnej témy. Musí byť
 * načítaný v <head>, pred CSS. Prepínač je v assets/shell.js (ľavá lišta).
 */
(function () {
  try {
    var t = localStorage.getItem('theme');
    if (t === 'dark' || t === 'light') {
      document.documentElement.setAttribute('data-theme', t);
    }
  } catch (e) { /* localStorage nedostupný (súkromné okno a pod.) — použije sa systémová téma */ }
})();
