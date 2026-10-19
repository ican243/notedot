<?php

namespace App\Http\Controllers\Shop;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Shop;
use App\Services\Payment\DemoGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// 고객 결제 화면: 보증금 결제창 열기 → 결제 끝나면 서버가 PG 에 확인 → 완료 화면
// 이 브라우저에서 방금 한 예약의 결제만 열 수 있다 (세션의 booked.{shop_id})
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments, private PaymentGateway $gateway) {}

    public function show(Request $request, Shop $shop, string $paymentKey): View|RedirectResponse
    {
        $payment = $this->find($request, $shop, $paymentKey);
        if ($payment->status !== 'ready') {
            return redirect()->route('shop.booking.done', $shop);
        }

        $reservation = $payment->reservation->load(['items', 'resource', 'customer']);

        return view('shop.pay', [
            'shop' => $shop,
            'payment' => $payment,
            'reservation' => $reservation,
            'policy' => $this->payments->policyLines($shop),
            'config' => [
                'gateway' => $this->gateway->clientConfig($payment),
                'paymentId' => $payment->payment_key,
                'orderName' => $reservation->items->pluck('service_name')->implode(', ').' 보증금',
                'amount' => $payment->amount,   // 표시·결제창용. 확인은 서버가 저장해 둔 금액으로 한다
                'customer' => ['fullName' => $reservation->customer->name, 'phoneNumber' => $reservation->customer->phone],
                'dueAt' => $payment->dueAt()->toIso8601String(),
                'urls' => [
                    'complete' => route('shop.pay.complete', [$shop, $paymentKey]),
                    'return' => route('shop.pay.return', [$shop, $paymentKey]),
                    'demo' => route('shop.pay.demo', [$shop, $paymentKey]),
                    'done' => route('shop.booking.done', $shop),
                ],
            ],
        ]);
    }

    // 결제창이 닫힌 뒤 화면이 부른다 → 서버가 PG 에 직접 확인
    public function complete(Request $request, Shop $shop, string $paymentKey): JsonResponse
    {
        $payment = $this->payments->confirm($this->find($request, $shop, $paymentKey));

        return response()->json([
            'status' => $payment->status,
            'message' => match ($payment->status) {
                'paid' => '결제가 완료되었습니다.',
                'ready' => '결제가 아직 확인되지 않았습니다. 잠시 후 다시 시도해 주세요.',
                default => $payment->failed_reason ?: '결제에 실패했습니다.',
            },
            'redirect' => $payment->status === 'ready' ? null : route('shop.booking.done', $shop),
        ]);
    }

    // 모바일 결제창은 다른 페이지로 갔다가 이 주소로 돌아온다
    public function return(Request $request, Shop $shop, string $paymentKey): RedirectResponse
    {
        $this->payments->confirm($this->find($request, $shop, $paymentKey));

        return redirect()->route('shop.booking.done', $shop);
    }

    // 데모 결제창의 "승인 / 실패" 버튼 (데모 결제일 때만)
    public function demo(Request $request, Shop $shop, string $paymentKey): JsonResponse
    {
        abort_unless($this->gateway instanceof DemoGateway, 404);
        $request->validate(['result' => ['required', 'in:paid,failed']]);
        $payment = $this->find($request, $shop, $paymentKey);

        if ($payment->status === 'ready') {
            $this->gateway->simulate($payment, $request->input('result'));
        }

        return $this->complete($request, $shop, $paymentKey);
    }

    private function find(Request $request, Shop $shop, string $paymentKey): Payment
    {
        $payment = Payment::where('shop_id', $shop->id)->where('payment_key', $paymentKey)->first();
        abort_unless($payment && $request->session()->get("booked.{$shop->id}") === $payment->reservation_id, 404);

        return $payment;
    }
}
