<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Checkout;
use App\Models\LoanRequest;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Throwable;

class MemberLibraryTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'libsql',
            'database.connections.libsql.database' => ':memory:',
            'database.connections.libsql.turso_url' => null,
            'database.connections.libsql.password' => null,
        ]);
        Artisan::call('migrate', ['--force' => true]);
        $this->category = Category::create(['name' => 'Theology']);
    }

    private function reader(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'user', 'phone' => '0900000000', 'password' => 'Member-password-1234'], $attributes));
    }

    private function book(array $attributes = []): Book
    {
        return Book::factory()->create(array_merge(['category_id' => $this->category->id, 'is_available' => true], $attributes));
    }

    private function requestBook(User $user, Book $book): LoanRequest
    {
        $response = $this->actingAs($user)->postJson('/api/loan-requests', ['book_id' => $book->id]);
        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        return LoanRequest::findOrFail($response->json('data.id'));
    }

    public function test_guests_browse_search_and_filter_without_private_inventory_data(): void
    {
        $book = $this->book(['title' => 'Knowing God', 'author' => 'J. I. Packer', 'notes' => 'Private acquisition note.', 'book_path' => 'private/asset', 'location' => 'A-01']);
        $this->book(['title' => 'On Loan', 'is_available' => false]);
        $this->getJson('/api/catalogue/books?q=PACKER&status=available&category='.$this->category->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $book->id)
            ->assertJsonMissingPath('data.0.notes')->assertJsonMissingPath('data.0.book_path')->assertJsonMissingPath('data.0.location');
        $this->getJson('/api/catalogue/books/'.$book->id)->assertOk()->assertJsonPath('data.title', 'Knowing God')->assertJsonMissingPath('data.notes')->assertJsonMissingPath('data.condition');
        $this->getJson('/api/catalogue/categories')->assertOk()->assertJsonPath('data.0.book_count', 2);
        $this->getJson('/api/catalogue/books?per_page=1000')->assertUnprocessable();
        $this->getJson('/api/books')->assertUnauthorized();
        $this->postJson('/api/loan-requests', ['book_id' => $book->id])->assertUnauthorized();
    }

    public function test_registration_creates_a_member_and_returns_a_working_token(): void
    {
        $credentials = ['name' => 'New Reader', 'email' => 'Reader@Example.test', 'phone' => '0911000000', 'password' => 'Member-password-1234', 'password_confirmation' => 'Member-password-1234'];
        $response = $this->postJson('/api/register', $credentials)->assertCreated()->assertJsonPath('user.role', 'user')->assertJsonPath('user.email', 'reader@example.test')->assertJsonMissingPath('user.password');
        $this->assertTrue(Hash::check($credentials['password'], User::sole()->password));
        $this->withToken($response->json('token'))->getJson('/api/my-loans')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/login', ['email' => 'READER@example.test', 'password' => $credentials['password']])->assertOk()->assertJsonPath('user.role', 'user');
        $this->postJson('/api/register', $credentials)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_registration_rejects_privilege_escalation_and_short_or_unconfirmed_passwords(): void
    {
        $attributes = ['name' => 'New Reader', 'email' => 'new@example.test', 'phone' => '0911000000', 'password' => 'password1234', 'password_confirmation' => 'password1234'];
        $this->postJson('/api/register', $attributes + ['role' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/register', array_replace($attributes, ['password' => 'password123', 'password_confirmation' => 'password123']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/register', array_replace($attributes, ['password_confirmation' => 'does-not-match']))->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_non_admin_roles_can_sign_in_but_cannot_use_any_management_api(): void
    {
        foreach (['user', 'librarian'] as $role) {
            $reader = $this->reader(['role' => $role]);
            $this->postJson('/api/login', ['email' => $reader->email, 'password' => 'Member-password-1234'])->assertOk()->assertJsonPath('user.role', $role);
            $this->actingAs($reader);
            foreach (['books', 'members', 'checkouts', 'categories', 'analytics', 'admin/loan-requests'] as $path) {
                $this->getJson('/api/'.$path)->assertForbidden();
            }
            foreach (['books', 'members', 'checkouts', 'categories'] as $path) {
                $this->postJson('/api/'.$path, [])->assertForbidden();
            }
            $this->getJson('/api/my-loans')->assertOk();
        }
    }

    public function test_request_uses_the_authenticated_reader_and_does_not_reserve_the_book(): void
    {
        $reader = $this->reader();
        $other = $this->reader();
        $book = $this->book();
        $response = $this->actingAs($reader)->postJson('/api/loan-requests', ['book_id' => $book->id, 'user_id' => $other->id, 'status' => 'approved', 'message' => 'I can collect this week.']);
        $response->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonMissingPath('data.user')->assertJsonMissingPath('data.pending_key');
        $this->assertSame($reader->id, LoanRequest::sole()->user_id);
        $this->assertTrue((bool) $book->fresh()->is_available);
        $this->assertDatabaseCount('checkouts', 0);
        $this->postJson('/api/loan-requests', ['book_id' => $book->id])->assertConflict();
        $this->actingAs($other)->getJson('/api/loan-requests')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/my-loans')->assertJsonCount(0, 'data');
    }

    public function test_member_profile_updates_cannot_change_role_and_management_mutations_are_denied(): void
    {
        $reader = $this->reader();
        $book = $this->book();
        $this->actingAs($reader)->postJson('/api/user/profile', ['name' => 'Updated Reader', 'email' => $reader->email, 'role' => 'admin'])
            ->assertOk()->assertJsonPath('user.role', 'user');
        $this->patchJson('/api/members/'.$reader->id, ['role' => 'admin'])->assertForbidden();
        $this->deleteJson('/api/members/'.$reader->id)->assertForbidden();
        $this->patchJson('/api/books/'.$book->id, ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson('/api/books/'.$book->id)->assertForbidden();
        $this->deleteJson('/api/categories/'.$this->category->id)->assertForbidden();
        $this->assertSame('user', $reader->fresh()->role);
        $this->assertNotNull($book->fresh());
    }

    public function test_admin_created_accounts_require_an_explicit_valid_password(): void
    {
        $admin = $this->reader(['role' => 'admin']);
        $attributes = ['name' => 'New Member', 'email' => 'New@Example.test', 'phone' => '0911000000', 'role' => 'user'];
        $this->actingAs($admin)->postJson('/api/members', $attributes)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/members', $attributes + ['password' => str_repeat('ሀ', 25), 'password_confirmation' => str_repeat('ሀ', 25)])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/members', $attributes + ['password' => 'Member-password-1234', 'password_confirmation' => 'Member-password-1234'])->assertCreated();
        $created = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('user', $created->role);
        $this->assertTrue(Hash::check('Member-password-1234', $created->password));
        $this->postJson('/api/login', ['email' => 'new@example.test', 'password' => 'Member-password-1234'])->assertOk()->assertJsonPath('user.role', 'user');
    }

    public function test_approval_checkout_return_and_re_request_form_one_integrated_flow(): void
    {
        $reader = $this->reader();
        $admin = $this->reader(['role' => 'admin']);
        $book = $this->book();
        $request = $this->requestBook($reader, $book);
        $this->postJson('/api/admin/loan-requests/'.$request->id.'/approve')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/loan-requests?status=pending')->assertOk()->assertJsonPath('data.0.user.email', $reader->email);
        $response = $this->postJson('/api/admin/loan-requests/'.$request->id.'/approve', ['return_date' => now()->addWeeks(2)->toDateString(), 'admin_note' => 'Please collect at the library.']);
        $response->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertFalse((bool) $book->fresh()->is_available);
        $this->assertDatabaseCount('checkouts', 1);
        $this->assertNull($request->fresh()->pending_key);
        $this->postJson('/api/admin/loan-requests/'.$request->id.'/approve')->assertConflict();
        $this->actingAs($reader)->getJson('/api/my-loans')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.book.id', $book->id)->assertJsonMissingPath('data.0.book.notes');
        $this->getJson('/api/loan-requests')->assertJsonPath('data.0.admin_note', 'Please collect at the library.');
        $checkout = Checkout::sole();
        $this->deleteJson('/api/checkouts/'.$checkout->id)->assertForbidden();
        $this->actingAs($admin)->deleteJson('/api/checkouts/'.$checkout->id)->assertOk();
        $this->assertTrue((bool) $book->fresh()->is_available);
        $this->assertNull($request->fresh()->checkout_id);
        $this->assertDatabaseCount('book_returns', 1);
        $this->requestBook($reader, $book);
        $this->assertDatabaseCount('loan_requests', 2);
    }

    public function test_cancellation_and_decline_preserve_ownership_and_resolved_history(): void
    {
        $reader = $this->reader();
        $other = $this->reader();
        $admin = $this->reader(['role' => 'admin']);
        $book = $this->book();
        $first = $this->requestBook($reader, $book);
        $this->actingAs($other)->deleteJson('/api/loan-requests/'.$first->id)->assertNotFound();
        $this->actingAs($reader)->deleteJson('/api/loan-requests/'.$first->id)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $second = $this->requestBook($reader, $book);
        $this->actingAs($admin)->postJson('/api/admin/loan-requests/'.$second->id.'/decline', ['admin_note' => 'The copy needs a repair.'])->assertOk()->assertJsonPath('data.status', 'declined');
        $this->postJson('/api/admin/loan-requests/'.$second->id.'/approve')->assertConflict();
        $this->actingAs($reader)->deleteJson('/api/loan-requests/'.$second->id)->assertConflict();
        $this->getJson('/api/loan-requests?status=declined')->assertOk()->assertJsonPath('data.0.admin_note', 'The copy needs a repair.');
        $this->assertDatabaseCount('checkouts', 0);
        $this->assertTrue((bool) $book->fresh()->is_available);
    }

    public function test_two_requests_cannot_loan_the_same_copy_twice(): void
    {
        $book = $this->book();
        $first = $this->requestBook($this->reader(), $book);
        $second = $this->requestBook($this->reader(), $book);
        $admin = $this->reader(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/api/admin/loan-requests/'.$first->id.'/approve')->assertOk();
        $this->postJson('/api/admin/loan-requests/'.$second->id.'/approve')->assertConflict();
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertDatabaseCount('checkouts', 1);
        $this->postJson('/api/checkouts', ['book_id' => $book->id, 'user_id' => $admin->id, 'return_date' => now()->addWeeks(2)->toDateString()])->assertConflict();
        $this->requestBook($this->reader(), $this->book());
    }

    public function test_request_and_approval_limits_are_enforced_without_partial_changes(): void
    {
        $reader = $this->reader();
        $requests = [];
        foreach (range(1, 3) as $index) {
            $requests[] = $this->requestBook($reader, $this->book());
        }
        $this->postJson('/api/loan-requests', ['book_id' => $this->book()->id])->assertUnprocessable();
        $this->deleteJson('/api/loan-requests/'.$requests[1]->id)->assertOk();
        $this->deleteJson('/api/loan-requests/'.$requests[2]->id)->assertOk();
        foreach (range(1, 3) as $index) {
            $book = $this->book(['is_available' => false]);
            Checkout::create(['book_id' => $book->id, 'user_id' => $reader->id, 'return_date' => now()->addWeeks(2)]);
        }
        $this->actingAs($this->reader(['role' => 'admin']))->postJson('/api/admin/loan-requests/'.$requests[0]->id.'/approve')->assertUnprocessable();
        $this->assertSame('pending', $requests[0]->fresh()->status);
        $this->assertTrue((bool) $requests[0]->book->fresh()->is_available);
        $this->assertDatabaseCount('checkouts', 3);
    }

    public function test_a_failed_checkout_insert_rolls_back_approval_and_availability(): void
    {
        $reader = $this->reader();
        $admin = $this->reader(['role' => 'admin']);
        $book = $this->book();
        $request = $this->requestBook($reader, $book);
        DB::statement("CREATE TRIGGER fail_loan_insert BEFORE INSERT ON checkouts BEGIN SELECT RAISE(ABORT, 'test loan failure'); END");
        $failure = null;
        try {
            app(BorrowingService::class)->review($request, $admin, true, null, null);
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        $this->assertNotNull($failure);
        $this->assertStringContainsString('test loan failure', $failure->getMessage());
        $this->assertTrue((bool) $book->fresh()->is_available);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertDatabaseCount('checkouts', 0);
    }
}
