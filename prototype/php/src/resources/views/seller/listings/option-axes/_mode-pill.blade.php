@php
    use App\Domain\Configurator\PricingMode;
@endphp

@if ($mode === PricingMode::Standalone)
    <span class="rounded-full bg-gray-900 dark:bg-gray-100 px-2 py-0.5 text-xs text-white dark:text-gray-900">each option priced on its own</span>
@else
    <span class="rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs">adds to your price</span>
@endif
