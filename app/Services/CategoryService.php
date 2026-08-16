<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    // get all
    public function all(): Collection
    {
        return Category::all();
    }

    // find by id
    public function findById(string $id): Category
    {
        return Category::findOrFail($id);
    }

    // create category
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    // update category
    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    // delete category
    public function delete(Category $category): void
    {
        $category->delete();
    }
}