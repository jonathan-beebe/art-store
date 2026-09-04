// Opens the off-canvas nav drawer (the seller and admin layouts each carry
// one, marked `data-nav-drawer`) from the header's hamburger button and
// closes it from its own button, native Escape, or a click on the
// backdrop area (native <dialog> behavior — no JS needed for that one).
// The hamburger button renders regardless, so a browser with this file
// blocked or absent shows it with no drawer behind it.
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
