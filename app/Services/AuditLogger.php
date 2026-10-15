<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;

// 변경 이력 남기기: 어떤 모델(target)을 누가(staff) 어떻게(action, changes) 바꿨는지
class AuditLogger
{
    /** @param array<string, array{0:mixed,1:mixed}> $changes  ['start_at' => [전, 후]] */
    public function log(Model $target, string $action, ?Staff $by, array $changes = []): void
    {
        AuditLog::create([
            'shop_id' => $target->shop_id,
            'staff_id' => $by?->id,
            'action' => $action,
            'target_type' => $target->getTable(),
            'target_id' => $target->getKey(),
            'changes' => $changes ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    // 두 배열을 비교해서 달라진 칸만 [전, 후] 로
    public function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changes[$key] = [$before[$key] ?? null, $value];
            }
        }

        return $changes;
    }
}
