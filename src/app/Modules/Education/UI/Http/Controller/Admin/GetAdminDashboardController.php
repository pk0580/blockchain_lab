<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Admin;

use App\Modules\Education\Application\UseCase\LoadDashboardOverview\LoadDashboardOverviewAction;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/admin/dashboard — polling-endpoint для админ-дашборда. Возвращает
 * тот же payload, что и initial Inertia-render, но в чистом JSON для частых
 * (5-сек) запросов с фронта.
 */
final readonly class GetAdminDashboardController
{
    public function __construct(private LoadDashboardOverviewAction $load) {}

    public function __invoke(): JsonResponse
    {
        $overview = $this->load->handle(new DateTimeImmutable());

        return new JsonResponse(['data' => $overview->toArray()]);
    }
}
