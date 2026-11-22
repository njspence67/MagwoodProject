<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class EventRequest extends ApiRequest
{
    public const TYPES = ['note', 'weight', 'birth', 'treatment', 'tag_change', 'paddock_move'];

    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'occurred_on' => ['required', 'string', 'regex:/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if (is_string($value) && str_starts_with($value, '0000-')) {
                    $fail('Use a calendar date with year 0001 or later');
                }
            }],
            'approximate' => ['sometimes', 'required', function ($attribute, $value, $fail) {
                if (! is_bool($value)) {
                    $fail('Use a JSON boolean');
                }
            }],
            'notes' => ['string', 'max:4000'],
            ...$this->sourceRules(),
        ];
        $type = $this->json('type');
        $rules += $type === 'paddock_move' ? [
            'group_id' => ['required', 'string', 'min:1', 'max:100'],
            'from_paddock_id' => ['nullable', 'string', 'min:1', 'max:100'],
            'to_paddock_id' => ['required', 'string', 'min:1', 'max:100'],
        ] : ['animal_id' => ['required', 'string', 'min:1', 'max:100']];

        switch ($type) {
            case 'note':
                $rules['notes'] = ['required', 'string', 'min:1', 'max:4000'];
                break;
            case 'weight':
                $rules['value'] = ['required', function ($attribute, $value, $fail) {
                    if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value <= 0) {
                        $fail('Use a finite positive JSON number');
                    }
                }];
                $rules['unit'] = ['required', 'string', Rule::in(['lb', 'kg'])];
                break;
            case 'birth':
                $rules['calf_id'] = ['nullable', 'string', 'min:1', 'max:100'];
                $rules['calf_description'] = ['required', 'string', 'min:1', 'max:1000'];
                break;
            case 'treatment':
                $rules['product'] = ['required', 'string', 'min:1', 'max:120'];
                $rules['dose_as_entered'] = ['required', 'string', 'min:1', 'max:200'];
                $rules['administered_by'] = ['nullable', 'string', 'min:1', 'max:120'];
                break;
            case 'tag_change':
                $rules['new_tag'] = ['required', 'string', 'min:1', 'max:120'];
                break;
        }

        return $rules;
    }

    protected function validateRelationships(Validator $validator): void
    {
        $this->validateSource($validator);
        $data = $this->validationData();
        if (($data['type'] ?? null) === 'tag_change' && ($data['approximate'] ?? false) === true) {
            $validator->errors()->add('approximate', 'Tag changes require an exact date; use a note for uncertain history');
        }
        if (($data['type'] ?? null) === 'paddock_move'
            && isset($data['from_paddock_id'], $data['to_paddock_id'])
            && $data['from_paddock_id'] === $data['to_paddock_id']) {
            $validator->errors()->add('to_paddock_id', 'The origin and destination paddocks must differ');
        }
    }
}
