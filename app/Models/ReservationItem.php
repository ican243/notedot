<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 예약에 담긴 상품 한 줄 (예약 당시 이름·시간·가격 복사본)
class ReservationItem extends Model
{
    protected $fillable = ['service_id', 'service_name', 'duration_min', 'price'];

    protected function casts(): array
    {
        return ['duration_min' => 'integer', 'price' => 'integer'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
