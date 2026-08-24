# Be sure to restart your server when you modify this file.

# The policy every response carries, the same set `prototype/node` sends
# (`app/plugins/security-headers.ts`): nothing loads from anywhere but this
# origin, `data:` is in `img-src` because a listing with no photograph
# renders a generated SVG placeholder inline (`PlaceholderImage`), and
# nothing on any page opens a frame or targets another origin's form.
Rails.application.configure do
  config.content_security_policy do |policy|
    policy.default_src :self
    policy.img_src :self, :data
    policy.style_src :self
    policy.script_src :self
    policy.form_action :self
    policy.frame_ancestors :none
  end

  # Importmap's own inline `<script type="importmap">` and module-preload
  # tags carry this nonce automatically (`importmap-rails` reads
  # `request.content_security_policy_nonce`); nothing else on any page is an
  # inline script, so `script-src` is the only directive that needs one.
  # Rails' own template suggests the session id as the nonce, but most of
  # this app's requests — an anonymous storefront visit, in particular —
  # never write anything into the session, which leaves `session.id` blank;
  # a random value per request is not reused by anything that would need it
  # to be (no fragment caching keys on it) and is never blank.
  config.content_security_policy_nonce_generator = ->(_request) { SecureRandom.base64(16) }
  config.content_security_policy_nonce_directives = %w[script-src]
end
