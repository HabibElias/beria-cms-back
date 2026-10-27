<?php

namespace App\Http\Controllers;

use App\Http\Resources\CatalogueBookResource;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['available', 'checked-out'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $books = Book::query()->with('category')->orderBy('title')->orderBy('id');
        if (! empty($filters['q'])) {
            $term = '%'.mb_strtolower(trim($filters['q'])).'%';
            $books->where(fn ($query) => $query->whereRaw('LOWER(title) LIKE ?', [$term])->orWhereRaw('LOWER(author) LIKE ?', [$term]));
        }
        if (! empty($filters['category'])) {
            $books->where('category_id', $filters['category']);
        }
        if (! empty($filters['status'])) {
            $books->where('is_available', $filters['status'] === 'available');
        }

        return CatalogueBookResource::collection($books->paginate($filters['per_page'] ?? 12));
    }

    public function show(Book $book)
    {
        return new CatalogueBookResource($book->load('category'));
    }

    public function categories()
    {
        return response()->json(['data' => Category::query()->withCount(['books as book_count'])->orderBy('name')->get(['id', 'name', 'description'])]);
    }
}
