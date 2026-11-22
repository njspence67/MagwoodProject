<?php

namespace App\Repositories;

/** Illustrative fixtures, never an import or a verified client roster. */
final class DemoData
{
    public static function state(): array
    {
        $animals = [
            [
                'id' => 'animal-020', 'kind' => 'cow',
                'description' => 'Illustrative cow with a former tag',
                'status' => 'unknown', 'group_id' => 'group-a',
                'tags' => [self::tag('#8 White', false), self::tag('#20 White')],
                'notes' => 'Prototype example only. Identity and status need confirmation.',
            ],
            [
                'id' => 'animal-calf-01', 'kind' => 'calf',
                'description' => 'Illustrative untagged brown calf with a white face',
                'status' => 'unknown', 'group_id' => null, 'tags' => [],
                'notes' => 'No mother confirmed. Sample record only.',
            ],
            [
                'id' => 'animal-1347', 'kind' => 'bull',
                'description' => 'Historical bull example from the frontend prototype',
                'status' => 'deceased', 'group_id' => null, 'tags' => [self::tag('#1347')],
                'notes' => 'Sample historical record; not a verified roster entry.',
            ],
        ];
        foreach ($animals as &$animal) {
            $animal += ['source' => null, 'review_status' => 'unverified'];
        }
        unset($animal);

        return [
            'animals' => array_column($animals, null, 'id'), 'events' => [],
            'groups' => [['id' => 'group-a', 'name' => 'Group A (sample)']],
            'paddocks' => array_map(fn ($n) => ['id' => "paddock-$n", 'name' => "Paddock $n"], range(1, 8)),
        ];
    }

    public static function tag(string $label, bool $current = true, ?string $from = null): array
    {
        return ['label' => $label, 'valid_from' => $from, 'valid_to' => null, 'current' => $current];
    }
}
