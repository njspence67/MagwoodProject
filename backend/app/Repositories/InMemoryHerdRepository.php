<?php

namespace App\Repositories;

use App\Contracts\HerdRepository;
use LogicException;
use Throwable;

/** Isolated test storage. PHP arrays have value semantics; no mutable objects are stored. */
class InMemoryHerdRepository implements HerdRepository
{
    public function __construct(protected array $state = [
        'animals' => [], 'events' => [], 'groups' => [], 'paddocks' => [],
    ]) {}

    public function transaction(callable $operation): mixed
    {
        $before = $this->state;
        try {
            return $operation();
        } catch (Throwable $e) {
            $this->state = $before;
            throw $e;
        }
    }

    public function listAnimals(): array
    {
        return array_values($this->state['animals']);
    }

    public function getAnimal(string $id): ?array
    {
        return $this->state['animals'][$id] ?? null;
    }

    public function saveAnimal(array $animal): void
    {
        $this->state['animals'][$animal['id']] = $animal;
    }

    public function listEvents(): array
    {
        return array_values($this->state['events']);
    }

    public function getEvent(string $id): ?array
    {
        return $this->state['events'][$id] ?? null;
    }

    public function addEvent(array $event): void
    {
        if (isset($this->state['events'][$event['id']])) {
            throw new LogicException('Event ID already exists');
        }
        $this->state['events'][$event['id']] = $event;
    }

    public function listGroups(): array
    {
        return array_values($this->state['groups']);
    }

    public function listPaddocks(): array
    {
        return array_values($this->state['paddocks']);
    }
}
