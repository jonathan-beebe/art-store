import type { CustomerId } from '../ids/entity-ids.ts'

/**
 * Who is buying, as much of them as checkout needs. A guest arrives with no
 * verified address and pays after verifying one.
 */
export type Purchaser = {
  id: CustomerId
  email: string | null
  isEmailVerified: boolean
}
