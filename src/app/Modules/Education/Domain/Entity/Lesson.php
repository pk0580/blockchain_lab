<?php

declare(strict_types=1);

namespace App\Modules\Education\Domain\Entity;

use App\Modules\Education\Domain\ValueObject\LessonModule;
use App\Modules\Education\Domain\ValueObject\LessonOrder;
use App\Modules\Education\Domain\ValueObject\LessonSlug;
use InvalidArgumentException;

/**
 * Урок курса — pure-PHP объект (без БД, без Eloquent). Render'ится из
 * markdown в Infrastructure, в Domain попадает уже как готовый HTML body.
 *
 *  - `module` определяет раздел (вертикальная навигация).
 *  - `order` — позиция внутри модуля.
 *  - `playgroundId` — необязательная привязка к интерактивному playground'у,
 *    который рендерится справа от контента.
 */
final readonly class Lesson
{
    public function __construct(
        public LessonSlug $slug,
        public LessonModule $module,
        public LessonOrder $order,
        public string $title,
        public string $summary,
        public string $bodyHtml,
        public ?string $playgroundId,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException("Урок '{$slug->value}': заголовок не может быть пустым.");
        }
        if (trim($bodyHtml) === '') {
            throw new InvalidArgumentException("Урок '{$slug->value}': bodyHtml не может быть пустым.");
        }
        if ($playgroundId !== null && preg_match('/^[a-z0-9-]+$/', $playgroundId) !== 1) {
            throw new InvalidArgumentException(
                "Урок '{$slug->value}': playgroundId должен быть в формате kebab-case, получено '{$playgroundId}'."
            );
        }
    }
}
