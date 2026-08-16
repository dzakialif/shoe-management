<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BrandService
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

        return Brand::query()
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    // get all
    public function all(): Collection
    {
        return Brand::all();
    }

    // get id + name (untuk dropdown / options)
    public function options(): Collection
    {
        return Brand::orderBy('name')->get(['id', 'name']);
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