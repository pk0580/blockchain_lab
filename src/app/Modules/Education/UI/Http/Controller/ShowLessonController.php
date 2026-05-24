<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller;

use App\Modules\Education\Application\UseCase\ListLessons\ListLessonsAction;
use App\Modules\Education\Application\UseCase\ShowLesson\ShowLessonAction;
use App\Modules\Education\Domain\Exception\LessonNotFoundException;
use App\Modules\Education\Domain\ValueObject\LessonSlug;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /lessons/{slug} — конкретный урок + список всех уроков для боковой
 * навигации. Здесь же передаётся `body_html` для рендера через `v-html`.
 */
final readonly class ShowLessonController
{
    public function __construct(
        private ShowLessonAction $show,
        private ListLessonsAction $list,
    ) {}

    public function __invoke(string $slug): Response
    {
        try {
            $lessonSlug = new LessonSlug($slug);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException();
        }

        try {
            $lesson = $this->show->handle($lessonSlug);
        } catch (LessonNotFoundException) {
            throw new NotFoundHttpException();
        }

        $lessons = $this->list->handle();

        return Inertia::render('Lessons/Show', [
            'lesson' => $lesson->toArray(),
            'lessons' => array_map(fn ($l) => $l->toArray(), $lessons),
        ]);
    }
}
