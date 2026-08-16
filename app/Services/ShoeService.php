<?php

namespace App\Services;

use App\Models\Shoe;
use Illuminate\Database\Eloquent\Collection;

class ShoeService
{
    // get all
    public function all(): Collection
    {
        return Shoe::all();
    }

    // find by id
    public function findById(string $id): Shoe
    {
        return Shoe::findOrFail($id);
    }

    // create
    public function create(array $data): Shoe
    {
        return Shoe::create($data);
    }

    // update
    public function update(Shoe $shoe, array $data): Shoe
    {
        $shoe->update($data);

        return $shoe->refresh();
    }

    // delete
    public function delete(Shoe $shoe): void
    {
        $shoe->delete();
    }
}