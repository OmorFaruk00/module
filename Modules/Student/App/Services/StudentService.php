<?php

namespace Modules\Student\App\Services;

use Modules\Student\App\Repositories\Contracts\StudentRepositoryInterface;

class StudentService
{
    public function __construct(
        protected StudentRepositoryInterface $repository
    ) {}

    public function index()
    {
        return $this->repository->all();
    }

    public function show(int $id)
    {
        return $this->repository->findOrFail($id);
    }

    public function store(array $data)
    {
        return $this->repository->create($data);
    }

    public function update(int $id, array $data)
    {
        return $this->repository->update($id, $data);
    }

    public function destroy(int $id)
    {
        return $this->repository->delete($id);
    }
}
