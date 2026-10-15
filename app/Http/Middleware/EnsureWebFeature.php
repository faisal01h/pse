<?php

namespace App\Http\Middleware;

use App\Web\WebFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebFeature
{
    public function __construct(private WebFeatures $features) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->features->isEnabled($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
