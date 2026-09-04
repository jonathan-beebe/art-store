// Makes the listings detail dialog a real modal at 2xl and up: focus
// moves to its Close link on open, Escape and a backdrop click close
// it, and the workspace behind it becomes inert automatically — none of
// that needs the manual `inert` the markup also carries, which is what
// keeps the workspace out of the tab order with this file blocked or
// absent too. Below 2xl the dialog stays closed; its own CSS keeps it
// visible without JS regardless, same as before this file existed.
(function () {
    var dialog = document.querySelector('[data-listing-detail-dialog]');
    if (dialog === null) return;

    var isWideViewport = window.matchMedia('(min-width: 1536px)');

    var sync = function () {
        if (isWideViewport.matches && !dialog.open) {
            dialog.showModal();
        } else if (!isWideViewport.matches && dialog.open) {
            dialog.close();
        }
    };

    if (dialog.hasAttribute('open')) {
        dialog.close();
    }

    sync();
    isWideViewport.addEventListener('change', sync);

    dialog.querySelectorAll('[data-dialog-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            dialog.close();
        });
    });
})();
