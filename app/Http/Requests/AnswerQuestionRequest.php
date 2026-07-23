<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnswerQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'between:10,10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'answer.required'      => 'Answer text is required.',
            'answer.between'       => 'Answer must be 10–10 000 characters.',
        ];
    }
}