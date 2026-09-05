<?php

declare(strict_types=1);

it('answers the Chrome DevTools well-known probe with no content', function (): void {
    $this->get('/.well-known/appspecific/com.chrome.devtools.json')
        ->assertNoContent();
});
