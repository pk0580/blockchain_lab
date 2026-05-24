<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO;

/**
 * Лёгкая проекция Lesson'а для index-страницы (без bodyHtml). Хранит только
 * navigation-данные; bodyHtml попадает на frontend только при показе урока.
 */
final readonly class LessonSummary
{
    public function __construct(
        public string $slug,
        public string $module,
        public string $moduleDisplayName,
        public int $moduleOrder,
        public int $order,
        public string $title,
        public string $summary,
        public ?string $playgroundId,
    ) {}

    /**
     * @return array{slug:string, module:string, module_display_name:string, module_order:int, order:int, title:string, summary:string, playground_id:?string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'module' => $this->module,
            'module_display_name' => $this->moduleDisplayName,
            'module_order' => $this->moduleOrder,
            'order' => $this->order,
            'title' => $this->title,
            'summary' => $this->summary,
            'playground_id' => $this->playgroundId,
        ];
    }
}
