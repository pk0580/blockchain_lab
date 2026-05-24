<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Education;

use App\Modules\Education\Domain\ValueObject\LessonSlug;
use App\Modules\Education\Infrastructure\Persistence\Filesystem\MarkdownLessonRepository;
use League\CommonMark\CommonMarkConverter;
use RuntimeException;

function writeLesson(string $root, string $module, string $filename, string $contents): void
{
    $dir = $root.'/'.$module;
    if (! is_dir($dir)) {
        mkdir($dir, 0o755, recursive: true);
    }
    file_put_contents($dir.'/'.$filename, $contents);
}

function makeRepo(string $root): MarkdownLessonRepository
{
    return new MarkdownLessonRepository(
        contentRoot: $root,
        converter: new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]),
    );
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/edu_'.uniqid('', true);
    mkdir($this->root, 0o755, recursive: true);
});

afterEach(function (): void {
    /** @var iterable<\SplFileInfo> $iterator */
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->root);
});

it('loads a lesson with frontmatter and renders markdown', function (): void {
    writeLesson($this->root, '01-foundations', '01-what-is-blockchain.md', <<<'MD'
---
title: "What is a blockchain?"
summary: "Mental model."
playground: keypair
---

# Hello

A *paragraph* with **strong** text.
MD);

    $repo = makeRepo($this->root);
    $lesson = $repo->findBySlug(new LessonSlug('what-is-blockchain'));

    expect($lesson)->not->toBeNull();
    expect($lesson?->title)->toBe('What is a blockchain?');
    expect($lesson?->module->value)->toBe('01-foundations');
    expect($lesson?->order->value)->toBe(1);
    expect($lesson?->playgroundId)->toBe('keypair');
    expect($lesson?->bodyHtml)->toContain('<h1>Hello</h1>');
    expect($lesson?->bodyHtml)->toContain('<strong>strong</strong>');
});

it('returns null for an unknown slug', function (): void {
    writeLesson($this->root, '01-foundations', '01-intro-page.md', "---\ntitle: intro\n---\nbody");
    expect(makeRepo($this->root)->findBySlug(new LessonSlug('not-here')))->toBeNull();
});

it('orders lessons by module then by file prefix', function (): void {
    writeLesson($this->root, '02-bitcoin', '01-utxo-model.md', "---\ntitle: u\n---\nb");
    writeLesson($this->root, '01-foundations', '02-keypairs-and-signatures.md', "---\ntitle: k\n---\nb");
    writeLesson($this->root, '01-foundations', '01-intro-page.md', "---\ntitle: i\n---\nb");

    $all = makeRepo($this->root)->all();

    expect($all)->toHaveCount(3);
    expect($all[0]->slug->value)->toBe('intro-page');
    expect($all[1]->slug->value)->toBe('keypairs-and-signatures');
    expect($all[2]->slug->value)->toBe('utxo-model');
});

it('throws when filename has no numeric prefix', function (): void {
    writeLesson($this->root, '01-foundations', 'no-prefix-here.md', "---\ntitle: x\n---\nbody");
    expect(fn () => makeRepo($this->root)->all())
        ->toThrow(RuntimeException::class);
});

it('throws when title is missing from frontmatter', function (): void {
    writeLesson($this->root, '01-foundations', '01-no-title-lesson.md', "---\nsummary: nope\n---\nbody");
    expect(fn () => makeRepo($this->root)->all())
        ->toThrow(RuntimeException::class);
});

it('caches subsequent all() calls', function (): void {
    writeLesson($this->root, '01-foundations', '01-cached-lesson.md', "---\ntitle: x\n---\nbody");
    $repo = makeRepo($this->root);
    $first = $repo->all();
    // Delete file underneath; cached result must still be returned.
    unlink($this->root.'/01-foundations/01-cached-lesson.md');
    expect($repo->all())->toBe($first);
});
