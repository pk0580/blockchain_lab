<?php

declare(strict_types=1);

namespace App\Modules\Education\Domain\ValueObject;

use InvalidArgumentException;

/**
 * URL-friendly идентификатор урока. Слаг живёт в URL'е (`/lessons/{slug}`)
 * и в frontmatter'е markdown-файла — поэтому charset узкий: `[a-z0-9-]`,
 * без uppercase / underscore / точек. Заодно это автоматически совпадает
 * с conventionalным именованием файлов в `content/lessons/`.
 */
final readonly class LessonSlug
{
    public const int MIN_LEN = 3;
    public const int MAX_LEN = 80;

    public function __construct(public string $value)
    {
        $len = strlen($value);
        if ($len < self::MIN_LEN || $len > self::MAX_LEN) {
            throw new InvalidArgumentException(
                "LessonSlug length must be ".self::MIN_LEN."..".self::MAX_LEN
                .", got {$len}."
            );
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "LessonSlug must be kebab-case `[a-z0-9-]`, got '{$value}'."
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
