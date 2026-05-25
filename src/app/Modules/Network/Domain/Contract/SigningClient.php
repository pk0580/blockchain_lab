<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;

/**
 * Шов (seam) уровня домена для связи с изолированным сервисом подписания.
 * Реализация для продакшена — {@see \App\Modules\Network\Infrastructure\Signer\HttpSigningClient};
 * в тестах используются фейки через сервис-провайдер.
 *
 * Контракт включает деривацию адресов, валидацию и подписание raw-транзакций
 * (нужно Withdrawal-модулю для отправки исходящих).
 */
interface SigningClient
{
    /**
     * Запрашивает у сервиса подписания создание (или импорт) HD-сида под
     * заданным непрозрачным идентификатором (reference). Возвращает true, если сид был создан заново,
     * false, если он уже существовал.
     */
    public function ensureSeed(string $reference, ?string $importMnemonic = null): bool;

    /**
     * Деривирует адрес для семейства сетей из сида по заданному пути BIP-32.
     *
     * @throws \App\Modules\Network\Domain\Exception\SigningClientException
     */
    public function deriveAddress(string $seedReference, ChainFamily $family, string $path): Address;

    /**
     * Запрашивает у сервиса подписания, является ли $address синтаксически корректным для
     * своего семейства. Сетевой запрос стоит дешево; вызывающая сторона может кэшировать результат.
     *
     * @throws \App\Modules\Network\Domain\Exception\SigningClientException
     */
    public function isAddressValid(ChainFamily $family, string $address): bool;

    /**
     * Подписывает уже построенную (необработанную) транзакцию. Семья + raw hex
     * + ссылка на HD seed + BIP-32 путь однозначно определяют ключ внутри
     * signing-svc. Дополнительные `extra` зависят от семьи:
     *  - BTC: `inputs` (для PSBT/sighash) — список prev-outs (txid, vout, scriptPubKey, amount_sat).
     *  - EVM: `chain_id` (int), `nonce` (int) — сериализуются в EIP-1559 tx внутри Go-сервиса.
     *
     * @param array<string, mixed> $extra
     *
     * @throws \App\Modules\Network\Domain\Exception\SigningClientException
     */
    public function signRawTx(
        ChainFamily $family,
        string $seedReference,
        string $path,
        string $rawHex,
        array $extra = [],
    ): SignedRawTx;
}
