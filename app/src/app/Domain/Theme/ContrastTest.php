<?php

declare(strict_types=1);

use App\Domain\Theme\Contrast;

it('rates black on white at 21:1', function (): void {
    expect(Contrast::ratio('#000000', '#ffffff'))->toEqualWithDelta(21.0, 0.01);
});

it('rates a color against itself at 1:1', function (): void {
    expect(Contrast::ratio('#b04f26', '#b04f26'))->toEqualWithDelta(1.0, 0.001);
});

it('reads the same ratio in either argument order', function (): void {
    expect(Contrast::ratio('#3d2f26', '#f6efe4'))
        ->toEqual(Contrast::ratio('#f6efe4', '#3d2f26'));
});

it('matches WebAIM for white on the light accent', function (): void {
    // webaim.org/resources/contrastchecker reads #b04f26 on #ffffff as 5.26.
    expect(Contrast::ratio('#b04f26', '#ffffff'))->toEqualWithDelta(5.26, 0.01);
});

it('linearises near-black channels through the low-value branch', function (): void {
    // Every channel of #080808 sits below the 0.03928 sRGB knee.
    expect(Contrast::ratio('#080808', '#000000'))->toBeGreaterThan(1.0)->toBeLessThan(1.1);
});

it('passes AA at 4.5 and fails just under it', function (): void {
    expect(Contrast::meetsAa(4.5))->toBeTrue()
        ->and(Contrast::meetsAa(4.49))->toBeFalse();
});

it('passes large-text AA at 3.0 and fails just under it', function (): void {
    expect(Contrast::meetsAaLarge(3.0))->toBeTrue()
        ->and(Contrast::meetsAaLarge(2.99))->toBeFalse();
});

it('composites a fully opaque fill as the fill color, ignoring the ground', function (): void {
    expect(Contrast::compositeOver('#1a110c', 1.0, '#ffffff'))->toEqual('#1a110c');
});

it('composites a fully transparent fill as the ground color, ignoring the fill', function (): void {
    expect(Contrast::compositeOver('#1a110c', 0.0, '#ffffff'))->toEqual('#ffffff');
});

it('blends a translucent fill toward its ground by the alpha', function (): void {
    // photo-scrim over a worst-case white photo, at the 0.72 stop
    // resources/css/app.css's .bg-photo-scrim gradient reaches: rgb(26,17,12)
    // * 0.72 + rgb(255,255,255) * 0.28 rounds to rgb(90,84,80).
    expect(Contrast::compositeOver('#1a110c', 0.72, '#ffffff'))->toEqual('#5a5450');
});
