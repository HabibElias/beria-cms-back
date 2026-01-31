<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('description');
            $table->string('notes')->nullable();
            $table->integer('pages');
            $table->string('location');
            $table->string('publisher')->nullable();
            $table->integer('published_year')->nullable();
            $table->foreignIdFor(Category::class)->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->enum('condition', ['excellent', 'good', 'bad']);
            $table->string('book_img')->nullable();
            $table->string('book_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
