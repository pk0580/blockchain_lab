<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Admin;

use App\Modules\Education\Application\UseCase\LoadDashboardOverview\LoadDashboardOverviewAction;
use DateTimeImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /admin — initial render админ-дашборда. После загрузки страница
 * сама поллит JSON-эндпоинт `/api/admin/dashboard` (Phase 8.4 — 5s).
 */
final readonly class ShowAdminDashboardController
{
    public function __construct(private LoadDashboardOverviewAction $load) {}

    public function __invoke(): Response
    {
        $overview = $this->load->handle(new DateTimeImmutable());

        return Inertia::render('Admin/Dashboard', [
            'dashboard' => $overview->toArray(),
        ]);
    }
}
