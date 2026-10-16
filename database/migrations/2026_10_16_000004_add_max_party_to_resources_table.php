<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// resources.max_party: 한 예약에 들어갈 수 있는 최대 인원 (공간 크기). 비우면 제한 없음
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_party')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('max_party');
        });
    }
};
