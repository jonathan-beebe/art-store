class ApplicationController < ActionController::Base
  include RequestStory
  include RateLimiting

  allow_browser versions: :modern
end
