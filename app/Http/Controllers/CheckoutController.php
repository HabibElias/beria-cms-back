<?php

namespace App\Http\Controllers;

use App\Models\Checkout;
use App\Services\BorrowingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        return Checkout::with(['book', 'user'])->latest()->latest('id')->paginate(min(100, max(1, (int) $request->get('per_page', 10))));
    }

    public function store(Request $request, BorrowingService $borrowing)
    {
        $attributes = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'return_date' => ['required', 'date', 'after:'.now()->addWeek()->toDateString()],
        ]);
        $checkout = $borrowing->issue((int) $attributes['book_id'], (int) $attributes['user_id'], $attributes['return_date']);

        return response()->json(['status' => true, 'message' => 'Checkout created', 'data' => $checkout], 201);
    }

    public function show(Checkout $checkout)
    {
        return response()->json(['status' => true, 'data' => $checkout]);
    }

    public function update(Checkout $checkout)
    {
        DB::transaction(function () use ($checkout) {
            $checkout = Checkout::query()->lockForUpdate()->findOrFail($checkout->id);
            abort_if($checkout->renewal_number >= 3 || strtotime($checkout->return_date) > strtotime(now()->toDateString()), 403, 'This checkout is not renewable yet, or has reached its renewal limit.');
            $checkout->update(['renewal_number' => $checkout->renewal_number + 1, 'return_date' => now()->addWeek()->toDateString()]);
        });

        return response()->json(['status' => true, 'message' => 'Checkout renewed successfully.']);
    }

    public function destroy(Checkout $checkout, BorrowingService $borrowing)
    {
        $borrowing->returnBook($checkout);

        return response()->json(['status' => true, 'message' => 'Book returned successfully.']);
    }
}
