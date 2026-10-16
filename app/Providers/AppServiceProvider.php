<?php

namespace App\Providers;

use App\Contracts\MessageSender;
use App\Contracts\PaymentGateway;
use App\Contracts\RealtimePublisher;
use App\Services\Messaging\LogSender;
use App\Services\Messaging\SolapiSender;
use App\Services\Payment\DemoGateway;
use App\Services\Payment\PortOneGateway;
use App\Services\Realtime\NodePublisher;
use App\Services\Realtime\PollingPublisher;
use App\Services\Realtime\RealtimeTokenService;
use App\Support\CurrentShop;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 실시간 알림: 설정이 node 이고 시크릿이 있으면 Node 서버로, 아니면 화면 폴링에 맡긴다
        $this->app->bind(RealtimePublisher::class, fn ($app) => $app->make(RealtimeTokenService::class)->usesNode()
            ? new NodePublisher(config('realtime.server_url'), config('realtime.secret'))
            : new PollingPublisher);

        // 결제: 설정이 portone 이고 키가 모두 있으면 포트원, 아니면 데모 결제창 (실제 돈이 나가지 않음)
        $this->app->singleton(DemoGateway::class);
        $this->app->bind(PaymentGateway::class, function ($app) {
            $cfg = config('payment.portone');
            $ready = config('payment.driver') === 'portone'
                && filled($cfg['store_id']) && filled($cfg['channel_key']) && filled($cfg['api_secret']);

            return $ready ? new PortOneGateway($cfg) : $app->make(DemoGateway::class);
        });

        // 알림: 설정이 solapi 이고 키·발신번호가 있으면 솔라피, 아니면 기록만
        $this->app->bind(MessageSender::class, function () {
            $cfg = config('notification.solapi');
            $ready = config('notification.driver') === 'solapi'
                && filled($cfg['api_key']) && filled($cfg['api_secret']) && filled($cfg['sender']);

            return $ready ? new SolapiSender($cfg) : new LogSender;
        });
    }

    public function boot(): void
    {
        $this->bindWithinShop();
    }

    // 주소의 {resource} {service} {staff} {schedule} {reservation} {classSession} {customer} {pass} 는 지금 매장 안에서만 찾는다
    // → 다른 매장 id 를 주소에 넣으면 404 (shop_id 범위 규칙)
    private function bindWithinShop(): void
    {
        $relations = [
            'resource' => 'resources',
            'service' => 'services',
            'staff' => 'staffs',
            'schedule' => 'resourceSchedules',
            'reservation' => 'reservations',
            'classSession' => 'classSessions',
            'customer' => 'customers',
            'pass' => 'passes',
            'notificationLog' => 'notificationLogs',
        ];

        foreach ($relations as $param => $relation) {
            Route::bind($param, function (string $value) use ($relation) {
                $shop = CurrentShop::get();
                abort_unless($shop, 404);

                return $shop->{$relation}()->findOrFail($value);
            });
        }
    }
}
