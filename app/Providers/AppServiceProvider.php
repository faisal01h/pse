<?php

namespace App\Providers;

use App\Inference\HttpReportReader;
use App\Inference\LocalReportReader;
use App\Inference\ReportReader;
use App\Search\Embedder;
use App\Search\HashingEmbedder;
use App\Search\HttpEmbedder;
use App\Search\SearchIndexer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
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

        $this->app->bind(ReportReader::class, function () {
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
    }
}
