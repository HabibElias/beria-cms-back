<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = config('bootstrap_admin');
        $validator = Validator::make($attributes, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:16', 'max:72'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        if ($validator->fails()) {
            // Never include environment values (especially passwords) in logs.
            throw new RuntimeException('Admin seeding requires a valid ADMIN_EMAIL, an ADMIN_PASSWORD of 16–72 characters, a non-empty ADMIN_PHONE, and a valid ADMIN_NAME.');
        }

        // The unique email index also protects concurrent deployments. Existing
        // users keep their password, profile and role on every subsequent run.
        $admin = User::query()->firstOrCreate(['email' => $attributes['email']], [
            'name' => $attributes['name'],
            'phone' => $attributes['phone'],
            'password' => $attributes['password'],
            'role' => 'admin',
        ]);

        if ($admin->role !== 'admin') {
            throw new RuntimeException('ADMIN_EMAIL already belongs to a non-admin account. No changes were made; choose an unused email.');
        }

        $this->command?->info($admin->wasRecentlyCreated
            ? 'Administrator created. Sign in using ADMIN_EMAIL and ADMIN_PASSWORD.'
            : 'Administrator already exists. Existing credentials were preserved.');
    }
}
