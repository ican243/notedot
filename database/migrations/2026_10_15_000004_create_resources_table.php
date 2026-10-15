<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 예약 대상 (사람 또는 공간). 화면에는 매장 용어(디자이너/강사/룸...)로 보인다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['person', 'space'])->default('person');
            $table->string('name', 50);
            $table->char('color', 7)->default('#2463b8')->comment('캘린더 블록 색');
            $table->unsignedSmallInteger('capacity')->default(1)->comment('한 타임 정원');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->comment('예약 받는 중');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
