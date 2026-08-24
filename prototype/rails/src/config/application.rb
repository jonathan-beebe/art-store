require_relative "boot"

require "rails"
# Pick the frameworks you want:
require "active_model/railtie"
require "active_job/railtie"
require "active_record/railtie"
require "active_storage/engine"
require "action_controller/railtie"
require "action_mailer/railtie"
# require "action_mailbox/engine"
# require "action_text/engine"
require "action_view/railtie"
require "action_cable/engine"
require "rails/test_unit/railtie"

# Require the gems listed in Gemfile, including any gems
# you've limited to :test, :development, or :production.
Bundler.require(*Rails.groups)

# Set before the class body so the logger can be built with it: the formatter
# has to exist before the app boots, which is earlier than autoloading.
require_relative "../lib/json_log_formatter"

module ArtStore
  class Application < Rails::Application
    # Initialize configuration defaults for originally generated Rails version.
    config.load_defaults 8.1

    # Please, add to the `ignore` list any other `lib` subdirectories that do
    # not contain `.rb` files, or that should not be reloaded or eager loaded.
    # Common ones are `templates`, `generators`, or `middleware`, for example.
    # The formatter is required by hand above, so Zeitwerk leaves it alone.
    config.autoload_lib(ignore: %w[assets tasks json_log_formatter.rb])

    # One JSON object per line on stdout, in every environment, so the log
    # reads the same wherever it is being read.
    config.logger = ActiveSupport::Logger.new($stdout, formatter: JsonLogFormatter.new)

    # The request lines are the ones every action tells for itself, so the
    # middleware that writes a prose line of its own comes out of the stack.
    config.middleware.delete Rails::Rack::Logger

    # Form fields render their own error text through
    # seller/shared/_field_error, so an invalid field keeps the markup the
    # valid one has rather than gaining Rails' wrapper div.
    config.action_view.field_error_proc = proc { |html_tag, _instance| html_tag }

    # Configuration for the application, engines, and railties goes here.
    #
    # These settings can be overridden in specific environments using the files
    # in config/environments, which are processed later.
    #
    # config.time_zone = "Central Time (US & Canada)"
    # config.eager_load_paths << Rails.root.join("extras")
  end
end
