<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Криптографический хеш блока — детерминированный отпечаток заголовка блока.
 * Любое изменение содержимого блока даёт совершенно другой хеш, поэтому хеш
 * выступает «адресом» блока и одновременно ссылкой родителя в следующем блоке.
 *
 * Сравнение через {@see equals()} нормализует регистр и префикс `0x`, чтобы
 * hex из Bitcoin RPC (без префикса) и EVM (с префиксом) сравнивались корректно.
 * Это ключевое сравнение в детекторе реоргов: новый блок утверждает родителя P,
 * а у нас на той же высоте хранится блок с другим хешем — значит, наш orphan.
 *
 * @see \GUIDE.md  Урок 1 (#урок-1--что-такое-блокчейн)
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи) — ChainComparator::analyze()
 */
final readonly class BlockHash
{
    public function __construct(public string $value)
    {
        // Поддерживаем оба формата: Bitcoin RPC отдаёт хеш без префикса,
        // EVM-ноды — с `0x`. Длина варьируется (BTC = 64 hex, EVM = 64 hex,
        // но иногда RPC возвращают полный 256-битный preimage).
        if (! preg_match('/^(0x)?[0-9a-fA-F]{40,128}$/', $value)) {
            throw new InvalidArgumentException("BlockHash must be hex (40-128 hex chars): '{$value}'.");
        }
    }

    public function normalized(): string
    {
        return strtolower(str_starts_with($this->value, '0x') ? $this->value : '0x'.$this->value);
    }

    public function equals(self $other): bool
    {
        return $this->normalized() === $other->normalized();
    }
}
