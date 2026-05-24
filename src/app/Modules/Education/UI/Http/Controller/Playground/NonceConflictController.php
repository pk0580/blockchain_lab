<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/playground/nonce/simulate — учебная симуляция «двух tx с одним
 * nonce». На вход — два псевдо-tx (nonce + fee + tag), на выход — какой
 * победит по правилам mempool replacement (выше fee → побеждает).
 *
 * Это НЕ настоящая EVM-симуляция; без подключения к testnet мы не можем
 * показать реальную замену. Endpoint существует ради образования: студент
 * меняет fee и видит почему replacement даёт RBF / гонку.
 */
final readonly class NonceConflictController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'nonce' => ['required', 'integer', 'min:0'],
            'tx_a.tag' => ['required', 'string', 'max:32'],
            'tx_a.priority_fee_gwei' => ['required', 'numeric', 'min:0'],
            'tx_b.tag' => ['required', 'string', 'max:32'],
            'tx_b.priority_fee_gwei' => ['required', 'numeric', 'min:0'],
            'replacement_bump_percent' => ['nullable', 'integer', 'min:0', 'max:200'],
        ]);
        $bump = (int) ($payload['replacement_bump_percent'] ?? 10);

        $a = ['tag' => $payload['tx_a']['tag'], 'fee' => (float) $payload['tx_a']['priority_fee_gwei']];
        $b = ['tag' => $payload['tx_b']['tag'], 'fee' => (float) $payload['tx_b']['priority_fee_gwei']];

        // tx A прилетает первым; tx B — replacement-кандидат.
        // По правилам EIP-1559 replacement требует fee хотя бы на bump% выше.
        $threshold = $a['fee'] * (1.0 + $bump / 100.0);
        $bumpsEnough = $b['fee'] >= $threshold;

        if ($bumpsEnough) {
            $winner = $b['tag'];
            $verdict = "B replaces A: fee {$b['fee']} >= threshold {$threshold} (bump {$bump}%).";
        } else {
            $winner = $a['tag'];
            $verdict = "A keeps the slot: B's fee {$b['fee']} < required threshold {$threshold} (bump {$bump}%).";
        }

        return new JsonResponse([
            'data' => [
                'nonce' => (int) $payload['nonce'],
                'tx_a' => $a,
                'tx_b' => $b,
                'required_bump_percent' => $bump,
                'threshold_priority_fee_gwei' => round($threshold, 2),
                'winner_tag' => $winner,
                'verdict' => $verdict,
            ],
        ]);
    }
}
