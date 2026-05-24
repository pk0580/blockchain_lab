<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Idempotency;

use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;
use InvalidArgumentException;

it('accepts 2xx-4xx status codes', function (int $status): void {
    $snap = new ResponseSnapshot($status, 'body');
    expect($snap->status)->toBe($status);
})->with([
    'OK' => 200,
    'Created' => 201,
    'Accepted' => 202,
    'Bad Request' => 400,
    'Conflict' => 409,
    'Unprocessable' => 422,
    'last allowed' => 499,
]);

it('rejects 1xx and 5xx', function (int $status): void {
    expect(fn () => new ResponseSnapshot($status, 'body'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'Continue' => 100,
    'just below' => 199,
    'Internal Server Error' => 500,
    'Bad Gateway' => 502,
]);

it('classifies storable statuses statically', function (): void {
    expect(ResponseSnapshot::isStorableStatus(200))->toBeTrue();
    expect(ResponseSnapshot::isStorableStatus(499))->toBeTrue();
    expect(ResponseSnapshot::isStorableStatus(199))->toBeFalse();
    expect(ResponseSnapshot::isStorableStatus(500))->toBeFalse();
});
