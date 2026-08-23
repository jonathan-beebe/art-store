/** A snake_case status column as a page reads it: `for_sale` → `For sale`. */
export function statusLabel(status: string): string {
  const words = status.replace(/_/g, ' ')

  return words.charAt(0).toUpperCase() + words.slice(1)
}
