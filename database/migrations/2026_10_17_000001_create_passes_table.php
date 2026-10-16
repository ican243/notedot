<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 회원권·정액권: 고객이 산 권 한 장 = 한 줄. 잔액(balance)은 pass_transactions 를 쌓으면서 함께 맞춘다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['amount', 'count'])->comment('amount 금액형(원) / count 횟수형(회)');
            $table->string('name', 50);
            $table->unsignedInteger('price')->default(0)->comment('실제로 받은 금액(원)');
            $table->unsignedInteger('total')->comment('처음 넣은 금액 또는 횟수');
            $table->unsignedInteger('balance')->comment('남은 금액 또는 횟수');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete()->comment('횟수형: 이 상품에만. 비우면 전체');
            $table->date('expires_on')->nullable();
            $table->string('memo', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staffs')->nullOnDelete();
            $table->dateTime('canceled_at')->nullable()->comment('환불·취소');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};
