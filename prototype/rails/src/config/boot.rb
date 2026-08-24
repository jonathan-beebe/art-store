ENV["BUNDLE_GEMFILE"] ||= File.expand_path("../Gemfile", __dir__)

# Loaded here, ahead of Bundler, so its constant is available to size the
# Rack setting below before Bundler.require loads Rack.
require_relative "../lib/upload_limits"

# Rack::Multipart::Parser reads this environment variable exactly once, the
# first time Bundler.require loads it, and freezes the result into a private
# constant — a value set from an initializer would be too late, since Rack
# is already loaded by the time initializers run. Unset, Rack defaults to a
# 10 GiB multipart body, so a seller with a session could otherwise park
# gigabytes on disk per request before Listing's own size check ever runs.
# The limit is the image cap plus 1 MiB of headroom for the multipart
# envelope: boundaries, headers, and the form's other fields (title,
# description, medium, dimensions, price, quantity), none of which
# individually exceeds a few KB.
ENV["RACK_MULTIPART_PARSER_BYTESIZE_LIMIT"] = (UploadLimits::MAX_IMAGE_BYTES + 1 * 1024 * 1024).to_s

require "bundler/setup" # Set up gems listed in the Gemfile.
require "bootsnap/setup" # Speed up boot time by caching expensive operations.
