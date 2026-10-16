<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Services\CustomerService;
use PHPUnit\Framework\TestCase;

// 전화번호 숫자만 남기기, 화면 마스킹
class CustomerPhoneTest extends TestCase
{
    public function test_phone_is_normalized_to_digits(): void
    {
        $this->assertSame('01012345678', CustomerService::normalizePhone('010-1234-5678'));
        $this->assertSame('0212345678', CustomerService::normalizePhone(' 02 1234 5678 '));
    }

    public function test_phone_is_masked_for_screen(): void
    {
        $this->assertSame('010-****-5678', (new Customer(['phone' => '01012345678']))->maskedPhone());
        $this->assertSame('021-****-5678', (new Customer(['phone' => '0212345678']))->maskedPhone());
    }
}
