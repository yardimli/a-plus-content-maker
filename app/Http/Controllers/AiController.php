<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\AiSetting;
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
        $generation = AiGeneration::create(['user_id' => $request->user()->id, 'project_id' => $project->id, 'kind' => 'text', 'model' => AiSetting::textModel() ?: 'not-configured', 'prompt' => $data['prompt'], 'status' => 'pending']);
        try {
            $call = $service->generateText(
                $data['prompt'],
                ['book' => $project->product_snapshot, 'author' => $project->author_name, 'genre' => $project->genre, 'audience' => $project->audience, 'tone' => $project->tone, 'module' => $data['module_type']],
                $request->user(), $project, $generation, 'builder.module_copy', ['module_type' => $data['module_type']]
            );
            $result = $call['data'];
            $generation->update(['model' => $call['model'], 'response_payload' => $result, 'status' => 'succeeded', 'input_tokens' => data_get($call, 'usage.prompt_tokens'), 'output_tokens' => data_get($call, 'usage.completion_tokens'), 'completed_at' => now()]);
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
        $generation = AiGeneration::create(['user_id' => $request->user()->id, 'project_id' => $project->id, 'kind' => 'image', 'model' => AiSetting::imageModel() ?: 'not-configured', 'prompt' => $prompt, 'status' => 'pending']);
        try {
            $result = $service->generateImage($prompt, $request->user(), $project, $generation, 'builder.image_generation', ['requested_width' => $data['width'], 'requested_height' => $data['height']]);
            $base64Image = data_get($result, 'response.data.0.b64_json');
            if (! is_string($base64Image)) {
                throw new \RuntimeException('The selected image model did not return a supported image.');
            }
            $bytes = base64_decode($base64Image, true);
            $dimensions = $bytes ? getimagesizefromstring($bytes) : false;
            $mimeType = is_array($dimensions) ? ($dimensions['mime'] ?? null) : null;
            if (! $dimensions || ! in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true) || strlen($bytes) > 15 * 1024 * 1024) {
                throw new \RuntimeException('The generated image could not be validated.');
            }
            $extension = $mimeType === 'image/jpeg' ? 'jpg' : substr($mimeType, 6);
            $path = 'projects/'.$project->uuid.'/ai-'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put($path, $bytes);
            $asset = Asset::create([
                'user_id' => $request->user()->id, 'project_id' => $project->id, 'source' => 'ai', 'disk' => 'public', 'path' => $path,
                'original_name' => 'AI generated image.'.$extension, 'mime_type' => $mimeType, 'extension' => $extension, 'size_bytes' => strlen($bytes),
                'width' => $dimensions[0], 'height' => $dimensions[1], 'alt_text' => $data['alt_text'], 'checksum' => hash('sha256', $bytes),
                'metadata' => ['model' => $result['model'], 'prompt' => $data['prompt'], 'requested_width' => $data['width'], 'requested_height' => $data['height']],
            ]);
            $generation->update(['model' => $result['model'], 'response_payload' => ['asset_id' => $asset->id], 'status' => 'succeeded', 'input_tokens' => data_get($result, 'usage.prompt_tokens'), 'output_tokens' => data_get($result, 'usage.completion_tokens'), 'completed_at' => now()]);
            return response()->json(['ok' => true, 'data' => $asset], 201);
        } catch (\Throwable $exception) {
            $generation->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'completed_at' => now()]);
            throw $exception;
        }
    }
}
