<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 결제: 결제 시도 한 번 = 한 줄. 금액은 서버가 계산하고, 결제 결과는 PG 에 직접 물어봐서 확인한다
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('purpose', ['deposit'])->default('deposit')->comment('보증금 (나중에 이용료 전액 등 추가)');
            $table->string('payment_key', 64)->unique()->comment('우리가 만들어 PG 에 넘기는 결제 번호');
            $table->string('provider', 20)->comment('portone / demo');
            $table->unsignedInteger('amount')->comment('서버가 계산한 금액(원)');
            $table->enum('status', ['ready', 'paid', 'failed', 'canceled', 'partial_refunded'])->default('ready');
            $table->string('method', 30)->nullable();
            $table->string('pg_tx_id', 100)->nullable()->comment('PG 거래번호');
            $table->string('receipt_url', 500)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('failed_reason', 200)->nullable();
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->dateTime('refunded_at')->nullable();
            $table->string('refund_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['shop_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
