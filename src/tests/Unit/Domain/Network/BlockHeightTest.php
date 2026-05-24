<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\ValueObject\BlockHeight;

it('rejects negatives', function (): void {
    new BlockHeight(-1);
})->throws(\InvalidArgumentException::class);

it('computes distance to head', function (): void {
    expect((new BlockHeight(95))->distanceTo(new BlockHeight(100)))->toBe(5);
    expect((new BlockHeight(100))->distanceTo(new BlockHeight(95)))->toBe(0);
});

it('advances by one', function (): void {
    expect((new BlockHeight(7))->next()->value)->toBe(8);
});
