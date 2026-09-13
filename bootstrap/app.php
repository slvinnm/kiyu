<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * API requests should always receive JSON responses.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Validation failed.
         */
        $exceptions->renderable(function (
            ValidationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        /*
         * User is not authenticated.
         */
        $exceptions->renderable(function (
            AuthenticationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        });

        /*
         * User is authenticated but not authorized.
         */
        $exceptions->renderable(function (
            AuthorizationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'This action is unauthorized.',
                'errors' => [],
            ], 403);
        });

        /*
         * Requested model/resource was not found.
         */
        $exceptions->renderable(function (
            ModelNotFoundException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'errors' => [],
            ], 404);
        });

        /*
         * LogicException — domain / business-rule conflicts.
         * Return a stable 422 JSON so the frontend can present a
         * clear, user-friendly message instead of a generic 500.
         */
        $exceptions->renderable(function (
            LogicException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], 422);
        });

        /*
         * HTTP exceptions such as:
         * 400 Bad Request
         * 405 Method Not Allowed
         * 409 Conflict
         * 429 Too Many Requests
         * etc.
         */
        $exceptions->renderable(function (
            HttpExceptionInterface $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'The request could not be completed.',
                'errors' => [],
            ], $exception->getStatusCode(), $exception->getHeaders());
        });

        /*
         * Fallback for unexpected API exceptions.
         *
         * Do not expose the original exception message because it may
         * contain sensitive application or database information.
         */
        $exceptions->renderable(function (
            Throwable $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => [],
            ], 500);
        });
    })
    ->create();
