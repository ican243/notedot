<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 담당자/공간별 가능한 상품 (다대다 연결표)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_service', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['resource_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_service');
    }
};
