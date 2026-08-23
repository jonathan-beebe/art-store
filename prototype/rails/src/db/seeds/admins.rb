# The platform operator. Admin rows are never created by signing in, so this
# list is the whole of who reaches /admin.
module Seeds
  module Admins
    module_function

    VERIFIED_AT = Time.utc(2026, 6, 1)

    RECORDS = [
      { email: "ops@example.com", name: "Ops" }
    ].freeze

    def create_all
      RECORDS.each { |attrs| Admin.create!(attrs.merge(email_verified_at: VERIFIED_AT)) }
    end
  end
end
