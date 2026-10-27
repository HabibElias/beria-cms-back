<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(
            file_get_contents(database_path('data/library-catalogue.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $created = DB::transaction(function () use ($catalogue): int {
            $this->call(CategorySeeder::class);
            $categories = Category::query()->pluck('id', 'name');
            $created = 0;

            foreach ($catalogue['books'] as $entry) {
                $book = Book::query()->firstOrCreate([
                    'title' => $entry['title'],
                    'author' => $entry['author'],
                ], [
                    'description' => $entry['description'],
                    'category_id' => $categories[$entry['category']],
                    'publisher' => $entry['publisher'],
                    'published_year' => $entry['published_year'],
                    'pages' => $entry['pages'],
                    'book_img' => $entry['book_img'],
                    // These operational values are defaults, not an inventory audit.
                    'location' => 'UNASSIGNED',
                    'condition' => 'good',
                    'is_available' => true,
                    'notes' => 'Starter catalogue. ISBN '.$entry['isbn'].'. Source: '.$entry['source_url']
                        .'. Shelf and condition are defaults; confirm a physical copy before lending.',
                ]);

                $created += (int) $book->wasRecentlyCreated;
            }

            return $created;
        });

        $this->command?->info('Library catalogue seeded: '.$created.' books added; '
            .(count($catalogue['books']) - $created).' existing books preserved.');
    }
}
