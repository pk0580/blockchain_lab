<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller;

use App\Modules\Education\Application\UseCase\ListLessons\ListLessonsAction;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET / — лендинг с кратким описанием курса и списком модулей.
 */
final readonly class ShowHomeController
{
    public function __construct(private ListLessonsAction $list) {}

    public function __invoke(): Response
    {
        $lessons = $this->list->handle();

        return Inertia::render('Home', [
            'lessons' => array_map(fn ($l) => $l->toArray(), $lessons),
        ]);
    }
}
