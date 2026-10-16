<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// PG 웹훅: "이 결제 상태가 바뀌었다" 알림. 서명이 맞을 때만 받고, 내용은 믿지 않고 PG 에 다시 물어본다
// (손님이 결제 후 창을 닫아 버려도 결제가 반영되게 하는 안전망)
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): Response
    {
        $key = $gateway->webhookPaymentKey($request);
        if ($key === null) {
            return response('invalid signature', 401);
        }

        $payment = Payment::where('payment_key', $key)->first();
        if ($payment) {
            $payments->confirm($payment);
        }

        // 모르는 결제 번호여도 200 (PG 가 계속 다시 보내지 않게)
        return response('ok');
    }
}
