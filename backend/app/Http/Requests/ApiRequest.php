<?php

namespace App\Http\Requests;

use App\Exceptions\DomainError;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use JsonException;
use stdClass;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isMethod('POST')) {
            return;
        }
        try {
            $body = json_decode($this->getContent(), false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalid('body', 'Use a valid JSON object');
        }
        if (! $this->isJson() || ! $body instanceof stdClass) {
            $this->invalid('body', 'Use an application/json object');
        }
        if (isset($body->source) && ! $body->source instanceof stdClass) {
            $this->invalid('body.source', 'Source must be an object or null');
        }
        $this->replace($this->normalize($body));
    }

    // Query parameters must not override JSON body fields (or GET filters via a body).
    public function validationData(): array
    {
        return $this->isMethod('POST') ? $this->json()->all() : $this->query->all();
    }

    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_replace('/^\s+|\s+$/u', '', $value);
        }
        if ($value instanceof stdClass || is_array($value)) {
            return array_map($this->normalize(...), (array) $value);
        }

        return $value;
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->isMethod('POST')) {
            return;
        }
        $validator->after(function (Validator $validator) {
            // Laravel skips non-implicit rules for empty strings. The API permits
            // empty notes, but never empty identifiers, labels, dates, or integers.
            foreach ($this->rules() as $path => $rules) {
                if (data_get($this->validationData(), $path) === ''
                    && (in_array('min:1', $rules, true) || $path === 'source.row')) {
                    $validator->errors()->add($path, 'This field must not be empty');
                }
            }
            $allowed = array_filter(array_keys($this->rules()), fn ($key) => ! str_contains($key, '.'));
            foreach (array_diff(array_keys($this->validationData()), $allowed) as $key) {
                $validator->errors()->add($key, 'Unexpected field');
            }
            $this->validateRelationships($validator);
        });
    }

    protected function validateRelationships(Validator $validator): void
    {
        $this->validateSource($validator);
    }

    protected function validateSource(Validator $validator): void
    {
        $data = $this->validationData();
        if (isset($data['source'])) {
            foreach (['document', 'original_text'] as $field) {
                if (! array_key_exists($field, $data['source'])) {
                    $validator->errors()->add('source.'.$field, 'This field is required');
                }
            }
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        $fields = [];
        foreach ($validator->errors()->messages() as $path => $messages) {
            foreach ($messages as $message) {
                $fields[] = ['path' => ($this->isMethod('POST') ? 'body.' : 'query.').$path, 'message' => $message];
            }
        }
        throw new DomainError(422, 'validation_error', 'Request validation failed', $fields);
    }

    protected function invalid(string $path, string $message): never
    {
        throw new DomainError(422, 'validation_error', 'Request validation failed', [['path' => $path, 'message' => $message]]);
    }

    protected function sourceRules(): array
    {
        return [
            'source' => ['nullable', 'array:document,sheet,row,original_text'],
            'source.document' => ['required_with:source', 'string', 'min:1', 'max:120'],
            'source.sheet' => ['nullable', 'string', 'min:1', 'max:120'],
            'source.row' => ['nullable', self::strictPositiveInteger()],
            'source.original_text' => [function ($attribute, $value, $fail) {
                if (! is_string($value) || mb_strlen($value) > 4000) {
                    $fail('Original text must be a string of at most 4000 characters');
                }
            }],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        if (isset($data['source'])) {
            $data['source'] += ['sheet' => null, 'row' => null];
        }

        return $data;
    }

    protected static function strictPositiveInteger(): Closure
    {
        return function ($attribute, $value, $fail) {
            if (! is_int($value) || $value <= 0) {
                $fail('Use a positive JSON integer');
            }
        };
    }
}
