<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = Category::all()->toArray();

        return [
            //
            'title' => fake()->jobTitle(),
            'author' => fake()->firstName() . " " . fake()->lastName(),
            'description' => fake()->paragraph(1),
            'category_id' => $category[random_int(0, count($category) - 1)]['id'],
            'notes' => fake()->paragraph(1),
            'condition' => ['excellent', 'good', 'bad'][random_int(0, 2)],
            'location' => strtoupper(fake()->randomLetter()) . "-" . fake()->randomDigit(),
            'pages' => fake()->numberBetween(100, 500),
            'is_available' => true,
            // 'book_img' => fake()->imageUrl()
            'published_year' => fake()->year('now')
        ];
    }
}
