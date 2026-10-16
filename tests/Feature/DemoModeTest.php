<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Staff;
use Database\Seeders\DemoShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// 데모 모드: 직원 삭제·로그인 정보 변경 막기, demo:reset 초기화
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoShops();
        config(['demo.enabled' => true]);
        $this->actingAs($this->staff(), 'admin');
    }

    private function updateStaff(Staff $staff, array $overrides = [])
    {
        return $this->from(route('admin.staffs.edit', $staff))->put(route('admin.staffs.update', $staff), $overrides + [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'resource_id' => $staff->resource_id,
        ]);
    }

    public function test_staff_cannot_be_deleted(): void
    {
        $staff = $this->staff('staff@notedot.test');

        $this->from(route('admin.staffs.edit', $staff))->delete(route('admin.staffs.destroy', $staff))
            ->assertRedirect(route('admin.staffs.edit', $staff))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($staff);
    }

    public function test_password_and_email_cannot_be_changed(): void
    {
        $staff = $this->staff('staff@notedot.test');

        $this->updateStaff($staff, ['password' => 'new-password-1'])->assertSessionHas('error');
        $this->updateStaff($staff, ['email' => 'other@notedot.test'])->assertSessionHas('error');

        $staff->refresh();
        $this->assertTrue(Hash::check(DemoShopSeeder::PASSWORD, $staff->password));
        $this->assertSame('staff@notedot.test', $staff->email);
    }

    public function test_other_fields_can_still_be_changed(): void
    {
        $staff = $this->staff('staff@notedot.test');

        $this->updateStaff($staff, ['name' => '이민호2'])->assertRedirect(route('admin.staffs.index'));

        $this->assertSame('이민호2', $staff->fresh()->name);
    }

    public function test_delete_works_when_demo_is_off(): void
    {
        config(['demo.enabled' => false]);
        $staff = $this->staff('staff@notedot.test');

        $this->delete(route('admin.staffs.destroy', $staff))->assertRedirect(route('admin.staffs.index'));

        $this->assertSoftDeleted($staff);
    }

    public function test_reset_restores_demo_data(): void
    {
        $this->shop()->update(['name' => '바뀐 이름']);

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame('노트닷 헤어', $this->shop()->name);
        $this->assertGreaterThan(0, Reservation::count());
    }

    public function test_reset_refuses_when_demo_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->artisan('demo:reset')->assertFailed();
    }

    public function test_login_page_lists_demo_accounts(): void
    {
        auth('admin')->logout();

        $this->get(route('admin.login'))->assertOk()->assertSee('staff@notedot.test');
    }
}
