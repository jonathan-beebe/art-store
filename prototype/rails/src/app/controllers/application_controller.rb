class ApplicationController < ActionController::Base
  include RequestStory

  allow_browser versions: :modern
end
