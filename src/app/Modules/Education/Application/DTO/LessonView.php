<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO;

/**
 * Полная проекция Lesson'а для show-страницы. bodyHtml уже отрендерен из
 * markdown в Infrastructure-слое; frontend выводит его через `v-html`.
 */
final readonly class LessonView
{
    public function __construct(
        public string $slug,
        public string $module,
        public string $moduleDisplayName,
        public int $moduleOrder,
        public int $order,
        public string $title,
        public string $summary,
        public string $bodyHtml,
        public ?string $playgroundId,
    ) {}

    /**
     * @return array{slug:string, module:string, module_display_name:string, module_order:int, order:int, title:string, summary:string, body_html:string, playground_id:?string}
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
            'body_html' => $this->bodyHtml,
            'playground_id' => $this->playgroundId,
        ];
    }
}
