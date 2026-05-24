<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;

it('requires maxReorgDepth >= requiredConfirmations', function (): void {
    new ConfirmationRequirement(requiredConfirmations: 12, maxReorgDepth: 6);
})->throws(\InvalidArgumentException::class);

it('marks finality once threshold reached', function (): void {
    $req = new ConfirmationRequirement(12, 64);
    expect($req->isFinal(11))->toBeFalse();
    expect($req->isFinal(12))->toBeTrue();
});
