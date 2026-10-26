<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_updates_reject_eleven_characters_and_accept_twelve(): void
    {
        $user = User::factory()->create([
            'phone' => '0900000000',
            'password' => 'Original-test-password!',
        ]);

        $this->actingAs($user)->postJson('/api/user/password', [
            'current_password' => 'Original-test-password!',
            'new_password' => 'password123',
            'new_password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_password');
        $this->assertTrue(Hash::check('Original-test-password!', $user->fresh()->password));

        $this->postJson('/api/user/password', [
            'current_password' => 'Original-test-password!',
            'new_password' => 'password1234',
            'new_password_confirmation' => 'password1234',
        ])->assertOk()->assertJson(['status' => true]);
        $this->assertTrue(Hash::check('password1234', $user->fresh()->password));

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password1234',
        ])->assertOk()->assertJson(['status' => true]);
    }
}
