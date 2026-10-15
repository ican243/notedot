<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

// 관리자 계정 (admin 가드). Authenticatable 을 상속해야 로그인 대상이 될 수 있다
class Staff extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'staffs';

    protected $fillable = ['shop_id', 'resource_id', 'role', 'name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    // 연결된 예약 대상 (캘린더에서 본인 일정)
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    // 이 직원이 캘린더에서 볼 수 있는 resource id: 대표는 전부, 직원은 연결된 자기 것만
    public function visibleResourceIds(): array
    {
        if ($this->isOwner()) {
            return $this->shop->resources()->pluck('id')->all();
        }

        return $this->resource_id ? [$this->resource_id] : [];
    }

    public function canAccessResource(int|string|null $resourceId): bool
    {
        return $resourceId && in_array((int) $resourceId, $this->visibleResourceIds(), true);
    }

    // 대표(전체 권한)인지
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
