<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $academicPrograms = config('academic_programs', []);
        $departments = array_keys($academicPrograms);
        $allPrograms = collect($academicPrograms)->flatten()->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'student_id' => [
                'required', 'string', 'size:11',
                'regex:/^[A-Za-z]\d{10}$/',
                Rule::unique('users', 'student_id'),
            ],
            'email'      => ['required', 'email', 'unique:users,email'],
            'department' => ['required', 'string', Rule::in($departments)],
            'program'    => ['required', 'string', Rule::in($allPrograms)],
            'level'      => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Cross-field check: the chosen program must actually belong to the
     * chosen department, not just be a valid value from some department.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $academicPrograms = config('academic_programs', []);
            $department = $this->input('department');
            $program = $this->input('program');

            if ($department && $program && ! in_array($program, $academicPrograms[$department] ?? [], true)) {
                $validator->errors()->add('program', 'Selected program does not belong to the selected department.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'student_id.regex' => 'Student ID must be 1 letter followed by 10 digits (e.g. P1152302037).',
            'student_id.size'  => 'Student ID must be exactly 11 characters.',
            'student_id.unique' => 'This Student ID is already registered.',
            'email.unique' => 'This email is already registered.',
            'department.in' => 'Please select a valid department.',
            'program.in' => 'Please select a valid program.',
        ];
    }
}
