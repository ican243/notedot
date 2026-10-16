<?php

namespace Tests\Feature\Admin;

use Database\Seeders\DemoShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 관리자 로그인과 실패 횟수 제한
class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
    }

    private function attempt(string $password)
    {
        return $this->post(route('admin.login.attempt'), ['email' => 'owner@notedot.test', 'password' => $password]);
    }

    public function test_owner_can_log_in(): void
    {
        $this->attempt(DemoShopSeeder::PASSWORD)->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->staff(), 'admin');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->attempt('wrong-password')->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    // 5번 틀리면 맞는 비밀번호도 1분 동안 막힌다
    public function test_too_many_failures_lock_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->attempt('wrong-password');
        }

        $this->attempt(DemoShopSeeder::PASSWORD)->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }
}
