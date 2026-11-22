<?php

namespace App\Contracts;

interface HerdRepository
{
    /** Commit writes together; roll back failures; serialize conflicting writes. */
    public function transaction(callable $operation): mixed;

    /** Return independent arrays; unknown IDs return null. */
    public function listAnimals(): array;

    public function getAnimal(string $id): ?array;

    public function saveAnimal(array $animal): void;

    public function listEvents(): array;

    public function getEvent(string $id): ?array;

    /** Append only; an existing event ID must never be overwritten. */
    public function addEvent(array $event): void;

    public function listGroups(): array;

    public function listPaddocks(): array;
}
