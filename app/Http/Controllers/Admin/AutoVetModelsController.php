<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AutoVetModelsController extends Controller
{
    public function __construct(private SettingsRepository $settings) {}

    public function index(): JsonResponse
    {
        $baseUrl = rtrim($this->settings->getString('autovet.llm.base_url'), '/');
        $apiKey = $this->settings->getDecrypted('autovet.llm.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            return response()->json([
                'models' => [],
                'error' => 'Base URL dan API Key harus diisi terlebih dahulu.',
            ], 422);
        }

        $cacheKey = 'autovet.models.'.md5($baseUrl);

        try {
            $models = Cache::remember($cacheKey, 60, function () use ($baseUrl, $apiKey) {
                $url = $this->modelsEndpoint($baseUrl);
                $response = Http::timeout(15)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->get($url)
                    ->throw()
                    ->json();

                $data = is_array($response['data'] ?? null) ? $response['data'] : [];

                return collect($data)
                    ->map(fn ($row) => is_array($row) ? (string) ($row['id'] ?? '') : '')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            });
        } catch (Throwable $e) {
            Cache::forget($cacheKey);

            return response()->json([
                'models' => [],
                'error' => 'Gagal memuat model: '.$e->getMessage(),
            ], 502);
        }

        return response()->json(['models' => $models]);
    }

    private function modelsEndpoint(string $baseUrl): string
    {
        if (str_ends_with($baseUrl, '/models')) {
            return $baseUrl;
        }

        if (str_ends_with($baseUrl, '/v1')) {
            return $baseUrl.'/models';
        }

        return $baseUrl.'/v1/models';
    }
}
