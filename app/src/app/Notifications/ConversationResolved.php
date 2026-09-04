<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the supported side of a thread that the other side marked it
 * resolved. "Reply to reopen" is the whole escape hatch: the notification
 * links straight back to the thread.
 */
final class ConversationResolved extends PrefixedUlidNotification
{
    public function __construct(private readonly string $topic, private readonly ?string $url)
    {
        parent::__construct();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('notifications.channels');
    }

    /**
     * @return array{subject: string, body: string, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return $this->message()->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message();
        $mail = (new MailMessage)->subject($message->subject)->line($message->body);

        return $message->url === null ? $mail : $mail->action('Reply to reopen', $message->url);
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::conversationResolved($this->topic, $this->url);
    }
}
