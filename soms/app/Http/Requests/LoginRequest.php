<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'string', 'size:11', 'regex:/^[A-Za-z]\d{10}$/'],
            'password'   => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.regex' => 'Student ID must be 1 letter followed by 10 digits (e.g. P1152302037).',
            'student_id.size'  => 'Student ID must be exactly 11 characters.',
        ];
    }
}