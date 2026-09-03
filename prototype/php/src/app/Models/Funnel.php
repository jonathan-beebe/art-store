<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\FunnelDefinition;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\FunnelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * An admin-defined path through the analytics vocabulary: a name plus an
 * ordered list of event names {@see FunnelDefinition} validates. Visitors
 * is every funnel's implied first step and is never stored in `steps`.
 * `position` orders a funnel among the tiles the analytics home shows.
 */
#[Fillable(['name', 'slug', 'steps', 'position'])]
class Funnel extends Model
{
    /** @use HasFactory<FunnelFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'fnl';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * The stored step names as their enum cases, in order.
     *
     * @return list<AnalyticsEventName>
     */
    public function steps(): array
    {
        /** @var list<string> $names */
        $names = $this->getAttribute('steps');

        return FunnelDefinition::of($names)->steps;
    }
}
