<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 그룹 일정 한 칸 (예: 월 10:00 그룹 리포머, 하은, 정원 6). 고객은 이 칸에 자리를 신청한다 (reservations.class_session_id)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('occupied_until');                 // 끝 + 정리 시간 (담당자가 다시 비는 시각)
            $table->unsignedSmallInteger('capacity');           // 정원 (상품 기본값을 복사, 일정마다 조절)
            $table->string('memo', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staffs')->nullOnDelete();
            $table->dateTime('canceled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resource_id', 'start_at']);
            $table->index(['shop_id', 'start_at']);
        });

        // 예약 → 일정 연결 (매장이 지워질 때 함께 지워진다. 평소에는 일정을 지우지 않고 취소만 한다)
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('class_session_id')->references('id')->on('class_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['class_session_id']);
        });
        Schema::dropIfExists('class_sessions');
    }
};
