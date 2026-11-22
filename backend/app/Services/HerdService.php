<?php

namespace App\Services;

use App\Contracts\HerdRepository;
use App\Exceptions\DomainError;
use Illuminate\Support\Str;

class HerdService
{
    public function __construct(public readonly HerdRepository $repository) {}

    public function getAnimal(string $id): array
    {
        return $this->repository->getAnimal($id)
            ?? throw new DomainError(404, 'animal_not_found', 'Animal not found');
    }

    public function listAnimals(string $query, int $limit, int $offset): array
    {
        $query = self::fold(trim($query));
        $animals = array_values(array_filter($this->repository->listAnimals(), function ($animal) use ($query) {
            $searchable = implode(' ', [
                $animal['id'], $animal['description'], $animal['kind'], $animal['status'],
                $animal['notes'], ...array_column($animal['tags'], 'label'),
            ]);

            return str_contains(self::fold($searchable), $query);
        }));
        usort($animals, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return $this->page($animals, $limit, $offset);
    }

    public function createAnimal(array $data): array
    {
        return $this->repository->transaction(function () use ($data) {
            $data += ['current_tag' => null, 'status' => 'unknown', 'group_id' => null, 'notes' => '', 'source' => null];
            if ($data['group_id'] !== null) {
                $this->requireGroup($data['group_id']);
            }
            $tag = $data['current_tag'];
            unset($data['current_tag']);
            $animal = ['id' => (string) Str::uuid(), ...$data,
                'tags' => $tag === null ? [] : [$this->tag($tag)], 'review_status' => 'unverified'];
            $this->repository->saveAnimal($animal);

            return $animal;
        });
    }

    public function requireGroup(string $id): void
    {
        if (! in_array($id, array_column($this->repository->listGroups(), 'id'), true)) {
            throw new DomainError(404, 'group_not_found', 'Group not found');
        }
    }

    public function getEvent(string $id): array
    {
        return $this->repository->getEvent($id)
            ?? throw new DomainError(404, 'event_not_found', 'Event not found');
    }

    public function listEvents(?string $animalId, ?string $groupId, int $limit, int $offset): array
    {
        if ($animalId !== null && $groupId !== null) {
            throw new DomainError(422, 'invalid_filter', 'Filter by animal_id or group_id, not both');
        }
        if ($animalId !== null) {
            $this->getAnimal($animalId);
        }
        if ($groupId !== null) {
            $this->requireGroup($groupId);
        }
        $events = array_values(array_filter($this->repository->listEvents(), function ($event) use ($animalId, $groupId) {
            $record = $event['record'];

            return ($animalId === null || ($record['animal_id'] ?? null) === $animalId || ($record['calf_id'] ?? null) === $animalId)
                && ($groupId === null || ($record['group_id'] ?? null) === $groupId);
        }));
        usort($events, fn ($a, $b) => [$b['record']['occurred_on'], $b['recorded_at'], $b['id']]
            <=> [$a['record']['occurred_on'], $a['recorded_at'], $a['id']]);

        return $this->page($events, $limit, $offset);
    }

    public function createEvent(array $data): array
    {
        return $this->repository->transaction(function () use ($data) {
            $data += ['approximate' => false, 'notes' => '', 'source' => null];
            if ($data['type'] === 'paddock_move') {
                $data += ['from_paddock_id' => null];
                $this->requireGroup($data['group_id']);
                $paddocks = array_column($this->repository->listPaddocks(), 'id');
                foreach ([$data['from_paddock_id'], $data['to_paddock_id']] as $id) {
                    if ($id !== null && ! in_array($id, $paddocks, true)) {
                        throw new DomainError(404, 'paddock_not_found', 'Paddock not found');
                    }
                }
            } else {
                $animal = $this->getAnimal($data['animal_id']);
                if ($data['type'] === 'birth') {
                    $data += ['calf_id' => null];
                    if ($data['calf_id'] !== null) {
                        $this->getAnimal($data['calf_id']);
                        if ($data['calf_id'] === $animal['id']) {
                            throw new DomainError(422, 'invalid_parent', 'Mother and calf must differ');
                        }
                    }
                }
                if ($data['type'] === 'treatment') {
                    $data += ['administered_by' => null];
                }
                if ($data['type'] === 'tag_change') {
                    $this->changeTag($animal, $data);
                }
            }
            $event = ['id' => (string) Str::uuid(), 'recorded_at' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'), 'record' => $data];
            $this->repository->addEvent($event);

            return $event;
        });
    }

    private function changeTag(array $animal, array $data): void
    {
        foreach ($animal['tags'] as $tag) {
            if ($tag['current'] && self::fold($tag['label']) === self::fold($data['new_tag'])) {
                throw new DomainError(409, 'unchanged_tag', "This is already the animal's current tag");
            }
            foreach ([$tag['valid_from'], $tag['valid_to']] as $date) {
                if ($date !== null && $data['occurred_on'] < $date) {
                    throw new DomainError(409, 'backdated_tag', 'Tag change precedes existing tag history');
                }
            }
        }
        foreach ($animal['tags'] as &$tag) {
            if ($tag['current']) {
                $tag['current'] = false;
                $tag['valid_to'] = $data['occurred_on'];
            }
        }
        unset($tag);
        $animal['tags'][] = $this->tag($data['new_tag'], $data['occurred_on']);
        $this->repository->saveAnimal($animal);
    }

    private function tag(string $label, ?string $from = null): array
    {
        return ['label' => $label, 'valid_from' => $from, 'valid_to' => null, 'current' => true];
    }

    private function page(array $items, int $limit, int $offset): array
    {
        return ['items' => array_slice($items, $offset, $limit), 'total' => count($items), 'limit' => $limit, 'offset' => $offset];
    }

    private static function fold(string $value): string
    {
        return mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
    }
}
