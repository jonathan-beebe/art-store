# One unit of work told in lines: `will` when it starts, then `did`,
# `refused`, or `failed` when it ends. Everything told from inside a story
# carries the story's `txn_id`, so one action reads as one run of lines.
class Story
  # A refusal the domain expects. The world is unchanged, so the line is
  # `refused` at info rather than `failed` at error.
  REFUSALS = [ TransitionError, ActiveRecord::RecordInvalid, ActiveRecord::RecordNotSaved ].freeze

  # The level an ending is told at, whatever level the story itself runs at.
  LEVELS = { refused: :info, failed: :error }.freeze

  # One line: how far along the story is, the sentence it reads as, the small
  # facts it carries, and the exception behind a failure.
  Line = Data.define(:phase, :message, :data, :error)

  # Tells the whole story around a block: the `will` line now and the ending
  # when the block returns or raises. The block does its writing in a
  # transaction of its own; this story's id is what joins those lines.
  def self.tell(event, message, level: :info, **data)
    outer_txn_id = Current.txn_id
    Current.txn_id ||= PrefixedUlid.generate(:txn)
    story = start(event, message, level: level, **data)

    yield story
  rescue *REFUSALS => refusal
    story.refused(refusal.message)
    raise
  rescue StandardError => failure
    story.failed(failure)
    raise
  ensure
    story.close
    Current.txn_id = outer_txn_id
  end

  # The `will` line on its own, for a caller that learns how the story ended
  # only after the stack around it has answered — a request whose status is
  # written by the middleware above the action.
  def self.start(event, message, level: :info, **data)
    new(event, message, level).tap { |story| story.will(data) }
  end

  def initialize(event, message, level)
    @event = event
    @level = level
    @started_at = Process.clock_gettime(Process::CLOCK_MONOTONIC)
    @message = message
    @ending = Line.new(phase: :did, message: message, data: {}, error: nil)
  end

  # What is about to happen.
  def will(data)
    write(Line.new(phase: :will, message: @message, data: data, error: nil))
  end

  # A step inside the unit of work, written as it happens rather than held
  # until the end: what a run over many rows says about each one.
  def doing(message, **data)
    write(Line.new(phase: :doing, message: message, data: data, error: nil))
  end

  # What happened. Held until the story closes, so the line lands after the
  # writing it describes has committed.
  def did(message, **data)
    @ending = Line.new(phase: :did, message: message, data: data, error: nil)
  end

  # Why the domain would not do it. The world is unchanged.
  def refused(message, **data)
    @ending = Line.new(phase: :refused, message: message, data: data, error: nil)
  end

  # What went wrong that nobody planned for.
  def failed(exception)
    @ending = Line.new(phase: :failed, message: exception.message, data: {}, error: exception)
  end

  # The ending line, with the time the story took.
  def close
    write(@ending, duration_ms: elapsed_ms)
  end

  private

  def write(line, duration_ms: nil)
    Rails.logger.public_send(
      LEVELS.fetch(line.phase, @level),
      {
        event: @event, phase: line.phase, msg: line.message, duration_ms: duration_ms,
        data: line.data.compact.presence, error: error_of(line)
      }.compact
    )
  end

  # A stack is worth its width only where someone is reading the log beside
  # the code it points into.
  def error_of(line)
    return nil if line.error.nil?

    {
      type: line.error.class.name,
      message: line.error.message,
      stack: (line.error.backtrace&.join("\n") if Rails.env.development?)
    }.compact
  end

  def elapsed_ms
    ((Process.clock_gettime(Process::CLOCK_MONOTONIC) - @started_at) * 1_000).round
  end
end
