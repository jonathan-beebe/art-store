require "json"
require "stringio"

# The lines the app wrote while a block ran, parsed back from the JSON they
# were written as. What a test asserts on is the payload a reader would see,
# not the call that produced it.
module LogCapture
  def captured_log_lines(&block)
    buffer = StringIO.new
    logger = ActiveSupport::Logger.new(buffer, formatter: JsonLogFormatter.new)
    logger.level = :debug
    written = Rails.logger

    begin
      Rails.logger = ActiveSupport::BroadcastLogger.new(logger)
      block.call
    ensure
      Rails.logger = written
    end

    buffer.string.lines.map { |line| JSON.parse(line) }
  end

  # The lines of one event, in the order they were written.
  def log_lines_for(event, lines)
    lines.select { |line| line["event"] == event }
  end

  # The events the log tells, in order, as "<event> <phase>" pairs.
  def log_story(lines)
    lines.map { |line| "#{line['event']} #{line['phase']}" }
  end
end
