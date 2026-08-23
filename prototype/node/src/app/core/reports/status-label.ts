const LEADING_LETTER = /^[a-z]/

/** A snake_case status column as a table cell reads it: `for_sale` → `For sale`. */
export function statusLabel(status: string): string {
  return status.replace(/_/g, ' ').replace(LEADING_LETTER, (letter) => letter.toUpperCase())
}
