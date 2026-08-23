/**
 * What verifying an address does to the customer rows already in play: the
 * anonymous row the cookie points at, and the row that already holds the
 * address. Each action carries exactly the ids it needs, so the caller reads
 * them without asking whether they are there.
 */
export type IdentityPlan =
  | { action: 'createVerified' }
  | { action: 'signInExisting'; verifiedCustomerId: number }
  | { action: 'claimAnonymous'; anonymousCustomerId: number }
  | { action: 'mergeAnonymousInto'; anonymousCustomerId: number; verifiedCustomerId: number }

export type IdentityAction = IdentityPlan['action']

export type IdentityPlanInput = {
  anonymousCustomerId: number | null
  ownerOfEmailId: number | null
}

export function planCustomerIdentity({
  anonymousCustomerId,
  ownerOfEmailId,
}: IdentityPlanInput): IdentityPlan {
  if (ownerOfEmailId === null) {
    return anonymousCustomerId === null
      ? { action: 'createVerified' }
      : { action: 'claimAnonymous', anonymousCustomerId }
  }

  // A cookie already pointing at the owning account has nothing to fold in.
  if (anonymousCustomerId === null || anonymousCustomerId === ownerOfEmailId) {
    return { action: 'signInExisting', verifiedCustomerId: ownerOfEmailId }
  }

  return { action: 'mergeAnonymousInto', anonymousCustomerId, verifiedCustomerId: ownerOfEmailId }
}
