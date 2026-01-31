<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Habib Elias',
            'email' => 'habibelias234@gmail.com',
            'password' => 'Ha@12312',
            'phone' => '+(251) 9 40 82 71 41',
            'role' => 'admin'
        ]);

        User::factory()->create([
            'name' => 'Natan Israel',
            'email' => 'natanisrael.job@gmail.com',
            'password' => 'password123',
            'phone' => '+(251) 9 67 51 26 13',
            'role' => 'admin'
        ]);

        $this->call(
            [
                CategorySeeder::class,
                BookSeeder::class,
            ]
        );
    }
}
