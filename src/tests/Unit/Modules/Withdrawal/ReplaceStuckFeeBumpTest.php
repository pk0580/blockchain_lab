<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Withdrawal;

use App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal\ReplaceStuckWithdrawalAction;
use ReflectionClass;

/**
 * Точечный unit-тест: ReplaceStuckWithdrawalAction::bumpFee — приватный, но
 * критичен для корректности RBF (BTC) / resend (EVM). Через рефлексию
 * проверяем, что bumping корректно работает на 256-битных wei-числах.
 */
it('multiplies wei-strings by bps without loss of precision', function (): void {
    $reflection = new ReflectionClass(ReplaceStuckWithdrawalAction::class);
    $mulBps = $reflection->getMethod('mulBps');
    $mulBps->setAccessible(true);

    /** @var ReplaceStuckWithdrawalAction $instance */
    $instance = $reflection->newInstanceWithoutConstructor();

    // 20 gwei × 1.25 = 25 gwei = 25_000_000_000 wei.
    expect($mulBps->invoke($instance, '20000000000', 12500))->toBe('25000000000');

    // 1 wei × 1.25 = 1 wei (bcdiv floor).
    expect($mulBps->invoke($instance, '1', 12500))->toBe('1');

    // Большое число — без переполнения int64.
    expect($mulBps->invoke($instance, '1000000000000000000000', 12500))
        ->toBe('1250000000000000000000');
});
