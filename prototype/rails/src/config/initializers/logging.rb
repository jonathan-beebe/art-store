# Rails and the gems under it write prose, each framework through a logger of
# its own. The stories this app tells replace those lines, so every one of
# those loggers is pointed at nothing and `Rails.logger` is left writing JSON
# to stdout.
nowhere = ActiveSupport::Logger.new(File::NULL)

ActiveSupport::LogSubscriber.logger = nowhere
ActiveStorage.logger = nowhere
ActiveSupport.on_load(:active_record) { self.logger = nowhere }
ActiveSupport.on_load(:action_controller) { self.logger = nowhere }
ActiveSupport.on_load(:action_view) { self.logger = nowhere }
ActiveSupport.on_load(:action_mailer) { self.logger = nowhere }
ActiveSupport.on_load(:active_job) { self.logger = nowhere }

# The process saying it is here and saying it is going. Both are written
# straight to the logger: a story is a unit of work with two ends, and these
# are single moments, one of them the last thing the process does.
Rails.application.config.after_initialize do
  Current.actor_type = "system"

  Rails.logger.info(
    event: "app.boot", phase: "did", msg: "started the application",
    data: { environment: Rails.env, pid: Process.pid }
  )
end

at_exit do
  Rails.logger.info(
    event: "app.shutdown", phase: "did", msg: "stopped the application",
    data: { environment: Rails.env, pid: Process.pid }
  )
end
