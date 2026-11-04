/* Multi-select dropdowns (details.ms): one open at a time, close on outside click or Escape. */
(function () {
  function openOnes() { return document.querySelectorAll('details.ms[open]'); }
  // Opening one dropdown closes the others.
  document.addEventListener('toggle', function (e) {
    if (e.target.matches && e.target.matches('details.ms') && e.target.open) {
      openOnes().forEach(function (d) { if (d !== e.target) d.open = false; });
    }
  }, true);
  // Click anywhere outside the open dropdown closes it.
  document.addEventListener('click', function (e) {
    openOnes().forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
  });
  // Escape closes it.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') openOnes().forEach(function (d) { d.open = false; });
  });
})();
