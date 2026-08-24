class ApplicationController < ActionController::Base
  include RequestStory
  include RateLimiting
  include PageViewRollup

  allow_browser versions: :modern
end
