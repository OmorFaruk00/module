<?php

namespace Modules\Student\App\Repositories;

use Modules\Student\App\Models\Student;
use Modules\Student\App\Repositories\Contracts\StudentRepositoryInterface;

class StudentRepository implements StudentRepositoryInterface
{
    public function __construct(
        protected Student $model
    ) {}

    public function all()
    {
        return $this->model
            ->latest()
            ->paginate(15);
    }

    public function findOrFail(int $id)
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data)
    {
        $model = $this->findOrFail($id);

        $model->update($data);

        return $model->fresh();
    }

    public function delete(int $id)
    {
        $model = $this->findOrFail($id);

        return $model->delete();
    }
}
