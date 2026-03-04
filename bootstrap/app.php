<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function(Schedule $schedule) {
        $schedule->command('app:update-currency-rates')->hourlyAt(5);
        $schedule->command('app:orders-expire-by-link')->everyTenMinutes()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            $isLockedPropertyException = $exception instanceof CannotUpdateLockedPropertyException;
            $isInfolistTypeError = $exception instanceof TypeError
                && str_contains($exception->getMessage(), 'BasePage::getInfolist(): Argument #1 ($name) must be of type string, null given');

            if (! $isLockedPropertyException && ! $isInfolistTypeError) {
                return;
            }

            /** @var Request|null $request */
            $request = request();
            if (! $request instanceof Request) {
                return;
            }

            $components = $request->input('components', []);
            $firstComponent = is_array($components) ? ($components[0] ?? null) : null;
            $snapshot = data_get($firstComponent, 'snapshot');
            $snapshotData = is_string($snapshot) ? json_decode($snapshot, true) : null;
            $updates = data_get($firstComponent, 'updates', []);
            $calls = data_get($firstComponent, 'calls', []);

            $updatePaths = [];
            if (is_array($updates)) {
                foreach ($updates as $path => $value) {
                    if (! is_string($path)) {
                        continue;
                    }

                    $updatePaths[] = $path;
                }
            }

            Log::warning('livewire.state-sync-debug', [
                'kind' => $isLockedPropertyException ? 'locked_property' : 'infolist_type_error',
                'message' => $exception->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'route_uri' => optional($request->route())->uri(),
                'referer' => $request->headers->get('referer'),
                'livewire_component' => data_get($snapshotData, 'memo.name'),
                'livewire_id' => data_get($snapshotData, 'memo.id'),
                'livewire_path' => data_get($snapshotData, 'memo.path'),
                'livewire_method_calls' => is_array($calls) ? array_slice(array_map(
                    static fn (array $call): array => [
                        'method' => data_get($call, 'method'),
                        'path' => data_get($call, 'path'),
                    ],
                    array_filter($calls, 'is_array')
                ), 0, 5) : [],
                'livewire_update_paths' => array_slice($updatePaths, 0, 25),
                'raw_components_count' => is_array($components) ? count($components) : null,
                'user_id' => optional($request->user())->getAuthIdentifier(),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'trace_id' => (string) Str::uuid(),
            ]);
        });
    })->create();
