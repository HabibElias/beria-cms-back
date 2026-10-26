<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_production_frontend_can_preflight_login_and_read_validation_errors(): void
    {
        $origin = 'https://beriabooks.vercel.app';
        $this->options('/api/login', [], [
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->postJson('/api/login', [], ['Origin' => $origin])
            ->assertUnprocessable()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_unrelated_origins_are_not_allowed(): void
    {
        $this->options('/api/login', [], [
            'Origin' => 'https://unrelated.example',
            'Access-Control-Request-Method' => 'POST',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
