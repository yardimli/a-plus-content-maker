<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\Asset;
use App\Models\Project;
use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiController extends Controller
{
    public function text(Request $request, Project $project, OpenRouterService $service)
    {
        $this->authorize('update', $project);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:3000'], 'module_type' => ['required', 'string', 'max:100']]);
        $generation = AiGeneration::create(['user_id' => $request->user()->id, 'project_id' => $project->id, 'kind' => 'text', 'model' => config('services.openrouter.text_model') ?: 'not-configured', 'prompt' => $data['prompt'], 'status' => 'pending']);
        try {
            $result = $service->generateText($data['prompt'], ['book' => $project->product_snapshot, 'author' => $project->author_name, 'genre' => $project->genre, 'audience' => $project->audience, 'tone' => $project->tone, 'module' => $data['module_type']]);
            $generation->update(['response_payload' => $result, 'status' => 'succeeded', 'completed_at' => now()]);
            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $exception) {
            $generation->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
            throw $exception;
        }
    }

    public function image(Request $request, Project $project, OpenRouterService $service)
    {
        $this->authorize('update', $project);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:3000'], 'alt_text' => ['required', 'string', 'max:250'], 'width' => ['required', 'integer', 'min:100', 'max:2000'], 'height' => ['required', 'integer', 'min:100', 'max:2000']]);
        $prompt = $data['prompt']."\nCreate a polished book-marketing image composed for {$data['width']}×{$data['height']} pixels. Do not add text unless explicitly requested.";
        $generation = AiGeneration::create(['user_id' => $request->user()->id, 'project_id' => $project->id, 'kind' => 'image', 'model' => config('services.openrouter.image_model') ?: 'not-configured', 'prompt' => $prompt, 'status' => 'pending']);
        try {
            $result = $service->generateImage($prompt);
            $imageUrl = data_get($result, 'response.choices.0.message.images.0.image_url.url') ?? data_get($result, 'response.choices.0.message.images.0.image_url');
            if (! is_string($imageUrl) || ! preg_match('/^data:(image\/(?:png|jpeg|webp));base64,(.+)$/s', $imageUrl, $matches)) {
                throw new \RuntimeException('The selected image model did not return a supported image.');
            }
            $bytes = base64_decode($matches[2], true);
            $dimensions = $bytes ? getimagesizefromstring($bytes) : false;
            if (! $dimensions || strlen($bytes) > 15 * 1024 * 1024) {
                throw new \RuntimeException('The generated image could not be validated.');
            }
            $extension = $matches[1] === 'image/jpeg' ? 'jpg' : substr($matches[1], 6);
            $path = 'projects/'.$project->uuid.'/ai-'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, $bytes);
            $asset = Asset::create([
                'user_id' => $request->user()->id, 'project_id' => $project->id, 'source' => 'ai', 'disk' => 'public', 'path' => $path,
                'original_name' => 'AI generated image.'.$extension, 'mime_type' => $matches[1], 'extension' => $extension, 'size_bytes' => strlen($bytes),
                'width' => $dimensions[0], 'height' => $dimensions[1], 'alt_text' => $data['alt_text'], 'checksum' => hash('sha256', $bytes),
                'metadata' => ['model' => $result['model'], 'prompt' => $data['prompt'], 'requested_width' => $data['width'], 'requested_height' => $data['height']],
            ]);
            $generation->update(['response_payload' => ['asset_id' => $asset->id], 'status' => 'succeeded', 'completed_at' => now()]);
            return response()->json(['ok' => true, 'data' => $asset], 201);
        } catch (\Throwable $exception) {
            $generation->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
            throw $exception;
        }
    }
}
