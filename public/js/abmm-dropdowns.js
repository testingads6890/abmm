/* Dropdowns, menus and dialogs: close on Escape and on outside click.
   Covers the topbar menus (.dd), the public mobile menu (#mnav) and the modal dialogs (.modal). */
(function () {
  function closeMenus() {
    document.querySelectorAll('.dd.open').forEach(function (d) { d.classList.remove('open'); });
    var mnav = document.getElementById('mnav');
    if (mnav) mnav.classList.add('hidden');
  }

  // Escape closes whatever is open: menus, dialogs and multi-select lists.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeMenus();
    document.querySelectorAll('.modal.open').forEach(function (m) { m.classList.remove('open'); });
    document.querySelectorAll('details.ms[open]').forEach(function (d) { d.open = false; });
  });

  // A click outside an open menu closes it. Clicks inside a menu, or on its toggle, are left alone.
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (!t.closest('.dd')) document.querySelectorAll('.dd.open').forEach(function (d) { d.classList.remove('open'); });
    var mnav = document.getElementById('mnav');
    if (mnav && !mnav.classList.contains('hidden') && !t.closest('#mnav') && !t.closest('[aria-label="Menu"]')) mnav.classList.add('hidden');
  });

  // Choosing a link inside the public mobile menu closes it.
  var mnav = document.getElementById('mnav');
  if (mnav) mnav.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('a')) mnav.classList.add('hidden');
  });
})();
