<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 알림 발송 기록: 보낸(또는 보내려던) 알림 한 건 = 한 줄
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->comment('confirmed 확정 / reminder 전날 리마인드 / canceled 취소');
            $table->enum('channel', ['alimtalk', 'sms', 'log'])->comment('log = 실제로 보내지 않고 기록만');
            $table->string('to_phone', 20);
            $table->text('message');
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->string('dedupe_key', 100)->nullable()->unique()->comment('같은 알림 두 번 보내기 방지 (예: reminder:5017)');
            $table->string('provider_message_id', 100)->nullable();
            $table->string('error', 255)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
