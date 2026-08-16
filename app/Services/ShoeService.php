<?php

namespace App\Services;

use App\Models\Shoe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ShoeService
{

    private const SORTABLE = ['name', 'price', 'stock', 'created_at', 'updated_at'];

    public function paginated(Request $request): LengthAwarePaginator
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id:asc');
        $perPage = max($request->integer('perPage', 10), 1);

        [$sortBy, $sortDir] = explode(':', $sort . ':asc');
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'id';

        return Shoe::query()
            ->with(['category', 'brand'])
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('shoes.name', 'like', "%{$search}%")
                        ->orWhere('shoes.description', 'like', "%{$search}%" )
                        ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn($b) => $b->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

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