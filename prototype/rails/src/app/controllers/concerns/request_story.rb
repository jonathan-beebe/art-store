# What every request tells the log: one id for the request, one for the
# browser behind it, who is acting, and a line on the way in and on the way
# out.
module RequestStory
  extend ActiveSupport::Concern

  HEADER = "X-Request-Id".freeze
  # The shape an id from outside is trusted in. Anything else is replaced, so
  # a header nobody controls cannot decide what a log line is keyed by.
  TRUSTED_ID = /\A[A-Za-z0-9_-]{1,64}\z/
  SESSION_COOKIE = :sid
  # One visit outlives a sign-in, so the browser keeps its id for a year.
  SESSION_LIFETIME = 1.year
  # A path segment can be a secret: a sign-in token is one. The parameters
  # Rails already filters say which, so the segment holding one is masked
  # rather than logged.
  MASK = ActiveSupport::ParameterFilter::FILTERED
  SECRETS = ActiveSupport::ParameterFilter.new(Rails.application.config.filter_parameters)

  included do
    around_action :tell_the_request_story
  end

  private

  # Who the lines of this request name. Each site answers with the identity
  # behind its own pages.
  def logged_actor
    nil
  end

  def tell_the_request_story
    Current.request_id = request_id
    Current.session_id = session_id
    Current.acting_as(logged_actor)
    # The middleware above the action echoes the id it finds on the request,
    # which is how the answer carries the same id the lines do.
    request.request_id = Current.request_id

    story = Story.start(
      "http.request", "#{request.request_method} #{logged_path}",
      method: request.request_method, path: logged_path
    )

    tell_the_response(story) { yield }
  end

  # The status the visitor is answered with, whether the action wrote it or an
  # exception on its way up the stack decided it.
  def tell_the_response(story)
    yield
    story.did(response_message(response.status), status: response.status)
  rescue StandardError => failure
    status = ActionDispatch::ExceptionWrapper.status_code_for_exception(failure.class.name)
    story.did(response_message(status), status: status)
    raise
  ensure
    story.close
  end

  def response_message(status)
    "#{request.request_method} #{logged_path} #{status}"
  end

  def logged_path
    @logged_path ||= SECRETS.filter(request.path_parameters).reduce(request.path) do |path, (name, value)|
      value == MASK ? path.sub(request.path_parameters[name].to_s, MASK) : path
    end
  end

  def request_id
    offered = request.headers[HEADER].to_s

    TRUSTED_ID.match?(offered) ? offered : PrefixedUlid.generate(:req)
  end

  # The id of the browser, minted on the first response it is handed and kept
  # for a year. Signing in and out leaves it where it is, so one visit reads
  # as one visit.
  def session_id
    known = PrefixedUlid.parse(cookies[SESSION_COOKIE], :ses)
    return known if known

    minted = PrefixedUlid.generate(:ses)
    cookies[SESSION_COOKIE] = { value: minted, expires: SESSION_LIFETIME.from_now, httponly: true }

    minted
  end
end
