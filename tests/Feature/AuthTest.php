<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'figma@gmail.com',
            'password' => 'Abcd1234',
            'password_confirmation' => 'Abcd1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token', 'requires_2fa']);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'figma@gmail.com',
            'password' => 'Abcd1234',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'figma@gmail.com',
            'password' => 'Abcd1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user', 'requires_2fa']);
    }

    public function test_login_requires_2fa_when_enabled()
    {
        $user = User::factory()->create([
            'email' => 'figma@gmail.com',
            'password' => bcrypt('Abcd1234'),
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt('test-secret'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'figma@gmail.com',
            'password' => 'Abcd1234',
        ]);

        $response->assertStatus(200)
            ->assertJson(['requires_2fa' => true]);
    }
}
