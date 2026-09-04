// Resubmits the listings sort form as soon as the seller picks a
// different column, instead of waiting on the Sort button. The form and
// its button render regardless, so a browser with this file blocked or
// absent keeps today's click-to-submit flow.
(function () {
    document.querySelectorAll('[data-sort-form]').forEach(function (form) {
        var submitButton = form.querySelector('[data-sort-submit]');
        var select = form.querySelector('[data-sort-select]');

        if (submitButton === null || select === null) {
            return;
        }

        submitButton.hidden = true;

        select.addEventListener('change', function () {
            form.requestSubmit(submitButton);
        });
    });
})();
