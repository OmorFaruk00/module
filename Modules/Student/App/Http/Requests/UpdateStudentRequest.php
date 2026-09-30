<?php

namespace Modules\Student\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email',
        'phone' => 'sometimes|string|max:255',
        'department_id' => 'sometimes|integer',
        'is_active' => 'sometimes|boolean',
        ];
    }
}
