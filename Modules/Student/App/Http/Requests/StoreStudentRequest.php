<?php

namespace Modules\Student\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        'email' => 'required|email',
        'phone' => 'required|string|max:255',
        'department_id' => 'required|integer',
        'is_active' => 'required|boolean',
        ];
    }
}
