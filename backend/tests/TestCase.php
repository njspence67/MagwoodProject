<?php

namespace Tests;

use App\Contracts\HerdRepository;
use App\Repositories\DemoData;
use App\Repositories\InMemoryHerdRepository;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

abstract class TestCase extends LaravelTestCase
{
    protected InMemoryHerdRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new InMemoryHerdRepository(DemoData::state());
        $this->app->instance(HerdRepository::class, $this->repository);
    }

    protected function note(array $changes = []): array
    {
        return array_replace([
            'type' => 'note', 'animal_id' => 'animal-020', 'occurred_on' => '2026-11-20',
            'notes' => 'Observed in pasture',
        ], $changes);
    }

    protected function tagChange(array $changes = []): array
    {
        return array_replace([
            'type' => 'tag_change', 'animal_id' => 'animal-020', 'occurred_on' => '2026-11-20',
            'new_tag' => '#200 Yellow',
        ], $changes);
    }
}
