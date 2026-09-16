<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Remove ValidatePostSize so large uploads don't get a raw 413
        $middleware->remove([
            \Illuminate\Http\Middleware\ValidatePostSize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ★ THE FIX: also honor Accept: application/json from any route
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Handle oversized uploads
        $exceptions->renderable(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'File too large',
                    'message' => 'Maximum file size is 100MB',
                ], 413);
            }

            return back()->with('error', 'The file is too large. Maximum size is 100MB.');
        });
    })
    ->create();