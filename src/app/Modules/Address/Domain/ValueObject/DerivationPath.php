<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\ValueObject;

use App\Modules\Address\Domain\Exception\InvalidDerivationPathException;

/**
 * Путь BIP-32. Принимает hardened-шаги, отмеченные апострофом или "h".
 * Пример: m/44'/60'/0'/0/0
 */
final readonly class DerivationPath
{
    public function __construct(public string $value)
    {
        if (! preg_match("#^m(?:/(?:\d+)['hH]?){1,12}$#", $value)) {
            throw new InvalidDerivationPathException("Некорректный путь BIP-32: '{$value}'.");
        }
    }

    public function leafIndex(): DerivationIndex
    {
        $parts = explode('/', $this->value);
        $leaf = end($parts);
        $leaf = rtrim($leaf, "'hH");
        return new DerivationIndex((int) $leaf);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
