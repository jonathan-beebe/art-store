/**
 * A storefront visitor holds a customer row before anyone gives an address, so
 * "has an email" is what separates an account from a browsing history.
 */
export function isVerifiedCustomer(customer: { email: string | null }): boolean {
  return customer.email !== null
}

export function isAnonymousCustomer(customer: { email: string | null }): boolean {
  return customer.email === null
}
