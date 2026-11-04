<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\AuditRequestContext::class,
            \App\Http\Middleware\BlockDisabledDev::class,
        ]);

        $middleware->alias([
            'dev.enabled' => \App\Http\Middleware\EnsureDevToolsEnabled::class,
            'dev.role' => \App\Http\Middleware\EnsureDevRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
