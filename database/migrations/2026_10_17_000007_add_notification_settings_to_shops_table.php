<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 매장별 알림 켜기·끄기와 리마인드 시각. 비어 있으면 기본값(모두 켬, 18:00)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('cancel_policy')->comment('{"confirmed":true,"canceled":true,"reminder":true,"reminder_at":"18:00"}');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('notification_settings');
        });
    }
};
