<?php

namespace App\Events;

use App\Models\ClassSession;
use App\Models\Staff;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

// 그룹 일정이 생기거나 바뀜 (created | updated | moved | canceled). 반복으로 여러 개 만들면 첫 일정 하나로 한 번만 알린다
class ClassSessionChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public ClassSession $session,
        public string $action,
        public ?Staff $by = null,
        public int $count = 1,
    ) {}
}
