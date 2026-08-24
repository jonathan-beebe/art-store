<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Notifications\NotificationMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a thread's other participant a message is waiting for them.
 */
final class MessageReceived extends Notification
{
    public function __construct(private readonly string $topic, private readonly ?string $url) {}

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

        return $message->url === null ? $mail : $mail->action('Open the thread', $message->url);
    }

    private function message(): NotificationMessage
    {
        return NotificationMessage::messageReceived($this->topic, $this->url);
    }
}
