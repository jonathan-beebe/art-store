// The unread badge arrives with the page; this keeps it current until the next
// one. Every page works with this file absent, blocked, or unsupported.
;(() => {
  if (typeof EventSource === 'undefined') return

  const badge = document.querySelector('[data-messages-badge]')
  const link = badge === null ? null : badge.closest('a[href$="/messages"]')
  if (link === null) return

  // Each site serves its own stream beside its own inbox: /events,
  // /seller/events, /admin/events.
  const source = new EventSource(link.getAttribute('href').replace(/messages$/, 'events'))

  source.addEventListener('unread', (event) => {
    const count = Number(event.data)

    badge.textContent = count > 0 ? String(count) : ''
    if (count > 0) badge.setAttribute('data-unread-messages', String(count))
    else badge.removeAttribute('data-unread-messages')
  })
})()
