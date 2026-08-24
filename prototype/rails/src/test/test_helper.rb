# Idempotent: `RUBYOPT` already required this ahead of `bin/rails` for a
# whole-suite run; for a path-filtered run (`bin/rails test path/to_test.rb`)
# this is coverage's first chance to start, before the `require_relative`
# below boots the application. See `coverage_boot.rb`.
require_relative "coverage_boot"

ENV["RAILS_ENV"] ||= "test"
require_relative "../config/environment"
require "rails/test_help"

Dir[Rails.root.join("test/support/**/*.rb")].each { |file| require file }

module ActiveSupport
  class TestCase
    # Serial: one SQLite file and one coverage result beat merging forked
    # workers for a suite this size.
    parallelize(workers: 1)

    # Setup all fixtures in test/fixtures/*.yml for all tests in alphabetical order.
    fixtures :all

    # Every sign-in enqueues a mailer job, so the whole suite runs on the test
    # queue adapter rather than delivering from a background thread.
    include ActiveJob::TestHelper
    include ActionMailer::TestHelper
    include TestRecords
    include LogCapture
  end
end

class ActionDispatch::IntegrationTest
  include IntegrationHelpers
end
