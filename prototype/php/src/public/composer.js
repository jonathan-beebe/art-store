// The composer's three progressive touches, shared by every site that has
// one: a live counter, Cmd/Ctrl+Enter to send, and nothing else. Growth is
// CSS (`field-sizing: content` on the textarea). Every form still posts
// with this file absent: the counter is server-rendered first, and Enter
// alone stays a newline.
//
// Contract: the textarea carries `data-composer` and its `maxlength`; the
// counter element, if any, carries `data-composer-count` inside the same
// form.
(() => {
    document.querySelectorAll('textarea[data-composer]').forEach((area) => {
        const form = area.form;
        const counter = form ? form.querySelector('[data-composer-count]') : null;
        const max = Number(area.getAttribute('maxlength')) || 0;

        const update = () => {
            if (counter) counter.textContent = `${area.value.length.toLocaleString()} / ${max.toLocaleString()}`;
        };

        area.addEventListener('input', update);
        update();

        area.addEventListener('keydown', (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter' && form) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
    });
})();
