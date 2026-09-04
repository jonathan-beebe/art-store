<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Seller\PeriodFigures;
use App\Models\Seller;
use App\Seller\EarningsPeriods;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * A statement exists only for one of the eight periods the earnings page
 * charts — the same eight calendar weeks for every seller, so `authorize()`
 * checks the date named on the route. A period outside that window,
 * malformed included, answers 404.
 */
final class StatementRequest extends FormRequest
{
    private bool $resolved = false;

    private ?PeriodFigures $figures = null;

    private ?EarningsPeriods $periods = null;

    public function authorize(): Response
    {
        return $this->figures() instanceof PeriodFigures
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * The route names the period; there is no body or query input to validate.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /** The named period's figures, or null when the route names none of the eight charted. */
    public function figures(): ?PeriodFigures
    {
        if ($this->resolved) {
            return $this->figures;
        }

        $this->resolved = true;
        $period = (string) $this->route('period');

        foreach ($this->periods()->periods as $figures) {
            if ($figures->period->start->format('Y-m-d') === $period) {
                return $this->figures = $figures;
            }
        }

        return null;
    }

    public function periods(): EarningsPeriods
    {
        return $this->periods ??= EarningsPeriods::for($this->seller(), now()->toDateTimeImmutable());
    }

    private function seller(): Seller
    {
        $seller = $this->user('seller');

        return $seller instanceof Seller
            ? $seller
            : throw new RuntimeException('The statement route runs behind the auth.seller middleware.');
    }
}
