<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;

// 관리자 폼 공통: 권한은 라우트 미들웨어에서 확인하므로 여기서는 통과, 지금 매장을 꺼내 쓰는 도우미
abstract class AdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function shop(): Shop
    {
        return $this->user('admin')->shop;
    }
}
