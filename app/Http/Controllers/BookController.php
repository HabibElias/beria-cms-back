<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\Checkout;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        //
        $query = Book::with(['category'])->latest();

        if ($request->has('title')) {
            $query->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($request->get('title')) . '%']);
        }

        if ($request->has('category')) {
            $query->where('category_id', '=', (int) $request->get('category'));
        }

        if ($request->has('status')) {
            if ($request->get('status') === 'available') {
                $query->where('is_available', true);
            } elseif ($request->get('status') === 'checked-out') {
                $query->where('is_available', false);
            }
        }

        return $query->paginate(
            $request->get('per_page', 10)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $attr = $request->validate(
                [
                    'title' => 'required|min:3|max:100',
                    'author' => 'required|min:3|max:100',
                    'description' => 'required|min:3|max:400',
                    'notes' => 'max:400',
                    'pages' => 'required|integer',
                    'location' => 'required|min:1|max:10',
                    'publisher' => 'max:100',
                    'published_year' => 'required|integer:min:0',
                    'category_id' => 'required|integer',
                    'condition' => 'required|in:excellent,good,bad',
                    'book_img' => 'url:http,https',
                    'book_path' => 'max:100'
                ]
            );

            // Proceed with storing book
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        if (!Category::find($attr["category_id"])) {
            return response()->json([
                'status' => false,
                'message' => 'category is not found',
            ], 404);
        }

        Book::create($attr);

        return response()->json([
            'status' => true,
            'message' => 'book created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $book = Book::with('category')->find($id);

        if (!$book) return response()->json(["status" => false, "message" => "No book found with that id", "data" => json_encode([])], 400);

        return response()->json(["status" => true, "message" => "Book found", "data" => $book], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        try {
            $attr = $request->validate(
                [
                    'title' => 'sometimes|required|min:3|max:100',
                    'author' => 'sometimes|required|min:3|max:100',
                    'description' => 'sometimes|required|min:3|max:400',
                    'notes' => 'sometimes|max:400',
                    'pages' => 'sometimes|required|integer',
                    'location' => 'sometimes|required|min:1|max:10',
                    'publisher' => 'sometimes|max:100',
                    'published_year' => 'sometimes|required|integer|min:0',
                    'category_id' => 'sometimes|required|integer',
                    'condition' => 'sometimes|required|in:excellent,good,bad',
                    'book_img' => 'sometimes|url:http,https',
                    'book_path' => 'sometimes|max:100'
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        $book = Book::find($id);

        if (!$book) {
            return response()->json(['status' => false, 'message' => 'book not found'], 400);
        }

        $book->update($attr);

        return response()->json([
            'status' => true,
            'message' => 'book updated successfully',
            'data' => $book
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $book = Book::find($id);

        if (!$book) {
            return response()->json(['status' => false, 'message' => 'book not found'], 400);
        }

        Book::destroy($id);
        $checkouts = Checkout::where('book_id', '=', $id);
        $checkouts?->delete();

        return response()->json(['status' => true, 'message' => 'book deleted successfully'], 200);
    }
}
