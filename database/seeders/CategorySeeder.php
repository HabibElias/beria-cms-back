<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Theology' => 'Books about the character of God, Christian doctrine, and the meaning of faith.',
        'Apologetics' => 'Thoughtful explanations of Christian belief and engagement with questions about faith.',
        'Christian living' => 'Guidance for following Christ in daily life and caring for Christian community.',
        'Christian classics' => 'Enduring works of Christian devotion, reflection, and spiritual autobiography.',
        'Spiritual formation' => 'Resources for prayer, worship, spiritual disciplines, and growth in faith.',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            Category::query()->firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
