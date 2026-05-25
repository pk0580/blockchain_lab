<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Domain\ValueObject\TxHash;

/**
 * Унифицированный контракт, который реализует каждое семейство сетей.
 *
 * Архитектурный приём из GUIDE.md, Урок 4: «семейство — это контракт работы
 * с сетью, а сеть — это конкретная инстанция семейства». Добавление новой L2
 * в существующее семейство (например, EVM-роллап) = одна запись в Chain-реестре,
 * никакого нового PHP-кода. Новое семейство (например, Solana) = новый адаптер
 * + регистрация в {@see ChainAdapterRegistry}.
 *
 * Конкретные адаптеры находятся в Infrastructure: {@see BitcoinAdapter}, {@see EvmAdapter}.
 *
 * @see \GUIDE.md  Урок 4 (#урок-4--l1-l2-и-семейства-сетей)
 */
interface ChainAdapter
{
    public function chain(): Chain;

    public function family(): ChainFamily;

    public function finality(): FinalityPolicy;

    public function addressValidator(): AddressValidator;

    public function feeEstimator(): FeeEstimator;

    /**
     * Получает последнюю известную высоту заголовка из настроенного пула RPC.
     */
    public function currentHead(): BlockHeight;

    /**
     * Проверка работоспособности (Echo health check) — возвращает true, если отвечает хотя бы одна точка RPC.
     */
    public function isHealthy(): bool;

    /**
     * Может ли этот адаптер взаимодействовать с сетью, на которую ссылается $txHash.
     * Контракт расширяется методами fetchBlock / fetchTransaction / broadcast по мере
     * необходимости — мы держим базовый интерфейс минимальным, чтобы расширения не
     * ломали уже написанные адаптеры.
     */
    public function supports(TxHash $txHash): bool;

    /**
     * Транслирует подписанную транзакцию в mempool сети и возвращает txid
     * (BTC: 64 hex без префикса, EVM: 0x + 64 hex).
     *
     * @throws \App\Modules\Network\Domain\Exception\BroadcastFailedException
     */
    public function broadcast(SignedRawTx $tx): TxHash;
}
