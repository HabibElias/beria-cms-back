<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Libsql\Laravel\Database\LibsqlConnector;
use Tests\TestCase;

class LibsqlTest extends TestCase
{
    public function test_remote_url_and_token_reach_the_connector_after_laravel_parses_configuration(): void
    {
        $endpoint = 'libsql://regression-test.turso.io';
        $token = 'non-secret-regression-test-token';
        $previous = [];
        foreach (['TURSO_DATABASE_URL' => $endpoint, 'TURSO_AUTH_TOKEN' => $token] as $key => $value) {
            $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        try {
            // Read the real environment mapping, as config:cache does at startup.
            $configuration = require config_path('database.php');
        } finally {
            foreach ($previous as $key => [$env, $server]) {
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }

        config(['database.default' => 'libsql', 'database.connections.libsql' => $configuration['connections']['libsql']]);
        $this->mock(LibsqlConnector::class, function ($mock) use ($endpoint, $token) {
            $mock->shouldReceive('connect')->once()->andReturnUsing(function (array $config) use ($endpoint, $token) {
                $this->assertTrue(($config['url'] ?? null) === $endpoint, 'The configured remote endpoint was lost.');
                $this->assertTrue(($config['password'] ?? null) === $token, 'The configured auth token was lost.');

                // Replace only the remote transport; still exercise the native SDK query.
                return (new LibsqlConnector())->connect(array_replace($config, [
                    'url' => null, 'password' => null, 'database' => ':memory:',
                ]));
            });
        });

        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_libsql_supports_migrations_transactions_and_authenticated_api_requests(): void
    {
        config([
            'database.default' => 'libsql',
            'database.connections.libsql.database' => ':memory:',
            'database.connections.libsql.turso_url' => null,
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
            'database.connections.libsql.turso_url' => null,
            'database.connections.libsql.password' => null,
        ]);
        $this->getJson('/api/health')->assertStatus(503)->assertJson(['status' => 'unavailable']);
    }
}
