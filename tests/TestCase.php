<?php

namespace Tests;

use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use App\Models\Staff;
use Database\Seeders\DemoShopSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

// 모든 테스트의 부모: 안전장치 + 데모 매장을 꺼내 쓰는 도우미
abstract class TestCase extends BaseTestCase
{
    public const TEST_DATABASE = 'notedot_test';

    // 테스트는 DB 를 지우고 다시 만든다 → 테스트 DB 가 아니면 시작 전에 멈춘다 (config:cache 로 진짜 DB 를 보는 사고 방지)
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');
        if ($database !== self::TEST_DATABASE) {
            throw new RuntimeException("테스트는 '".self::TEST_DATABASE."' DB 에서만 실행합니다. (지금: {$database}) php artisan config:clear 후 다시 실행하세요.");
        }

        return $app;
    }

    // 데모 매장 3개 (미용실·필라테스·스터디룸) 만들기
    protected function seedDemoShops(): void
    {
        $this->seed(DemoShopSeeder::class);
    }

    protected function shop(string $slug = 'demo-hair'): Shop
    {
        return Shop::where('slug', $slug)->firstOrFail();
    }

    protected function staff(string $email = 'owner@notedot.test'): Staff
    {
        return Staff::where('email', $email)->firstOrFail();
    }

    protected function resourceNamed(string $name, string $slug = 'demo-hair'): Resource
    {
        return $this->shop($slug)->resources()->where('name', $name)->firstOrFail();
    }

    protected function serviceNamed(string $name, string $slug = 'demo-hair'): Service
    {
        return $this->shop($slug)->services()->where('name', $name)->firstOrFail();
    }
}
