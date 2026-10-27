<?php

namespace App\Http\Controllers;

use App\Http\Resources\CatalogueBookResource;
use App\Http\Resources\LoanRequestResource;
use App\Models\Book;
use App\Models\Checkout;
use App\Models\LoanRequest;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'declined', 'cancelled'])],
            'book_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $query = LoanRequest::query()->where('user_id', $request->user()->id)->with(['book.category', 'checkout'])->latest('id');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['book_id'])) {
            $query->where('book_id', $filters['book_id']);
        }

        return LoanRequestResource::collection($query->paginate($filters['per_page'] ?? 12));
    }

    public function store(Request $request)
    {
        $attributes = $request->validate(['book_id' => ['required', 'integer', 'exists:books,id'], 'message' => ['nullable', 'string', 'max:400']]);
        try {
            $loanRequest = DB::transaction(function () use ($request, $attributes) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                $book = Book::query()->lockForUpdate()->findOrFail($attributes['book_id']);
                $key = $user->id.':'.$book->id;
                abort_if(LoanRequest::query()->where('pending_key', $key)->exists(), 409, 'You already have a pending request for this book.');
                abort_unless($book->is_available && ! Checkout::query()->where('book_id', $book->id)->exists(), 409, 'This book is currently on loan. Please check back after it is returned.');
                if ($user->checkouts()->count() + LoanRequest::query()->where('user_id', $user->id)->where('status', 'pending')->count() >= 3) {
                    throw ValidationException::withMessages(['book_id' => 'You can have up to three current loans and pending requests.']);
                }

                return LoanRequest::create([
                    'user_id' => $user->id, 'book_id' => $book->id, 'pending_key' => $key,
                    'status' => 'pending', 'message' => $attributes['message'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'You already have a pending request for this book.');
        }

        return (new LoanRequestResource($loanRequest->load(['book.category', 'checkout'])))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, LoanRequest $loanRequest)
    {
        abort_unless((int) $loanRequest->user_id === (int) $request->user()->id, 404);
        $changed = LoanRequest::query()->whereKey($loanRequest->id)->where('status', 'pending')->update(['status' => 'cancelled', 'pending_key' => null]);
        abort_unless($changed === 1, 409, 'Only a pending request can be cancelled.');

        return new LoanRequestResource($loanRequest->fresh()->load(['book.category', 'checkout']));
    }

    public function adminIndex(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'declined', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $query = LoanRequest::query()->with(['book.category', 'user', 'checkout'])->latest('id');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.mb_strtolower(trim($filters['q'])).'%';
            $query->where(fn ($q) => $q->whereHas('book', fn ($b) => $b->whereRaw('LOWER(title) LIKE ?', [$term]))
                ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term])));
        }

        return LoanRequestResource::collection($query->paginate($filters['per_page'] ?? 12));
    }

    public function approve(Request $request, LoanRequest $loanRequest, BorrowingService $borrowing)
    {
        $attributes = $request->validate([
            'return_date' => ['nullable', 'date', 'after:'.now()->addWeek()->toDateString()],
            'admin_note' => ['nullable', 'string', 'max:400'],
        ]);

        return new LoanRequestResource($borrowing->review($loanRequest, $request->user(), true, $attributes['return_date'] ?? null, $attributes['admin_note'] ?? null));
    }

    public function decline(Request $request, LoanRequest $loanRequest, BorrowingService $borrowing)
    {
        $attributes = $request->validate(['admin_note' => ['nullable', 'string', 'max:400']]);

        return new LoanRequestResource($borrowing->review($loanRequest, $request->user(), false, null, $attributes['admin_note'] ?? null));
    }

    public function myLoans(Request $request)
    {
        return response()->json(['data' => $request->user()->checkouts()->with('book.category')->orderBy('return_date')->get()->map(fn ($loan) => [
            'id' => $loan->id, 'book' => new CatalogueBookResource($loan->book),
            'return_date' => $loan->return_date, 'renewal_number' => $loan->renewal_number,
        ])]);
    }
}
