<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use App\Modules\Network\Domain\ValueObject\TxHash;

/**
 * Унифицированный контракт, который реализует каждое семейство сетей. Добавление новой L2,
 * которая подходит под существующее семейство (например, EVM-роллап), позволяет повторно использовать адаптер семейства —
 * требуется только новая запись в реестре Chain. Для действительно нового семейства (например, Solana)
 * требуется новый класс адаптера.
 *
 * Конкретные адаптеры находятся в Infrastructure (BitcoinAdapter, EvmAdapter, …).
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
     * Фаза 4+ расширяет этот контракт методами fetchBlock / fetchTransaction /
     * broadcast — мы сохраняем минимальный интерфейс здесь, чтобы каждая фаза могла расширять его
     * без изменения ранее написанного кода.
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
