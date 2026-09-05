<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\FlashesUrlToSession;
use App\Notifications\Channels\SessionFlashChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use Override;

/**
 * Carries a one-time sign-in link to the address that asked for it.
 */
final class MagicLinkIssued extends Notification implements FlashesUrlToSession
{
    public function __construct(private readonly string $url) {}

    /**
     * The channel `config/magic_links.php` names. The recipient is a bare
     * address, which cannot route itself the way a model can, so the
     * sender fixes the channel here.
     */
    public static function channel(): string
    {
        $delivery = config('magic_links.delivery');

        return match ($delivery) {
            'session' => SessionFlashChannel::class,
            'mail' => 'mail',
            default => throw new InvalidArgumentException("Unknown magic link delivery [{$delivery}]."),
        };
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [self::channel()];
    }

    #[Override]
    public function toSessionFlash(object $notifiable): string
    {
        return $this->url;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in link')
            ->line('Follow this link to sign in. It expires in '.config('magic_links.expiry_minutes').' minutes.')
            ->action('Sign in', $this->url);
    }
}
