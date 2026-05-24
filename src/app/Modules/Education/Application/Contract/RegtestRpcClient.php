<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\Contract;

/**
 * Узкий порт к Bitcoin Core JSON-RPC для playground'ов. Возвращает raw
 * результаты от bitcoind — без Domain mapping'а (playground proxy thin).
 *
 * Реализация (`HttpRegtestRpcClient`) живёт в Infrastructure и использует
 * базовый Illuminate\\Http\\Client — никакой зависимости от чужих модулей.
 */
interface RegtestRpcClient
{
    public function getBlockCount(): int;

    public function getBestBlockHash(): string;

    /**
     * @return array<string, mixed>
     */
    public function getBlock(string $hash, int $verbosity = 1): array;

    /**
     * @return list<string>
     */
    public function getRawMempool(): array;

    /**
     * @return list<string>  hashes сгенерированных блоков
     */
    public function generateToAddress(int $blocks, string $address): array;

    public function invalidateBlock(string $hash): void;

    /**
     * Best-effort генерация regtest-адреса для playground'а. Возвращает
     * `null` если узел не предоставляет встроенный кошелёк (тогда фронту
     * нужно явно прислать адрес).
     */
    public function getNewAddress(): ?string;
}
