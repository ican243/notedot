<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 변경 이력 한 줄 (수정하지 않고 쌓기만 한다)
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'created' => '생성',
        'updated' => '수정',
        'moved' => '시간 이동',
        'status_changed' => '상태 변경',
    ];

    protected $fillable = ['shop_id', 'staff_id', 'action', 'target_type', 'target_id', 'changes', 'ip'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }
}
