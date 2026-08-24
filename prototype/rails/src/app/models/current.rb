# What every log line written while this request or unit of work runs carries:
# which request it belongs to, which browser is behind it, who is acting, and
# which transaction it sits inside.
class Current < ActiveSupport::CurrentAttributes
  attribute :request_id, :session_id, :actor_type, :actor_id, :txn_id

  # Names the actor behind the lines from here on. A record says its own type,
  # so a seller, a customer and an admin need no case of their own; nobody in
  # particular leaves the pair off the line.
  def acting_as(record)
    self.actor_type = record&.model_name&.singular
    self.actor_id = record&.id
  end
end
