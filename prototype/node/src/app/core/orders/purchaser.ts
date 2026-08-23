/**
 * Who is buying, as much of them as checkout needs. A guest arrives with no
 * verified address and pays after verifying one.
 */
export type Purchaser = {
  id: number
  email: string | null
  isEmailVerified: boolean
}
