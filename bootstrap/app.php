<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxy (CloudPanel/Nginx di depan PHP-FPM)
        $middleware->trustProxies(at: '*');

        // Global rate limiter: 60 request/menit per IP
        $middleware->append(\Illuminate\Routing\Middleware\ThrottleRequests::class . ':global');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
