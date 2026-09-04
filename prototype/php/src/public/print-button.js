// A Print button on the statement or a shipping label: opens the browser's
// print dialog on the page already in front of it, the output the browser
// already has. The page carries a `<noscript>` fallback beside each
// button, since window.print() needs JavaScript and no markup-only
// substitute opens the print dialog.
document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () {
        window.print();
    });
});
