<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 예약. occupied_until = 끝 시각 + 정리 시간 → 겹침 검사는 이 값으로 한다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            // class_sessions 표는 그룹 수업 단계(3-3)에서 생기므로 지금은 칸만
            $table->unsignedBigInteger('class_session_id')->nullable()->index();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('occupied_until')->comment('정리 시간까지 포함한 끝');
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->enum('status', ['pending', 'confirmed', 'completed', 'canceled', 'no_show'])->default('confirmed');
            $table->enum('channel', ['online', 'phone', 'walk_in'])->default('phone');
            $table->unsignedInteger('total_price')->default(0);
            $table->unsignedInteger('deposit')->default(0);
            $table->string('customer_request', 500)->nullable();
            $table->string('staff_memo', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staffs')->nullOnDelete();
            $table->dateTime('canceled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resource_id', 'start_at']);
            $table->index(['shop_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
