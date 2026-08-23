/**
 * How a seller reads on the storefront. A seller signs up with an address and
 * names their shop later, so an unnamed shop falls back to the part of the
 * address in front of the host.
 */
export function shopName(seller: { shopName: string | null; email: string }): string {
  const named = (seller.shopName ?? '').trim()

  return named.length === 0 ? (seller.email.split('@')[0] ?? '') : named
}
