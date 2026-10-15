<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 매장: 모든 데이터의 주인. 다른 표는 전부 shop_id 로 여기에 매달린다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique()->comment('고객 예약 주소 /s/{slug}');
            $table->string('name', 100);
            $table->string('industry', 30)->comment('config/industries.php 의 키');
            $table->json('labels')->nullable()->comment('용어 덮어쓰기 {"resource":"원장"}');
            $table->json('business_hours')->nullable()->comment('요일별 영업시간');
            $table->unsignedSmallInteger('slot_unit')->default(30)->comment('예약 단위(분)');
            $table->json('cancel_policy')->nullable();
            $table->enum('theme', ['auto', 'spring', 'summer', 'autumn', 'winter'])->default('auto');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
