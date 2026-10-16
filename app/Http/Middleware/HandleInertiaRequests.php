<?php

namespace App\Http\Middleware;

use App\Plugins\PluginManager;
use App\Web\WebFeatures;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array
     */
    public function share(Request $request)
    {
        $user = $request->user();

        $features = app(WebFeatures::class);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user,
                'is_active' => $user ? (bool) $user->is_active : false,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'toast' => fn () => $request->session()->get('toast'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'features' => $features->toArray(),
            'plugins' => [
                'realpolitik' => app(PluginManager::class)->isEnabled('realpolitik'),
            ],
            'site_notice' => $features->siteNotice(),
            'turnstile' => [
                'sitekey' => config('turnstile.sitekey'),
                'enabled' => $features->turnstileEnabled(),
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ]);
    }
}
