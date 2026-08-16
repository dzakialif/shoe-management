<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return apiResponse(
                    message: 'Data not found.',
                    success: false,
                    status: 404,
                );
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return apiResponse(
                    message: 'Route or data not found.',
                    success: false,
                    status: 404,
                );
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return apiResponse(
                    message: 'The given data was invalid.',
                    success: false,
                    status: 422,
                    errors: $e->errors(),
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return apiResponse(
                    message: 'Unauthenticated.',
                    success: false,
                    status: 401,
                );
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson())
                && $e->getCode() === '23000'
                && str_contains((string) $e->getPrevious(), '1451')) {
                return apiResponse(
                    message: 'Cannot delete: data is still referenced by other records.',
                    success: false,
                    status: 409,
                );
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson()) && !$e instanceof HttpExceptionInterface) {
                return apiResponse(
                    message: 'Internal server error.',
                    success: false,
                    status: 500,
                );
            }
        });
    })->create();
