<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 포인트 내역. 잔액은 customers.points 에 함께 맞춘다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['earn', 'use', 'revoke', 'adjust'])->comment('적립 / 사용 / 적립 회수 / 직접 조정');
            $table->integer('amount')->comment('+ 늘어남, - 줄어듦');
            $table->unsignedInteger('balance_after');
            $table->string('memo', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staffs')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
            $table->index(['reservation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
