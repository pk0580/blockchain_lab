<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Service;

use App\Modules\Address\Domain\ValueObject\DerivationIndex;
use App\Modules\Address\Domain\ValueObject\DerivationPath;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Создает пути деривации по стандарту BIP-44 для конкретных семейств блокчейнов.
 *
 * Структура пути BIP-44 (см. GUIDE.md, Урок 2 — раздел «HD-кошельки: один сид —
 * много адресов»):
 *
 *     m / purpose' / coin_type' / account' / change / address_index
 *
 * Апостроф (`'`) — «hardened» шаг: дочерний ключ нельзя вывести только из
 * публичного родителя, нужен приватный. Это критично: утечка одного pubkey
 * не открывает соседние аккаунты.
 *
 * Типы монет (SLIP-44):
 *   - Bitcoin = 0  (мы используем BIP-84 P2WPKH → purpose 84, адреса `bc1q...`)
 *   - Ethereum = 60 (Polygon и другие EVM-сети используют его в нашей конфигурации)
 *   - Tron = 195
 *
 * Аккаунт зафиксирован на 0. Деривация по нескольким аккаунтам (горячие
 * кошельки для каждого мерчанта) пока не реализована.
 *
 * @see \GUIDE.md  Урок 2 (#урок-2--ключи-адреса-и-hd-кошельки)
 */
final class DerivationPathFactory
{
    private const int ACCOUNT = 0;
    private const int CHANGE = 0;

    /**
     * Внешний (receive) путь: `change = 0`. Внутренний (change-адреса,
     * куда возвращается сдача) был бы `change = 1` — пока не используется.
     */
    public function buildExternal(ChainFamily $family, DerivationIndex $index): DerivationPath
    {
        $i = $index->value;
        $a = self::ACCOUNT;
        $c = self::CHANGE;

        // Шаблоны путей зафиксированы и совпадают с конвенцией кошельков
        // экосистемы (Trust Wallet, Ledger, MetaMask), что позволяет
        // импортировать сид и восстановить те же адреса.
        return new DerivationPath(match ($family) {
            ChainFamily::Bitcoin => "m/84'/0'/{$a}'/{$c}/{$i}",
            ChainFamily::Evm => "m/44'/60'/{$a}'/{$c}/{$i}",
            ChainFamily::Tron => "m/44'/195'/{$a}'/{$c}/{$i}",
        });
    }
}
