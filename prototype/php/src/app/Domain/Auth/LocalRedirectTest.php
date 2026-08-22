<?php

namespace App\Domain\Auth;

use PHPUnit\Framework\TestCase;

final class LocalRedirectTest extends TestCase
{
    private const ORIGIN = 'http://localhost:8000';

    private const FALLBACK = '/account';

    public function test_a_missing_target_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve(null));
    }

    public function test_a_blank_target_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve('   '));
    }

    public function test_a_root_relative_path_is_kept(): void
    {
        $this->assertSame('/checkout?step=2', $this->resolve('/checkout?step=2'));
    }

    public function test_an_absolute_url_on_this_origin_is_kept(): void
    {
        $this->assertSame(self::ORIGIN.'/checkout', $this->resolve(self::ORIGIN.'/checkout'));
    }

    public function test_the_origin_itself_is_kept(): void
    {
        $this->assertSame(self::ORIGIN, $this->resolve(self::ORIGIN));
    }

    public function test_another_host_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve('http://evil.example/steal'));
    }

    public function test_a_host_that_only_prefixes_this_origin_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve(self::ORIGIN.'.evil.example/steal'));
    }

    public function test_a_protocol_relative_url_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve('//evil.example/steal'));
    }

    public function test_a_backslash_escaped_path_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve('/\\evil.example/steal'));
    }

    public function test_a_target_carrying_a_newline_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, $this->resolve("/checkout\nSet-Cookie: x=1"));
    }

    public function test_it_keeps_a_local_target_on_its_own(): void
    {
        $this->assertSame('/checkout', LocalRedirect::keepIfLocal('/checkout', self::ORIGIN));
    }

    public function test_it_drops_a_foreign_target_on_its_own(): void
    {
        $this->assertNull(LocalRedirect::keepIfLocal('http://evil.example/steal', self::ORIGIN));
    }

    private function resolve(?string $requested): string
    {
        return LocalRedirect::resolve($requested, self::FALLBACK, self::ORIGIN);
    }
}
