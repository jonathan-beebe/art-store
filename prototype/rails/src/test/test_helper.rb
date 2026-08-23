require "simplecov"

# Started before the application is loaded so every app/ and lib/ file is
# measured.
SimpleCov.start do
  skip "/config/"
  skip "/db/"
  skip "/test/"
  skip "/vendor/"

  cover "{app,lib}/**/*.rb"

  group "Domain", "app/domain"
  group "Controllers", "app/controllers"
  group "Models", "app/models"
  group "Mailers", "app/mailers"

  minimum_coverage line: Integer(ENV["COVERAGE_MIN"]) if ENV["COVERAGE_MIN"]
end

# There is no browser in the container to open coverage/index.html, so the
# per-group numbers are printed as well.
SimpleCov.at_exit do
  SimpleCov.result.format!

  SimpleCov.result.groups.each do |name, files|
    next if files.empty?

    puts format("%-12s %6.2f%%  %d files", name, files.covered_percent, files.count)
  end
end

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
  end
end

class ActionDispatch::IntegrationTest
  include IntegrationHelpers
end
