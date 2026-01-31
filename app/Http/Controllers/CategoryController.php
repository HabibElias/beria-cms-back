<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();

        foreach ($categories as $cat) {
            $cat['book_count'] = DB::table('books')->where('category_id', '=', $cat->id)->count();
        }

        //
        return response()->json(['status' => true, 'data' => $categories]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $attr = $request->validate(
                [
                    'name' => 'required|min:1|max:200',
                    'description' => 'max:500'
                ]
            );
        } catch (ValidationException $err) {
            //throw $th;
            return response()->json(
                [
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $err->errors()
                ],
                422
            );
        }

        // after validation
        Category::create($attr);

        return response()->json(
            [
                'status' => true,
                'message' => 'Category Created Successfully',
            ],
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
        return response()->json(['status' => true, 'data' => $category]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //
        try {
            $attr = $request->validate(
                [
                    'name' => 'required|min:1|max:200',
                    'description' => 'max:500'
                ]
            );
        } catch (ValidationException $err) {
            //throw $th;
            return response()->json(
                [
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $err->errors()
                ],
                422
            );
        }

        $category->update($attr);
        $category->save();

        return response()->json(
            [
                'status' => true,
                'message' => 'Category Updated Successfully',
            ],
            200
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
        Book::where('category_id', '=', $category->id)->delete();
        $category->delete();

        return response()->json(
            [
                'status' => true,
                'message' => 'Category Delete Successfully',
            ],
            200
        );
    }
}
