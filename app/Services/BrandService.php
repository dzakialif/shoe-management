<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    // get all
    public function all(): Collection
    {
        return Brand::all();
    }

    // find by id
    public function findById(string $id): Brand
    {
        return Brand::findOrFail($id);
    }

    // create
    public function create(array $data): Brand
    {
        return Brand::create($data);
    }

    // update
    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->refresh();
    }

    // delete
    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}