<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookReturn;
use App\Models\Checkout;
use App\Models\LoanRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorrowingService
{
    public function issue(int $bookId, int $userId, string $returnDate): Checkout
    {
        return DB::transaction(fn () => $this->createCheckout($bookId, $userId, $returnDate));
    }

    public function review(LoanRequest $request, User $admin, bool $approve, ?string $returnDate, ?string $note): LoanRequest
    {
        return DB::transaction(function () use ($request, $admin, $approve, $returnDate, $note) {
            $request = LoanRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === 'pending', 409, 'This request has already been resolved.');

            $checkout = $approve
                ? $this->createCheckout($request->book_id, $request->user_id, $returnDate ?? now()->addWeeks(2)->toDateString())
                : null;
            $request->update([
                'status' => $approve ? 'approved' : 'declined',
                'pending_key' => null,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'checkout_id' => $checkout?->id,
            ]);

            return $request->load(['book.category', 'user', 'checkout']);
        });
    }

    public function returnBook(Checkout $checkout): void
    {
        DB::transaction(function () use ($checkout) {
            $checkout = Checkout::query()->lockForUpdate()->findOrFail($checkout->id);
            $book = Book::query()->lockForUpdate()->findOrFail($checkout->book_id);
            BookReturn::create($checkout->only(['book_id', 'user_id', 'return_date', 'renewal_number']));
            LoanRequest::query()->where('checkout_id', $checkout->id)->update(['checkout_id' => null]);
            $checkout->delete();
            $book->update(['is_available' => ! Checkout::query()->where('book_id', $book->id)->exists()]);
        });
    }

    /** Called only inside an issue/review transaction. */
    private function createCheckout(int $bookId, int $userId, string $returnDate): Checkout
    {
        $user = User::query()->lockForUpdate()->findOrFail($userId);
        $book = Book::query()->lockForUpdate()->findOrFail($bookId);
        if ($user->checkouts()->count() >= 3) {
            throw ValidationException::withMessages(['user_id' => 'This reader already has three books on loan.']);
        }
        abort_if(Checkout::query()->where('book_id', $book->id)->exists(), 409, 'This book is already on loan.');
        $claimed = Book::query()->whereKey($book->id)->where('is_available', true)->update(['is_available' => false]);
        abort_unless($claimed === 1, 409, 'This book is no longer available.');

        return Checkout::create(['book_id' => $book->id, 'user_id' => $user->id, 'return_date' => $returnDate]);
    }
}
