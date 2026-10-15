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

    // 원장(전체 권한)인지
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
