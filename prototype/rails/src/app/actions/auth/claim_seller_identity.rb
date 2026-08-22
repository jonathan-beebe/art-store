module Auth
  # A verified link is the whole of the seller sign-up flow: the first one for
  # an address creates the account.
  class ClaimSellerIdentity
    def call(email:, now: Time.current)
      seller = Seller.find_or_initialize_by(email: email)
      seller.email_verified_at ||= now
      seller.save!

      seller
    end
  end
end
