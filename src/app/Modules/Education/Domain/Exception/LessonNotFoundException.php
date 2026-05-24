<?php

declare(strict_types=1);

namespace App\Modules\Education\Domain\Exception;

use App\Modules\Education\Domain\ValueObject\LessonSlug;
use DomainException;

final class LessonNotFoundException extends DomainException
{
    public static function bySlug(LessonSlug $slug): self
    {
        return new self("Lesson with slug '{$slug->value}' was not found.");
    }
}
