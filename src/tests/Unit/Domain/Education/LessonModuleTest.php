<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Education;

use App\Modules\Education\Domain\ValueObject\LessonModule;
use InvalidArgumentException;

it('parses order from numeric prefix', function (): void {
    expect((new LessonModule('01-foundations'))->order())->toBe(1);
    expect((new LessonModule('05-system-design'))->order())->toBe(5);
});

it('humanizes display name', function (): void {
    expect((new LessonModule('03-ethereum'))->displayName())->toBe('ethereum');
    expect((new LessonModule('05-system-design'))->displayName())->toBe('system design');
});

it('rejects bad module names', function (string $value): void {
    expect(fn () => new LessonModule($value))->toThrow(InvalidArgumentException::class);
})->with([
    'no prefix' => 'foundations',
    'single-digit prefix' => '1-foundations',
    'uppercase' => '01-Foundations',
    'underscore' => '01-system_design',
]);
