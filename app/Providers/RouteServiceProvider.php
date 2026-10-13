<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('catalog', function (Request $request) {
            return $this->readLimits(
                $request,
                'catalog',
                (int) config('catalog.per_minute'),
                (int) config('catalog.per_hour'),
            );
        });

        RateLimiter::for('catalog-api', function (Request $request) {
            return $this->readLimits(
                $request,
                'catalog-api',
                (int) config('catalog.api_per_minute'),
                (int) config('catalog.api_per_hour'),
            );
        });

        RateLimiter::for('attachments', function (Request $request) {
            return $this->readLimits(
                $request,
                'attachments',
                (int) config('catalog.attachments_per_minute'),
                (int) config('catalog.attachments_per_hour'),
            );
        });

        RateLimiter::for('search', function (Request $request) {
            return $this->readLimits(
                $request,
                'search',
                (int) config('catalog.search_per_minute'),
                (int) config('catalog.search_per_hour'),
            );
        });

        RateLimiter::for('track', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip())->response(function () {
                return back()->withErrors([
                    'tracking_code' => 'Terlalu banyak percobaan. Coba lagi nanti.',
                ]);
            });
        });

        RateLimiter::for('disputes', function (Request $request) {
            $tooMany = function () {
                return redirect('/dispute')->withErrors([
                    'captcha' => 'Terlalu banyak sanggahan dari jaringan ini. Coba lagi nanti.',
                ]);
            };

            return [
                Limit::perMinute(3)->by('dispute-minute|'.$request->ip())->response($tooMany),
                Limit::perHour(15)->by('dispute-hour|'.$request->ip())->response($tooMany),
            ];
        });

        RateLimiter::for('reports', function (Request $request) {
            $tooMany = function () {
                return redirect('/reports')->withErrors([
                    'captcha' => 'Terlalu banyak laporan dari jaringan ini. Coba lagi nanti.',
                ]);
            };

            return [
                Limit::perMinute(3)->by('minute|'.$request->ip())->response($tooMany),
                Limit::perHour(15)->by('hour|'.$request->ip())->response($tooMany),
            ];
        });
    }

    /**
     * @return array<int, Limit>
     */
    private function readLimits(Request $request, string $name, int $perMinute, int $perHour): array
    {
        $tooMany = function (Request $request, array $headers) {
            $message = 'Terlalu banyak permintaan. Coba lagi nanti.';

            $response = $request->expectsJson()
                ? response()->json(['message' => $message], 429)
                : response($message, 429, ['Content-Type' => 'text/plain; charset=UTF-8']);

            return $response->withHeaders($headers);
        };

        return [
            Limit::perMinute($perMinute)->by($name.'-minute|'.$request->ip())->response($tooMany),
            Limit::perHour($perHour)->by($name.'-hour|'.$request->ip())->response($tooMany),
        ];
    }
}
