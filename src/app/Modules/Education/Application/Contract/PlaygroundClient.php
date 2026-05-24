<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\Contract;

/**
 * Thin port в signing-svc playground endpoint'ы. Возвращаемые массивы
 * пробрасываются в JsonResponse как-есть — никакой Domain-логики, просто
 * proxy, чтобы не открывать signing-svc на публичный port.
 */
interface PlaygroundClient
{
    /**
     * Генерирует ephemeral keypair. Если `$mnemonic` передан — восстанавливает
     * по нему; иначе генерирует случайный 128-битный seed.
     *
     * @return array<string, mixed>
     */
    public function generateKeypair(?string $mnemonic): array;

    /**
     * Подписывает sha256d(messageHex) приватным ключом 32 байта (hex).
     *
     * @return array<string, mixed>
     */
    public function sign(string $privateKeyHex, string $messageHex): array;

    /**
     * Декодирует raw tx hex в structured JSON.
     *
     * @return array<string, mixed>
     */
    public function decode(string $chain, string $rawHex): array;
}
