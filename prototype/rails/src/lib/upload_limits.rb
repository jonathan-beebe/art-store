# Read by config/boot.rb, ahead of Bundler and Zeitwerk, so the multipart
# transport limit it sets there and Listing::MAX_IMAGE_UPLOAD_BYTES come
# from the same number. Plain arithmetic rather than ActiveSupport's
# Integer#megabytes: boot.rb runs before Bundler.require loads ActiveSupport.
module UploadLimits
  MAX_IMAGE_BYTES = 5 * 1024 * 1024
end
