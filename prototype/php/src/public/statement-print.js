// The statement page's Print button: opens the browser's print dialog on
// the page in front of it, rather than a submit that would need a server
// round trip for output the browser already has. The button still reads as
// a button with this file blocked or absent; it simply does nothing.
document.querySelectorAll('[data-print]').forEach(function (button) {
    button.addEventListener('click', function () {
        window.print();
    });
});
