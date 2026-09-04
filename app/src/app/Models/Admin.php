<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

#[Fillable(['email', 'name', 'email_verified_at'])]
#[Hidden(['remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use HasPrefixedUlid;
    use Notifiable;

    public static function idPrefix(): string
    {
        return 'adm';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        return $this->name ?? $this->email;
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return MorphMany<Message, $this> */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

    /**
     * The one admin a support thread opens against, in an app with no
     * assignment model. Null when no admin has been seeded yet. Two admins
     * seeded in the same second are separated by their ids, which a
     * prefixed ULID orders the way they were minted.
     */
    public static function platformAdmin(): ?self
    {
        return self::query()->oldest('created_at')->oldest('id')->first();
    }
}
