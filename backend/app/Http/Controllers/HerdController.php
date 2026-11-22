<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnimalRequest;
use App\Http\Requests\EventRequest;
use App\Http\Requests\ListRequest;
use App\Services\HerdService;
use Illuminate\Http\JsonResponse;

class HerdController
{
    public function __construct(private readonly HerdService $herd) {}

    public function animals(ListRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->herd->listAnimals($data['q'] ?? '', (int) ($data['limit'] ?? 50), (int) ($data['offset'] ?? 0)));
    }

    public function createAnimal(AnimalRequest $request): JsonResponse
    {
        $animal = $this->herd->createAnimal($request->payload());

        return response()->json($animal, 201, ['Location' => '/api/v1/animals/'.$animal['id']]);
    }

    public function animal(string $id): JsonResponse
    {
        return response()->json($this->herd->getAnimal($id));
    }

    public function events(ListRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->herd->listEvents($data['animal_id'] ?? null, $data['group_id'] ?? null,
            (int) ($data['limit'] ?? 50), (int) ($data['offset'] ?? 0)));
    }

    public function createEvent(EventRequest $request): JsonResponse
    {
        $event = $this->herd->createEvent($request->payload());

        return response()->json($event, 201, ['Location' => '/api/v1/events/'.$event['id']]);
    }

    public function event(string $id): JsonResponse
    {
        return response()->json($this->herd->getEvent($id));
    }

    public function groups(): JsonResponse
    {
        return response()->json($this->herd->repository->listGroups());
    }

    public function paddocks(): JsonResponse
    {
        return response()->json($this->herd->repository->listPaddocks());
    }
}
