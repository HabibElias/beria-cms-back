<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the same native SQLite/libSQL driver used by Turso.
        config([
            'database.default' => 'libsql',
            'database.connections.libsql.database' => ':memory:',
            'database.connections.libsql.turso_url' => null,
            'database.connections.libsql.password' => null,
            'bootstrap_admin.email' => 'administrator@example.com',
            'bootstrap_admin.password' => 'password1234',
            'bootstrap_admin.name' => 'Administrator',
            'bootstrap_admin.phone' => '0900000000',
        ]);
        Artisan::call('migrate', ['--force' => true]);
    }

    public function test_seeded_administrator_can_sign_in_without_demo_data(): void
    {
        $this->artisan('db:seed', ['--class' => AdminSeeder::class, '--force' => true])
            ->expectsOutputToContain('Administrator created.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('categories', 0);
        $admin = User::sole();
        $this->assertSame('admin', $admin->role);
        $this->assertNotSame(config('bootstrap_admin.password'), $admin->password);
        $this->assertTrue(Hash::check(config('bootstrap_admin.password'), $admin->password));

        $this->postJson('/api/login', [
            'email' => config('bootstrap_admin.email'),
            'password' => config('bootstrap_admin.password'),
        ])->assertOk()->assertJson(['status' => true, 'user' => ['role' => 'admin']]);
    }

    public function test_repeated_seeding_preserves_existing_password_and_profile(): void
    {
        $this->seed(AdminSeeder::class);
        $original = User::sole()->getAttributes();
        config([
            'bootstrap_admin.password' => 'Different-test-password!',
            'bootstrap_admin.name' => 'Changed name',
        ]);
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($original, User::sole()->getAttributes());
        $this->postJson('/api/login', [
            'email' => config('bootstrap_admin.email'),
            'password' => 'password1234',
        ])->assertOk()->assertJson(['status' => true]);
    }

    public function test_existing_member_is_not_promoted_or_changed(): void
    {
        $member = User::factory()->create([
            'email' => config('bootstrap_admin.email'), 'phone' => '0900000000', 'role' => 'user',
        ]);
        $original = $member->fresh()->getAttributes();

        try {
            $this->seed(AdminSeeder::class);
            $this->fail('Seeding must reject an existing non-admin email.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('non-admin account', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($original, $member->fresh()->getAttributes());
    }

    public function test_missing_or_invalid_credentials_create_no_account_and_are_not_logged(): void
    {
        $valid = config('bootstrap_admin');
        foreach ([
            [['password' => null], 'ADMIN_PASSWORD', 'required'],
            [['password' => 'password123'], 'ADMIN_PASSWORD', 'at least 12'],
            [['password' => str_repeat('long-secret', 8)], 'ADMIN_PASSWORD', '72'],
            [['email' => null], 'ADMIN_EMAIL', 'required'],
            [['email' => 'invalid-email'], 'ADMIN_EMAIL', 'valid email'],
            [['phone' => null], 'ADMIN_PHONE', 'required'],
            [['phone' => ''], 'ADMIN_PHONE', 'required'],
            [['phone' => '0'], 'ADMIN_PHONE', 'at least 3'],
            [['name' => null], 'ADMIN_NAME', 'required'],
        ] as [$invalid, $variable, $requirement]) {
            config(['bootstrap_admin' => array_replace($valid, $invalid)]);
            try {
                $this->seed(AdminSeeder::class);
                $this->fail('Seeding must reject invalid admin configuration.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString($variable, $exception->getMessage());
                $this->assertStringContainsString($requirement, $exception->getMessage());
                $this->assertStringNotContainsString('password123', $exception->getMessage());
                $this->assertStringNotContainsString('long-secret', $exception->getMessage());
                $this->assertStringNotContainsString('invalid-email', $exception->getMessage());
                $this->assertStringNotContainsString($valid['password'], $exception->getMessage());
            }
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_an_empty_optional_admin_name_uses_the_default(): void
    {
        $previous = [$_ENV['ADMIN_NAME'] ?? null, $_SERVER['ADMIN_NAME'] ?? null];
        $_ENV['ADMIN_NAME'] = $_SERVER['ADMIN_NAME'] = '';

        try {
            $configuration = require config_path('bootstrap_admin.php');
            $this->assertSame('Administrator', $configuration['name']);
        } finally {
            foreach (['_ENV', '_SERVER'] as $index => $global) {
                if ($previous[$index] === null) {
                    unset($GLOBALS[$global]['ADMIN_NAME']);
                } else {
                    $GLOBALS[$global]['ADMIN_NAME'] = $previous[$index];
                }
            }
        }
    }
}
