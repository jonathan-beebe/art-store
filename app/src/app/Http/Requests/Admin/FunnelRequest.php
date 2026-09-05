<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Analytics\FunnelDefinition;
use App\Models\Funnel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Backs both `admin.funnels.store` and `.update`. The submitted `op`
 * ("save", or an editor action {@see \App\Domain\Analytics\FunnelStepListOp}
 * reads) decides how much of the form the request enforces: an editor
 * action carries the same fields but needs none of them to be a complete,
 * valid funnel yet, since the controller only rebuilds the working step
 * list from it and re-renders. Only "save" — the default when no button
 * names an op — runs {@see FunnelDefinition} and the slug uniqueness check.
 */
final class FunnelRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isSave() ? 'required' : 'nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'steps' => ['array'],
            'steps.*' => ['nullable', 'string'],
            'op' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->isSave()) {
                return;
            }

            if (self::slugTaken($this->slug(), $this->route('funnel'))) {
                $validator->errors()->add('slug', 'That slug is already used by another funnel.');
            }

            try {
                FunnelDefinition::of($this->stepNames());
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('steps', $e->getMessage());
            }
        });
    }

    /**
     * The button an admin pressed — "save" when the form carries none,
     * which a request built outside a browser (a test, an API caller) never
     * has to name.
     */
    public function op(): string
    {
        $op = $this->string('op')->toString();

        return $op === '' ? 'save' : $op;
    }

    public function isSave(): bool
    {
        return $this->op() === 'save';
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    /**
     * `$slug`, or the name's own slug when the field was left blank.
     */
    public function slug(): string
    {
        $slug = $this->string('slug')->toString();

        return $slug === '' ? Str::slug($this->name()) : $slug;
    }

    /**
     * @return list<string>
     */
    public function stepNames(): array
    {
        /** @var list<string> $steps */
        $steps = $this->input('steps', []);

        return $steps;
    }

    public function definition(): FunnelDefinition
    {
        return FunnelDefinition::of($this->stepNames());
    }

    private static function slugTaken(string $slug, mixed $ignoring): bool
    {
        return Funnel::query()
            ->where('slug', $slug)
            ->when($ignoring instanceof Funnel, fn ($query) => $query->whereKeyNot($ignoring))
            ->exists();
    }
}
