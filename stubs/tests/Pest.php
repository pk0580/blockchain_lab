<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature & Integration tests boot the framework via Tests\TestCase.
| Unit tests under Unit/Domain stay framework-free (no uses() needed).
|
*/

uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeReadonly', function () {
    $reflection = new ReflectionClass($this->value);
    return expect($reflection->isReadOnly())->toBeTrue(
        "Expected [{$this->value}] to be a readonly class."
    );
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function freezeTime(string $instant = '2026-01-01T00:00:00Z'): void
{
    \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse($instant));
}
