<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);

        // Sessions carry access to health records; treat them accordingly.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // The two website forms post from the static marketing site, which is
        // a different origin and cannot carry a session CSRF token. Exempting
        // them costs nothing real: CSRF defends against an attacker making an
        // ALREADY SIGNED-IN user act without meaning to, and these endpoints
        // are anonymous — anyone can submit them by visiting the page. The
        // actual threats here are spam and flooding, which the route's rate
        // limit and the honeypot field handle.
        $middleware->validateCsrfTokens(except: [
            'intake/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
