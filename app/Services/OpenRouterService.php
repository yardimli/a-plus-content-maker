<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenRouterService
{
    public function generateText(string $prompt, array $context): array
    {
        $model = config('services.openrouter.text_model');
        $this->ensureConfigured($model, 'text');
        $response = $this->client()->post(rtrim(config('services.openrouter.base_url'), '/').'/chat/completions', [
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'You write concise, truthful Amazon KDP A+ content for books. Return JSON with headline and body_html keys. Never invent awards, reviews, or claims.'],
                ['role' => 'user', 'content' => json_encode(['request' => $prompt, 'book_context' => $context], JSON_UNESCAPED_SLASHES)],
            ],
        ])->throw();
        $content = $response->json('choices.0.message.content', '{}');
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : ['body_html' => strip_tags((string) $content)];
    }

    public function generateImage(string $prompt): array
    {
        $model = config('services.openrouter.image_model');
        $this->ensureConfigured($model, 'image');
        $response = $this->client()->post(rtrim(config('services.openrouter.base_url'), '/').'/chat/completions', [
            'model' => $model,
            'modalities' => ['image', 'text'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ])->throw();

        return ['model' => $model, 'response' => $response->json()];
    }

    private function client()
    {
        return Http::acceptJson()->timeout(90)->withToken(config('services.openrouter.key'))->withHeaders(array_filter([
            'HTTP-Referer' => config('services.openrouter.site_url'),
            'X-Title' => config('services.openrouter.app_name'),
        ]));
    }

    private function ensureConfigured(?string $model, string $kind): void
    {
        if (! config('services.openrouter.key') || ! $model) {
            throw ValidationException::withMessages(['prompt' => "OpenRouter {$kind} generation is not configured yet."]);
        }
    }
}
