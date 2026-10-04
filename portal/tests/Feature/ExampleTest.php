<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_sends_visitors_to_sign_in(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_is_sent_home_not_round_in_a_loop(): void
    {
        foreach ([User::ROLE_ADMIN => 'admin.dashboard', User::ROLE_GUARDIAN => 'guardian.dashboard'] as $role => $home) {
            $user = User::create([
                'name' => $role, 'email' => "{$role}@example.test", 'password' => 'password',
                'role' => $role, 'status' => 'active',
            ]);

            $this->actingAs($user)->get('/')->assertRedirect(route($home));
            $this->actingAs($user)->get('/login')->assertRedirect(route($home));
        }
    }
}
