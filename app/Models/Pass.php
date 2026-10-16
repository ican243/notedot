<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// 회원권·정액권 한 장 (금액형 amount: 원 / 횟수형 count: 회)
class Pass extends Model
{
    use SoftDeletes;

    public const TYPES = ['amount' => '금액형', 'count' => '횟수형'];

    protected $fillable = [
        'shop_id', 'customer_id', 'type', 'name', 'price', 'total', 'balance',
        'service_id', 'expires_on', 'memo', 'created_by', 'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'total' => 'integer',
            'balance' => 'integer',
            'expires_on' => 'date',
            'canceled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PassTransaction::class);
    }

    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    // 지금 쓸 수 있는 권: 취소 안 됨 + 만료 안 됨 + 잔액 있음
    public function scopeUsable(Builder $q): void
    {
        $q->whereNull('canceled_at')
            ->where('balance', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today()));
    }

    // 화면 표시: "7회" / "120,000원"
    public function format(int $value): string
    {
        return $this->type === 'count' ? number_format($value).'회' : number_format($value).'원';
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isCanceled() => '취소',
            $this->isExpired() => '만료',
            $this->balance === 0 => '소진',
            default => '사용 중',
        };
    }
}
