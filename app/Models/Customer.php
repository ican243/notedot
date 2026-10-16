<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

// 예약자 (customer 가드)
class Customer extends Authenticatable
{
    use SoftDeletes;

    protected $fillable = [
        'shop_id', 'name', 'phone', 'password', 'memo', 'points',
        'privacy_agreed_at', 'marketing_agreed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'points' => 'integer',
            'no_show_count' => 'integer',
            'privacy_agreed_at' => 'datetime',
            'marketing_agreed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    // 전체 번호 010-1234-5678 (대표 화면의 상세에서만)
    public function formattedPhone(): string
    {
        $p = preg_replace('/\D/', '', $this->phone);

        return strlen($p) === 11 ? substr($p, 0, 3).'-'.substr($p, 3, 4).'-'.substr($p, 7) : (strlen($p) === 10 ? substr($p, 0, 3).'-'.substr($p, 3, 3).'-'.substr($p, 6) : $p);
    }

    // 화면 표시용 전화번호 010-****-1234
    public function maskedPhone(): string
    {
        $p = preg_replace('/\D/', '', $this->phone);

        return strlen($p) >= 10 ? substr($p, 0, 3).'-****-'.substr($p, -4) : $p;
    }
}
