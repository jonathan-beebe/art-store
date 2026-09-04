// A Print button on the statement or a shipping label: opens the browser's
// print dialog on the page in front of it, rather than a submit that would
// need a server round trip for output the browser already has. The page
// carries a `<noscript>` fallback beside each button, since window.print()
// needs JavaScript and no markup-only substitute opens the print dialog.
document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () {
        window.print();
    });
});
