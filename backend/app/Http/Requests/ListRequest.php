<?php

namespace App\Http\Requests;

class ListRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'q' => ['string', 'max:200'],
            'limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'required', 'integer', 'min:0'],
            'animal_id' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'group_id' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
        ];
    }
}
