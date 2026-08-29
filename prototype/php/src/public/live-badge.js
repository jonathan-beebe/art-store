// The live unread-message badge. Every page renders the count itself on the
// server, so a browser with this file blocked or absent still shows a
// correct number as of the last page load — this only keeps it current
// while the page sits open.
(function () {
    if (typeof EventSource === 'undefined') {
        return;
    }

    // Connect only after the load event: a stream opened while the page is
    // still parsing holds the tab in "loading" (spinner, never idle) for as
    // long as the connection stays open.
    function connect() {
        document.querySelectorAll('[data-events-url]').forEach(function (link) {
            var label = link.dataset.liveBadge;
            var source = new EventSource(link.dataset.eventsUrl);

            source.addEventListener('unread', function (event) {
                var count = Number(event.data);
                link.textContent = count > 0 ? label + ' (' + count + ')' : label;
            });
        });
    }

    if (document.readyState === 'complete') {
        connect();
    } else {
        window.addEventListener('load', connect);
    }
})();
