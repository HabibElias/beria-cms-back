<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookReturn;
use App\Models\Checkout;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    //

    public function index()
    {

        $total_books_count = DB::table('books')->count();
        $total_members_count = DB::table('users')->count();
        $total_checkout_books = Book::where('is_available', '=', false)->count();
        $overdue_books = Checkout::whereNotNull('return_date')
            ->where('return_date', '<', now())
            ->count();



        // Popular books: most borrowed this month
        $popular_books = DB::table('book_returns')
            ->select('book_id', DB::raw('count(*) as borrow_count'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->groupBy('book_id')
            ->orderByDesc('borrow_count')
            ->limit(5)
            ->get();

        $popular_books = $popular_books->map(function ($row) {
            $row->book = Book::find($row->book_id);
            return $row;
        });




        // Recent activity: combine latest 10 check-outs and returns, ordered by date
        $recent_checkouts = DB::table('checkouts')
            ->select('id', 'book_id', 'user_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $row->type = 'checkout';
                return $row;
            });

        $recent_returns = DB::table('book_returns')
            ->select('id', 'book_id', 'user_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $row->type = 'return';
                return $row;
            });

        $recent_activities = $recent_checkouts->concat($recent_returns)
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->map(function ($row) {
                $row->book = Book::find($row->book_id);
                $row->user = User::find($row->user_id);
                return $row;
            });

        return response()->json(
            [
                'total_books' => $total_books_count,
                'total_members' => $total_members_count,
                'total_checkouts' => $total_checkout_books,
                'overdue_books' => $overdue_books,
                'popular_books' => $popular_books,
                'recent_activities' => $recent_activities,
            ],
            200
        );
    }
}
