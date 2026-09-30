<?php

namespace Modules\Student\App\Repositories\Contracts;

interface StudentRepositoryInterface
{
    public function all();

    public function findOrFail(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);
}
