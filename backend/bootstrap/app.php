<?php

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RemoveStackHeaders;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$sensitiveInput = ['password', 'password_confirmation', 'current_password', 'token', 'secret', 'code'];

$traceShort = fn (Throwable $e) => collect(array_slice($e->getTrace(), 0, 5))
    ->map(fn ($frame) => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '')
        .' ('.basename($frame['file'] ?? '').':'.($frame['line'] ?? '?').')')
    ->all();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(RemoveStackHeaders::class);
        $middleware->alias(['active' => EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($sensitiveInput, $traceShort): void {
        $exceptions->dontReport([BusinessRuleViolation::class]);

        // /bug-report: capture server errors with context in the `errors` channel.
        $exceptions->report(function (Throwable $e) use ($sensitiveInput, $traceShort) {
            if ($e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof NotFoundHttpException
                || $e instanceof ModelNotFoundException
                || $e instanceof BusinessRuleViolation
                || ($e instanceof HttpException && $e->getStatusCode() < 500)
            ) {
                return false;
            }

            $request = request();
            $route = $request->route();
            $context = [
                'action' => $route ? $route->getActionName() : 'unknown',
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
                'ip' => $request->ip(),
                'input' => $request->except($sensitiveInput),
            ];

            if ($e instanceof ValidationException) {
                Log::channel('errors')->warning($e->getMessage(), [
                    ...$context,
                    'exception_class' => 'ValidationException',
                    'validation_errors' => $e->errors(),
                ]);

                return false;
            }

            Log::channel('errors')->error($e->getMessage(), [
                ...$context,
                'exception_class' => get_class($e),
                'route_name' => $route?->getName(),
                'user_agent' => $request->userAgent(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace_short' => $traceShort($e),
            ]);

            return false;
        });

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*', 'mcp', 'mcp/*') || $request->expectsJson());

        // Every API error uses the `{ data, message, errors }` envelope.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error('Los datos enviados no son válidos.', 422, $e->errors()),
                $e instanceof BusinessRuleViolation => ApiResponse::error($e->getMessage(), $e->status, null, ['code' => $e->ruleCode, ...$e->context]),
                $e instanceof AuthenticationException => ApiResponse::error('No autenticado.', 401),
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error('No tenés permiso para esta acción.', 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('No encontrado.', 404),
                $e instanceof HttpException => ApiResponse::error($e->getStatusCode() === 429 ? 'Demasiados intentos. Probá en un rato.' : ($e->getMessage() ?: 'Error'), $e->getStatusCode()),
                default => ApiResponse::error(config('app.debug') ? $e->getMessage() : 'Error interno del servidor.', 500),
            };
        });
    })->create();
