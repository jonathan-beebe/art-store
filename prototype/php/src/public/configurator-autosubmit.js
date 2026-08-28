// Refreshes the configurator as soon as a shopper changes an axis, unit, or
// quantity control, instead of waiting on the "Update options" button. The
// listing page renders that button and the plain form regardless, so a
// browser with this file blocked or absent keeps today's click-to-refresh
// flow.
(function () {
    document.querySelectorAll('[data-configurator]').forEach(function (form) {
        var updateButton = form.querySelector('[data-configurator-update]');
        var focusField = form.querySelector('[data-configurator-focus]');

        if (updateButton === null || focusField === null) {
            return;
        }

        updateButton.hidden = true;

        form.addEventListener('change', function (event) {
            var control = event.target;

            if (!(control instanceof Element) || !control.matches('[data-configurator-refresh]')) {
                return;
            }

            focusField.value = control.id;

            // formmethod="GET" on this button, never the form's own POST to cart.
            form.requestSubmit(updateButton);
        });
    });
})();
