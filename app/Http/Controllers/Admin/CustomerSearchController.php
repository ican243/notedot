<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// 예약 창의 고객 검색 (이름 또는 전화번호 일부). 전화번호는 가려서 보낸다
class CustomerSearchController extends Controller
{
    public function __invoke(Request $request, CustomerService $customers): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        return response()->json(
            $customers->search($request->user('admin')->shop, $q)->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->maskedPhone(),
                'no_show_count' => $c->no_show_count,
            ])
        );
    }
}
