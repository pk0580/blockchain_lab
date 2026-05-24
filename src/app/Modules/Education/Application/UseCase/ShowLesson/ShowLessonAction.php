<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\UseCase\ShowLesson;

use App\Modules\Education\Application\DTO\LessonView;
use App\Modules\Education\Domain\Exception\LessonNotFoundException;
use App\Modules\Education\Domain\Repository\LessonRepository;
use App\Modules\Education\Domain\ValueObject\LessonSlug;

final readonly class ShowLessonAction
{
    public function __construct(private LessonRepository $repo) {}

    public function handle(LessonSlug $slug): LessonView
    {
        $lesson = $this->repo->findBySlug($slug)
            ?? throw LessonNotFoundException::bySlug($slug);

        return new LessonView(
            slug: $lesson->slug->value,
            module: $lesson->module->value,
            moduleDisplayName: $lesson->module->displayName(),
            moduleOrder: $lesson->module->order(),
            order: $lesson->order->value,
            title: $lesson->title,
            summary: $lesson->summary,
            bodyHtml: $lesson->bodyHtml,
            playgroundId: $lesson->playgroundId,
        );
    }
}
