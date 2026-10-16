<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 회원권 거래 내역 (통장처럼 쌓기만 하고 고치지 않는다)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['issue', 'use', 'restore', 'adjust', 'cancel'])->comment('발급 / 사용 / 사용 취소 / 직접 조정 / 권 취소');
            $table->integer('amount')->comment('+ 늘어남, - 줄어듦');
            $table->unsignedInteger('balance_after');
            $table->string('memo', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staffs')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pass_id', 'created_at']);
            $table->index(['reservation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_transactions');
    }
};
