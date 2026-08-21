<?php

namespace App\Services;

use App\Models\AiCallLog;
use App\Models\AiGeneration;
use App\Models\AiSetting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenRouterService
{
    public function models(bool $refresh = false): array
    {
        $cacheKey = 'openrouter.model-catalog.v2';
        if ($refresh) Cache::forget($cacheKey);

        return Cache::remember($cacheKey, now()->addMinutes(15), function (): array {
            $response = $this->client()->get($this->endpoint('/models'), ['output_modalities' => 'all'])->throw();
            return collect($response->json('data', []))->map(function (array $model): array {
                $modalities = data_get($model, 'architecture.output_modalities', []);
                $pricing = $model['pricing'] ?? [];
                if (array_is_list($pricing)) $pricing = $pricing[0] ?? [];
                return [
                    'id' => $model['id'], 'name' => $model['name'] ?? $model['id'], 'description' => $model['description'] ?? null,
                    'context_length' => $model['context_length'] ?? null,
                    'supports_text' => in_array('text', $modalities, true), 'supports_image' => in_array('image', $modalities, true),
                    'pricing' => $pricing,
                ];
            })->filter(fn (array $model) => $model['supports_text'] || $model['supports_image'])
                ->sortBy(fn (array $model) => strtolower($model['name']))->values()->all();
        });
    }

    public function generateText(string $prompt, array $context, User $actor, ?Project $project = null, ?AiGeneration $generation = null, string $location = 'unknown', array $metadata = []): array
    {
        $model = AiSetting::textModel();
        $this->ensureConfigured($model, 'text');
        $started = microtime(true);
        try {
            $response = $this->client()->post($this->endpoint('/chat/completions'), [
                'model' => $model, 'usage' => ['include' => true],
                'messages' => [
                    ['role' => 'system', 'content' => 'You write concise, truthful Amazon KDP A+ content for books. Return JSON with headline and body_html keys. Never invent awards, reviews, or claims.'],
                    ['role' => 'user', 'content' => json_encode(['request' => $prompt, 'book_context' => $context], JSON_UNESCAPED_SLASHES)],
                ],
            ])->throw();
            $content = $response->json('choices.0.message.content', '{}');
            $decoded = $this->decodeJsonObject((string) $content);
            $result = is_array($decoded) ? $decoded : ['body_html' => strip_tags((string) $content)];
            $usage = $response->json('usage', []);
            $this->logCall($actor, $project, $generation, 'text', $model, $location, 'succeeded', $started, $response, $usage, null, $metadata);
            return ['data' => $result, 'model' => $model, 'usage' => $usage, 'request_id' => $response->json('id')];
        } catch (RequestException $exception) {
            $this->logCall($actor, $project, $generation, 'text', $model, $location, 'failed', $started, null, [], $exception, $metadata);
            $providerMessage = $exception->response->json('error.message');
            $message = $exception->response->status() === 404
                ? 'The selected text model is not currently available. Ask an administrator to choose another default text model.'
                : ($providerMessage ?: 'OpenRouter could not draft the module copy. Please try again.');
            throw ValidationException::withMessages(['prompt' => $message]);
        } catch (\Throwable $exception) {
            $this->logCall($actor, $project, $generation, 'text', $model, $location, 'failed', $started, null, [], $exception, $metadata);
            throw $exception;
        }
    }

    public function generateImage(string $prompt, User $actor, ?Project $project = null, ?AiGeneration $generation = null, string $location = 'unknown', array $metadata = []): array
    {
        $model = AiSetting::imageModel();
        $this->ensureConfigured($model, 'image');
        $started = microtime(true);
        try {
            $response = $this->client(180)->post($this->endpoint('/images'), [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'output_format' => 'png',
            ])->throw();
            $usage = $response->json('usage', []);
            $this->logCall($actor, $project, $generation, 'image', $model, $location, 'succeeded', $started, $response, $usage, null, $metadata);
            return ['model' => $model, 'response' => $response->json(), 'usage' => $usage, 'request_id' => $response->json('id')];
        } catch (RequestException $exception) {
            $this->logCall($actor, $project, $generation, 'image', $model, $location, 'failed', $started, null, [], $exception, $metadata);
            $providerMessage = $exception->response->json('error.message');
            $message = $exception->response->status() === 404
                ? 'The selected image model is not currently available for image generation. Ask an administrator to choose another default image model.'
                : ($providerMessage ?: 'OpenRouter could not generate the image. Please try again.');
            throw ValidationException::withMessages(['prompt' => $message]);
        } catch (\Throwable $exception) {
            $this->logCall($actor, $project, $generation, 'image', $model, $location, 'failed', $started, null, [], $exception, $metadata);
            throw $exception;
        }
    }

    private function logCall(User $actor, ?Project $project, ?AiGeneration $generation, string $kind, string $model, string $location, string $status, float $started, ?Response $response, array $usage, ?\Throwable $exception, array $metadata): void
    {
        AiCallLog::create([
            'user_id' => $actor->id, 'project_id' => $project?->id, 'ai_generation_id' => $generation?->id,
            'kind' => $kind, 'model' => $model, 'location' => $location, 'status' => $status,
            'input_tokens' => data_get($usage, 'prompt_tokens'), 'output_tokens' => data_get($usage, 'completion_tokens'), 'total_tokens' => data_get($usage, 'total_tokens'),
            'cost_usd' => data_get($usage, 'cost'), 'provider_request_id' => $response?->json('id'),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'error_message' => $exception?->getMessage(), 'metadata' => $metadata ?: null,
        ]);
    }

    private function client(int $timeout = 90)
    {
        return Http::acceptJson()->timeout($timeout)->withToken(config('services.openrouter.key'))->withHeaders(array_filter([
            'HTTP-Referer' => config('services.openrouter.site_url'),
            'X-Title' => config('services.openrouter.app_name'),
        ]));
    }

    private function endpoint(string $path): string
    {
        return rtrim(config('services.openrouter.base_url'), '/').$path;
    }

    private function decodeJsonObject(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }

    private function ensureConfigured(?string $model, string $kind): void
    {
        if (! config('services.openrouter.key') || ! $model) {
            throw ValidationException::withMessages(['prompt' => "OpenRouter {$kind} generation is not configured yet."]);
        }
    }
}
