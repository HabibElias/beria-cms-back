<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReturn;
use App\Models\Checkout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Checkout::with(
            [
                'book',
                'user'
            ]
        )->latest();

        return $query->paginate(
            $request->get('per_page', 10)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        try {

            $attr = $request->validate(
                [
                    'book_id' => 'required|integer',
                    'user_id' => 'required|integer',
                    'return_date' => [
                        'required',
                        'date',
                        'after:' . now()->addWeek()->toDateString()
                    ]
                ]
            );

            $user = User::find($attr['user_id']);

            $book = Book::find($attr['book_id']);
            $bookAttr = $book?->attributesToArray();

            // checking if book is available
            if (!$book || !$bookAttr['is_available'])
                return response()->json(
                    ['status' => false, 'message' => 'Book is not available'],
                    400
                );
            else $bookAttr['is_available'] = false;

            // checking if user is available
            if (!$user)
                return response()->json(
                    ['status' => false, 'message' => 'No user for this checkout'],
                    400
                );

            // checking if user is within checking out rights
            if (count($user->checkouts) >= 3) {
                return response()->json(
                    ['status' => false, 'message' => 'User should return remaining checkouts'],
                    403
                );
            }

            $book->update($bookAttr);

            $checkout = Checkout::create($attr);

            return response()->json(
                ['status' => true, 'message' => 'Checkout created', 'data' => $checkout]
            );
        } catch (ValidationException $err) {
            return response()->json(
                ['status' => false, 'message' => 'validation errors', 'errors' => $err->errors()],
                422
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Checkout $checkout)
    {
        //
        if (!$checkout)
            return response()->json(['status' => false, 'message' => 'checkout not found']);

        return response()->json(['status' => true, 'message' => 'checkout found', 'data' => $checkout]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Checkout $checkout)
    {
        if (!$checkout) {
            return response()->json(
                [
                    'status' => false,
                    'message' => 'Checkout not found',
                ],
                404
            );
        }



        $checkoutAttr = $checkout->attributesToArray();

        if ($checkoutAttr['renewal_number'] >= 3)
            return response()->json(
                [
                    'status' => false,
                    'message' => 'Checkout is not renewable',
                ],
                403
            );
        else if (strtotime($checkoutAttr['return_date']) > strtotime(now()->toDateString())) {
            return response()->json(
                [
                    'status' => false,
                    'message' => 'Checkout is not renewable',
                ],
                403
            );
        } else {
            $checkoutAttr['renewal_number']++;
            $checkoutAttr['return_date'] =  now()->addWeek()->toDateString();
        }

        $checkout->update($checkoutAttr);
        $checkout->save();

        return response()->json(
            [
                'status' => true,
                'message' => 'Checkout Renewal Successful',
            ],
            200
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Checkout $checkout)
    {
        //
        if (!$checkout)
            return response()->json(['status' => false, 'message' => 'checkout not found']);

        // first store it in return
        $attr = $checkout->attributesToArray();
        BookReturn::create(array_slice($attr, 1));


        // make the book available
        $book = Book::find($attr['book_id']);
        $book->is_available = true;
        $book->save();

        $checkout->delete();
        return response()->json(['status' => true, 'message' => 'checkout deleted']);
    }
}
