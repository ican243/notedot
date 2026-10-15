<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 관리자 로그인 계정 (admin 가드의 명단)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            // resources 표는 Phase 2에서 생기므로 지금은 칸만 두고 외래키는 그때 연결
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->enum('role', ['owner', 'staff'])->default('staff');
            $table->string('name', 50);
            $table->string('email', 191);
            $table->string('password');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staffs');
    }
};
