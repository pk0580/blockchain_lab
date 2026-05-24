<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Rpc;

use RuntimeException;

/**
 * Признак сетевого сбоя при работе с EVM RPC: соединение/таймаут/нечитаемый
 * ответ. Поднимается только из {@see EvmJsonRpc} и перехватывается там же,
 * где маппится в Domain-исключения. Не выходит за пределы Infrastructure.
 */
final class TransportError extends RuntimeException
{
}
