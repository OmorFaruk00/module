<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\App\Http\Controllers\StudentController;

Route::apiResource(
    'students',
    StudentController::class
);
