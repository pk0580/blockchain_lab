<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Подписанная необработанная транзакция, готовая к broadcast.
 *
 *  - BTC: hex без префикса `0x` (выход `signrawtransactionwithkey`).
 *  - EVM: hex с обязательным префиксом `0x` (RLP-сериализация EIP-1559 tx).
 *
 * Семейство сохраняем явно, чтобы downstream-адаптер мог быстро отвергнуть
 * чужую транзакцию без анализа байт. Локальные адаптеры выполняют
 * семейство-специфичный sanity-check (BTC vs EVM) ниже.
 */
final readonly class SignedRawTx
{
    public function __construct(
        public ChainFamily $family,
        public string $hex,
    ) {
        $normalized = strtolower(trim($hex));
        if ($normalized === '' || strlen($normalized) > 200000) {
            throw new InvalidArgumentException('SignedRawTx hex должен быть непустой строкой длиной до 200000 символов.');
        }

        match ($family) {
            ChainFamily::Bitcoin => $this->assertBitcoinHex($normalized),
            ChainFamily::Evm => $this->assertEvmHex($normalized),
            ChainFamily::Tron => $this->assertTronHex($normalized),
        };
    }

    public function withoutPrefix(): string
    {
        return str_starts_with($this->hex, '0x') ? substr($this->hex, 2) : $this->hex;
    }

    public function withPrefix(): string
    {
        return str_starts_with($this->hex, '0x') ? $this->hex : '0x'.$this->hex;
    }

    private function assertBitcoinHex(string $hex): void
    {
        if (str_starts_with($hex, '0x')) {
            throw new InvalidArgumentException('SignedRawTx для bitcoin НЕ должен использовать префикс 0x.');
        }
        if (preg_match('/^[0-9a-f]+$/', $hex) !== 1) {
            throw new InvalidArgumentException('SignedRawTx hex содержит нешестнадцатеричные символы.');
        }
        if (strlen($hex) % 2 !== 0) {
            throw new InvalidArgumentException('SignedRawTx hex должен содержать целые байты.');
        }
    }

    private function assertEvmHex(string $hex): void
    {
        if (! str_starts_with($hex, '0x')) {
            throw new InvalidArgumentException('SignedRawTx для evm должен начинаться с префикса 0x.');
        }
        $body = substr($hex, 2);
        if (preg_match('/^[0-9a-f]+$/', $body) !== 1) {
            throw new InvalidArgumentException('SignedRawTx hex содержит нешестнадцатеричные символы.');
        }
        if (strlen($body) % 2 !== 0) {
            throw new InvalidArgumentException('SignedRawTx hex должен содержать целые байты.');
        }
    }

    private function assertTronHex(string $hex): void
    {
        $body = str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
        if (preg_match('/^[0-9a-f]+$/', $body) !== 1) {
            throw new InvalidArgumentException('SignedRawTx hex содержит нешестнадцатеричные символы.');
        }
        if (strlen($body) % 2 !== 0) {
            throw new InvalidArgumentException('SignedRawTx hex должен содержать целые байты.');
        }
    }
}
