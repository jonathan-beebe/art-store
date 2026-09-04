// Opens the "New listing" dialog from the Listings page's own button and
// closes it from its Cancel button, native Escape, or a click on the
// backdrop area. The dialog and its plain-GET form render regardless, so a
// browser with this file blocked or absent keeps today's direct link to
// seller.listings.create for a seller who reaches it another way.
(function () {
    var dialog = document.getElementById('new-listing-dialog');
    if (dialog === null) return;

    document.querySelectorAll('[data-new-listing-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            dialog.showModal();
        });
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
