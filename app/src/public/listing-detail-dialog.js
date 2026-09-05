// Makes the listings detail dialog a real modal at 2xl and up: focus
// moves to its Close link on open, and the workspace behind it becomes
// inert automatically — none of that needs the manual `inert` the markup
// also carries, which is what keeps the workspace out of the tab order
// with this file blocked or absent too. Escape, the Close link, and a
// click on the backdrop all fire the dialog's native `close` event; that
// event navigates to `data-close-href` (the same place the takeover's own
// back link points), so closing the modal leaves the page rather than
// stranding its empty box over an inert one. Below 2xl the dialog stays
// closed and its own CSS keeps it visible without JS regardless, same as
// before this file existed — closing it for a viewport change alone is
// suppressed, since that swap uses the same page, not a navigation.
(function () {
    var dialog = document.querySelector('[data-listing-detail-dialog]');
    if (dialog === null) return;

    var closeHref = dialog.getAttribute('data-close-href');
    var isWideViewport = window.matchMedia('(min-width: 1536px)');
    var suppressNextClose = false;

    var closeWithoutNavigating = function () {
        suppressNextClose = true;
        dialog.close();
    };

    var sync = function () {
        if (isWideViewport.matches && !dialog.open) {
            dialog.showModal();
        } else if (!isWideViewport.matches && dialog.open) {
            closeWithoutNavigating();
        }
    };

    if (dialog.hasAttribute('open')) {
        closeWithoutNavigating();
    }

    sync();
    isWideViewport.addEventListener('change', sync);

    dialog.addEventListener('close', function () {
        if (suppressNextClose) {
            suppressNextClose = false;
            return;
        }

        if (closeHref) window.location.assign(closeHref);
    });

    dialog.querySelectorAll('[data-dialog-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            dialog.close();
        });
    });

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) dialog.close();
    });
})();
