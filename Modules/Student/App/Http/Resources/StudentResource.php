<?php

namespace Modules\Student\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department_id' => $this->department_id,
            'is_active' => $this->is_active,
            'age' => $this->age,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
