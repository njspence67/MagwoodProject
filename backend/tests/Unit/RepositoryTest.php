<?php

namespace Tests\Unit;

use App\Repositories\DemoData;
use App\Repositories\FileHerdRepository;
use App\Repositories\InMemoryHerdRepository;
use App\Services\HerdService;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/test-'.Str::uuid());
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_returned_arrays_cannot_mutate_storage(): void
    {
        $animal = $this->repository->getAnimal('animal-020');
        $animal['tags'] = [];
        self::assertCount(2, $this->repository->getAnimal('animal-020')['tags']);
    }

    public function test_memory_transaction_rolls_back_both_writes_on_failure(): void
    {
        $repository = new class(DemoData::state()) extends InMemoryHerdRepository
        {
            public function addEvent(array $event): void
            {
                parent::addEvent($event);
                throw new RuntimeException('Simulated write failure');
            }
        };
        $before = $repository->getAnimal('animal-020');
        try {
            (new HerdService($repository))->createEvent($this->tagChange());
            self::fail('Expected storage failure');
        } catch (RuntimeException $e) {
            self::assertSame('Simulated write failure', $e->getMessage());
        }
        self::assertSame($before, $repository->getAnimal('animal-020'));
        self::assertSame([], $repository->listEvents());
    }

    public function test_file_storage_survives_new_instances_and_preserves_failed_transaction(): void
    {
        $path = $this->directory.'/demo.json';
        $repository = new FileHerdRepository($path);
        $event = (new HerdService($repository))->createEvent($this->tagChange());
        $other = new FileHerdRepository($path);
        self::assertSame($event, $other->getEvent($event['id']));
        $before = file_get_contents($path);
        try {
            $other->transaction(function () use ($other) {
                $animal = $other->getAnimal('animal-020');
                $animal['tags'] = [];
                $other->saveAnimal($animal);
                $other->addEvent(['id' => 'failed', 'record' => $this->note()]);
                throw new RuntimeException('Simulated transaction failure');
            });
            self::fail('Expected failure');
        } catch (RuntimeException $e) {
            self::assertSame('Simulated transaction failure', $e->getMessage());
        }
        self::assertSame($before, file_get_contents($path));
        self::assertNull($other->getEvent('failed'));
        self::assertCount(3, $other->getAnimal('animal-020')['tags']);
    }

    public function test_duplicate_event_ids_are_not_overwritten(): void
    {
        $service = new HerdService($this->repository);
        $event = $service->createEvent($this->note());
        try {
            $this->repository->transaction(fn () => $this->repository->addEvent($event));
            self::fail('Expected duplicate rejection');
        } catch (\LogicException $e) {
            self::assertSame('Event ID already exists', $e->getMessage());
        }
        self::assertSame([$event], $this->repository->listEvents());
    }

    public function test_corrupt_storage_is_not_silently_reseeded(): void
    {
        $path = $this->directory.'/demo.json';
        file_put_contents($path, '{broken');
        try {
            (new FileHerdRepository($path))->listAnimals();
            self::fail('Expected corrupted file rejection');
        } catch (\JsonException) {
            self::assertSame('{broken', file_get_contents($path));
        }
    }

    public function test_independent_php_processes_serialize_tag_changes_without_losing_events(): void
    {
        $path = $this->directory.'/demo.json';
        $workers = [];
        try {
            for ($number = 0; $number < 8; $number++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/tag_worker.php'), $path, (string) $number], base_path());
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $worker;
            }
            foreach ($workers as $worker) {
                $worker->wait();
                self::assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
        $repository = new FileHerdRepository($path);
        self::assertCount(8, $repository->listEvents());
        self::assertCount(8, array_unique(array_column($repository->listEvents(), 'id')));
        $tags = $repository->getAnimal('animal-020')['tags'];
        self::assertCount(10, $tags);
        self::assertCount(1, array_filter($tags, fn ($tag) => $tag['current']));
        self::assertCount(8, array_unique(array_column(array_slice($tags, 2), 'label')));
    }

    public function test_tied_event_dates_use_recorded_time_then_id_for_order(): void
    {
        foreach ([['id' => 'a', 'recorded_at' => '2026-11-20T10:00:00.000000Z'],
            ['id' => 'b', 'recorded_at' => '2026-11-20T10:00:00.000000Z'],
            ['id' => 'c', 'recorded_at' => '2026-11-20T11:00:00.000000Z']] as $event) {
            $this->repository->addEvent($event + ['record' => $this->note()]);
        }
        $page = (new HerdService($this->repository))->listEvents(null, null, 50, 0);
        self::assertSame(['c', 'b', 'a'], array_column($page['items'], 'id'));
    }
}
