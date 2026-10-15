<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 근무표: weekday 를 채우면 매주 반복, date 를 채우면 그날만 적용 (둘 중 하나만)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['work', 'break', 'off']);
            $table->unsignedTinyInteger('weekday')->nullable()->comment('0=일 ~ 6=토, 반복 일정');
            $table->date('date')->nullable()->comment('특정 날짜 일정');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('memo', 100)->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'weekday']);
            $table->index(['resource_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_schedules');
    }
};
