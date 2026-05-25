<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Persistence\Filesystem;

use App\Modules\Education\Domain\Entity\Lesson;
use App\Modules\Education\Domain\Repository\LessonRepository;
use App\Modules\Education\Domain\ValueObject\LessonModule;
use App\Modules\Education\Domain\ValueObject\LessonOrder;
use App\Modules\Education\Domain\ValueObject\LessonSlug;
use League\CommonMark\CommonMarkConverter;
use RuntimeException;
use Spatie\YamlFrontMatter\YamlFrontMatter;

/**
 * Читает уроки с диска: `content/lessons/{NN-module}/{NN-slug}.md`. Уроки
 * статичные — кешируем результат в-инстансе после первого `all()`. Для
 * production cache можно обернуть в `Cache::remember`, но даже 50 markdown'ов
 * парсятся быстрее одного RTT'а к Redis'у.
 *
 * Формат файла:
 *
 *     ---
 *     title: "What is a blockchain?"
 *     summary: "Core mental model — append-only ledger, hash chain, consensus."
 *     playground: "keypair"   # optional
 *     ---
 *
 *     # Heading
 *
 *     Markdown body...
 */
final class MarkdownLessonRepository implements LessonRepository
{
    /** @var list<Lesson>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly string $contentRoot,
        private readonly CommonMarkConverter $converter,
    ) {}

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (! is_dir($this->contentRoot)) {
            throw new RuntimeException("Корневая директория контента уроков не найдена: {$this->contentRoot}");
        }

        $lessons = [];
        $moduleDirs = $this->listDirs($this->contentRoot);
        sort($moduleDirs);

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $module = new LessonModule($moduleName);

            $files = glob($moduleDir.'/*.md') ?: [];
            sort($files);

            foreach ($files as $file) {
                $lessons[] = $this->loadOne($module, $file);
            }
        }

        usort($lessons, function (Lesson $a, Lesson $b): int {
            $byModule = $a->module->order() <=> $b->module->order();
            return $byModule !== 0 ? $byModule : $a->order->value <=> $b->order->value;
        });

        return $this->cache = $lessons;
    }

    public function findBySlug(LessonSlug $slug): ?Lesson
    {
        foreach ($this->all() as $lesson) {
            if ($lesson->slug->value === $slug->value) {
                return $lesson;
            }
        }
        return null;
    }

    private function loadOne(LessonModule $module, string $absolutePath): Lesson
    {
        $filename = basename($absolutePath, '.md');
        if (preg_match('/^(\d{2})-(.+)$/', $filename, $m) !== 1) {
            throw new RuntimeException(
                "Имя файла урока должно соответствовать шаблону 'NN-slug.md', получено '{$filename}.md'."
            );
        }
        $order = new LessonOrder((int) $m[1]);
        $slug = new LessonSlug($m[2]);

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException("Не удалось прочитать файл урока: {$absolutePath}");
        }

        $document = YamlFrontMatter::parse($contents);
        /** @var mixed $titleRaw */
        $titleRaw = $document->matter('title');
        /** @var mixed $summaryRaw */
        $summaryRaw = $document->matter('summary', '');
        /** @var mixed $playgroundRaw */
        $playgroundRaw = $document->matter('playground');

        if (! is_string($titleRaw) || trim($titleRaw) === '') {
            throw new RuntimeException("Урок '{$slug->value}': отсутствует 'title' в метаданных (front matter).");
        }
        $summary = is_string($summaryRaw) ? $summaryRaw : '';
        $playground = is_string($playgroundRaw) && $playgroundRaw !== '' ? $playgroundRaw : null;

        $bodyHtml = $this->converter->convert($document->body())->getContent();

        return new Lesson(
            slug: $slug,
            module: $module,
            order: $order,
            title: $titleRaw,
            summary: $summary,
            bodyHtml: $bodyHtml,
            playgroundId: $playground,
        );
    }

    /**
     * @return list<string>
     */
    private function listDirs(string $root): array
    {
        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }
        $result = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $root.DIRECTORY_SEPARATOR.$entry;
            if (is_dir($path)) {
                $result[] = $path;
            }
        }
        return $result;
    }
}
