<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

/**
 * Перечисление типов операций. Phase 5 использует Deposit и ReorgReversal;
 * Withdrawal/Fee добавятся в Phase 6 без миграции схемы (колонка string).
 */
enum OperationType: string
{
    case Deposit = 'deposit';
    case ReorgReversal = 'reorg_reversal';
    case Withdrawal = 'withdrawal';
    case Fee = 'fee';
}
