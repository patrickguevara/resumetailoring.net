<?php

use App\Exceptions\UsageLimitExceededException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->validateCsrfTokens(except: ['stripe/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (UsageLimitExceededException $exception, Request $request) {
            $payload = [
                'message' => $exception->userMessage(),
                'feature' => $exception->feature->value,
                'limit' => $exception->limit,
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, 403);
            }

            $redirect = $request->isMethod('get')
                ? to_route('billing.edit')
                : back()->withInput($request->except(['resume_file']));

            return $redirect
                ->with('flash', [
                    'type' => 'warning',
                    'message' => $payload['message'],
                ])
                ->with('usageLimit', $payload);
        });
    })->create();
