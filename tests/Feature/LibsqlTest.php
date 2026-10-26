<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibsqlTest extends TestCase
{
    public function test_libsql_supports_migrations_transactions_and_authenticated_api_requests(): void
    {
        config([
            'database.default' => 'libsql',
            'database.connections.libsql.database' => ':memory:',
            'database.connections.libsql.url' => null,
            'database.connections.libsql.password' => null,
            'cache.default' => 'database',
        ]);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));

        $user = User::factory()->create(['phone' => '0900000000']);
        $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);
        $login->assertOk()->assertJson(['status' => true]);
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $category = Category::create(['name' => 'Turso category']);
        $response = $this->withToken($token)->postJson('/api/books', [
            'title' => 'Turso book', 'author' => 'Test Author', 'description' => 'Test description',
            'pages' => 100, 'location' => 'A-1', 'published_year' => 2025,
            'category_id' => $category->id, 'condition' => 'good',
        ]);
        $response->assertCreated();
        $this->withToken($token)->getJson('/api/books')->assertOk()->assertJsonPath('data.0.title', 'Turso book');
        $this->withToken($token)->getJson('/api/analytics')->assertOk();

        DB::beginTransaction();
        Category::create(['name' => 'Rolled back']);
        DB::rollBack();
        $this->assertFalse(Category::where('name', 'Rolled back')->exists());
        cache()->put('turso-check', 'persistent-cache', 60);
        $this->assertSame('persistent-cache', cache()->get('turso-check'));
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertSame('sqlite', DB::connection('sqlite')->getDriverName());
    }

    public function test_missing_remote_credentials_fail_health_instead_of_using_temporary_memory(): void
    {
        config([
            'database.default' => 'libsql',
            'database.connections.libsql.database' => null,
            'database.connections.libsql.url' => null,
            'database.connections.libsql.password' => null,
        ]);
        $this->getJson('/api/health')->assertStatus(503)->assertJson(['status' => 'unavailable']);
    }
}
