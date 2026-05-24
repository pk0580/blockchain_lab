<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

enum RpcKind: string
{
    case Http = 'http';
    case WebSocket = 'ws';
}
