<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use Tests\TestCase;

class RuntimeVersionTest extends TestCase
{
    public function test_php_is_at_least_pinned_version(): void
    {
        $this->assertTrue(version_compare(PHP_VERSION, '8.5.12', '>='));
    }

    public function test_laravel_major_is_13(): void
    {
        $this->assertSame(13, (int) explode('.', Application::VERSION)[0]);
    }

    public function test_locale_and_timezone_are_indonesian(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }
}
