<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 포인트 적립률(%). 0 이면 포인트를 쓰지 않는다
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedTinyInteger('point_rate')->default(0)->after('slot_unit')->comment('예약 완료 시 결제액의 N% 적립');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('point_rate');
        });
    }
};
