/** A snake_case status column as a page reads it: `for_sale` → `For sale`. */
export function statusLabel(status: string): string {
  const words = status.replace(/_/g, ' ')

  return words.charAt(0).toUpperCase() + words.slice(1)
}

/** A status a button switches something to, and the verb phrase it shows. */
export type StatusButton = { status: string; label: string }

/** The buttons a page draws for a set of legal status transitions. */
export function statusButtons(transitions: readonly string[]): readonly StatusButton[] {
  return transitions.map((status) => ({ status, label: `Mark ${statusLabel(status).toLowerCase()}` }))
}
