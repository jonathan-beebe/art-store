# Starts SimpleCov before the application boots, so `app/models/current.rb`
# and `lib/json_log_formatter.rb` — both loaded while `config.logger` is
# built — are measured like every other file.
#
# `bin/rails test` (the whole suite) runs `test:prepare`, which
# `tailwindcss-rails` enhances to depend on `:environment`; that boots the
# app before Rake even requires `test_helper.rb`. The Makefile's `test`,
# `smoke`, and `coverage` targets set `RUBYOPT=-r./test/coverage_boot` on
# that invocation, so this file is required by the Ruby process itself,
# ahead of `bin/rails`.
#
# `bin/rails test test/some_file_test.rb` (a single file) skips
# `test:prepare` and boots the app from inside `test_helper.rb`, after
# `test_helper.rb`'s own `require_relative "coverage_boot"` below. Either
# way, `require` only loads this file once — whichever entry point gets here
# first is the one that runs, and the other's require is a no-op.
#
# Required this early, `config/boot.rb` has not put the bundle's gems on the
# load path yet, so `simplecov` sets up the bundle itself before requiring
# `simplecov` — the same thing `bundler/setup` does when `config/boot.rb`
# runs a moment later, and safe to call twice.
ENV["BUNDLE_GEMFILE"] ||= File.expand_path("../Gemfile", __dir__)
require "bundler/setup"
require "simplecov"

SimpleCov.start do
  skip "/config/"
  skip "/db/"
  skip "/test/"
  skip "/vendor/"

  cover "{app,lib}/**/*.rb"

  group "Models", "app/models"
  group "Controllers", "app/controllers"
  group "Helpers", "app/helpers"
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
