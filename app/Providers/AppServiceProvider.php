<?php

namespace App\Providers;

use App\Duplicates\NameNormalizer;
use App\Inference\HttpReportReader;
use App\Inference\LayaReportReader;
use App\Inference\LocalReportReader;
use App\Inference\ReportReader;
use App\Models\Business;
use App\Models\Party;
use App\Models\Politician;
use App\Models\RecordMerge;
use App\Plugins\PluginManager;
use App\Search\Embedder;
use App\Search\HashingEmbedder;
use App\Search\HttpEmbedder;
use App\Search\SearchIndexer;
use App\Settings\SettingsRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MarcReichel\Laya\Laya;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(PluginManager::class);

        $this->app->singleton(Embedder::class, function () {
            $dimensions = (int) config('search.embeddings.dimensions');
            $driver = config('search.embeddings.driver');
            $url = (string) config('search.embeddings.url');
            $key = (string) config('search.embeddings.key');

            if ($driver === 'http' && $url !== '' && $key !== '') {
                return new HttpEmbedder(
                    $url,
                    $key,
                    (string) config('search.embeddings.model'),
                    $dimensions > 0 ? $dimensions : null,
                    (int) config('search.embeddings.timeout', 8),
                );
            }

            return new HashingEmbedder($dimensions > 0 ? $dimensions : 256);
        });

        $this->app->bind(ReportReader::class, function ($app) {
            $local = new LocalReportReader;
            $url = (string) config('inference.url');
            $key = (string) config('inference.key');

            if (config('inference.driver') === 'http' && $url !== '' && $key !== '') {
                return new HttpReportReader(
                    $url,
                    $key,
                    (string) config('inference.model'),
                    (int) config('inference.timeout', 8),
                    $local,
                );
            }

            if (config('inference.driver') === 'laya') {
                return new LayaReportReader(
                    $app->make(Laya::class),
                    $local,
                    (float) config('inference.laya_min_confidence', 0.55),
                );
            }

            return $local;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        SearchIndexer::register();
        $this->registerMergeRedirects();
        $this->app->make(PluginManager::class)->bootRegistered();
    }

    /**
     * Links to a record that was merged into another keep working: GETs are redirected
     * to the surviving record, anything else is a plain 404.
     */
    private function registerMergeRedirects(): void
    {
        $bindings = [
            'politician' => [NameNormalizer::POLITICIAN, Politician::class, 'politicians'],
            'party' => [NameNormalizer::PARTY, Party::class, 'parties'],
            'business' => [NameNormalizer::BUSINESS, Business::class, 'businesses'],
        ];

        foreach ($bindings as $parameter => [$type, $model, $segment]) {
            Route::bind($parameter, function (string $value) use ($type, $model, $segment) {
                $found = $model::query()->find($value);

                if ($found) {
                    return $found;
                }

                $canonical = ctype_digit($value) ? RecordMerge::canonicalFor($type, (int) $value) : null;
                $request = request();

                if ($canonical !== null && $request->isMethodSafe()) {
                    $path = preg_replace('#/'.$segment.'/'.preg_quote($value, '#').'(?=/|$)#', '/'.$segment.'/'.$canonical, $request->getPathInfo());
                    $query = $request->getQueryString();

                    throw new HttpResponseException(redirect($path.($query ? '?'.$query : ''), 301));
                }

                abort(404);
            });
        }
    }
}
