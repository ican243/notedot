<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 예약 상품 (메뉴판). 금액은 원 단위 정수
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50)->nullable()->comment('묶음 이름');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('booking_type', ['one_to_one', 'group', 'space'])->default('one_to_one');
            $table->unsignedSmallInteger('duration_min')->comment('소요 시간(분)');
            $table->unsignedSmallInteger('buffer_min')->default(0)->comment('끝난 뒤 정리 시간(분)');
            $table->unsignedSmallInteger('capacity')->default(1)->comment('정원');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('deposit')->default(0)->comment('예약 보증금');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
