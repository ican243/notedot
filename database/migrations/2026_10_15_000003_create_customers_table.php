<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 예약자 (customer 가드의 명단). 관리자가 직접 등록한 비회원은 password 가 비어 있다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('phone', 20)->comment('숫자만 저장 01012345678');
            $table->string('password')->nullable();
            $table->text('memo')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->timestamp('privacy_agreed_at')->nullable()->comment('개인정보 수집 동의');
            $table->timestamp('marketing_agreed_at')->nullable()->comment('마케팅 수신 동의 (별도)');
            $table->unsignedSmallInteger('no_show_count')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
