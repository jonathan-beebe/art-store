<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

#[Fillable(['email', 'name', 'email_verified_at'])]
#[Hidden(['remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use Notifiable;

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
}
