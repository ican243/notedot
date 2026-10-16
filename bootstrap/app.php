<?php

use App\Http\Middleware\DemoGuard;
use App\Http\Middleware\EnsureOwner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // 관리자 / 고객 주소표를 각각 접두어·이름표를 붙여 등록
        then: function () {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->prefix('s/{shop:slug}')
                ->name('shop.')
                ->group(base_path('routes/shop.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 라우트에서 ->middleware('owner'), ->middleware('demo') 로 쓰는 이름표
        $middleware->alias([
            'owner' => EnsureOwner::class,
            'demo' => DemoGuard::class,
        ]);

        // PG 서버가 보내는 웹훅은 우리 화면에서 오는 요청이 아니라 CSRF 토큰이 없다 → 대신 서명으로 확인
        $middleware->validateCsrfTokens(except: ['payments/webhook']);

        // 로그인이 필요한 곳에 손님이 오면 보낼 곳
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('s/*')
            ? route('shop.home', $request->route('shop'))
            : route('admin.login'));

        // 이미 로그인했는데 로그인 화면에 오면 보낼 곳
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('s/*')
            ? route('shop.home', $request->route('shop'))
            : route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
