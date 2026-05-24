<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Education;

use App\Modules\Education\Domain\ValueObject\LessonSlug;
use InvalidArgumentException;

it('accepts kebab-case lower-alphanumeric slugs', function (string $value): void {
    $slug = new LessonSlug($value);
    expect($slug->value)->toBe($value);
})->with([
    'simple' => 'what-is-blockchain',
    'with-numbers' => 'eip-1559',
    'long-but-ok' => 'understanding-the-utxo-model-and-how-it-differs-from-accounts',
]);

it('rejects bad slugs', function (string $value): void {
    expect(fn () => new LessonSlug($value))->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => 'ab',
    'underscore' => 'what_is_blockchain',
    'uppercase' => 'What-Is-Blockchain',
    'leading dash' => '-bad',
    'trailing dash' => 'bad-',
    'double dash' => 'bad--slug',
    'space' => 'what is',
    'unicode' => 'что-это',
]);
