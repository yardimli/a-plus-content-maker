<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SerperImageSearchService
{
    public const SOURCES = ['pexels', 'unsplash', 'pixabay'];

    public function search(string $query, string $source): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        $query = trim(preg_replace('/\s+(?:pexels|unsplash|pixabay)$/i', '', $query) ?? $query);
        $providerQuery = trim($query.' '.$source);
        $cachePath = $this->cachePath($providerQuery);
        $disk = Storage::disk('local');
        $cached = $disk->exists($cachePath)
            ? json_decode($disk->get($cachePath), true)
            : null;

        if (is_array($cached) && ($cached['query'] ?? null) === $providerQuery && is_array($cached['images'] ?? null)) {
            $images = $cached['images'];
        } else {
            $key = config('services.serper.key');
            if (! $key) {
                throw ValidationException::withMessages(['query' => 'SERPER_API_KEY is not configured.']);
            }

            $payload = Http::withHeaders(['X-API-KEY' => $key])
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 250)
                ->post(rtrim(config('services.serper.base_url'), '/').'/images', [
                    'q' => $providerQuery,
                    'num' => 100,
                ])
                ->throw()
                ->json();

            $images = collect($payload['images'] ?? [])
                ->filter(fn ($image) => is_array($image)
                    && $this->isAllowedImageUrl($image['imageUrl'] ?? null)
                    && filter_var($image['thumbnailUrl'] ?? null, FILTER_VALIDATE_URL))
                ->take(100)
                ->map(fn ($image) => [
                    'title' => Str::limit(trim((string) ($image['title'] ?? 'Stock image')), 250, ''),
                    'image_url' => $image['imageUrl'],
                    'thumbnail_url' => $image['thumbnailUrl'],
                    'width' => (int) ($image['imageWidth'] ?? 0),
                    'height' => (int) ($image['imageHeight'] ?? 0),
                    'source' => (string) ($image['source'] ?? ''),
                    'domain' => (string) ($image['domain'] ?? ''),
                    'link' => (string) ($image['link'] ?? ''),
                    'position' => (int) ($image['position'] ?? 0),
                ])
                ->values()
                ->all();

            $disk->put($cachePath, json_encode([
                'query' => $providerQuery,
                'cached_at' => now()->toIso8601String(),
                'images' => $images,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return [
            'query' => $providerQuery,
            'images' => collect($images)->map(function (array $image): array {
                $image['token'] = Crypt::encryptString(json_encode($image, JSON_THROW_ON_ERROR));
                return $image;
            })->all(),
        ];
    }

    private function cachePath(string $providerQuery): string
    {
        $label = Str::limit(Str::slug($providerQuery), 80, '') ?: 'search';

        return 'serper/'.$label.'--'.hash('sha256', Str::lower($providerQuery)).'.json';
    }

    public function resultFromToken(string $token): array
    {
        try {
            $image = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'This search result is no longer valid. Please search again.']);
        }

        if (! is_array($image) || ! $this->isAllowedImageUrl($image['image_url'] ?? null)) {
            throw ValidationException::withMessages(['token' => 'The selected image is not from an allowed stock image provider.']);
        }

        return $image;
    }

    private function isAllowedImageUrl(?string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        return collect(self::SOURCES)->contains(fn ($domain) => $host === $domain.'.com' || str_ends_with($host, '.'.$domain.'.com'));
    }
}
