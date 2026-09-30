<?php

namespace Modules\Student\App\Http\Controllers;

use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Modules\Student\App\Http\Requests\StoreStudentRequest;
use Modules\Student\App\Http\Requests\UpdateStudentRequest;
use Modules\Student\App\Http\Resources\StudentResource;
use Modules\Student\App\Services\StudentService;

class StudentController
{
    public function __construct(
        protected StudentService $service
    ) {}

    public function index()
    {
        $data = $this->service->index();

        return ResponseService::success(
            StudentResource::collection($data),
            'Student list retrieved successfully.'
        );
    }

    public function store(StoreStudentRequest $request)
    {
        $data = $this->service->store(
            $request->validated()
        );

        return ResponseService::success(
            new StudentResource($data),
            'Student created successfully.',
            201
        );
    }

    public function show(int $id)
    {
        $data = $this->service->show($id);

        return ResponseService::success(
            new StudentResource($data),
            'Student retrieved successfully.'
        );
    }

    public function update(
        UpdateStudentRequest $request,
        int $id
    ) {
        $data = $this->service->update(
            $id,
            $request->validated()
        );

        return ResponseService::success(
            new StudentResource($data),
            'Student updated successfully.'
        );
    }

    public function destroy(int $id)
    {
        $this->service->destroy($id);

        return ResponseService::success(
            null,
            'Student deleted successfully.'
        );
    }
}
