/**
 * How the customer on the other side of a thread reads. A storefront visitor
 * has neither a name nor an address until they verify one, so an unnamed
 * customer is named by the row they already are.
 */
export function customerName(customer: {
  id: number
  name: string | null
  email: string | null
}): string {
  const named = (customer.name ?? '').trim()
  if (named.length > 0) return named

  const address = (customer.email ?? '').trim()

  return address.length > 0 ? address : `Guest #${customer.id}`
}
