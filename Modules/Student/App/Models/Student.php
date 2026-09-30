<?php

namespace Modules\Student\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'department_id',
        'is_active'
    ];

    protected static function newFactory()
    {
        return \Modules\Student\Database\Factories\StudentFactory::new();
    }
}
