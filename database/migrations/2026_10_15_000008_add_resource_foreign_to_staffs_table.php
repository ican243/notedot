<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// staffs.resource_id 를 resources 표에 연결 (resource 가 지워지면 빈칸)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            $table->foreign('resource_id')->references('id')->on('resources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            $table->dropForeign(['resource_id']);
        });
    }
};
