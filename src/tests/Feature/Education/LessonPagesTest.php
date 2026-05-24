<?php

declare(strict_types=1);

namespace Tests\Feature\Education;

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // В feature-тестах vite manifest не собран; рендерить @vite() нечем.
    // withoutVite() подменяет helper'ы пустыми строками.
    $this->withoutVite();
});

it('renders Home with the lesson list', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('lessons', fn (Assert $lessons) => $lessons->etc())
        );
});

it('renders the Lessons index', function (): void {
    $this->get('/lessons')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lessons/Index')
            ->has('lessons.0', fn (Assert $l) => $l
                ->where('module', '01-foundations')
                ->etc()
            )
        );
});

it('renders an individual lesson with rendered html body', function (): void {
    $this->get('/lessons/what-is-blockchain')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lessons/Show')
            ->where('lesson.slug', 'what-is-blockchain')
            ->where('lesson.module', '01-foundations')
            ->where('lesson.order', 1)
            ->has('lesson.body_html')
            ->has('lessons')
        );
});

it('exposes playground id when frontmatter declares it', function (): void {
    $this->get('/lessons/keypairs-and-signatures')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('lesson.playground_id', 'keypair')
            ->etc()
        );
});

it('returns 404 for an unknown lesson slug', function (): void {
    $this->get('/lessons/nope-not-here')->assertNotFound();
});

it('rejects route param that violates slug shape', function (): void {
    $this->get('/lessons/BAD_SLUG')->assertNotFound();
});
