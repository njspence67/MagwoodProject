<?php

namespace Tests\Feature;

use App\Contracts\HerdRepository;
use App\Repositories\InMemoryHerdRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HerdApiTest extends TestCase
{
    private const ANIMALS = '/api/v1/animals';

    private const EVENTS = '/api/v1/events';

    public function test_search_matches_current_and_former_tags_and_description(): void
    {
        foreach (['#20 WHITE', ' #8 white ', 'former tag'] as $query) {
            $this->getJson(self::ANIMALS.'?'.http_build_query(['q' => $query]))
                ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.id', 'animal-020');
        }
        $this->getJson(self::ANIMALS.'?q=no-such-tag')->assertJsonPath('total', 0);
    }

    public function test_pagination_is_stable_and_bounded(): void
    {
        $first = $this->getJson(self::ANIMALS.'?limit=1')->assertOk()->assertJsonPath('total', 3);
        $second = $this->getJson(self::ANIMALS.'?limit=1&offset=1')->assertJsonPath('total', 3);
        self::assertNotSame($first->json('items.0.id'), $second->json('items.0.id'));
        $this->getJson(self::ANIMALS.'?offset=100')->assertJsonPath('items', []);
        foreach (['limit=0', 'limit=101', 'offset=-1', 'limit[]=2', 'offset=no', 'animal_id='] as $query) {
            $this->getJson(self::EVENTS.'?'.$query)->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
        }
    }

    public function test_create_and_retrieve_an_untagged_animal(): void
    {
        $response = $this->postJson(self::ANIMALS, ['kind' => 'calf', 'description' => '  Brown calf  ', 'group_id' => 'group-a'])
            ->assertCreated()->assertJsonPath('tags', [])->assertJsonPath('description', 'Brown calf')
            ->assertJsonPath('review_status', 'unverified')->assertJsonPath('status', 'unknown')->assertJsonPath('source', null);
        $this->getJson($response->headers->get('Location'))->assertExactJson($response->json());
    }

    public function test_tags_are_reusable_labels_not_primary_keys(): void
    {
        $data = ['kind' => 'cow', 'description' => 'Sample', 'current_tag' => '#20 White'];
        $first = $this->postJson(self::ANIMALS, $data)->assertCreated();
        $second = $this->postJson(self::ANIMALS, $data)->assertCreated();
        self::assertNotSame($first->json('id'), $second->json('id'));
        $this->getJson(self::ANIMALS.'?q=%2320%20White')->assertJsonPath('total', 3);
    }

    public function test_animal_creation_rejects_unknown_group_and_user_assigned_id(): void
    {
        $data = ['kind' => 'calf', 'description' => 'Sample'];
        $this->postJson(self::ANIMALS, $data + ['group_id' => 'missing'])->assertNotFound();
        $this->postJson(self::ANIMALS, $data + ['id' => 'chosen'])->assertStatus(422);
        $this->getJson(self::ANIMALS)->assertJsonPath('total', 3);
    }

    public function test_note_preserves_approximation_and_source(): void
    {
        $source = ['document' => 'Sample.xlsx', 'sheet' => 'Roster', 'row' => 8, 'original_text' => 'Observed around November 20'];
        $response = $this->postJson(self::EVENTS, $this->note(['approximate' => true, 'source' => $source]))
            ->assertCreated()->assertJsonPath('record.approximate', true)->assertJsonPath('record.source', $source);
        self::assertStringEndsWith('Z', $response->json('recorded_at'));
        $this->getJson($response->headers->get('Location'))->assertExactJson($response->json());
    }

    public static function invalidEvents(): array
    {
        return array_map(fn ($change) => [$change], [
            ['occurred_on' => '2026-02-30'], ['occurred_on' => '11/20/2026'],
            ['occurred_on' => '2026-11-20T00:00:00Z'], ['occurred_on' => 0], ['occurred_on' => null],
            ['occurred_on' => '0000-01-01'], ['notes' => '   '], ['notes' => null],
            ['approximate' => 'false'], ['approximate' => ''], ['approximate' => 0], ['approximate' => null],
            ['unexpected' => true], ['type' => 'invented_event'], ['type' => []], ['animal_id' => ' '],
            ['source' => []], ['source' => (object) []],
            ['source' => ['document' => 'sample', 'original_text' => '', 'row' => true]],
            ['source' => ['document' => 'sample', 'original_text' => '', 'row' => '8']],
            ['source' => ['document' => 'sample', 'original_text' => '', 'row' => '']],
            ['source' => ['document' => 'sample', 'original_text' => '', 'unexpected' => 1]],
            ['source' => ['document' => 'sample']],
        ]);
    }

    #[DataProvider('invalidEvents')]
    public function test_invalid_event_fields_never_write_records(array $change): void
    {
        $this->postJson(self::EVENTS, $this->note($change))->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
        $this->getJson(self::EVENTS)->assertJsonPath('total', 0);
    }

    public function test_validation_does_not_echo_private_values_and_requires_json_objects(): void
    {
        $response = $this->postJson(self::EVENTS, $this->note(['occurred_on' => 'invalid', 'notes' => 'Private note']))->assertStatus(422);
        self::assertStringNotContainsString('Private note', $response->getContent());
        self::assertNotEmpty($response->json('error.fields'));
        foreach (['[]', 'null', '{"type":'] as $body) {
            $this->call('POST', self::EVENTS, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(422);
        }
        $this->post(self::EVENTS, $this->note())->assertStatus(422);
    }

    public function test_optional_strings_remain_distinct_from_null_and_empty(): void
    {
        foreach (['current_tag', 'group_id'] as $field) {
            $this->postJson(self::ANIMALS, ['kind' => 'calf', 'description' => 'Sample', $field => ' '])->assertStatus(422);
        }
        $this->postJson(self::ANIMALS, ['kind' => 'calf', 'description' => 'Sample', 'notes' => null])->assertStatus(422);
        $this->postJson(self::ANIMALS, ['kind' => 'calf', 'description' => 'Sample', 'notes' => ''])
            ->assertCreated()->assertJsonPath('notes', '');
        $this->postJson(self::EVENTS, $this->note(['source' => ['document' => 'Sample', 'original_text' => '']]))
            ->assertCreated()->assertJsonPath('record.source.sheet', null)->assertJsonPath('record.source.row', null);
    }

    public function test_weight_requires_a_positive_json_number_and_explicit_unit(): void
    {
        $data = ['type' => 'weight', 'animal_id' => 'animal-020', 'occurred_on' => '2026-11-20'];
        foreach ([['value' => -1, 'unit' => 'lb'], ['value' => 0, 'unit' => 'kg'],
            ['value' => true, 'unit' => 'lb'], ['value' => '500', 'unit' => 'lb'],
            ['value' => 500], ['value' => 500, 'unit' => 'buckets']] as $extra) {
            $this->postJson(self::EVENTS, $data + $extra)->assertStatus(422);
        }
        $this->postJson(self::EVENTS, $data + ['value' => 500.5, 'unit' => 'lb'])
            ->assertCreated()->assertJsonPath('record.value', 500.5);
        $this->postJson(self::EVENTS, $data + ['value' => 500, 'unit' => 'kg'])->assertCreated();
    }

    public function test_missing_animal_does_not_create_an_orphan_event(): void
    {
        $this->postJson(self::EVENTS, $this->note(['animal_id' => 'missing']))->assertNotFound();
        $this->getJson(self::EVENTS)->assertJsonPath('total', 0);
    }

    public function test_history_sorts_by_occurrence_and_filters_before_paginating(): void
    {
        foreach (['2026-11-20', '2026-01-01', '2026-11-21'] as $day) {
            $this->postJson(self::EVENTS, $this->note(['occurred_on' => $day]))->assertCreated();
        }
        $this->postJson(self::EVENTS, $this->note(['animal_id' => 'animal-calf-01']))->assertCreated();
        $this->getJson(self::EVENTS.'?animal_id=animal-020&limit=2')->assertJsonPath('total', 3)
            ->assertJsonPath('items.0.record.occurred_on', '2026-11-21')->assertJsonPath('items.1.record.occurred_on', '2026-11-20');
        $this->getJson(self::EVENTS.'?animal_id=missing')->assertNotFound();
        $this->getJson(self::EVENTS.'?group_id=missing')->assertNotFound();
        $this->getJson(self::EVENTS.'?animal_id=animal-020&group_id=group-a')->assertStatus(422)->assertJsonPath('error.code', 'invalid_filter');
    }

    public function test_tag_change_preserves_identity_and_searchable_history(): void
    {
        $this->postJson(self::EVENTS, $this->tagChange())->assertCreated();
        $animal = $this->getJson(self::ANIMALS.'/animal-020')->assertJsonPath('id', 'animal-020')->json();
        self::assertSame(['#200 Yellow'], array_values(array_column(array_filter($animal['tags'], fn ($tag) => $tag['current']), 'label')));
        self::assertSame('2026-11-20', $animal['tags'][1]['valid_to']);
        self::assertNull($animal['tags'][0]['valid_from']);
        foreach (['#8 White', '#20 White', '#200 Yellow'] as $label) {
            $this->getJson(self::ANIMALS.'?'.http_build_query(['q' => $label]))->assertJsonPath('items.0.id', 'animal-020');
        }
    }

    public function test_rejected_tag_changes_leave_history_unchanged_and_same_day_changes_work(): void
    {
        $this->postJson(self::EVENTS, $this->tagChange(['approximate' => true]))->assertStatus(422);
        $this->postJson(self::EVENTS, $this->tagChange())->assertCreated();
        $before = $this->getJson(self::ANIMALS.'/animal-020')->json();
        $this->postJson(self::EVENTS, $this->tagChange(['new_tag' => '#200 YELLOW']))->assertStatus(409)->assertJsonPath('error.code', 'unchanged_tag');
        $this->postJson(self::EVENTS, $this->tagChange(['occurred_on' => '2026-11-19', 'new_tag' => '#201 Yellow']))->assertStatus(409);
        $this->getJson(self::EVENTS)->assertJsonPath('total', 1);
        $this->getJson(self::ANIMALS.'/animal-020')->assertExactJson($before);
        $this->postJson(self::EVENTS, $this->tagChange(['new_tag' => '#201 Yellow']))->assertCreated();
        $this->getJson(self::ANIMALS.'/animal-020')->assertJsonPath('tags.2.valid_to', '2026-11-20')->assertJsonPath('tags.3.current', true);
    }

    public function test_birth_is_visible_in_mother_and_linked_calf_histories(): void
    {
        $data = ['type' => 'birth', 'animal_id' => 'animal-020', 'occurred_on' => '2026-11-20',
            'calf_id' => 'animal-calf-01', 'calf_description' => 'Brown calf', 'approximate' => true];
        $this->postJson(self::EVENTS, array_replace($data, ['calf_id' => 'missing']))->assertNotFound();
        $this->postJson(self::EVENTS, array_replace($data, ['calf_id' => 'animal-020']))->assertStatus(422);
        $this->postJson(self::EVENTS, $data)->assertCreated();
        foreach (['animal-020', 'animal-calf-01'] as $id) {
            $this->getJson(self::EVENTS.'?animal_id='.$id)->assertJsonPath('total', 1);
        }
        $this->getJson(self::ANIMALS)->assertJsonPath('total', 3);
        unset($data['calf_id']);
        $this->postJson(self::EVENTS, $data)->assertCreated()->assertJsonPath('record.calf_id', null);
    }

    public function test_treatment_retains_entered_details_without_inventing_a_schedule(): void
    {
        $response = $this->postJson(self::EVENTS, ['type' => 'treatment', 'animal_id' => 'animal-020',
            'occurred_on' => '2026-11-20', 'product' => 'Illustrative product', 'dose_as_entered' => 'As recorded on source sheet'])
            ->assertCreated()->assertJsonPath('record.dose_as_entered', 'As recorded on source sheet')->assertJsonPath('record.administered_by', null);
        self::assertArrayNotHasKey('next_due', $response->json('record'));
    }

    public function test_paddock_moves_require_known_references_and_do_not_infer_membership(): void
    {
        $data = ['type' => 'paddock_move', 'group_id' => 'group-a', 'occurred_on' => '2026-11-20',
            'from_paddock_id' => 'paddock-1', 'to_paddock_id' => 'paddock-2'];
        foreach (['group_id', 'from_paddock_id', 'to_paddock_id'] as $field) {
            $this->postJson(self::EVENTS, array_replace($data, [$field => 'missing']))->assertNotFound();
        }
        $this->postJson(self::EVENTS, array_replace($data, ['to_paddock_id' => 'paddock-1']))->assertStatus(422);
        $this->postJson(self::EVENTS, $data)->assertCreated();
        $this->getJson(self::EVENTS.'?group_id=group-a')->assertJsonPath('total', 1);
        $this->getJson(self::EVENTS.'?animal_id=animal-020')->assertJsonPath('total', 0);
        $this->getJson('/api/v1/paddocks')->assertJsonCount(8);
        $this->getJson('/api/v1/groups')->assertJsonCount(1);
        unset($data['from_paddock_id']);
        $this->postJson(self::EVENTS, $data)->assertCreated()->assertJsonPath('record.from_paddock_id', null);
    }

    public function test_unknown_resources_and_methods_have_consistent_json_errors(): void
    {
        foreach (['/missing', self::ANIMALS.'/missing', self::EVENTS.'/missing'] as $path) {
            $this->get($path)->assertNotFound()->assertJsonStructure(['error' => ['code', 'message', 'fields']]);
        }
        $this->deleteJson(self::ANIMALS.'/animal-020')->assertStatus(405)->assertHeader('Allow');
    }

    public function test_cors_allowlist_and_rejected_preflights(): void
    {
        $headers = ['Origin' => 'http://localhost:4173', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'content-type'];
        $this->options(self::EVENTS, [], $headers)->assertOk()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4173');
        $this->options(self::EVENTS, [], array_replace($headers, ['Origin' => 'https://unrelated.example']))->assertStatus(400)->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->options(self::EVENTS, [], array_replace($headers, ['Access-Control-Request-Method' => 'DELETE']))->assertStatus(400);
        $this->getJson(self::ANIMALS, ['Origin' => 'http://localhost:4173'])->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4173');
        config(['herd.cors_origins' => ['https://frontend.example']]);
        $this->options(self::EVENTS, [], $headers)->assertStatus(400);
        $this->options(self::EVENTS, [], array_replace($headers, ['Origin' => 'https://frontend.example']))->assertOk();
    }

    public function test_openapi_and_local_docs_describe_all_six_event_types(): void
    {
        $response = $this->get('/openapi.json')->assertOk();
        // BinaryFileResponse is used so large contracts need not be copied into a controller.
        $schema = json_decode(file_get_contents($response->baseResponse->getFile()->getPathname()), true, 512, JSON_THROW_ON_ERROR);
        $body = $schema['paths'][self::EVENTS]['post']['requestBody']['content']['application/json']['schema'];
        self::assertCount(6, $body['oneOf']);
        self::assertSame('type', $body['discriminator']['propertyName']);
        self::assertArrayHasKey('ErrorResponse', $schema['components']['schemas']);
        $this->get('/docs')->assertOk()->assertSee('Magwood API');
        $this->getJson('/health')->assertJsonPath('status', 'ok');
    }

    public function test_storage_can_be_replaced_without_changing_the_api(): void
    {
        $this->postJson(self::EVENTS, $this->note())->assertCreated();
        $this->app->instance(HerdRepository::class, new InMemoryHerdRepository);
        $this->getJson(self::ANIMALS)->assertJsonPath('total', 0);
        $this->getJson(self::EVENTS)->assertJsonPath('total', 0);
    }

    public function test_production_cannot_accidentally_serve_the_unauthenticated_demo(): void
    {
        $this->app->forgetInstance(HerdRepository::class);
        $this->app->instance('env', 'production');
        $this->getJson(self::ANIMALS)->assertStatus(503)->assertJsonPath('error.code', 'storage_not_configured');
        $this->getJson('/health')->assertOk();
    }

    public function test_query_parameters_cannot_override_json_input(): void
    {
        $this->postJson(self::EVENTS.'?animal_id=missing&type=weight', $this->note())->assertCreated()->assertJsonPath('record.type', 'note');
        $data = $this->note();
        unset($data['animal_id']);
        $this->postJson(self::EVENTS.'?animal_id=animal-020', $data)->assertStatus(422);
    }
}
