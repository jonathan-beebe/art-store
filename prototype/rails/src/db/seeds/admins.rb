# The platform operators. Admin rows are never created by signing in, so this
# list is the whole of who reaches /admin. Jonathan is first, so Admin.on_duty
# answers the support desk.
module Seeds
  module Admins
    module_function

    VERIFIED_AT = Time.utc(2026, 6, 1)

    RECORDS = [
      { email: "jonathan-beebe@outlook.com", name: "Jonathan Beebe" },
      { email: "annaschmunk@pm.me", name: "Anna Schmunk" }
    ].freeze

    def create_all
      RECORDS.each { |attrs| Admin.create!(attrs.merge(email_verified_at: VERIFIED_AT)) }
    end
  end
end
