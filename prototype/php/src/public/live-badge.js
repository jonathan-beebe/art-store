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

            // Left to the browser, an abandoned stream's connection is
            // released lazily — held slots from left pages queue the next
            // navigation behind them, and each open stream parks one of the
            // dev server's workers for the stream's full lifetime. pagehide
            // also fires entering bfcache, so a restored page's badge goes
            // static until its next full load.
            window.addEventListener('pagehide', function () {
                source.close();
            });

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
