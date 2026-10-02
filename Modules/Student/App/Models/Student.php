<?php

namespace Modules\Student\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'department_id',
        'is_active',
        'age',
    ];

    protected static function newFactory()
    {
        return \Modules\Student\Database\Factories\StudentFactory::new();
    }
}
