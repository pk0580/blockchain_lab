<?php

declare(strict_types=1);

namespace App\Modules\Education\Domain\Repository;

use App\Modules\Education\Domain\Entity\Lesson;
use App\Modules\Education\Domain\ValueObject\LessonSlug;

interface LessonRepository
{
    /**
     * Все уроки в детерминированном порядке: модуль ASC, затем order ASC.
     *
     * @return list<Lesson>
     */
    public function all(): array;

    public function findBySlug(LessonSlug $slug): ?Lesson;
}
