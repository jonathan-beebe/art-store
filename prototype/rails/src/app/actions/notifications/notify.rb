module Notifications
  class Notify
    def call(recipient_type:, recipient_id:, message:)
      notification = Notification.create!(
        { Notification.recipient_column(recipient_type) => recipient_id }
          .merge(subject: message.subject, body: message.body, url: message.url)
      )

      deliver_by_email(notification)

      notification
    end

    private

    # The prototype delivers to the in-app inbox only. Mail hangs off this hook,
    # behind the same port shape as MagicLinkDelivery.
    def deliver_by_email(notification)
    end
  end
end
