<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\UseCase\ListLessons;

use App\Modules\Education\Application\DTO\LessonSummary;
use App\Modules\Education\Domain\Repository\LessonRepository;

/**
 * Возвращает все уроки, спроецированные в `LessonSummary`. Группировка по
 * модулям делается на UI-слое, чтобы Action оставалась простой.
 */
final readonly class ListLessonsAction
{
    public function __construct(private LessonRepository $repo) {}

    /**
     * @return list<LessonSummary>
     */
    public function handle(): array
    {
        $result = [];
        foreach ($this->repo->all() as $lesson) {
            $result[] = new LessonSummary(
                slug: $lesson->slug->value,
                module: $lesson->module->value,
                moduleDisplayName: $lesson->module->displayName(),
                moduleOrder: $lesson->module->order(),
                order: $lesson->order->value,
                title: $lesson->title,
                summary: $lesson->summary,
                playgroundId: $lesson->playgroundId,
            );
        }
        return $result;
    }
}
