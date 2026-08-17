<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'between:10,2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required'      => 'Question text is required.',
            'content.between'       => 'Question must be 10–2000 characters.',
        ];
    }
}
