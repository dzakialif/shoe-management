<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CategoryService
{

    private const SORTABLE = ['name', 'created_at', 'updated_at'];

    public function paginated(Request $request): LengthAwarePaginator
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id:asc');
        $perPage = max($request->integer('perPage', 10), 1);

        [$sortBy, $sortDir] = explode(':', $sort . ':asc');
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'id';

        return Category::query()
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    // get all
    public function all(): Collection
    {
        return Category::all();
    }

    // get id + name (untuk dropdown / options)
    public function options(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
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