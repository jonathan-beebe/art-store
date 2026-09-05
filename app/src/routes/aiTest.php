<?php

declare(strict_types=1);

it('answers GET on the MCP path with 405 and names POST, before any key is checked', function (): void {
    $this->get('/mcp')
        ->assertStatus(405)
        ->assertHeader('Allow', 'POST');
});

it('names the MCP route', function (): void {
    expect(route('mcp'))->toEndWith('/mcp');
});
