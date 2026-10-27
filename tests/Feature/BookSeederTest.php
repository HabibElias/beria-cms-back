<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Checkout;
use App\Models\User;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class BookSeederTest extends TestCase
{
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
    }

    public function test_catalogue_has_complete_edition_metadata_and_valid_isbns(): void
    {
        $catalogue = json_decode(file_get_contents(database_path('data/library-catalogue.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(16, $catalogue['books']);
        $seen = [];

        foreach ($catalogue['books'] as $entry) {
            $this->assertArrayHasKey($entry['category'], CategorySeeder::CATEGORIES);
            $this->assertMatchesRegularExpression('/^97[89][0-9]{10}$/', $entry['isbn']);
            $checksum = 0;
            foreach (str_split($entry['isbn']) as $index => $digit) {
                $checksum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
            }
            $this->assertSame(0, $checksum % 10, $entry['title'].' must have a valid ISBN-13.');
            $this->assertNotContains($entry['isbn'], $seen);
            $seen[] = $entry['isbn'];
            $this->assertGreaterThan(0, $entry['pages']);
            $this->assertLessThanOrEqual((int) date('Y'), $entry['published_year']);
            foreach (['title' => 100, 'author' => 100, 'publisher' => 100, 'description' => 255] as $key => $limit) {
                $this->assertNotEmpty($entry[$key]);
                $this->assertLessThanOrEqual($limit, mb_strlen($entry[$key]));
            }
            $this->assertStringStartsWith('https://www.goodreads.com/book/show/', $entry['source_url']);
            $this->assertStringStartsWith('https://', $entry['book_img']);
        }
    }

    public function test_book_only_seed_creates_real_catalogue_without_accounts_or_loans(): void
    {
        $this->artisan('db:seed', ['--class' => BookSeeder::class, '--force' => true])
            ->expectsOutputToContain('16 books added; 0 existing books preserved.')
            ->assertSuccessful();

        $this->assertDatabaseCount('books', 16);
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('checkouts', 0);
        $this->assertDatabaseCount('book_returns', 0);
        $this->assertDatabaseHas('books', [
            'title' => 'Knowing God', 'author' => 'J. I. Packer',
            'publisher' => 'InterVarsity Press', 'published_year' => 1993,
            'pages' => 286, 'location' => 'UNASSIGNED',
        ]);
        $book = Book::where('title', 'Knowing God')->firstOrFail();
        $this->assertStringContainsString('9780830816507', $book->notes);
        $this->assertStringContainsString('defaults', $book->notes);

        $admin = User::factory()->create(['role' => 'admin', 'phone' => '0900000000']);
        $this->actingAs($admin)->getJson('/api/books/'.$book->id)->assertOk()
            ->assertJsonPath('data.category.name', 'Theology')
            ->assertJsonPath('data.title', 'Knowing God');
    }

    public function test_repeated_seed_preserves_edits_existing_accounts_and_active_loans(): void
    {
        $category = Category::create(['name' => 'Theology', 'description' => 'Our existing category description.']);
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '0900000000']);
        $existing = Book::factory()->create([
            'title' => 'Knowing God', 'author' => 'J. I. Packer',
            'category_id' => $category->id, 'location' => 'B-02',
            'condition' => 'bad', 'is_available' => false,
            'notes' => 'Existing copy: preserve this note.',
        ]);
        $unrelated = Book::factory()->create(['category_id' => $category->id]);
        $checkout = Checkout::create([
            'book_id' => $existing->id, 'user_id' => $admin->id,
            'return_date' => now()->addWeek(), 'renewal_number' => 1,
        ]);
        $original = $existing->fresh()->getAttributes();
        $account = $admin->fresh()->getAttributes();
        $loan = $checkout->fresh()->getAttributes();

        $this->seed(BookSeeder::class);
        $newBook = Book::where('title', 'The Cross of Christ')->firstOrFail();
        $newBook->update(['description' => 'A librarian edited this entry.', 'location' => 'C-01']);
        $edited = $newBook->fresh()->getAttributes();

        $this->artisan('db:seed', ['--class' => BookSeeder::class, '--force' => true])
            ->expectsOutputToContain('0 books added; 16 existing books preserved.')
            ->assertSuccessful();

        $this->assertDatabaseCount('books', 17);
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('checkouts', 1);
        $this->assertSame($original, $existing->fresh()->getAttributes());
        $this->assertSame($edited, $newBook->fresh()->getAttributes());
        $this->assertSame($account, $admin->fresh()->getAttributes());
        $this->assertSame($loan, $checkout->fresh()->getAttributes());
        $this->assertNotNull($unrelated->fresh());
        $this->assertSame('Our existing category description.', $category->fresh()->description);
    }

    public function test_a_failed_import_rolls_back_books_and_categories(): void
    {
        DB::statement("CREATE TRIGGER reject_catalogue_book BEFORE INSERT ON books
            WHEN NEW.title = 'Knowing God' BEGIN SELECT RAISE(ABORT, 'test seed failure'); END");
        $failure = null;

        try {
            $this->seed(BookSeeder::class);
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        $this->assertNotNull($failure);
        $this->assertStringContainsString('test seed failure', $failure->getMessage());
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('categories', 0);
    }
}
