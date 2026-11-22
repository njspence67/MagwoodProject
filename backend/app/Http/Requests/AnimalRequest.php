<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AnimalRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(['cow', 'bull', 'heifer', 'steer', 'calf'])],
            'description' => ['required', 'string', 'min:1', 'max:1000'],
            'current_tag' => ['nullable', 'string', 'min:1', 'max:120'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'sold', 'deceased', 'unknown'])],
            'group_id' => ['nullable', 'string', 'min:1', 'max:100'],
            'notes' => ['string', 'max:4000'],
            ...$this->sourceRules(),
        ];
    }
}
