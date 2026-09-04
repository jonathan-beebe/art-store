// Opens the off-canvas nav drawer (the seller and admin layouts each carry
// one, marked `data-nav-drawer`) from the header's hamburger button.
// Escape closes it natively; its own button and the flex-1 filler button
// that spans the backdrop area both carry `data-drawer-close`, so this one
// wiring below closes it from either. The hamburger button renders
// regardless, so a browser with this file blocked or absent shows it with
// no drawer behind it.
(function () {
    var drawer = document.querySelector('[data-nav-drawer]');
    if (drawer === null) return;

    document.querySelectorAll('[data-drawer-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            drawer.showModal();
        });
    });

    drawer.querySelectorAll('[data-drawer-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            drawer.close();
        });
    });
})();
