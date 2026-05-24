<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/playground/gas/chart — синтетические данные «priority fee vs time
 * to inclusion». Это не настоящая аналитика mainnet'а, а образовательная
 * аппроксимация: экспоненциальная зависимость с шумом, чтобы студент видел
 * характерную форму кривой.
 *
 * Если в Phase 9 добавим реальный сбор fee-метрик — endpoint поменяет источник
 * данных, контракт response'а остаётся стабильным.
 */
final readonly class GasChartController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var int $seed */
        $seed = (int) $request->query('seed', '0');
        mt_srand($seed !== 0 ? $seed : (int) (microtime(true) * 1000));

        $samples = [];
        // priority fee от 1 до 30 gwei, шаг 1 gwei.
        for ($pf = 1; $pf <= 30; $pf++) {
            // Базовая модель: t = max(1, 60 * e^(-0.15 * pf) + noise).
            $base = 60.0 * exp(-0.15 * $pf);
            $noise = (mt_rand(0, 1000) / 1000.0 - 0.5) * 4.0;
            $seconds = max(1.0, $base + $noise);
            $samples[] = [
                'priority_fee_gwei' => $pf,
                'avg_seconds_to_inclusion' => round($seconds, 1),
            ];
        }

        return new JsonResponse([
            'data' => [
                'note' => 'Synthetic data — exponential decay with noise. Replace with real ETH mainnet metrics in Phase 9+.',
                'unit' => 'gwei / seconds',
                'samples' => $samples,
            ],
        ]);
    }
}
