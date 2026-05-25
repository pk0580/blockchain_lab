<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

/**
 * Перечисление типов операций. Сейчас активно используются Deposit и
 * ReorgReversal; Withdrawal/Fee зарезервированы (колонка string, миграции
 * схемы не понадобится).
 */
enum OperationType: string
{
    case Deposit = 'deposit';
    case ReorgReversal = 'reorg_reversal';
    case Withdrawal = 'withdrawal';
    case Fee = 'fee';
}
