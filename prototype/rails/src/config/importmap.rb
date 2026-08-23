# The two modules the browser loads, both served from the application's own
# assets: the entrypoint and Turbo. No CDN and no Node build step.
pin "application"
pin "@hotwired/turbo-rails", to: "turbo.min.js"
