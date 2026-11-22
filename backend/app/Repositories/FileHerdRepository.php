<?php

namespace App\Repositories;

use LogicException;
use RuntimeException;
use Throwable;

/** Local demo storage across PHP requests. Replace with the team's database adapter. */
class FileHerdRepository extends InMemoryHerdRepository
{
    private bool $inTransaction = false;

    public function __construct(private readonly string $path)
    {
        parent::__construct();
    }

    public function transaction(callable $operation): mixed
    {
        if ($this->inTransaction) {
            throw new LogicException('Nested file transactions are not supported');
        }
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create demo storage directory');
        }
        // Lock a separate, stable inode: the data file is replaced atomically at commit.
        $lock = fopen($this->path.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open demo storage lock');
        }
        $temporary = null;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock demo storage');
            }
            $this->state = $this->readState();
            $before = $this->state;
            $this->inTransaction = true;
            $result = $operation();
            if ($before !== $this->state) {
                $json = json_encode($this->state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                $temporary = tempnam($directory, '.herd-');
                if ($temporary === false) {
                    throw new RuntimeException('Cannot create demo transaction file');
                }
                if (file_put_contents($temporary, $json) !== strlen($json)) {
                    throw new RuntimeException('Cannot write complete demo transaction');
                }
                if (! rename($temporary, $this->path)) {
                    throw new RuntimeException('Cannot commit demo transaction');
                }
                $temporary = null;
            }

            return $result;
        } catch (Throwable $e) {
            $this->state = $before ?? [];
            throw $e;
        } finally {
            $this->inTransaction = false;
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readState(): array
    {
        if (! is_file($this->path)) {
            return DemoData::state();
        }
        $json = file_get_contents($this->path);
        if ($json === false) {
            throw new RuntimeException('Cannot read demo storage');
        }
        $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        foreach (['animals', 'events', 'groups', 'paddocks'] as $key) {
            if (! is_array($state[$key] ?? null)) {
                throw new RuntimeException('Invalid demo storage; restore from a known copy');
            }
        }

        return $state;
    }

    private function read(callable $operation): mixed
    {
        return $this->inTransaction ? $operation() : $this->transaction($operation);
    }

    public function listAnimals(): array
    {
        return $this->read(fn () => parent::listAnimals());
    }

    public function getAnimal(string $id): ?array
    {
        return $this->read(fn () => parent::getAnimal($id));
    }

    public function listEvents(): array
    {
        return $this->read(fn () => parent::listEvents());
    }

    public function getEvent(string $id): ?array
    {
        return $this->read(fn () => parent::getEvent($id));
    }

    public function listGroups(): array
    {
        return $this->read(fn () => parent::listGroups());
    }

    public function listPaddocks(): array
    {
        return $this->read(fn () => parent::listPaddocks());
    }

    public function saveAnimal(array $animal): void
    {
        if (! $this->inTransaction) {
            throw new LogicException('Writes require a transaction');
        }
        parent::saveAnimal($animal);
    }

    public function addEvent(array $event): void
    {
        if (! $this->inTransaction) {
            throw new LogicException('Writes require a transaction');
        }
        parent::addEvent($event);
    }
}
