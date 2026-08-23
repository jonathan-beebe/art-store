/** Order and fulfillment states are stored snake_case; a page reads one back as
 * a sentence. */
export function statusLabel(status: string): string {
  const words = status.replace(/_/g, ' ')

  return words.charAt(0).toUpperCase() + words.slice(1)
}
